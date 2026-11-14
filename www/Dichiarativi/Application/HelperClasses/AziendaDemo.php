<?php

namespace Dichiarativi\Application\HelperClasses;

use Deleghe\Application\Entities\Intermediario;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\AperturaContabilita;
use Contabilita\Application\Services\Partitario;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CodiceTributoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\Iva\PropostaTr;
use Dichiarativi\Application\Services\Iva\VersamentiTr;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Telonio\Application\Entities\Studio;

/**
 * Costruzione delle aziende demo delle fixture del modulo: soggetto
 * dell'Anagrafica con dati fiscali, sede e legami, contabilità aperta dal
 * piano dei conti standard e controparti.
 */
class AziendaDemo
{

    public function __construct(private DataMapper $dataMapper)
    {

    }

    public function creaSocieta(Studio $studio, string $codice, string $partitaIva, string $denominazione, NaturaGiuridica $natura, string $ateco,
            PeriodicitaIva $periodicita, string $comune, string $indirizzo, string $cap): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::societaCapitali;
        $soggetto->codice = $codice;
        $soggetto->codiceFiscale = $partitaIva;
        $soggetto->nominativo = $denominazione;
        $soggetto->partitaIva = $partitaIva;
        $soggetto->clienteStudio = true;
        $soggetto->dataCostituzione = new SismaDate('2018-03-01');
        $this->dataMapper->save($soggetto);
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $soggetto;
        $datiFiscali->validoDal = new SismaDate('2018-03-01');
        $datiFiscali->denominazione = $denominazione;
        $datiFiscali->naturaGiuridica = $natura;
        $datiFiscali->partitaIva = $partitaIva;
        $datiFiscali->regimeFiscale = RegimeFiscale::ordinario;
        $datiFiscali->periodicitaIva = $periodicita;
        $datiFiscali->soggettoIsa = true;
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, $ateco);
        $this->dataMapper->save($datiFiscali);
        $sede = new Indirizzo($this->dataMapper);
        $sede->soggetto = $soggetto;
        $sede->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $sede->validoDal = new SismaDate('2018-03-01');
        $sede->indirizzo = $indirizzo;
        $sede->cap = $cap;
        $sede->comune = (new ComuneModel($this->dataMapper))->getByCodice($comune);
        $sede->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($sede);
        return $soggetto;
    }

    /**
     * Nuovo periodo dei dati fiscali dalla data, con le proprietà indicate
     * diverse dal periodo vigente, che si chiude il giorno prima.
     *
     * @param array<string, mixed> $variazioni
     */
    public function variaDatiFiscali(Soggetto $soggetto, string $dal, array $variazioni): DatiFiscali
    {
        $vigenti = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($dal));
        $nuovi = new DatiFiscali($this->dataMapper);
        foreach (['denominazione', 'naturaGiuridica', 'tipoAttivita', 'partitaIva', 'codiceAteco', 'regimeFiscale', 'periodicitaIva', 'soggettoIsa', 'dataInizioAttivita', 'regimeDetrazioneIva', 'opzioniOrdinarie', 'rinunciaEsonero', 'trimestraleSpeciale', 'ivaPerCassa'] as $proprieta) {
            $nuovi->$proprieta = $vigenti->$proprieta;
        }
        $vigenti->validoAl = new SismaDate((new \DateTimeImmutable($dal))->modify('-1 day')->format('Y-m-d'));
        $this->dataMapper->save($vigenti);
        $nuovi->soggetto = $soggetto;
        $nuovi->validoDal = new SismaDate($dal);
        foreach ($variazioni as $proprieta => $valore) {
            $nuovi->$proprieta = $valore;
        }
        $this->dataMapper->save($nuovi);
        return $nuovi;
    }

    /**
     * Sede operativa (unità locale) del soggetto.
     */
    public function sedeOperativa(Soggetto $soggetto, string $comune, string $indirizzo, string $cap): Indirizzo
    {
        $sede = new Indirizzo($this->dataMapper);
        $sede->soggetto = $soggetto;
        $sede->tipoIndirizzo = TipoIndirizzo::sedeOperativa;
        $sede->validoDal = new SismaDate('2018-03-01');
        $sede->indirizzo = $indirizzo;
        $sede->cap = $cap;
        $sede->comune = (new ComuneModel($this->dataMapper))->getByCodice($comune);
        $sede->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->dataMapper->save($sede);
        return $sede;
    }

    /**
     * Sezionale IVA collegato a un'unità locale.
     */
    public function sezionale(Soggetto $soggetto, TipoRegistroIva $tipo, string $sezionale, string $descrizione, ?Indirizzo $unitaLocale): RegistroIva
    {
        $registro = new RegistroIva($this->dataMapper);
        $registro->studio = $soggetto->studio;
        $registro->soggetto = $soggetto;
        $registro->tipo = $tipo;
        $registro->sezionale = $sezionale;
        $registro->descrizione = $descrizione;
        $registro->unitaLocale = $unitaLocale;
        $this->dataMapper->save($registro);
        return $registro;
    }

    public function persona(Studio $studio, string $codice, string $codiceFiscale, string $cognome, string $nome, Sesso $sesso, string $nascita, string $comune, ?string $note = null): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codice = $codice;
        $soggetto->codiceFiscale = $codiceFiscale;
        $soggetto->nominativo = mb_strtoupper($cognome . ' ' . $nome);
        $soggetto->clienteStudio = false;
        $soggetto->cognome = $cognome;
        $soggetto->nome = $nome;
        $soggetto->sesso = $sesso;
        $soggetto->dataNascita = new SismaDate($nascita);
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl($comune, $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->note = $note;
        $this->dataMapper->save($soggetto);
        return $soggetto;
    }

    public function legame(Soggetto $soggetto, Soggetto $collegato, RuoloLegame $ruolo, string $dal = '2018-03-01', ?float $quota = null): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->quotaPartecipazione = $quota;
        $legame->soggetto = $soggetto;
        $legame->soggettoCollegato = $collegato;
        $legame->ruolo = $ruolo;
        $legame->validoDal = new SismaDate($dal);
        $this->dataMapper->save($legame);
    }

    /**
     * Apre la contabilità dal piano standard con il primo esercizio.
     */
    /**
     * Modello IVA TR presentato: proposta dai registri con gli importi
     * indicati (TD6, TD7, TE50, TE51), credito in Deleghe e annotazione
     * sulla liquidazione che chiude il trimestre.
     *
     * @param array<string, float> $importi
     */
    public function presentaTr(Soggetto $soggetto, int $anno, int $trimestre, array $importi, string $presentazione, ?Soggetto $visto = null, ?Intermediario $intermediario = null): Dichiarazione
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->anno = $anno;
        $dichiarazione->trimestre = $trimestre;
        (new Frontespizio($this->dataMapper))->proponi($dichiarazione);
        $dichiarazione->vistoProfessionista = $visto;
        $dichiarazione->intermediario = $intermediario ?? $dichiarazione->intermediario;
        $this->dataMapper->save($dichiarazione);
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        foreach ($importi as $codice => $importo) {
            $quadri->imposta($codice, $importo);
        }
        (new PropostaTr($this->dataMapper))->proponi($dichiarazione, $quadri);
        $dichiarazione->stato = StatoDichiarazione::presentata;
        $dichiarazione->dataPresentazione = new SismaDate($presentazione);
        $this->dataMapper->save($dichiarazione);
        (new VersamentiTr($this->dataMapper))->registraCredito($dichiarazione, new QuadriDichiarazione($dichiarazione, $this->dataMapper));
        return $dichiarazione;
    }

    /**
     * Delega trasmessa con il credito trimestrale compensato con le
     * ritenute del mese.
     */
    public function compensaCreditoTr(Dichiarazione $dichiarazione, float $importo, string $scadenza): void
    {
        $this->compensaCredito((new VersamentiTr($this->dataMapper))->getCredito($dichiarazione), $importo, $scadenza);
    }

    /**
     * Delega trasmessa con il credito compensato con le ritenute del mese.
     */
    public function compensaCredito(Credito $credito, float $importo, string $scadenza): void
    {
        $data = new SismaDate($scadenza);
        $delega = new Delega($this->dataMapper);
        $delega->studio = $credito->studio;
        $delega->soggetto = $credito->soggetto;
        $delega->scadenza = $data;
        $delega->stato = StatoDelega::trasmessa;
        $delega->descrizione = 'Ritenute compensate con il credito IVA';
        $this->dataMapper->save($delega);
        $codici = new CodiceTributoModel($this->dataMapper);
        $mese = (int) $data->format('n') - 1;
        foreach ([[$codici->getByCodice(SezioneCodiceTributo::erario, '1040'), $importo, null], [$credito->codiceTributo, null, $importo]] as $indice => [$codice, $debito, $compensato]) {
            $riga = new Versamento($this->dataMapper);
            $riga->delega = $delega;
            $riga->studio = $delega->studio;
            $riga->soggetto = $delega->soggetto;
            $riga->scadenza = $data;
            $riga->sezione = SezioneF24::erario;
            $riga->ordine = $indice + 1;
            $riga->codiceTributo = $codice;
            $riga->rateazione = $compensato === null ? sprintf('00%02d', $mese) : null;
            $riga->annoRiferimento = $compensato === null ? (int) $data->format('Y') : $credito->annoRiferimento;
            $riga->importoDebito = $debito ?? 0.0;
            $riga->importoCredito = $compensato ?? 0.0;
            $riga->credito = $compensato === null ? null : $credito;
            $this->dataMapper->save($riga);
        }
    }

    public function apriContabilita(Soggetto $soggetto, int $anno): EsercizioContabile
    {
        $modello = (new PianoContiModel($this->dataMapper))->getEntityById(1)
                ?? throw new \RuntimeException('Piano dei conti standard assente: eseguire Contabilita/Database/dati-riferimento.sql prima delle fixture.');
        return (new AperturaContabilita($this->dataMapper))->apri($soggetto, $modello, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'));
    }

    public function apriEsercizio(Soggetto $soggetto, int $anno): EsercizioContabile
    {
        return (new AperturaContabilita($this->dataMapper))->apriEsercizio($soggetto, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'));
    }

    /**
     * Cliente o fornitore dell'azienda; il conto di contropartita è un
     * codice del piano standard (es. 02.04.0002 macchine d'ufficio).
     */
    public function controparte(Soggetto $azienda, string $denominazione, ?string $partitaIva, bool $cliente, ?string $contoContropartita = null, string $nazione = 'IT'): Controparte
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($azienda);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $azienda->studio;
        $controparte->soggetto = $azienda;
        $controparte->denominazione = $denominazione;
        $controparte->partitaIva = $partitaIva;
        $controparte->codiceFiscale = $nazione === 'IT' ? $partitaIva : null;
        $controparte->cliente = $cliente;
        $controparte->fornitore = $cliente === false;
        if ($contoContropartita !== null) {
            $controparte->contoContropartita = (new ContoModel($this->dataMapper))->getPerCodice($piano, $contoContropartita);
        }
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        return $controparte;
    }

    /**
     * Attività con contabilità separata dell'azienda.
     */
    public function attivita(Soggetto $soggetto, int $numero, string $descrizione, string $ateco, ?PeriodicitaIva $periodicita, int $dal): AttivitaIva
    {
        $attivita = new AttivitaIva($this->dataMapper);
        $attivita->studio = $soggetto->studio;
        $attivita->soggetto = $soggetto;
        $attivita->numero = $numero;
        $attivita->descrizione = $descrizione;
        $attivita->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, $ateco);
        $attivita->periodicita = $periodicita;
        $attivita->dalAnno = $dal;
        $this->dataMapper->save($attivita);
        return $attivita;
    }

    /**
     * Assegna il registro (principale o sezionale) a un'attività.
     */
    public function assegnaRegistro(Soggetto $soggetto, TipoRegistroIva $tipo, string $sezionale, ?AttivitaIva $attivita): RegistroIva
    {
        $registro = (new RegistroIvaModel($this->dataMapper))->getRegistro($soggetto, $tipo, $sezionale);
        $registro->attivitaIva = $attivita;
        $this->dataMapper->save($registro);
        return $registro;
    }
}
