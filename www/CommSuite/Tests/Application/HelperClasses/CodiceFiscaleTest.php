<?php

namespace CommSuite\Tests\Application\HelperClasses;

use CommSuite\Application\HelperClasses\CodiceFiscale;
use CommSuite\Tests\SuiteTestCase;

class CodiceFiscaleTest extends SuiteTestCase
{

    public function testNormalizzaRimuoveSpaziEConverteInMaiuscolo(): void
    {
        $this->assertSame('RSSMRA80A01H501U', CodiceFiscale::normalizza(" rssmra 80a01\th501u\n"));
        $this->assertSame('01234567897', CodiceFiscale::normalizza(' 012 345 67897 '));
    }

    public function testCalcolaUomoConConsonantiMenoDiQuattro(): void
    {
        // Rossi Mario, M, 01/01/1980, Roma (H501) -> RSSMRA80A01H501U
        $cf = CodiceFiscale::calcola('Rossi', 'Mario', 'M', new \DateTimeImmutable('1980-01-01'), 'h501');
        $this->assertSame('RSSMRA80A01H501U', $cf);
        $this->assertTrue(CodiceFiscale::isValidoPersonaFisica($cf));
    }

    public function testCalcolaDonnaConConsonantiAlmenoQuattroEVocali(): void
    {
        // Fo Alessandro -> cognome FO (1 cons + 1 voc + X = FOX), nome Alessandro (LSSNDR -> 1a, 3a, 4a cons = LSN)
        // Sesso F -> giorno + 40 (15 + 40 = 55), mese Maggio = E
        $cf = CodiceFiscale::calcola('Fo', 'Alessandra', 'F', new \DateTimeImmutable('1992-05-15'), 'F205');
        $this->assertSame('FOXLSN92E55F205F', $cf);
        $this->assertTrue(CodiceFiscale::isValidoPersonaFisica($cf));
    }

    public function testValidazionePersonaFisicaEControlloErrori(): void
    {
        $this->assertTrue(CodiceFiscale::isValidoPersonaFisica('rssmra80a01h501u'));
        // Carattere di controllo errato
        $this->assertFalse(CodiceFiscale::isValidoPersonaFisica('RSSMRA80A01H501Z'));
        // Lunghezza / formato non valido
        $this->assertFalse(CodiceFiscale::isValidoPersonaFisica('RSSMRA80A01H501'));
        $this->assertFalse(CodiceFiscale::isValidoPersonaFisica('01234567897'));
        // Mese non valido (F non è nella tabella CODICI_MESE)
        $this->assertFalse(CodiceFiscale::isValidoPersonaFisica('RSSMRA80F01H501U'));
    }

    public function testValidazioneEDecodificaOmocodia(): void
    {
        // RSSMRA80A01H501U con ultima cifra numerica (1 in pos 14 -> M) -> RSSMRA80A01H50MU
        // Calcoliamo il carattere di controllo per RSSMRA80A01H50M:
        // Con pos 14 = 'M' (indice 14, pari -> dispari in 1-based -> VALORI_DISPARI['M'] = 18 invece di '1' = 0, +18 -> U(20)+18=38%26=12 -> M)
        $cfOmocodico = 'RSSMRA80A01H50MM';
        $this->assertTrue(CodiceFiscale::isValidoPersonaFisica($cfOmocodico));
        $this->assertSame('M', CodiceFiscale::estraiSesso($cfOmocodico));
        $this->assertSame('H501', CodiceFiscale::estraiCodiceCatastale($cfOmocodico));
        $this->assertSame('1980-01-01', CodiceFiscale::estraiDataNascita($cfOmocodico, new \DateTimeImmutable('2025-01-01'))->format('Y-m-d'));
    }

    public function testValidazioneNumerico(): void
    {
        $this->assertTrue(CodiceFiscale::isValidoNumerico('01234567897'));
        $this->assertFalse(CodiceFiscale::isValidoNumerico('01234567890'));
        $this->assertFalse(CodiceFiscale::isValidoNumerico('RSSMRA80A01H501U'));
    }

    public function testEstrazioneSessoDataNascitaConSecoloECodiceCatastale(): void
    {
        $cfMaschio = 'RSSMRA80A01H501U';
        $cfFemmina = CodiceFiscale::calcola('Bianchi', 'Giulia', 'F', new \DateTimeImmutable('2005-12-24'), 'A944');

        $this->assertSame('M', CodiceFiscale::estraiSesso($cfMaschio));
        $this->assertSame('F', CodiceFiscale::estraiSesso($cfFemmina));

        $this->assertSame('H501', CodiceFiscale::estraiCodiceCatastale($cfMaschio));
        $this->assertSame('A944', CodiceFiscale::estraiCodiceCatastale($cfFemmina));

        // Anno 80 rispetto a 2025 -> 1980
        $data1980 = CodiceFiscale::estraiDataNascita($cfMaschio, new \DateTimeImmutable('2025-06-01'));
        $this->assertSame('1980-01-01', $data1980->format('Y-m-d'));

        // Anno 05 rispetto a 2025 -> 2005
        $data2005 = CodiceFiscale::estraiDataNascita($cfFemmina, new \DateTimeImmutable('2025-06-01'));
        $this->assertSame('2005-12-24', $data2005->format('Y-m-d'));
    }
}
