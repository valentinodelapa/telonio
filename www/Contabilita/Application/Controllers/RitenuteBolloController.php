<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Services\ImpostaBolloFatture;
use Contabilita\Application\Services\RitenuteOperate;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\EsitoRegistrazione;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ritenute d'acconto operate sui pagamenti e imposta di bollo sulle fatture
 * elettroniche emesse dell'anno, con la registrazione dei versamenti
 * nell'archivio di Deleghe F24.
 */
class RitenuteBolloController extends ContabilitaController
{

    public function index(Soggetto $soggetto, int $anno = 0): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $anno = $anno > 0 ? $anno : (int) date('Y');
        $this->getEsercizio($soggetto, $anno);
        $versamenti = new VersamentoModel($this->dataMapper);
        $ritenute = (new RitenuteOperate($this->dataMapper))->getPerMese($soggetto, $anno);
        $mesi = [];
        for ($mese = 1; $mese <= 12; $mese++) {
            $registrati = iterator_to_array($versamenti->getGenerati($soggetto, RitenuteOperate::ORIGINE, RitenuteOperate::getRiferimento($anno, $mese)), false);
            if (isset($ritenute[$mese]) || (count($registrati) > 0)) {
                $mesi[$mese] = ['importo' => $ritenute[$mese]['importo'] ?? 0.0, 'righe' => $ritenute[$mese]['righe'] ?? [], 'scadenza' => RitenuteOperate::getScadenza($anno, $mese),
                    'registrati' => $registrati];
            }
        }
        $bollo = new ImpostaBolloFatture($this->dataMapper);
        $riepilogo = $bollo->riepiloga($soggetto, $anno);
        $this->vars['annoTributi'] = $anno;
        $this->vars['anniTributi'] = range((int) date('Y') + 1, (int) date('Y') - 5);
        $this->vars['mesiRitenute'] = $mesi;
        $this->vars['codiceRitenute'] = RitenuteOperate::CODICE_TRIBUTO;
        $this->vars['trimestriBollo'] = $riepilogo['trimestri'];
        $this->vars['pianoBollo'] = ImpostaBolloFatture::pianoVersamenti($anno, array_map(fn(array $trimestre) => $trimestre['importo'], $riepilogo['trimestri']));
        $this->vars['bolloDaVerificare'] = $riepilogo['daVerificare'];
        $this->vars['bolloRegistrati'] = iterator_to_array($versamenti->getGenerati($soggetto, ImpostaBolloFatture::ORIGINE, ImpostaBolloFatture::getRiferimento($anno)), false);
        $this->vars['sezioneAzienda'] = 'ritenuteBollo';
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · Ritenute e bollo ' . $anno;
        $this->impostaBriciole($soggetto, null, 'Ritenute e bollo ' . $anno);
        return $this->render->generateView('ritenuteBollo/index', $this->vars);
    }

    /**
     * Registra il versamento delle ritenute del mese.
     */
    public function ritenute(Request $request, Soggetto $soggetto, int $anno, int $mese): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        try {
            $esito = (new RitenuteOperate($this->dataMapper))->registra($soggetto, $anno, $mese);
            $this->messaggiEsito($esito, sprintf('Ritenute di %02d/%d: ', $mese, $anno));
        } catch (\DomainException | \InvalidArgumentException | DelegaNonModificabileException $errore) {
            $this->aggiungiMessaggio('Versamento non registrato: ' . $errore->getMessage(), 'danger');
        }
        return $this->router->redirect('ritenute-bollo/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    /**
     * Registra i versamenti dell'imposta di bollo dell'anno.
     */
    public function bollo(Request $request, Soggetto $soggetto, int $anno): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $this->verificaCsrf($request);
        try {
            $esito = (new ImpostaBolloFatture($this->dataMapper))->registra($soggetto, $anno);
            $this->messaggiEsito($esito, 'Imposta di bollo ' . $anno . ': ');
        } catch (\DomainException | \InvalidArgumentException | DelegaNonModificabileException $errore) {
            $this->aggiungiMessaggio('Versamenti non registrati: ' . $errore->getMessage(), 'danger');
        }
        return $this->router->redirect('ritenute-bollo/index/soggetto/' . $soggetto->id . '/anno/' . $anno);
    }

    private function messaggiEsito(EsitoRegistrazione $esito, string $prefisso): void
    {
        if (count($esito->versamenti) === 0) {
            $this->aggiungiMessaggio($prefisso . 'nessun versamento da registrare.');
        }
        foreach ($esito->versamenti as $versamento) {
            $this->aggiungiMessaggio($prefisso . 'versamento di € ' . Importo::euro($versamento->importoDebito) . ' (' . $versamento->codiceTributo->codice . ') registrato in Deleghe F24 per il '
                    . $versamento->scadenza->format('d/m/Y') . '.');
        }
        foreach ($esito->differenze as $differenza) {
            $this->aggiungiMessaggio($differenza, 'warning');
        }
    }
}
