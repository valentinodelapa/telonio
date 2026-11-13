<?php

namespace Contabilita\Application\Enumerations;

/**
 * Modalità di pagamento della fattura elettronica (campo
 * ModalitaPagamento del blocco DatiPagamento, schema VFPR12): usata per le
 * rate delle partite e per i termini abituali della controparte.
 */
enum ModalitaPagamento: string
{

    case contanti = 'MP01';
    case assegno = 'MP02';
    case assegnoCircolare = 'MP03';
    case contantiTesoreria = 'MP04';
    case bonifico = 'MP05';
    case vaglia = 'MP06';
    case bollettinoBancario = 'MP07';
    case carta = 'MP08';
    case rid = 'MP09';
    case ridUtenze = 'MP10';
    case ridVeloce = 'MP11';
    case riba = 'MP12';
    case mav = 'MP13';
    case quietanzaErario = 'MP14';
    case giroconto = 'MP15';
    case domiciliazioneBancaria = 'MP16';
    case domiciliazionePostale = 'MP17';
    case bollettinoPostale = 'MP18';
    case sepaDirectDebit = 'MP19';
    case sepaCore = 'MP20';
    case sepaB2b = 'MP21';
    case trattenuta = 'MP22';
    case pagoPa = 'MP23';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::contanti => 'Contanti',
            self::assegno => 'Assegno',
            self::assegnoCircolare => 'Assegno circolare',
            self::contantiTesoreria => 'Contanti presso Tesoreria',
            self::bonifico => 'Bonifico',
            self::vaglia => 'Vaglia cambiario',
            self::bollettinoBancario => 'Bollettino bancario',
            self::carta => 'Carta di pagamento',
            self::rid => 'RID',
            self::ridUtenze => 'RID utenze',
            self::ridVeloce => 'RID veloce',
            self::riba => 'RIBA',
            self::mav => 'MAV',
            self::quietanzaErario => 'Quietanza erario',
            self::giroconto => 'Giroconto su conti di contabilità speciale',
            self::domiciliazioneBancaria => 'Domiciliazione bancaria',
            self::domiciliazionePostale => 'Domiciliazione postale',
            self::bollettinoPostale => 'Bollettino di c/c postale',
            self::sepaDirectDebit => 'SEPA Direct Debit',
            self::sepaCore => 'SEPA Direct Debit CORE',
            self::sepaB2b => 'SEPA Direct Debit B2B',
            self::trattenuta => 'Trattenuta su somme già riscosse',
            self::pagoPa => 'PagoPA',
        };
    }

    public function getLabelBreve(): string
    {
        return substr($this->getLabel(), 7);
    }
}
