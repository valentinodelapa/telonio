<?php

namespace Deleghe\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Punto di ingresso per i moduli che calcolano versamenti (IMU, IVA,
 * dichiarazioni, ...): registrano nell'archivio del contribuente i
 * versamenti dovuti, ciascuno con la sua data di versamento; le deleghe si
 * generano poi dall'archivio (Services\GenerazioneDeleghe).
 *
 * Ogni gruppo di versamenti è identificato da origine (il modulo, es. "Imu")
 * e riferimento (es. l'id del calcolo): una nuova registrazione con gli
 * stessi identificativi sostituisce i versamenti precedenti. Quelli già in
 * una delega in bozza sono sostituiti nella stessa delega; quelli in una
 * delega confermata, trasmessa o quietanzata non si toccano e la differenza
 * con il nuovo calcolo è restituita nell'esito. Esempio:
 *
 * $acconto = new Versamento($dataMapper);
 * $acconto->scadenza = new SismaDate('2026-06-16');
 * $acconto->sezione = SezioneF24::tributiLocali;
 * $acconto->codiceTributo = (new CodiceTributoModel($dataMapper))->getByCodice(SezioneCodiceTributo::tributiLocali, '3918');
 * $acconto->codiceEnte = 'H501';
 * $acconto->acconto = true;
 * $acconto->numeroImmobili = 1;
 * $acconto->annoRiferimento = 2026;
 * $acconto->importoDebito = 412.00;
 * $acconto->descrizione = 'IMU acconto 2026';
 * $saldo = ... (scadenza 16/12)
 * (new ArchivioVersamenti($dataMapper))->registra($soggetto, 'Imu', 'calcolo-15', [$acconto, $saldo]);
 */
class ArchivioVersamenti
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @param Versamento[] $versamenti versamenti dovuti, non salvati, con la
     *        scadenza; un elenco vuoto elimina quelli registrati in precedenza
     * @param Delega|null $destinazione delega in cui registrarli (es. il
     *        ravvedimento calcolato in una delega aperta dall'utente): la
     *        scadenza è quella della delega
     * @throws DelegaNonModificabileException se la delega di destinazione non è in bozza
     * @throws \InvalidArgumentException se un versamento non è compilato correttamente
     */
    public function registra(Soggetto $soggetto, string $origine, string $riferimento, array $versamenti, ?Delega $destinazione = null): EsitoRegistrazione
    {
        if (($destinazione !== null) && (($destinazione->isModificabile() === false) || ($destinazione->soggetto->id !== $soggetto->id))) {
            throw new DelegaNonModificabileException($destinazione);
        }
        foreach ($versamenti as $versamento) {
            if ($destinazione !== null) {
                $versamento->scadenza = $destinazione->scadenza;
            }
        }
        $this->verifica($versamenti);
        $esito = new EsitoRegistrazione();
        $primaTransazione = $this->dataMapper->startTransaction();
        [$deleghePerScadenza, $bloccati, $conDetrazione] = $this->rimuoviPrecedenti($soggetto, $origine, $riferimento);
        $versamentoModel = new VersamentoModel($this->dataMapper);
        $nuoviBloccati = [];
        foreach ($versamenti as $versamento) {
            $scadenza = $versamento->scadenza->format('Y-m-d');
            if (isset($bloccati[$scadenza])) {
                $nuoviBloccati[$scadenza] = ($nuoviBloccati[$scadenza] ?? 0) + EsitoControllo::centesimi($versamento->importoDebito - $versamento->importoCredito);
                continue;
            }
            $versamento->studio = $soggetto->studio;
            $versamento->soggetto = $soggetto;
            $versamento->delega = $destinazione ?? $deleghePerScadenza[$scadenza] ?? null;
            $versamento->origine = $origine;
            $versamento->riferimentoOrigine = $riferimento;
            if ($versamento->delega !== null) {
                $versamento->ordine = $versamentoModel->getProssimoOrdine($versamento->delega, $versamento->sezione);
                $versamento->delega->dataModifica = new SismaDateTime();
                $this->dataMapper->save($versamento->delega);
            }
            $this->dataMapper->save($versamento);
            $esito->versamenti[] = $versamento;
        }
        $this->aggiornaDetrazioni(array_merge(array_values($deleghePerScadenza), array_filter(array_map(fn(Versamento $versamento) => $versamento->delega, $esito->versamenti))), $conDetrazione);
        foreach ($bloccati as $scadenza => ['delega' => $delega, 'importo' => $importo]) {
            $nuovo = $nuoviBloccati[$scadenza] ?? 0;
            if ($nuovo !== $importo) {
                $esito->differenze[] = sprintf('Versamento del %s già nella delega %s: in delega € %s, ricalcolato € %s.', (new \DateTimeImmutable($scadenza))->format('d/m/Y'),
                        mb_strtolower($delega->stato->getLabel()), EsitoControllo::euro($importo), EsitoControllo::euro($nuovo));
            }
        }
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito;
    }

    /**
     * @param Versamento[] $versamenti
     */
    private function verifica(array $versamenti): void
    {
        $controllo = new ControlloDelega($this->dataMapper);
        foreach ($versamenti as $indice => $versamento) {
            if (isset($versamento->scadenza) === false) {
                throw new \InvalidArgumentException('Versamento ' . ($indice + 1) . ' senza data di versamento.');
            }
            $errori = $controllo->verificaDatiRiga($versamento, $versamento->sezione, $versamento->scadenza);
            if (count($errori) > 0) {
                throw new \InvalidArgumentException('Versamento ' . ($indice + 1) . ' non valido: ' . implode(' ', $errori));
            }
        }
    }

    /**
     * Elimina i versamenti registrati in precedenza che si possono ancora
     * sostituire.
     *
     * @return array{0: array<string, Delega>, 1: array<string, array{delega: Delega, importo: int}>, 2: bool}
     *         deleghe in bozza che li contenevano e versamenti in deleghe non
     *         modificabili (importo netto in centesimi), per scadenza; se tra
     *         gli eliminati c'erano righi con la detrazione IMU
     */
    private function rimuoviPrecedenti(Soggetto $soggetto, string $origine, string $riferimento): array
    {
        $deleghePerScadenza = $bloccati = [];
        $conDetrazione = false;
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, $origine, $riferimento) as $precedente) {
            $scadenza = $precedente->scadenza->format('Y-m-d');
            if ($precedente->isModificabile() === false) {
                $bloccati[$scadenza] ??= ['delega' => $precedente->delega, 'importo' => 0];
                $bloccati[$scadenza]['importo'] += EsitoControllo::centesimi($precedente->importoDebito - $precedente->importoCredito);
                continue;
            }
            if ($precedente->delega !== null) {
                $deleghePerScadenza[$scadenza] = $precedente->delega;
                $conDetrazione = $conDetrazione || ($precedente->detrazioneImu !== null);
            }
            $this->dataMapper->delete($precedente);
        }
        return [$deleghePerScadenza, $bloccati, $conDetrazione];
    }

    /**
     * Allinea la detrazione IMU delle deleghe in bozza toccate dalla
     * registrazione.
     *
     * @param Delega[] $deleghe
     */
    private function aggiornaDetrazioni(array $deleghe, bool $azzeraSeAssente): void
    {
        $generazione = new GenerazioneDeleghe($this->dataMapper);
        $aggiornate = [];
        foreach ($deleghe as $delega) {
            if (isset($aggiornate[$delega->id]) === false) {
                $aggiornate[$delega->id] = true;
                $generazione->aggiornaDetrazioneImu($delega, $azzeraSeAssente);
                $this->dataMapper->save($delega);
            }
        }
    }
}
