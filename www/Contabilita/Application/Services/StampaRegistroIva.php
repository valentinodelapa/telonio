<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CorrispettivoGiornalieroModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Registro IVA del periodo: documenti in ordine di protocollo con le
 * righe IVA (per i corrispettivi registrati per mese anche l'ammontare
 * giornaliero per codice IVA), totali per codice IVA e, nel registro delle vendite
 * principale, le integrazioni dell'imposta degli acquisti in inversione
 * contabile (annotate anche tra le fatture emesse: art. 17, c. 5 e 6,
 * D.P.R. 633/1972, art. 47 D.L. 331/1993).
 */
class StampaRegistroIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return array{documenti: array, totali: array<string, array{codice: string, imponibile: float, imposta: float, detraibile: float}>, integrazioni: array, totaliIntegrazioni: array{imponibile: float, imposta: float}}
     */
    public function dati(RegistroIva $registro, SismaDate $dal, SismaDate $al): array
    {
        $rigaIvaModel = new RigaIvaModel($this->dataMapper);
        $righe = [];
        foreach ($rigaIvaModel->getPerPeriodo($registro, $dal, $al) as $riga) {
            $righe[$riga->registrazioneContabile->id][] = $riga;
        }
        $documenti = $totali = [];
        foreach ((new RegistrazioneContabileModel($this->dataMapper))->getPerRegistroIva($registro, $dal, $al) as $id => $registrazione) {
            $righeDocumento = $righe[$id] ?? [];
            usort($righeDocumento, fn($prima, $seconda) => $prima->ordine <=> $seconda->ordine);
            foreach ($righeDocumento as $riga) {
                $chiave = $riga->codiceIva->codice;
                $totali[$chiave] ??= ['codice' => $riga->codiceIva->getEtichetta(), 'imponibile' => 0.0, 'imposta' => 0.0, 'detraibile' => 0.0, 'rivendita' => 0.0];
                if ($riga->rivendita) {
                    $totali[$chiave]['rivendita'] = round($totali[$chiave]['rivendita'] + $riga->imponibile + $riga->imposta, 2);
                }
                $totali[$chiave]['imponibile'] = round($totali[$chiave]['imponibile'] + $riga->imponibile, 2);
                $totali[$chiave]['imposta'] = round($totali[$chiave]['imposta'] + $riga->imposta, 2);
                $totali[$chiave]['detraibile'] = round($totali[$chiave]['detraibile'] + $riga->getImpostaDetraibile(), 2);
            }
            $giornalieri = [];
            if ($registro->tipo === TipoRegistroIva::corrispettivi) {
                foreach ((new CorrispettivoGiornalieroModel($this->dataMapper))->getPerRegistrazione($registrazione) as $corrispettivo) {
                    $giornalieri[$corrispettivo->data->format('Y-m-d')][$corrispettivo->codiceIva->codice] = $corrispettivo;
                }
            }
            $documenti[] = ['registrazione' => $registrazione, 'righe' => $righeDocumento, 'giornalieri' => $giornalieri];
        }
        $integrazioni = [];
        $totaliIntegrazioni = ['imponibile' => 0.0, 'imposta' => 0.0];
        if (($registro->tipo === TipoRegistroIva::vendite) && ($registro->sezionale === '')) {
            foreach ($rigaIvaModel->getPerPeriodo($registro->soggetto, $dal, $al) as $riga) {
                if (($riga->registroIva->tipo === TipoRegistroIva::acquisti) && $riga->codiceIva->inversioneContabile) {
                    $integrazioni[] = $riga;
                    $totaliIntegrazioni['imponibile'] = round($totaliIntegrazioni['imponibile'] + $riga->imponibile, 2);
                    $totaliIntegrazioni['imposta'] = round($totaliIntegrazioni['imposta'] + $riga->imposta, 2);
                }
            }
        }
        ksort($totali);
        return ['documenti' => $documenti, 'totali' => $totali, 'integrazioni' => $integrazioni, 'totaliIntegrazioni' => $totaliIntegrazioni];
    }
}
