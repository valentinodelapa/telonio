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
