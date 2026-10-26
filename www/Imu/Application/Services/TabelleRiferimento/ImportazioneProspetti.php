<?php

namespace Imu\Application\Services\TabelleRiferimento;

use Anagrafica\Application\Models\ImmobileModel;
use Telonio\Application\Entities\Studio;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Models\AliquotaApplicataModel;
use Imu\Application\Models\AliquotaDifferenziataModel;
use Imu\Application\Models\ProspettoAliquoteModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Importa nel database i prospetti pubblicati (ProspettoAliquote senza
 * studio) per i comuni indicati, dal 2025 (primo anno di obbligo del
 * prospetto). Un prospetto già importato si aggiorna; le aliquote
 * differenziate si riconoscono per fattispecie e descrizione, così le
 * scelte degli studi (AliquotaApplicata) restano collegate.
 */
class ImportazioneProspetti
{

    public const PRIMO_ANNO = 2025;

    public function __construct(private DataMapper $dataMapper = new DataMapper(), private ProspettiMef $fonte = new ProspettiMef(), private LettoreProspetto $lettore = new LettoreProspetto())
    {

    }

    /**
     * Codici catastali dei comuni degli immobili (dello studio o di tutti
     * gli studi).
     *
     * @return string[]
     */
    public function getComuniImmobili(?Studio $studio = null): array
    {
        $immobileModel = new ImmobileModel($this->dataMapper);
        $codici = [];
        foreach (($studio === null ? $immobileModel->getEntityCollection() : $immobileModel->getByStudio($studio)) as $immobile) {
            $codici[$immobile->comune->codiceCatastale] = $immobile->comune->codiceCatastale;
        }
        sort($codici);
        return array_values($codici);
    }

    /**
     * @param string[] $codici codici catastali dei comuni
     * @param callable(string): void $messaggio
     * @return array{importati: int, non_pubblicati: string[], errori: string[]}
     */
    public function importa(int $anno, array $codici, callable $messaggio): array
    {
        if ($anno < self::PRIMO_ANNO) {
            throw new \InvalidArgumentException('Il prospetto delle aliquote è obbligatorio dal ' . self::PRIMO_ANNO . ': per gli anni precedenti le aliquote si inseriscono dallo studio.');
        }
        $elenco = $this->fonte->getElenco($anno);
        $esito = ['importati' => 0, 'non_pubblicati' => [], 'errori' => []];
        foreach ($codici as $codice) {
            $voci = $elenco[$codice] ?? [];
            if (count($voci) === 0) {
                $esito['non_pubblicati'][] = $codice;
                $messaggio(sprintf('  %s: nessun prospetto pubblicato per il %d', $codice, $anno));
                continue;
            }
            foreach ($voci as $voce) {
                $etichetta = $codice . ($voce['exComune'] !== null ? ' ex ' . $voce['exComune'] : '');
                try {
                    [$testo, $url] = $this->fonte->getTesto($codice, $anno, $voce['file']);
                    $letto = $this->lettore->leggi($testo);
                    if ($letto['anno'] !== $anno) {
                        throw new \RuntimeException('il prospetto è riferito al ' . $letto['anno']);
                    }
                    $prospetto = $this->salva($codice, $anno, $voce, $letto, $url);
                    $esito['importati']++;
                    $messaggio(sprintf('  %s %s: prospetto n. %d, %d aliquote di base, %d differenziate%s', $etichetta, $prospetto->denominazioneComune, $letto['idProspetto'], count($letto['aliquote']), count($letto['differenziate']),
                                    $voce['note'] !== null ? ' (' . mb_strtolower($voce['note']) . ')' : ''));
                } catch (\RuntimeException $errore) {
                    $esito['errori'][] = $etichetta . ': ' . $errore->getMessage();
                    $messaggio(sprintf('  %s: errore, %s', $etichetta, $errore->getMessage()));
                }
            }
        }
        return $esito;
    }

    private function salva(string $codice, int $anno, array $voce, array $letto, string $url): ProspettoAliquote
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $prospetto = (new ProspettoAliquoteModel($this->dataMapper))->getPubblicatoExComune($codice, $anno, $voce['exComune']) ?? new ProspettoAliquote($this->dataMapper);
        $prospetto->codiceCatastale = $codice;
        $prospetto->denominazioneComune = mb_convert_case($letto['comune'] !== '' ? $letto['comune'] : $voce['comune'], MB_CASE_TITLE, 'UTF-8');
        $prospetto->anno = $anno;
        $prospetto->exComune = $voce['exComune'] === null ? null : mb_convert_case($voce['exComune'], MB_CASE_TITLE, 'UTF-8');
        $prospetto->idProspetto = $letto['idProspetto'];
        $prospetto->numeroDelibera = mb_substr((string) ($letto['numeroDelibera'] ?? $voce['numeroDelibera']), 0, 20);
        $dataDelibera = $letto['dataDelibera'] ?? $voce['dataDelibera'];
        $prospetto->dataDelibera = $dataDelibera === null ? null : new SismaDate($dataDelibera);
        $prospetto->dataPubblicazione = $voce['dataPubblicazione'] === null ? null : new SismaDate($voce['dataPubblicazione']);
        $prospetto->fileProspetto = $voce['file'];
        $prospetto->urlProspetto = mb_substr($url, 0, 255);
        foreach (FattispecieImu::cases() as $fattispecie) {
            $prospetto->{$fattispecie->getProprietaProspetto()} = $letto['aliquote'][$fattispecie->value] ?? null;
        }
        $prospetto->assimilazioneAnziani = $letto['assimilazioneAnziani'];
        $prospetto->esenzioni = $letto['esenzioni'];
        $prospetto->dataImportazione = new SismaDateTime();
        $this->dataMapper->save($prospetto);
        $this->salvaDifferenziate($prospetto, $letto['differenziate']);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $prospetto;
    }

    private function salvaDifferenziate(ProspettoAliquote $prospetto, array $differenziate): void
    {
        $esistenti = [];
        foreach ((new AliquotaDifferenziataModel($this->dataMapper))->getElenco($prospetto) as $esistente) {
            $esistenti[self::chiave($esistente->fattispecie?->value, $esistente->descrizione)][] = $esistente;
        }
        foreach ($differenziate as $ordine => $letta) {
            $chiave = self::chiave($letta['fattispecie'], $letta['descrizione']);
            $differenziata = isset($esistenti[$chiave]) && (count($esistenti[$chiave]) > 0) ? array_shift($esistenti[$chiave]) : new AliquotaDifferenziata($this->dataMapper);
            $differenziata->prospettoAliquote = $prospetto;
            $differenziata->fattispecie = $letta['fattispecie'] === null ? null : FattispecieImu::from($letta['fattispecie']);
            $differenziata->descrizione = $letta['descrizione'] !== '' ? $letta['descrizione'] : '(senza descrizione)';
            $differenziata->aliquota = $letta['aliquota'];
            $differenziata->ordine = $ordine + 1;
            $this->dataMapper->save($differenziata);
        }
        foreach ($esistenti as $residui) {
            foreach ($residui as $residuo) {
                $this->sganciaScelte($residuo);
                $this->dataMapper->delete($residuo);
            }
        }
    }

    /**
     * Le scelte degli studi di una differenziata non più presente nel
     * prospetto diventano aliquote indicate dallo studio, da verificare.
     */
    private function sganciaScelte(AliquotaDifferenziata $differenziata): void
    {
        foreach ((new AliquotaApplicataModel($this->dataMapper))->getByAliquotaDifferenziata($differenziata) as $scelta) {
            $scelta->aliquotaDifferenziata = null;
            $scelta->aliquota = $differenziata->aliquota;
            $scelta->motivo = 'Aliquota differenziata non più presente nel prospetto aggiornato: verificare';
            $this->dataMapper->save($scelta);
        }
    }

    private static function chiave(?string $fattispecie, string $descrizione): string
    {
        return ($fattispecie ?? '') . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', trim($descrizione)));
    }
}
