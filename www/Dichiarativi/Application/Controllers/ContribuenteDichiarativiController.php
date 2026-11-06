<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\SoggettoModel;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\AttoAccertamentoModel;
use Dichiarativi\Application\Models\CampoModelloModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\ModelliContribuente;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Ingresso del modulo: si sceglie il contribuente e si lavora nella sua
 * scheda dell'anno d'imposta (dichiarazioni dovute, presentate e
 * versamenti registrati per le deleghe F24).
 */
class ContribuenteDichiarativiController extends DichiarativiController
{

    private const RISULTATI_PER_PAGINA = 25;

    /**
     * Contribuenti dello studio (di default i soli clienti) con le
     * dichiarazioni dell'anno.
     */
    public function index(string $testo = '', bool $tutti = false, int $pagina = 1, int $anno = 0): Response
    {
        $anno = self::normalizzaAnno($anno);
        $soggettoModel = new SoggettoModel($this->dataMapper);
        $totale = $soggettoModel->contaRisultati($this->studio, $testo, null, null, $tutti === false);
        $pagine = max(1, (int) ceil($totale / self::RISULTATI_PER_PAGINA));
        $pagina = min(max(1, $pagina), $pagine);
        $dichiarazioneModel = new DichiarazioneModel($this->dataMapper);
        $contribuenti = [];
        foreach ($soggettoModel->cerca($this->studio, $testo, null, null, $tutti === false, ($pagina - 1) * self::RISULTATI_PER_PAGINA, self::RISULTATI_PER_PAGINA) as $soggetto) {
            $contribuenti[] = ['soggetto' => $soggetto, 'dichiarazioni' => iterator_to_array($dichiarazioneModel->getElenco($soggetto, $anno), false)];
        }
        $this->vars['contribuenti'] = $contribuenti;
        $this->vars['totale'] = $totale;
        $this->vars['pagina'] = $pagina;
        $this->vars['pagine'] = $pagine;
        $this->vars['testo'] = $testo;
        $this->vars['inclusiTutti'] = $tutti;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['briciole'] = ['Dichiarazioni' => null];
        return $this->render->generateView('contribuenteDichiarativi/index', $this->vars);
    }

    /**
     * Scheda del contribuente per l'anno d'imposta.
     */
    public function show(Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $anno = self::normalizzaAnno($anno);
        $dichiarazioneModel = new DichiarazioneModel($this->dataMapper);
        $catalogoModel = new CampoModelloModel($this->dataMapper);
        $modelli = [];
        foreach ((new ModelliContribuente($this->dataMapper))->getModelli($soggetto, $anno) as $valore => $motivo) {
            $modello = ModelloDichiarativo::from($valore);
            // modelli trimestrali: una riga per trimestre
            foreach ($modello->isTrimestrale() ? [1, 2, 3] : [null] as $trimestre) {
                $modelli[] = [
                    'modello' => $modello,
                    'trimestre' => $trimestre,
                    'motivo' => $motivo,
                    'dichiarazione' => $dichiarazioneModel->getPerModello($soggetto, $modello, $anno, $trimestre),
                    'catalogo' => $catalogoModel->contaPerModello($modello, ModelloDichiarativo::getAnnoModello($anno, $modello)) > 0,
                ];
            }
        }
        $versamenti = [];
        foreach (ModelloDichiarativo::cases() as $modello) {
            if ($modello->isTrimestrale()) {
                continue;
            }
            $riferimento = mb_strtolower($modello->value) . '-' . $anno;
            array_push($versamenti, ...iterator_to_array((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, Dichiarazione::ORIGINE, $riferimento), false));
        }
        $this->vars['soggetto'] = $soggetto;
        $this->vars['anno'] = $anno;
        $this->vars['anni'] = self::getAnni();
        $this->vars['modelli'] = $modelli;
        $this->vars['versamenti'] = $versamenti;
        $this->vars['atti'] = (new AttoAccertamentoModel($this->dataMapper))->getElenco($soggetto);
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · dichiarazioni anno ' . $anno;
        $this->impostaBricioleContribuente($soggetto, $anno);
        return $this->render->generateView('contribuenteDichiarativi/show', $this->vars);
    }
}
