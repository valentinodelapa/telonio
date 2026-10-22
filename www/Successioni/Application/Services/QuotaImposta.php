<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\BeneficiarioSuccessione;

/**
 * Imposta di successione sulla quota di un beneficiario, con i passaggi
 * della guida dell'Agenzia ("Modalità di calcolo dell'imposta di
 * successione", allegato alle specifiche tecniche):
 * QE quota ereditaria (esclusi beni esenti e denaro, gioielli e mobilia),
 * QDN denaro, gioielli e mobilia, QP passività, AN = QE + QDN - QP,
 * FR franchigia, QN = AN - FR, PR presunzione del 10% su denaro, gioielli
 * e mobilia, QTI = QN + PR, ISL = QTI x aliquota, RID riduzioni,
 * DET imposta estera detraibile, ISN = ISL - RID - DET.
 */
class QuotaImposta
{

    public float $quotaEreditaria = 0.0;
    public float $quotaDenaro = 0.0;
    public float $quotaPassivo = 0.0;
    public float $attivoNetto = 0.0;
    public float $franchigia = 0.0;
    public float $quotaNetta = 0.0;
    public float $presunzione = 0.0;
    public float $imponibile = 0.0;
    public float $aliquota = 0.0;
    public float $impostaLorda = 0.0;
    public float $riduzioni = 0.0;
    public float $detrazioneEstero = 0.0;
    public float $impostaNetta = 0.0;

    public function __construct(public readonly BeneficiarioSuccessione $beneficiario)
    {

    }
}
