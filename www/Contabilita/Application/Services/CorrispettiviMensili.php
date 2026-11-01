<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\CorrispettivoGiornaliero;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CorrispettivoGiornalieroModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Corrispettivi di un mese in un'unica registrazione (art. 6, c. 4, D.P.R.
 * 695/1996: le operazioni con scontrino, ricevuta o documento commerciale
 * del mese si possono annotare anche con unica registrazione entro il 15
 * del mese successivo), con il dettaglio giornaliero per codice IVA che il
 * registro dei corrispettivi riporta (art. 24 D.P.R. 633/1972).
 *
 * Gli importi giornalieri sono comprensivi dell'imposta; le righe IVA
 * della registrazione hanno il totale del mese per codice con l'imposta
 * scorporata (art. 27, c. 4: corrispettivi divisi per 100 più l'aliquota e
 * moltiplicati per l'aliquota, al centesimo). La registrazione è all'ultimo
 * giorno del mese, che è anche la data di competenza IVA, ed è
 * identificata da registro e mese (origine "Corrispettivi"): salvare di
 * nuovo il mese la aggiorna, azzerare tutti gli importi la elimina.
 */
class CorrispettiviMensili
{

    public const ORIGINE = 'Corrispettivi';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function getRiferimento(RegistroIva $registro, int $anno, int $mese): string
    {
        return sprintf('corrispettivi-%d-%04d-%02d', $registro->id, $anno, $mese);
    }

    public static function getFineMese(int $anno, int $mese): SismaDate
    {
        return new SismaDate((new \DateTimeImmutable(sprintf('%04d-%02d-01', $anno, $mese)))->format('Y-m-t'));
    }

    /**
     * Imponibile e imposta scorporati dai corrispettivi comprensivi
     * dell'imposta (senza imposta per i codici con natura).
     *
     * @return array{0: float, 1: float}
     */
    public static function scorpora(CodiceIva $codice, float $lordo): array
    {
        $imposta = $codice->aliquota > 0 ? round($lordo * $codice->aliquota / (100 + $codice->aliquota), 2) : 0.0;
        return [round($lordo - $imposta, 2), $imposta];
    }

    public function getRegistrazione(RegistroIva $registro, int $anno, int $mese): ?RegistrazioneContabile
    {
        return (new RegistrazioneContabileModel($this->dataMapper))->getGenerata($registro->soggetto, self::ORIGINE, self::getRiferimento($registro, $anno, $mese));
    }

    /**
     * Importi registrati nel mese.
     *
     * @return array<string, array<int, float>> per data (AAAA-MM-GG) e id del codice IVA
     */
    public function getImporti(?RegistrazioneContabile $registrazione): array
    {
        $importi = [];
        if ($registrazione !== null) {
            foreach ((new CorrispettivoGiornalieroModel($this->dataMapper))->getPerRegistrazione($registrazione) as $corrispettivo) {
                $importi[$corrispettivo->data->format('Y-m-d')][$corrispettivo->codiceIva->id] = $corrispettivo->importo;
            }
        }
        return $importi;
    }

    /**
     * Registra (o aggiorna, o elimina se tutti gli importi sono zero) i
     * corrispettivi del mese.
     *
     * @param array<string, array<int, float>> $importi per data e id del codice IVA
     * @param array<int, CodiceIva> $codici codici IVA per id
     * @return RegistrazioneContabile|null null se non ci sono corrispettivi
     * @throws \DomainException se registro, date, codici o esercizio non sono validi
     */
    public function registra(RegistroIva $registro, int $anno, int $mese, array $importi, array $codici): ?RegistrazioneContabile
    {
        if ($registro->tipo !== TipoRegistroIva::corrispettivi) {
            throw new \DomainException('Scegliere un registro dei corrispettivi.');
        }
        $fine = self::getFineMese($anno, $mese);
        $prefisso = sprintf('%04d-%02d-', $anno, $mese);
        $totali = [];
        $giornalieri = [];
        foreach ($importi as $data => $perCodice) {
            if ((str_starts_with((string) $data, $prefisso) === false) || ((string) $data > $fine->format('Y-m-d')) || (\DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data) === false)) {
                throw new \DomainException('Giorno ' . $data . ' fuori dal mese.');
            }
            foreach ($perCodice as $idCodice => $importo) {
                $codice = $codici[$idCodice] ?? throw new \DomainException('Codice IVA non disponibile.');
                if ($codice->inversioneContabile) {
                    throw new \DomainException('Il codice ' . $codice->codice . ' (integrazione dell\'imposta) non vale per i corrispettivi.');
                }
                $importo = round((float) $importo, 2);
                if ($importo == 0) {
                    continue;
                }
                $giornalieri[] = [$data, $codice, $importo];
                $totali[$idCodice] = round(($totali[$idCodice] ?? 0.0) + $importo, 2);
            }
        }
        foreach ($totali as $idCodice => $totale) {
            if ($totale < 0) {
                throw new \DomainException('Il totale del mese per il codice ' . $codici[$idCodice]->codice . ' è negativo.');
            }
        }
        $totali = array_filter($totali, fn(float $totale) => $totale != 0);
        $esistente = $this->getRegistrazione($registro, $anno, $mese);
        if (($esistente !== null) && ($esistente->isModificabile() === false)) {
            throw new \DomainException('L\'esercizio della registrazione n. ' . $esistente->numero . ' è chiuso.');
        }
        $salvataggio = new SalvataggioRegistrazione($this->dataMapper);
        if (count($totali) === 0) {
            if ($esistente !== null) {
                $salvataggio->elimina($esistente);
            }
            return null;
        }
        $soggetto = $registro->soggetto;
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerData($soggetto, $fine);
        if (($esercizio === null) || $esercizio->chiuso) {
            throw new \DomainException('Nessun esercizio aperto al ' . $fine->format('d/m/Y') . '.');
        }
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto) ?? throw new \DomainException('La contabilità dell\'azienda non è aperta.');
        $registrazione = $esistente ?? new RegistrazioneContabile($this->dataMapper);
        $chiaveProtocollo = SalvataggioRegistrazione::chiaveProtocollo($registrazione);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->causale = CausaleContabile::corrispettivi;
        $registrazione->dataRegistrazione = $fine;
        $registrazione->dataCompetenzaIva = $fine;
        $registrazione->registroIva = $registro;
        $registrazione->descrizione = 'Corrispettivi di ' . LiquidazioneIva::descriviPeriodo(PeriodicitaIva::mensile, $anno, $mese);
        $registrazione->totaleDocumento = round(array_sum($totali), 2);
        $registrazione->origine = self::ORIGINE;
        $registrazione->riferimentoOrigine = self::getRiferimento($registro, $anno, $mese);
        $righeIva = [];
        foreach ($totali as $idCodice => $totale) {
            $riga = new RigaIva($this->dataMapper);
            $riga->codiceIva = $codici[$idCodice];
            [$riga->imponibile, $riga->imposta] = self::scorpora($codici[$idCodice], $totale);
            $righeIva[] = $riga;
        }
        $righe = (new PropostaRighe($this->dataMapper))->proponi($piano, $registrazione, $righeIva);
        $errori = (new ControlloRegistrazione())->verifica($piano, $registrazione, $righeIva, $righe);
        if (count($errori) > 0) {
            throw new \DomainException(implode(' ', $errori));
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        $salvataggio->salva($registrazione, $righeIva, $righe, $chiaveProtocollo);
        foreach ($giornalieri as [$data, $codice, $importo]) {
            $corrispettivo = new CorrispettivoGiornaliero($this->dataMapper);
            $corrispettivo->registrazioneContabile = $registrazione;
            $corrispettivo->soggetto = $soggetto;
            $corrispettivo->data = new SismaDate($data);
            $corrispettivo->codiceIva = $codice;
            $corrispettivo->importo = $importo;
            $this->dataMapper->save($corrispettivo);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $registrazione;
    }
}
