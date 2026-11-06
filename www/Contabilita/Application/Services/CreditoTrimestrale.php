<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\CreditoTrimestraleIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\CreditoTrimestraleIvaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Credito del trimestre chiesto con il modello IVA TR: non si riporta al
 * periodo successivo (istruzioni della comunicazione delle liquidazioni,
 * rigo VP8). Registrato il credito, lo annota sulla liquidazione che chiude
 * il trimestre, della società o del gruppo, e rielabora le liquidazioni
 * successive con i loro dati; si ferma alla prima che non si può più
 * cambiare (comunicazione inviata, versamento in una delega trasmessa,
 * giroconto stampato in definitiva), segnalandola.
 */
class CreditoTrimestrale
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Registra il credito chiesto (zero lo toglie) per il soggetto o, con
     * il gruppo IVA, per la controllante.
     *
     * @return string[] messaggi per l'utente
     */
    public function registra(Soggetto $soggetto, ?GruppoIva $gruppo, int $anno, int $trimestre, float $importo): array
    {
        $importo = round(max(0.0, $importo), 2);
        $model = new CreditoTrimestraleIvaModel($this->dataMapper);
        $riga = $model->getPerTrimestre($soggetto, $anno, $trimestre);
        if (abs(($riga?->importo ?? 0.0) - $importo) < 0.005) {
            return [];
        }
        if ($importo <= 0) {
            $this->dataMapper->delete($riga);
        } else {
            $riga ??= new CreditoTrimestraleIva($this->dataMapper);
            $riga->studio = $soggetto->studio;
            $riga->soggetto = $soggetto;
            $riga->gruppoIva = $gruppo;
            $riga->anno = $anno;
            $riga->trimestre = $trimestre;
            $riga->importo = $importo;
            $this->dataMapper->save($riga);
        }
        $chiusura = $gruppo !== null ? $this->sincronizzaGruppo($gruppo, $anno, $trimestre, null, $importo) : $this->sincronizza($soggetto, $anno, $trimestre, null, $importo);
        if ($chiusura === null) {
            return $importo > 0 ? [sprintf('Liquidazione del %d° trimestre non ancora elaborata: il credito chiesto sarà escluso dal riporto alla sua elaborazione.', $trimestre)] : [];
        }
        $messaggi = [$importo > 0 ? sprintf('Credito chiesto con il modello IVA TR di € %s annotato sulla liquidazione di %s.', number_format($importo, 2, ',', '.'), $chiusura->getDescrizionePeriodo())
                    : sprintf('Credito del modello IVA TR tolto dalla liquidazione di %s.', $chiusura->getDescrizionePeriodo())];
        return [...$messaggi, ...($gruppo !== null ? $this->rielaboraGruppo($gruppo, $anno, $chiusura) : $this->rielaboraSoggetto($soggetto, $anno, $chiusura))];
    }

    /**
     * Allinea le liquidazioni del trimestre al credito registrato: l'importo
     * sta su quella che chiude il trimestre.
     */
    public function sincronizza(Soggetto $soggetto, int $anno, int $trimestre, ?LiquidazioneIva $corrente = null, ?float $importo = null): ?LiquidazioneIva
    {
        if (($trimestre < 1) || ($trimestre > 3)) {
            return null;
        }
        if ($importo === null) {
            $riga = (new CreditoTrimestraleIvaModel($this->dataMapper))->getPerTrimestre($soggetto, $anno, $trimestre);
            $importo = ($riga !== null) && ($riga->gruppoIva === null) ? $riga->importo : 0.0;
        }
        $model = new LiquidazioneIvaModel($this->dataMapper);
        $mensile = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno) === PeriodicitaIva::mensile;
        $candidate = $mensile ? [$model->getPerPeriodo($soggetto, $anno, $trimestre * 3), $model->getPerPeriodo($soggetto, $anno, $trimestre, true)] : [$model->getPerPeriodo($soggetto, $anno, $trimestre)];
        return $this->annota($candidate, (new ElaborazioneLiquidazione($this->dataMapper))->getLiquidazioneTrimestre($soggetto, $anno, $trimestre), $corrente, $importo);
    }

    /**
     * Come sincronizza(), per le liquidazioni del gruppo.
     */
    public function sincronizzaGruppo(GruppoIva $gruppo, int $anno, int $trimestre, ?LiquidazioneGruppoIva $corrente = null, ?float $importo = null): ?LiquidazioneGruppoIva
    {
        if (($trimestre < 1) || ($trimestre > 3)) {
            return null;
        }
        $importo ??= (new CreditoTrimestraleIvaModel($this->dataMapper))->getPerTrimestre($gruppo->controllante, $anno, $trimestre)?->importo ?? 0.0;
        $model = new LiquidazioneGruppoIvaModel($this->dataMapper);
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        $candidate = $servizio->getPeriodicita($gruppo, $anno) === PeriodicitaIva::mensile
                ? [$model->getPerPeriodo($gruppo, $anno, $trimestre * 3), $model->getPerPeriodo($gruppo, $anno, $trimestre, true)] : [$model->getPerPeriodo($gruppo, $anno, $trimestre)];
        return $this->annota($candidate, $servizio->getLiquidazioneTrimestre($gruppo, $anno, $trimestre), $corrente, $importo);
    }

    /**
     * Trimestre della liquidazione che può chiuderlo, null per le altre.
     */
    public static function getTrimestreChiuso(LiquidazioneIva|LiquidazioneGruppoIva $liquidazione): ?int
    {
        if ($liquidazione->trimestreSeparato || ($liquidazione->periodicita === PeriodicitaIva::trimestrale)) {
            return $liquidazione->periodo;
        }
        return $liquidazione->periodo % 3 === 0 ? intdiv($liquidazione->periodo, 3) : null;
    }

    private static function getTrimestre(LiquidazioneIva|LiquidazioneGruppoIva $liquidazione): int
    {
        return $liquidazione->trimestreSeparato || ($liquidazione->periodicita === PeriodicitaIva::trimestrale) ? $liquidazione->periodo : intdiv($liquidazione->periodo + 2, 3);
    }

    /**
     * @param array<int, LiquidazioneIva|LiquidazioneGruppoIva|null> $candidate
     */
    private function annota(array $candidate, LiquidazioneIva|LiquidazioneGruppoIva|null $chiusura, LiquidazioneIva|LiquidazioneGruppoIva|null $corrente, float $importo): LiquidazioneIva|LiquidazioneGruppoIva|null
    {
        foreach ($candidate as $liquidazione) {
            if ($liquidazione === null) {
                continue;
            }
            if (($corrente !== null) && ($corrente->id === $liquidazione->id)) {
                $liquidazione = $corrente;
            }
            $valore = $liquidazione->id === $chiusura?->id ? $importo : 0.0;
            if (abs($liquidazione->creditoTrimestrale - $valore) >= 0.005) {
                $liquidazione->creditoTrimestrale = $valore;
                $this->dataMapper->save($liquidazione);
            }
        }
        return $chiusura;
    }

    /**
     * @return string[]
     */
    private function rielaboraSoggetto(Soggetto $soggetto, int $anno, LiquidazioneIva $chiusura): array
    {
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        return $this->rielabora((new LiquidazioneIvaModel($this->dataMapper))->getSequenza($soggetto, $anno), $chiusura, $this->getBlocco(...),
                        function (LiquidazioneIva $liquidazione) use ($elaborazione, $soggetto, $anno): void {
                            $giroconto = $liquidazione->registrazioneContabile !== null;
                            if ($liquidazione->trimestreSeparato) {
                                $elaborazione->elaboraTrimestre($soggetto, $anno, $liquidazione->periodo, $liquidazione->creditoAnnoPrecedente, $giroconto, $liquidazione->versamentiAuto, $liquidazione->creditiImposta);
                            } else {
                                $elaborazione->elabora($soggetto, $anno, $liquidazione->periodo, $liquidazione->creditoAnnoPrecedente, $giroconto, $liquidazione->versamentiAuto, $liquidazione->creditiImposta, $liquidazione->trimestreAnticipato !== null);
                            }
                        });
    }

    /**
     * @return string[]
     */
    private function rielaboraGruppo(GruppoIva $gruppo, int $anno, LiquidazioneGruppoIva $chiusura): array
    {
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        return $this->rielabora((new LiquidazioneGruppoIvaModel($this->dataMapper))->getSequenza($gruppo, $anno), $chiusura, $this->getBloccoGruppo(...),
                        function (LiquidazioneGruppoIva $liquidazione) use ($servizio, $gruppo, $anno): void {
                            if ($liquidazione->trimestreSeparato) {
                                $servizio->elaboraTrimestre($gruppo, $anno, $liquidazione->periodo, $liquidazione->creditoAnnoPrecedente);
                            } else {
                                $servizio->elabora($gruppo, $anno, $liquidazione->periodo, $liquidazione->creditoAnnoPrecedente, $liquidazione->trimestreAnticipato !== null);
                            }
                        });
    }

    /**
     * Rielabora nell'ordine dei riporti le liquidazioni dopo quella che
     * chiude il trimestre.
     *
     * @return string[]
     */
    private function rielabora(array $sequenza, LiquidazioneIva|LiquidazioneGruppoIva $chiusura, callable $blocco, callable $elabora): array
    {
        $dopo = false;
        $rielaborate = [];
        $messaggi = [];
        foreach ($sequenza as $liquidazione) {
            if ($dopo === false) {
                $dopo = $liquidazione->id === $chiusura->id;
                continue;
            }
            $motivo = $blocco($liquidazione);
            if ($motivo !== null) {
                $messaggi[] = sprintf('Liquidazione di %s non rielaborata (%s): da quel periodo il riporto va corretto a mano.', $liquidazione->getDescrizionePeriodo(), $motivo);
                break;
            }
            try {
                $elabora($liquidazione);
            } catch (\DomainException | \InvalidArgumentException $errore) {
                $messaggi[] = sprintf('Liquidazione di %s non rielaborata: %s', $liquidazione->getDescrizionePeriodo(), $errore->getMessage());
                break;
            }
            $rielaborate[] = $liquidazione->getDescrizionePeriodo();
        }
        if (count($rielaborate) > 0) {
            array_unshift($messaggi, 'Riporto aggiornato, liquidazioni rielaborate: ' . implode(', ', $rielaborate) . '.');
        }
        return $messaggi;
    }

    private function getBlocco(LiquidazioneIva $liquidazione): ?string
    {
        $comunicazione = (new ComunicazioneLipeModel($this->dataMapper))->getPerTrimestre($liquidazione->soggetto, $liquidazione->anno, self::getTrimestre($liquidazione));
        if ($comunicazione?->stato === StatoComunicazioneLipe::inviata) {
            return 'comunicazione del trimestre già inviata';
        }
        if ($this->isInDelega($liquidazione->soggetto, LiquidazioneIva::ORIGINE, $liquidazione->getRiferimentoVersamento())) {
            return 'versamento in una delega trasmessa';
        }
        $giroconto = $liquidazione->registrazioneContabile;
        return $giroconto === null ? null : (new StampeDefinitive($this->dataMapper))->getBlocco($giroconto);
    }

    private function getBloccoGruppo(LiquidazioneGruppoIva $liquidazione): ?string
    {
        $controllante = $liquidazione->gruppoIva->controllante;
        $comunicazione = (new ComunicazioneLipeModel($this->dataMapper))->getPerTrimestre($controllante, $liquidazione->anno, self::getTrimestre($liquidazione), $liquidazione->gruppoIva);
        if ($comunicazione?->stato === StatoComunicazioneLipe::inviata) {
            return 'comunicazione del trimestre già inviata';
        }
        return $this->isInDelega($controllante, GruppoIva::ORIGINE, $liquidazione->getRiferimentoVersamento()) ? 'versamento in una delega trasmessa' : null;
    }

    private function isInDelega(Soggetto $soggetto, string $origine, string $riferimento): bool
    {
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, $origine, $riferimento) as $versamento) {
            if (in_array($versamento->delega?->stato, [StatoDelega::trasmessa, StatoDelega::quietanzata], true)) {
                return true;
            }
        }
        return false;
    }
}
