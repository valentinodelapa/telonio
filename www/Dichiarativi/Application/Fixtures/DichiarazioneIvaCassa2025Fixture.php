<?php

namespace Dichiarativi\Application\Fixtures;

use Contabilita\Application\Services\DestinazioneRisultato;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Contabilita\Application\Services\RateiRisconti;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Services\Ammortamenti;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RateoRisconto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\Cespite;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Studio grafico demo (trimestrale, sede a Firenze) con l'opzione per
 * l'IVA per cassa dal 2025 (art. 32-bis D.L. 83/2012; dal 2027 art. 132
 * D.Lgs. 10/2026): fatture emesse incassate in parte e in ritardo,
 * acquisti pagati in parte. Le liquidazioni 2025 hanno l'imposta esigibile
 * e detraibile nei trimestri degli incassi e dei pagamenti abbinati; la
 * fattura di novembre non incassata resta sospesa (diventa comunque
 * esigibile dopo un anno, a novembre 2026). Dichiarazione IVA 2025
 * proposta con le operazioni sospese nel VE37 e nel VF23 (campi 2 per
 * l'IVA per cassa) e l'opzione nel rigo VO15. Fine dell'esercizio 2025:
 * computer tra i cespiti con l'ammortamento (metà del 20% nel primo
 * esercizio), autovettura con le quote deducibili al 20% sul costo fino a
 * 18.075,99 euro (variazione RF18), risconto della polizza annuale da
 * luglio, epilogo e chiusura dei conti con la riapertura nel 2026 e
 * destinazione dell'utile alla delibera del 30 aprile 2026.
 */
class DichiarazioneIvaCassa2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoBianchiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $demo = new AziendaDemo($this->dataMapper);
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $azienda = $demo->creaSocieta($studio, 'C077', '09990008709', 'Lumen Grafica S.r.l.', NaturaGiuridica::societaResponsabilitaLimitata, '74.12.01', PeriodicitaIva::trimestrale,
                'D612', 'Via dei Servi 18', '50122');
        $demo->variaDatiFiscali($azienda, '2025-01-01', ['ivaPerCassa' => true]);
        $demo->legame($azienda, $this->getEntityByFixtureName(SoggettoBianchiFixture::class), RuoloLegame::amministratoreUnico);
        $esercizio = $demo->apriContabilita($azienda, 2025);
        $editore = $demo->controparte($azienda, 'Editoriale Toscana S.r.l.', '09990018708', true);
        $museo = $demo->controparte($azienda, 'Fondazione Arte Viva', '09990028707', true);
        $agenzia = $demo->controparte($azienda, 'Agenzia Viaggi Ponte S.r.l.', '09990038706', true);
        $tipografia = $demo->controparte($azienda, 'Tipografia Medicea S.r.l.', '09990048705', false);
        $software = $demo->controparte($azienda, 'Software Creativo S.p.A.', '09990058704', false);
        $vendita = CausaleContabile::fatturaVendita;
        $acquisto = CausaleContabile::fatturaAcquisto;
        $cassa = ['esigibilita' => EsigibilitaIva::perCassa];
        // vendite: incassata in due rate (aprile e luglio), incassata a giugno, non incassata nell'anno
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $editore, $vendita, '2025-02-10', '1', ['22' => 10000.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $museo, $vendita, '2025-05-20', '2', ['22' => 5000.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $agenzia, $vendita, '2025-11-15', '3', ['22' => 8000.0], $cassa);
        // acquisti: pagato a marzo, pagato per metà a dicembre
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $tipografia, $acquisto, '2025-01-20', 'TM/14', ['22' => 4000.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $software, $acquisto, '2025-09-10', 'SC/880', ['22' => 3000.0], $cassa);
        // incassi e pagamenti, abbinati alle fatture (partite aperte)
        $incasso = CausaleContabile::incasso;
        $pagamento = CausaleContabile::pagamento;
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $tipografia, $pagamento, '2025-03-31', null, [], ['totale' => 4880.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $editore, $incasso, '2025-04-15', null, [], ['totale' => 6100.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $museo, $incasso, '2025-06-30', null, [], ['totale' => 6100.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $editore, $incasso, '2025-07-10', null, [], ['totale' => 6100.0]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $software, $pagamento, '2025-12-10', null, [], ['totale' => 1830.0]);
        // computer acquistato a giugno e pagato subito: cespite ammortizzato al 20%, metà nel primo esercizio
        $informatica = $demo->controparte($azienda, 'Informatica Fiorentina S.r.l.', '09990068703', false, '02.04.0002');
        $computer = RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $informatica, $acquisto, '2025-06-16', 'IF/233', ['22' => 2000.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $informatica, $pagamento, '2025-06-30', null, [], ['totale' => 2440.0]);
        // polizza annuale da luglio, esente: risconto della parte del 2026
        $assicurazione = $demo->controparte($azienda, 'Assicurazioni Arno S.p.A.', '09990078702', false, '22.01.0005');
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $assicurazione, $acquisto, '2025-07-01', 'POL-5521', ['N4' => 1200.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $assicurazione, $pagamento, '2025-07-01', null, [], ['totale' => 1200.0]);
        // autovettura aziendale: IVA detraibile al 40%, quote deducibili al 20% sul costo fino a 18.075,99 euro
        $concessionario = $demo->controparte($azienda, 'Auto Toscana S.p.A.', '09990088701', false, '02.04.0003');
        $auto = RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $concessionario, $acquisto, '2025-09-30', 'AT/1180', ['A22' => 25000.0], $cassa);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $concessionario, $pagamento, '2025-09-30', null, [], ['totale' => 30500.0]);
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        for ($trimestre = 1; $trimestre <= 4; $trimestre++) {
            $elaborazione->elabora($azienda, 2025, $trimestre, 0.0, false);
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $studio;
        $dichiarazione->soggetto = $azienda;
        $dichiarazione->modello = ModelloDichiarativo::iva;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $this->dataMapper->save($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, new QuadriDichiarazione($dichiarazione, $this->dataMapper));
        $this->chiudiEsercizio($demo, $azienda, $esercizio, $computer, $auto);
        $this->addEntity($dichiarazione);
    }

    /**
     * Fine dell'esercizio 2025: cespite e ammortamento, risconto della
     * polizza, chiusura dei conti e riapertura nel 2026.
     */
    private function chiudiEsercizio(AziendaDemo $demo, Soggetto $azienda, EsercizioContabile $esercizio, RegistrazioneContabile $computer, RegistrazioneContabile $auto): void
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($azienda);
        $conti = new ContoModel($this->dataMapper);
        $cespite = new Cespite($this->dataMapper);
        $cespite->studio = $azienda->studio;
        $cespite->soggetto = $azienda;
        $cespite->codice = '001';
        $cespite->descrizione = 'Computer per la grafica';
        $cespite->contoCespite = $conti->getPerCodice($piano, '02.04.0002');
        $cespite->contoFondo = $conti->getPerCodice($piano, '03.02.0005');
        $cespite->contoAmmortamento = $conti->getPerCodice($piano, '25.01.0002');
        $cespite->dataAcquisto = new SismaDate('2025-06-16');
        $cespite->costo = 2000.0;
        $cespite->percentuale = 20.0;
        $cespite->riduzionePrimoEsercizio = true;
        $cespite->registrazioneAcquisto = $computer;
        $this->dataMapper->save($cespite);
        // costo dell'autovettura dalle righe sul conto del bene (imponibile e IVA indetraibile)
        $vettura = new Cespite($this->dataMapper);
        $vettura->studio = $azienda->studio;
        $vettura->soggetto = $azienda;
        $vettura->codice = '002';
        $vettura->descrizione = 'Autovettura aziendale';
        $vettura->contoCespite = $conti->getPerCodice($piano, '02.04.0003');
        $vettura->contoFondo = $conti->getPerCodice($piano, '03.02.0006');
        $vettura->contoAmmortamento = $conti->getPerCodice($piano, '25.01.0002');
        $vettura->dataAcquisto = new SismaDate('2025-09-30');
        foreach ((new RigaContabileModel($this->dataMapper))->getRighe($auto) as $riga) {
            if ($riga->conto->id === $vettura->contoCespite->id) {
                $vettura->costo += $riga->dare;
            }
        }
        $vettura->percentuale = 25.0;
        $vettura->riduzionePrimoEsercizio = true;
        $vettura->percentualeDeducibile = Ammortamenti::DEDUCIBILE_AUTOVETTURE;
        $vettura->limiteCostoFiscale = Ammortamenti::LIMITE_AUTOVETTURE;
        $vettura->registrazioneAcquisto = $auto;
        $this->dataMapper->save($vettura);
        (new Ammortamenti($this->dataMapper))->registra($azienda, $esercizio);
        $polizza = new RateoRisconto($this->dataMapper);
        $polizza->studio = $azienda->studio;
        $polizza->soggetto = $azienda;
        $polizza->anno = 2025;
        $polizza->conto = $conti->getPerCodice($piano, '22.01.0005');
        $polizza->descrizione = 'Polizza RC professionale 07/2025-06/2026';
        $polizza->importo = 1200.0;
        $polizza->competenzaDal = new SismaDate('2025-07-01');
        $polizza->competenzaAl = new SismaDate('2026-06-30');
        $polizza->registratoNellEsercizio = true;
        $this->dataMapper->save($polizza);
        $demo->apriEsercizio($azienda, 2026);
        (new RateiRisconti($this->dataMapper))->registra($azienda, $esercizio);
        (new ChiusuraEsercizio($this->dataMapper))->genera($azienda, $esercizio);
        // destinazione dell'utile alla delibera del 30 aprile 2026: 5% alla riserva legale, il resto alla straordinaria
        $destinazione = new DestinazioneRisultato($this->dataMapper);
        $destinazione->registra($azienda, $esercizio, new \DateTimeImmutable('2026-04-30'), $destinazione->proponi($azienda, $esercizio)['righe']);
    }
}
