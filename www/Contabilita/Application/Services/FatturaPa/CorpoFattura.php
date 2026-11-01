<?php

namespace Contabilita\Application\Services\FatturaPa;

use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;

/**
 * Corpo della fattura elettronica (blocco 2): dati generali del documento
 * e riepiloghi per aliquota o natura (2.2.2).
 */
class CorpoFattura
{

    public int $indice = 1;
    public TipoDocumentoFattura $tipoDocumento;
    public string $divisa = 'EUR';
    public \DateTimeImmutable $data;
    public string $numero;
    public ?float $importoTotale = null;
    public ?string $causale = null;

    /** @var array<int, array{aliquota: float, natura: ?NaturaIva, imponibile: float, imposta: float, esigibilita: EsigibilitaIva, riferimentoNormativo: ?string}> */
    public array $riepiloghi = [];

    /** @var string[] descrizioni delle prime linee di dettaglio */
    public array $descrizioni = [];

    public function getTotale(): float
    {
        $totale = 0.0;
        foreach ($this->riepiloghi as $riepilogo) {
            $totale += $riepilogo['imponibile'] + $riepilogo['imposta'];
        }
        return round($totale, 2);
    }

    /**
     * Esigibilità del documento: scissione o differita se indicata in un
     * riepilogo, altrimenti immediata.
     */
    public function getEsigibilita(): EsigibilitaIva
    {
        $esigibilita = EsigibilitaIva::immediata;
        foreach ($this->riepiloghi as $riepilogo) {
            if ($riepilogo['esigibilita'] !== EsigibilitaIva::immediata) {
                $esigibilita = $riepilogo['esigibilita'];
            }
        }
        return $esigibilita;
    }
}
