<?php

namespace Dichiarativi\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\BaseClasses\DichiarativiController;
use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero;
use Dichiarativi\Application\Forms\AttivitaEsteraForm;
use Dichiarativi\Application\Models\AttivitaEsteraModel;
use Dichiarativi\Application\Services\TabelleRiferimento\StatiEsteriRedditi;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Archivio delle attività estere del contribuente (quadro RW e proventi
 * esteri): inserimento, modifica, eliminazione e copia nell'anno
 * successivo con il valore finale come valore iniziale.
 */
class AttivitaEsteraController extends DichiarativiController
{

    public function create(Request $request, Soggetto $soggetto, int $anno = 0): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $attivita = new AttivitaEstera($this->dataMapper);
        $attivita->studio = $this->studio;
        $attivita->soggetto = $soggetto;
        $attivita->annoImposta = self::normalizzaAnno($anno);
        return $this->gestisci($request, $attivita, 'Nuova attività estera');
    }

    public function update(Request $request, AttivitaEstera $attivitaEstera): Response
    {
        $this->autorizzaSoggetto($attivitaEstera->soggetto);
        return $this->gestisci($request, $attivitaEstera, 'Modifica attività estera');
    }

    public function delete(Request $request, AttivitaEstera $attivitaEstera): Response
    {
        $this->autorizzaSoggetto($attivitaEstera->soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($attivitaEstera);
        $this->aggiungiMessaggio('Attività estera eliminata.');
        return $this->vaiAlContribuente($attivitaEstera->soggetto, $attivitaEstera->annoImposta);
    }

    /**
     * Copia le attività dell'anno nell'anno successivo (detenute al 31
     * dicembre, non già copiate): valore finale come valore iniziale,
     * proventi e imposte da indicare.
     */
    public function copia(Request $request, Soggetto $soggetto, int $anno): Response
    {
        $this->autorizzaSoggetto($soggetto);
        $this->verificaCsrf($request);
        $model = new AttivitaEsteraModel($this->dataMapper);
        $esistenti = array_map(fn(AttivitaEstera $voce) => $voce->descrizione . '|' . $voce->codiceInvestimento->value, $model->getPerAnno($soggetto, $anno + 1));
        $copiate = 0;
        $transazione = $this->dataMapper->startTransaction();
        foreach ($model->getPerAnno($soggetto, $anno) as $voce) {
            if (($voce->dataFine !== null) || in_array($voce->descrizione . '|' . $voce->codiceInvestimento->value, $esistenti, true)) {
                continue;
            }
            $nuova = new AttivitaEstera($this->dataMapper);
            foreach (['studio', 'soggetto', 'codiceInvestimento', 'codiceStatoEstero', 'descrizione', 'intermediario', 'titoloPossesso', 'tipoContribuente', 'quota', 'criterioValore',
                'mesiAbitazionePrincipale', 'codiceQuadri', 'quotaPartecipazione', 'codiceFiscaleEntita', 'cointestatario1', 'cointestatario2', 'piuCointestatari', 'soloMonitoraggio', 'tipoProventi', 'note'] as $campo) {
                $nuova->$campo = $voce->$campo;
            }
            $nuova->annoImposta = $anno + 1;
            $nuova->valoreIniziale = $voce->valoreFinale;
            $nuova->valoreFinale = $voce->valoreFinale;
            $this->dataMapper->save($nuova);
            $copiate++;
        }
        $this->dataMapper->commitTransaction($transazione);
        $this->aggiungiMessaggio($copiate . ' attività estere copiate nel ' . ($anno + 1) . ': aggiorna valori finali, proventi e imposte.');
        return $this->vaiAlContribuente($soggetto, $anno + 1);
    }

    private function gestisci(Request $request, AttivitaEstera $attivita, string $titolo): Response
    {
        $form = new AttivitaEsteraForm($attivita);
        if ($this->isInvioValido($request, $form)) {
            $attivita = $form->resolveEntity();
            $this->dataMapper->save($attivita);
            $this->aggiungiMessaggio('Attività estera salvata.');
            return $this->vaiAlContribuente($attivita->soggetto, $attivita->annoImposta);
        }
        $this->vars['formEntity'] = $form->isSubmitted() ? $form->getEntityDataToStandardEntity() : $attivita;
        $this->vars['errors'] = $form->getFilterErrors();
        $this->vars['codiciInvestimento'] = CodiceInvestimentoEstero::cases();
        $this->vars['statiEsteri'] = StatiEsteriRedditi::getElenco();
        $this->vars['tipiProventi'] = AttivitaEstera::PROVENTI;
        $this->vars['titoliPossesso'] = [1 => '1 - Proprietà', 2 => '2 - Usufrutto', 3 => '3 - Nuda proprietà', 4 => '4 - Altro (altro diritto reale, beneficiario di trust)'];
        $this->vars['tipiContribuente'] = [1 => '1 - Delegato al prelievo o alla movimentazione', 2 => '2 - Titolare effettivo'];
        $this->vars['criteriValore'] = [1 => '1 - Valore di mercato', 2 => '2 - Valore nominale', 3 => '3 - Valore di rimborso', 4 => '4 - Costo d\'acquisto', 5 => '5 - Valore catastale', 6 => '6 - Dichiarazione di successione o altri atti'];
        $this->vars['codiciQuadri'] = [1 => '1 - Quadro RL', 2 => '2 - Quadro RM', 3 => '3 - Quadro RT', 4 => '4 - Due o tre quadri tra RL, RM e RT', 5 => '5 - Redditi in anni successivi o bene infruttifero'];
        $this->vars['soggetto'] = $attivita->soggetto;
        $this->vars['anno'] = $attivita->annoImposta;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBricioleContribuente($attivita->soggetto, $attivita->annoImposta, $titolo);
        return $this->render->generateView('attivitaEstera/form', $this->vars, $form->getResponseType());
    }
}
