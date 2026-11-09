<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Contratto di locazione o di affitto. L'immobile è impostato dal
 * controller. Controlli dalle istruzioni e dalle specifiche tecniche del
 * modello Redditi PF (quadro RB, sezioni II e III): codice identificativo
 * del contratto di 16 o 17 caratteri alternativo agli estremi di
 * registrazione, CIN di 18 caratteri, cedolare secca solo per i
 * fabbricati.
 */
class ContrattoLocazioneForm extends BaseForm
{

    private const SERIE = ['1T', '3', '3P', '3T', '3A', '3B', '3X'];
    private const MAIUSCOLI = ['codiceFiscaleConduttore', 'serieRegistrazione', 'codiceUfficio', 'codiceIdentificativo', 'cin'];
    private const SPUNTE = ['cedolareSecca', 'cedolareRidotta', 'veneziaCentro', 'emergenzaCalamita', 'giovaneAgricoltore'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return ContrattoLocazione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::MAIUSCOLI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['canoneAnnuo']) && is_string($this->request->input['canoneAnnuo'])) {
            $canone = trim($this->request->input['canoneAnnuo']);
            $this->addRequest('canoneAnnuo', str_contains($canone, ',') ? str_replace(',', '.', str_replace('.', '', $canone)) : $canone);
        }
        foreach (self::SPUNTE as $campo) {
            $this->addRequest($campo, ($this->request->input[$campo] ?? '') === '1' ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipo', FilterType::isEnumeration)
                ->addFilterFieldMode('conduttore', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('codiceFiscaleConduttore', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('dataInizio', FilterType::isDate)
                ->addFilterFieldMode('dataFine', FilterType::isDate, [], true)
                ->addFilterFieldMode('canoneAnnuo', FilterType::isNumeric)
                ->addFilterFieldMode('cedolareSecca', FilterType::isBoolean)
                ->addFilterFieldMode('cedolareRevocataDal', FilterType::isDate, [], true)
                ->addFilterFieldMode('cedolareRidotta', FilterType::isBoolean)
                ->addFilterFieldMode('veneziaCentro', FilterType::isBoolean)
                ->addFilterFieldMode('emergenzaCalamita', FilterType::isBoolean)
                ->addFilterFieldMode('giovaneAgricoltore', FilterType::isBoolean)
                ->addFilterFieldMode('dataRegistrazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('serieRegistrazione', FilterType::isMaxLimitString, [2], true)
                ->addFilterFieldMode('numeroRegistrazione', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('codiceUfficio', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('codiceIdentificativo', FilterType::isMaxLimitString, [17], true)
                ->addFilterFieldMode('cin', FilterType::isMaxLimitString, [18], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $dati = $this->entityData;
        $errore = function (string $campo, string $messaggio) use (&$valido): void {
            $this->formFilterError->{$campo . 'CustomMessage'} = $messaggio;
            $valido = false;
        };
        $tipo = $dati->tipo;
        $terreno = isset($this->entity->immobile) && ($this->entity->immobile->tipoCatasto === TipoCatasto::terreni);
        if (($tipo instanceof TipoContrattoLocazione) && ($tipo->isTerreno() !== $terreno)) {
            $errore('tipo', $terreno ? 'Per un terreno il contratto è un affitto (vincolistico o di libero mercato).' : 'L\'affitto in regime vincolistico o libero riguarda i terreni.');
        }
        if (($dati->dataFine !== null) && ($dati->dataInizio !== null) && ($dati->dataFine < $dati->dataInizio)) {
            $errore('dataFine', 'La fine non può precedere l\'inizio del contratto.');
        }
        if (($dati->canoneAnnuo !== null) && ($dati->canoneAnnuo < 0)) {
            $errore('canoneAnnuo', 'Il canone non può essere negativo.');
        }
        if ($dati->cedolareSecca && ($tipo instanceof TipoContrattoLocazione) && ($tipo->ammetteCedolare() === false)) {
            $errore('cedolareSecca', 'La cedolare secca riguarda le locazioni di fabbricati.');
        }
        if (($dati->cedolareRevocataDal !== null) && ($dati->cedolareSecca === false)) {
            $errore('cedolareRevocataDal', 'La revoca richiede l\'opzione per la cedolare secca.');
        }
        if ($dati->cedolareRidotta && ($tipo !== TipoContrattoLocazione::locazioneBreve)) {
            $errore('cedolareRidotta', 'Il 21 per cento sull\'unità scelta riguarda le locazioni brevi.');
        }
        if ($dati->emergenzaCalamita && (($tipo !== TipoContrattoLocazione::canoneConcordato) || ($dati->cedolareSecca === false))) {
            $errore('emergenzaCalamita', 'Riguarda il canone concordato con la cedolare secca (RB, col. 20).');
        }
        if ($dati->giovaneAgricoltore && (($tipo instanceof TipoContrattoLocazione) && ($tipo->isTerreno() === false))) {
            $errore('giovaneAgricoltore', 'Riguarda l\'affitto di terreni.');
        }
        if (($dati->serieRegistrazione !== null) && (in_array($dati->serieRegistrazione, self::SERIE, true) === false)) {
            $errore('serieRegistrazione', 'Serie ammesse: ' . implode(', ', self::SERIE) . '.');
        }
        $estremi = ($dati->dataRegistrazione !== null) || ($dati->numeroRegistrazione !== null) || ($dati->serieRegistrazione !== null) || ($dati->codiceUfficio !== null);
        if ($estremi && ($dati->codiceIdentificativo !== null)) {
            $errore('codiceIdentificativo', 'Il codice identificativo è alternativo agli estremi di registrazione.');
        }
        if (($dati->codiceIdentificativo !== null) && (in_array(strlen($dati->codiceIdentificativo), [16, 17], true) === false)) {
            $errore('codiceIdentificativo', 'Il codice identificativo del contratto ha 16 o 17 caratteri.');
        }
        if (($dati->cin !== null) && (strlen($dati->cin) !== 18)) {
            $errore('cin', 'Il codice identificativo nazionale (CIN) ha 18 caratteri.');
        }
        return $valido;
    }
}
