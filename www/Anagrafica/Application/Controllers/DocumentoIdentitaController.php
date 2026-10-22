<?php

namespace Anagrafica\Application\Controllers;

use Anagrafica\Application\BaseClasses\AnagraficaController;
use Anagrafica\Application\Entities\DocumentoIdentita;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoDocumento;
use Anagrafica\Application\Forms\DocumentoIdentitaForm;
use Anagrafica\Application\Services\SincronizzazioneSoggetto;
use SismaFramework\Core\Exceptions\BadRequestException;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

class DocumentoIdentitaController extends AnagraficaController
{

    public function create(Request $request, Soggetto $soggetto): Response
    {
        $this->autorizza($soggetto);
        if ($soggetto->isPersonaFisica() === false) {
            throw new BadRequestException('Documenti di identità previsti solo per le persone fisiche');
        }
        $documento = new DocumentoIdentita($this->dataMapper);
        $documento->soggetto = $soggetto;
        return $this->gestisci($request, $documento, 'Nuovo documento di identità');
    }

    public function update(Request $request, DocumentoIdentita $documentoIdentita): Response
    {
        $this->autorizza($documentoIdentita->soggetto);
        return $this->gestisci($request, $documentoIdentita, 'Modifica documento di identità');
    }

    public function delete(Request $request, DocumentoIdentita $documentoIdentita): Response
    {
        $soggetto = $documentoIdentita->soggetto;
        $this->autorizza($soggetto);
        $this->verificaCsrf($request);
        $this->dataMapper->delete($documentoIdentita);
        (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($soggetto);
        $this->aggiungiMessaggio('Documento eliminato.');
        return $this->vaiAllaScheda($soggetto, 'documenti');
    }

    private function gestisci(Request $request, DocumentoIdentita $documento, string $titolo): Response
    {
        $form = new DocumentoIdentitaForm($documento);
        if ($this->isInvioValido($request, $form)) {
            $documento = $form->resolveEntity();
            $this->dataMapper->save($documento);
            (new SincronizzazioneSoggetto($this->dataMapper))->aggiorna($documento->soggetto);
            $this->aggiungiMessaggio('Documento salvato.');
            return $this->vaiAllaScheda($documento->soggetto, 'documenti');
        }
        $this->impostaVariabiliForm($form, $documento);
        $this->vars['soggetto'] = $documento->soggetto;
        $this->vars['tipiDocumento'] = TipoDocumento::cases();
        $this->vars['nuovo'] = isset($documento->id) === false;
        $this->vars['pageTitle'] = $titolo;
        $this->impostaBriciole($documento->soggetto, $titolo);
        return $this->render->generateView('documentoIdentita/form', $this->vars, $form->getResponseType());
    }
}
