<?php

namespace Contabilita\Application\Enumerations;

/**
 * Ruolo del conto nelle proposte automatiche della prima nota: ciascuna
 * funzione è assegnata al più a un conto del piano. Clienti e fornitori
 * sono conti (livello C) sotto cui si aprono i sottoconti del partitario;
 * le altre funzioni sono sottoconti.
 */
enum FunzioneConto: string
{

    case clienti = 'CLI';
    case fornitori = 'FOR';
    case ivaVendite = 'IVV';
    case ivaAcquisti = 'IVA';
    case erarioIva = 'EIV';
    case accontoIva = 'ACI';
    case banca = 'BAN';
    case cassa = 'CAS';
    case ricavi = 'RIC';
    case corrispettivi = 'RCO';
    case costi = 'COS';
    case arrotondamentiAttivi = 'ARA';
    case arrotondamentiPassivi = 'ARP';
    case ivaGruppo = 'IVG';
    case ritenuteSubite = 'RTS';
    case ritenuteDaVersare = 'RTV';
    case rateiAttivi = 'RAA';
    case riscontiAttivi = 'RIA';
    case rateiPassivi = 'RAP';
    case riscontiPassivi = 'RIP';
    case utileEsercizio = 'UTE';
    case perditaEsercizio = 'PEE';
    case aperturaPatrimoniale = 'SPA';
    case chiusuraPatrimoniale = 'SPC';
    case contoEconomico = 'CEC';
    case plusvalenze = 'PLV';
    case minusvalenze = 'MNV';
    case capitaleSociale = 'CSO';
    case capitaleNetto = 'CNE';
    case riservaLegale = 'RLE';
    case riservaStraordinaria = 'RST';
    case utiliANuovo = 'UNU';

    public function getLabel(): string
    {
        return match ($this) {
            self::clienti => 'Clienti (partitario)',
            self::fornitori => 'Fornitori (partitario)',
            self::ivaVendite => 'IVA sulle vendite',
            self::ivaAcquisti => 'IVA sugli acquisti',
            self::erarioIva => 'Erario c/IVA (liquidazioni)',
            self::accontoIva => 'Erario c/acconto IVA',
            self::banca => 'Banca (incassi e pagamenti)',
            self::cassa => 'Cassa (corrispettivi)',
            self::ricavi => 'Ricavi di vendita proposti',
            self::corrispettivi => 'Ricavi dei corrispettivi',
            self::costi => 'Costi di acquisto proposti',
            self::arrotondamentiAttivi => 'Arrotondamenti attivi',
            self::arrotondamentiPassivi => 'Arrotondamenti passivi',
            self::ivaGruppo => 'IVA di gruppo (debiti e crediti trasferiti alla controllante)',
            self::ritenuteSubite => 'Erario c/ritenute subite (incassi dei compensi)',
            self::ritenuteDaVersare => 'Erario c/ritenute da versare (pagamenti ai professionisti)',
            self::rateiAttivi => 'Ratei attivi (assestamento)',
            self::riscontiAttivi => 'Risconti attivi (assestamento)',
            self::rateiPassivi => 'Ratei passivi (assestamento)',
            self::riscontiPassivi => 'Risconti passivi (assestamento)',
            self::utileEsercizio => 'Utile dell\'esercizio (chiusura)',
            self::perditaEsercizio => 'Perdita dell\'esercizio (chiusura)',
            self::aperturaPatrimoniale => 'Stato patrimoniale di apertura',
            self::chiusuraPatrimoniale => 'Stato patrimoniale di chiusura',
            self::contoEconomico => 'Conto economico (epilogo)',
            self::plusvalenze => 'Plusvalenze (cessione dei cespiti)',
            self::minusvalenze => 'Minusvalenze (cessione dei cespiti)',
            self::capitaleSociale => 'Capitale sociale',
            self::capitaleNetto => 'Capitale netto (imprese individuali e società di persone)',
            self::riservaLegale => 'Riserva legale',
            self::riservaStraordinaria => 'Riserva straordinaria',
            self::utiliANuovo => 'Utili (perdite) portati a nuovo',
        };
    }

    /**
     * Livello del conto a cui la funzione si assegna.
     */
    public function getLivello(): LivelloConto
    {
        return match ($this) {
            self::clienti, self::fornitori => LivelloConto::conto,
            default => LivelloConto::sottoconto,
        };
    }
}
