<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Chiusura e riapertura dei conti dell'esercizio, dopo le scritture di
 * assestamento (ammortamenti, ratei e risconti):
 *
 * - epilogo: i saldi dei conti economici al conto "Conto economico" e il
 *   risultato all'utile o alla perdita dell'esercizio;
 * - chiusura: i saldi dei conti patrimoniali (compresi utile o perdita)
 *   allo "Stato patrimoniale di chiusura";
 * - riapertura nell'esercizio successivo, se aperto: gli stessi saldi dallo
 *   "Stato patrimoniale di apertura" (il risultato resta da destinare).
 *
 * Epilogo e chiusura hanno l'origine "Chiusura" e sono esclusi dai saldi
 * del bilancio di verifica e dai componenti del reddito; la riapertura ha
 * l'origine "Riapertura" ed entra nei saldi dell'esercizio successivo. Le
 * righe sui sottoconti dei clienti e fornitori non hanno la controparte:
 * le partite aperte restano quelle dello scadenzario. Le scritture si
 * sostituiscono rigenerandole, salvo esercizio chiuso o stampa definitiva.
 */
class ChiusuraEsercizio
{

    public const ORIGINE = 'Chiusura';
    public const ORIGINE_RIAPERTURA = 'Riapertura';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function isChiusuraORiapertura(RegistrazioneContabile $registrazione): bool
    {
        return in_array($registrazione->origine, [self::ORIGINE, self::ORIGINE_RIAPERTURA], true);
    }

    /**
     * Saldi (dare meno avere) dei sottoconti nell'esercizio, senza epilogo e
     * chiusura.
     *
     * @return array<int, array{conto: Conto, saldo: float}> per id del conto
     */
    public function getSaldi(Soggetto $soggetto, EsercizioContabile $esercizio): array
    {
        $saldi = [];
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $esercizio->dataInizio, $esercizio->dataFine) as $riga) {
            if ($riga->registrazioneContabile->origine === self::ORIGINE) {
                continue;
            }
            $saldi[$riga->conto->id] ??= ['conto' => $riga->conto, 'saldo' => 0.0];
            $saldi[$riga->conto->id]['saldo'] += $riga->dare - $riga->avere;
        }
        foreach ($saldi as $id => $voce) {
            $saldi[$id]['saldo'] = round($voce['saldo'], 2);
            if ($saldi[$id]['saldo'] == 0) {
                unset($saldi[$id]);
            }
        }
        uasort($saldi, fn(array $primo, array $secondo) => $primo['conto']->codice <=> $secondo['conto']->codice);
        return $saldi;
    }

    /**
     * Risultato dell'esercizio (ricavi meno costi, positivo se utile).
     */
    public static function getRisultato(array $saldi): float
    {
        $risultato = 0.0;
        foreach ($saldi as $voce) {
            if ($voce['conto']->natura->isEconomico()) {
                $risultato -= $voce['saldo'];
            }
        }
        return round($risultato, 2);
    }

    public static function getRiferimento(string $fase, int $anno): string
    {
        return $fase . '-' . $anno;
    }

    /**
     * Genera (o sostituisce) epilogo, chiusura e riapertura.
     *
     * @param string[] $avvisi
     * @return float risultato dell'esercizio
     * @throws \DomainException se manca un conto di sistema o una scrittura non si può sostituire
     */
    public function genera(Soggetto $soggetto, EsercizioContabile $esercizio, array &$avvisi = []): float
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto) ?? throw new \DomainException('Piano dei conti dell\'azienda mancante.');
        $conti = [];
        foreach ([FunzioneConto::contoEconomico, FunzioneConto::utileEsercizio, FunzioneConto::perditaEsercizio, FunzioneConto::chiusuraPatrimoniale, FunzioneConto::aperturaPatrimoniale] as $funzione) {
            $conti[$funzione->value] = (new ContoModel($this->dataMapper))->getPerFunzione($piano, $funzione)
                    ?? throw new \DomainException('Nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".');
        }
        // tutte le scritture o nessuna: esercizio aperto e giornale non stampato alle date
        $successivo = (new RateiRisconti($this->dataMapper))->getSuccessivo($soggetto, $esercizio);
        Ammortamenti::verificaScrivibile($this->dataMapper, $soggetto, $esercizio, $esercizio->dataFine);
        if ($successivo !== null) {
            Ammortamenti::verificaScrivibile($this->dataMapper, $soggetto, $successivo, $successivo->dataInizio);
        }
        $saldi = $this->getSaldi($soggetto, $esercizio);
        $risultato = self::getRisultato($saldi);
        // epilogo dei conti economici e risultato
        $epilogo = [];
        $patrimoniali = [];
        foreach ($saldi as $voce) {
            $conto = $voce['conto'];
            if ($conto->natura->isEconomico()) {
                $epilogo[] = self::riga($this->dataMapper, $conto, -$voce['saldo']);
                $epilogo[] = self::riga($this->dataMapper, $conti[FunzioneConto::contoEconomico->value], $voce['saldo']);
            } elseif ($conto->natura !== NaturaConto::sistema) {
                $patrimoniali[$conto->id] = $voce;
            }
        }
        if ($risultato != 0) {
            $contoRisultato = $conti[$risultato > 0 ? FunzioneConto::utileEsercizio->value : FunzioneConto::perditaEsercizio->value];
            $epilogo[] = self::riga($this->dataMapper, $conti[FunzioneConto::contoEconomico->value], $risultato);
            $epilogo[] = self::riga($this->dataMapper, $contoRisultato, -$risultato);
            $patrimoniali[$contoRisultato->id] = ['conto' => $contoRisultato, 'saldo' => round(($patrimoniali[$contoRisultato->id]['saldo'] ?? 0.0) - $risultato, 2)];
        }
        Ammortamenti::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimento('epilogo', $esercizio->anno), $esercizio->dataFine,
                'Epilogo dei conti economici dell\'esercizio ' . $esercizio->getEtichetta(), self::compatta($epilogo), $avvisi);
        // chiusura e riapertura dei conti patrimoniali
        $chiusura = $riapertura = [];
        foreach ($patrimoniali as $voce) {
            if ($voce['saldo'] == 0) {
                continue;
            }
            $chiusura[] = self::riga($this->dataMapper, $voce['conto'], -$voce['saldo']);
            $chiusura[] = self::riga($this->dataMapper, $conti[FunzioneConto::chiusuraPatrimoniale->value], $voce['saldo']);
            $riapertura[] = self::riga($this->dataMapper, $voce['conto'], $voce['saldo']);
            $riapertura[] = self::riga($this->dataMapper, $conti[FunzioneConto::aperturaPatrimoniale->value], -$voce['saldo']);
        }
        Ammortamenti::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimento('chiusura', $esercizio->anno), $esercizio->dataFine,
                'Chiusura dei conti patrimoniali dell\'esercizio ' . $esercizio->getEtichetta(), self::compatta($chiusura), $avvisi);
        if ($successivo === null) {
            $avvisi[] = 'Riapertura dei conti non registrata: aprire l\'esercizio successivo e rigenerare la chiusura.';
        } else {
            Ammortamenti::sostituisci($this->dataMapper, $soggetto, $successivo, self::ORIGINE_RIAPERTURA, self::getRiferimento('riapertura', $esercizio->anno), $successivo->dataInizio,
                    'Riapertura dei conti al ' . $successivo->dataInizio->format('d/m/Y'), self::compatta($riapertura), $avvisi);
        }
        return $risultato;
    }

    /**
     * Scritture di chiusura e riapertura già registrate per l'esercizio.
     *
     * @return array<string, ?RegistrazioneContabile>
     */
    public function getScritture(Soggetto $soggetto, int $anno): array
    {
        $model = new \Contabilita\Application\Models\RegistrazioneContabileModel($this->dataMapper);
        return ['epilogo' => $model->getGenerata($soggetto, self::ORIGINE, self::getRiferimento('epilogo', $anno)),
            'chiusura' => $model->getGenerata($soggetto, self::ORIGINE, self::getRiferimento('chiusura', $anno)),
            'riapertura' => $model->getGenerata($soggetto, self::ORIGINE_RIAPERTURA, self::getRiferimento('riapertura', $anno))];
    }

    /**
     * Riga con l'importo in dare (positivo) o in avere (negativo).
     */
    private static function riga(DataMapper $dataMapper, Conto $conto, float $importo): \Contabilita\Application\Entities\RigaContabile
    {
        return Ammortamenti::riga($dataMapper, $conto, max(0.0, $importo), max(0.0, -$importo));
    }

    /**
     * Righe sullo stesso conto e nello stesso segno sommate (le
     * contropartite di sistema in una sola riga per lato).
     *
     * @param \Contabilita\Application\Entities\RigaContabile[] $righe
     * @return \Contabilita\Application\Entities\RigaContabile[]
     */
    private static function compatta(array $righe): array
    {
        $somme = [];
        foreach ($righe as $riga) {
            if (($riga->dare == 0) && ($riga->avere == 0)) {
                continue;
            }
            $chiave = $riga->conto->id . ($riga->dare > 0 ? 'D' : 'A');
            if (isset($somme[$chiave])) {
                $somme[$chiave]->dare = round($somme[$chiave]->dare + $riga->dare, 2);
                $somme[$chiave]->avere = round($somme[$chiave]->avere + $riga->avere, 2);
            } else {
                $somme[$chiave] = $riga;
            }
        }
        return array_values($somme);
    }
}
