<?php

namespace Telonio\Tests\Application\HelperClasses;

use Telonio\Application\HelperClasses\Iban;
use Telonio\Tests\SuiteTestCase;

class IbanTest extends SuiteTestCase
{

    public function testNormalizzaRimuoveSpaziEConverteInMaiuscolo(): void
    {
        $this->assertSame('IT60X0542811101000000123456', Iban::normalizza(' it60 x054 2811 1010 0000 0123 456 '));
    }

    public function testCalcolaCinBbanItaliano(): void
    {
        $this->assertSame('X', Iban::calcolaCin('0542811101000000123456'));
        $this->assertSame('X', Iban::calcolaCin('0542811101000000123456'));
    }

    public function testIbanItalianoValidoENonValido(): void
    {
        // IBAN italiano valido
        $this->assertTrue(Iban::isValido('IT60X0542811101000000123456'));

        // CIN errato (pur mantenendo lunghezza 27)
        $this->assertFalse(Iban::isValido('IT60A0542811101000000123456'));

        // Cifre di controllo mod 97 errate (CIN corretto X ma check digit 61 invece di 60)
        $this->assertFalse(Iban::isValido('IT61X0542811101000000123456'));

        // Lunghezza italiana errata (26 caratteri)
        $this->assertFalse(Iban::isValido('IT60X054281110100000012345'));

        // Formato generale non valido (es. minuscole o troppo corto)
        $this->assertFalse(Iban::isValido('it60x0542811101000000123456'));
        $this->assertFalse(Iban::isValido('IT60'));
    }

    public function testIbanEsteroSepaValidoENonValido(): void
    {
        // Germania (DE89370400440532013000)
        $this->assertTrue(Iban::isValido('DE89370400440532013000'));
        $this->assertFalse(Iban::isValido('DE00370400440532013000'));

        // Francia (FR7630006000011234567890189)
        $this->assertTrue(Iban::isValido('FR7630006000011234567890189'));
        $this->assertFalse(Iban::isValido('FR7630006000011234567890100'));
    }
}
