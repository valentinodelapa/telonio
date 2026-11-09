<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\PertinenzaLocata;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\VariazioneCanone;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoContrattoLocazione;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\ResidenzaRossiFixture;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use Anagrafica\Application\Models\ComuneModel;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use Dichiarativi\Application\Entities\CertificazioneUnica;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreCertificazioneUnica;
use Dichiarativi\Application\Enumerations\CinquePerMille;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\OttoPerMille;
use Dichiarativi\Application\HelperClasses\AziendaDemo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditi;
use Dichiarativi\Application\Services\Redditi\PuntiCertificazioneUnica as Punti;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Imu\Application\Fixtures\ImmobileMassimenoRossiFixture;
use Imu\Application\Fixtures\PossessoMassimenoRossiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Modello Redditi PF 2026 demo di Mario Rossi (anno 2025), in bozza:
 * - lavoratore dipendente con la Certificazione Unica del datore di lavoro
 *   (quadro RC, ritenute, addizionali trattenute, ulteriore detrazione);
 * - abitazione principale a Roma e casa di Massimeno locata a canone
 *   libero con la cedolare secca al 21 per cento (contratto con il box
 *   pertinenza locato insieme e l'aggiornamento ISTAT dal 1° luglio):
 *   quadri RB e LC;
 * - prospetto dei familiari proposto dai legami familiari: la coniuge
 *   Laura senza redditi (a carico), il figlio Marco di 22 anni studente
 *   (a carico, detrazione per i 12 mesi da 21 anni, al 100 per cento perché
 *   la coniuge è a carico) e la figlia Giulia di 15 anni (nel prospetto,
 *   senza detrazione: assegno unico);
 * - scelte dell'otto per mille (Stato, edilizia scolastica) e del cinque
 *   per mille (ricerca sanitaria);
 * - saldi e acconti registrati in Deleghe F24.
 */
class RedditiRossi2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class)
                ->addDependency(ResidenzaRossiFixture::class)
                ->addDependency(ImmobileMassimenoRossiFixture::class)
                ->addDependency(PossessoMassimenoRossiFixture::class)
                ->addDependency(IntermediarioStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $rossi = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $casa = $this->getEntityByFixtureName(ImmobileMassimenoRossiFixture::class);
        $box = $this->creaBox($rossi, $casa);

        $contratto = new ContrattoLocazione($this->dataMapper);
        $contratto->immobile = $casa;
        $contratto->tipo = TipoContrattoLocazione::canoneLibero;
        $contratto->conduttore = 'BIANCHI LUCA';
        $contratto->dataInizio = new SismaDate('2023-09-01');
        $contratto->canoneAnnuo = 6000.0;
        $contratto->cedolareSecca = true;
        $contratto->codiceIdentificativo = 'TNT23T001234000AB';
        $this->dataMapper->save($contratto);
        $pertinenza = new PertinenzaLocata($this->dataMapper);
        $pertinenza->contrattoLocazione = $contratto;
        $pertinenza->immobile = $box;
        $this->dataMapper->save($pertinenza);
        $variazione = new VariazioneCanone($this->dataMapper);
        $variazione->contrattoLocazione = $contratto;
        $variazione->validoDal = new SismaDate('2025-07-01');
        $variazione->canoneAnnuo = 6120.0;
        $variazione->note = 'Aggiornamento ISTAT';
        $this->dataMapper->save($variazione);

        $certificazione = new CertificazioneUnica($this->dataMapper);
        $certificazione->studio = $rossi->studio;
        $certificazione->soggetto = $rossi;
        $certificazione->annoImposta = 2025;
        $certificazione->codiceFiscaleSostituto = '01234560785';
        $certificazione->denominazioneSostituto = 'ALFA CONSULTING S.R.L.';
        $this->dataMapper->save($certificazione);
        foreach ([Punti::INDETERMINATO => 32000.0, Punti::GIORNI_LAVORO => 365, Punti::RITENUTE_IRPEF => 6150.0, Punti::ADDIZIONALE_REGIONALE => 520.0,
            Punti::ACCONTO_COMUNALE => 80.0, Punti::SALDO_COMUNALE => 190.0, Punti::ACCONTO_COMUNALE_SUCCESSIVO => 85.0, Punti::CODICE_TRATTAMENTO_INTEGRATIVO => 2,
            Punti::TIPOLOGIA_RIDUZIONE => 1, Punti::REDDITI_ART_49 => 32000.0, Punti::GIORNI_RIDUZIONE => 365, Punti::CODICE_SOMMA_ESENTE => 2] as $punto => $importo) {
            $valore = new ValoreCertificazioneUnica($this->dataMapper);
            $valore->certificazioneUnica = $certificazione;
            $valore->punto = $punto;
            $valore->valore = (float) $importo;
            $this->dataMapper->save($valore);
        }

        // famiglia: coniuge e figli
        $demo = new AziendaDemo($this->dataMapper);
        $laura = $demo->persona($rossi->studio, 'C071', 'RSSLRA82A41H501Z', 'Rossi', 'Laura', Sesso::femmina, '1982-01-01', 'H501', 'Coniuge di Mario Rossi');
        $marco = $demo->persona($rossi->studio, 'C072', 'RSSMRC03C15H501Z', 'Rossi', 'Marco', Sesso::maschio, '2003-03-15', 'H501', 'Figlio di Mario Rossi, studente');
        $giulia = $demo->persona($rossi->studio, 'C073', 'RSSGLI10P60H501I', 'Rossi', 'Giulia', Sesso::femmina, '2010-09-20', 'H501', 'Figlia di Mario Rossi');
        $demo->legame($rossi, $laura, RuoloLegame::coniuge, '2002-06-15');
        $demo->legame($rossi, $marco, RuoloLegame::figlio, '2003-03-15');
        $demo->legame($rossi, $giulia, RuoloLegame::figlio, '2010-09-20');

        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $rossi->studio;
        $dichiarazione->soggetto = $rossi;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        // scelte: otto per mille allo Stato per l'edilizia scolastica, cinque per mille alla ricerca sanitaria, due per mille non espresso
        $dichiarazione->ottoPerMille = OttoPerMille::stato;
        $dichiarazione->finalitaStato = 3;
        $dichiarazione->cinquePerMille = CinquePerMille::ricercaSanitaria;
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        (new PropostaRedditi($this->dataMapper))->proponi($dichiarazione, $quadri);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $versamenti = new VersamentiRedditi($this->dataMapper);
        $versamenti->registraVersamenti($dichiarazione, $quadri);
        $versamenti->registraCrediti($dichiarazione, $quadri);
        $this->addEntity($dichiarazione);
    }

    /**
     * Box di pertinenza della casa di Massimeno (C/6), locato con la casa.
     */
    private function creaBox($rossi, Immobile $casa): Immobile
    {
        $box = new Immobile($this->dataMapper);
        $box->studio = $rossi->studio;
        $box->tipoCatasto = TipoCatasto::fabbricati;
        $box->comune = (new ComuneModel($this->dataMapper))->getByCodice('F045');
        $box->foglio = $casa->foglio;
        $box->particella = $casa->particella;
        $box->subalterno = '9';
        $box->indirizzo = $casa->indirizzo;
        $box->descrizione = 'Box auto';
        $this->dataMapper->save($box);
        $dati = new DatiCatastali($this->dataMapper);
        $dati->immobile = $box;
        $dati->validoDal = new SismaDate('2005-01-01');
        $dati->causale = CausaleClassamento::costituzione;
        $dati->categoria = CategoriaCatastale::c6;
        $dati->classe = '2';
        $dati->consistenza = 15;
        $dati->rendita = 60.0;
        $this->dataMapper->save($dati);
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $box;
        $possesso->soggetto = $rossi;
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2005-01-01');
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $this->dataMapper->save($possesso);
        return $box;
    }
}
