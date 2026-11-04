<?php

namespace Dichiarativi\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Models\RecapitoModel;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\Models\IntermediarioModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Dati del frontespizio comuni ai modelli: natura giuridica dei soggetti
 * diversi dalle persone fisiche (tabella generale delle istruzioni dei
 * modelli), firmatario (rappresentante legale in carica dai legami
 * dell'Anagrafica, con il codice di carica), intermediario predefinito
 * dello studio con l'impegno alla presentazione, recapiti.
 */
class Frontespizio
{

    /**
     * Codici della "Tabella generale di classificazione natura giuridica"
     * (soggetti residenti) per la natura giuridica dell'Anagrafica. Dove la
     * tabella distingue casi che l'Anagrafica non distingue (consorzi e
     * associazioni con o senza personalità giuridica, cooperative iscritte
     * o no all'albo) si propone il caso più frequente, modificabile nel
     * frontespizio.
     */
    public const NATURE_GIURIDICHE = [
        'SAPA' => 1, 'SRL' => 2, 'SRLS' => 2, 'SPA' => 3, 'COOP' => 4, 'FOND' => 9, 'ECCL' => 10, 'CONS' => 11, 'ASS' => 12, 'COM' => 12,
        'PUBB' => 15, 'SS' => 23, 'SNC' => 24, 'SAS' => 25, 'STA' => 27, 'COND' => 51,
    ];

    /**
     * Tabella generale di classificazione della natura giuridica delle
     * istruzioni dei modelli (soggetti residenti e non residenti).
     */
    public const TABELLA_NATURA_GIURIDICA = [
        1 => 'Società in accomandita per azioni', 2 => 'Società a responsabilità limitata', 3 => 'Società per azioni',
        4 => 'Società cooperative e loro consorzi iscritti nell\'Albo Nazionale delle società cooperative', 5 => 'Altre società cooperative',
        6 => 'Mutue assicuratrici', 7 => 'Consorzi con personalità giuridica', 8 => 'Associazioni riconosciute', 9 => 'Fondazioni',
        10 => 'Altri enti ed istituti con personalità giuridica', 11 => 'Consorzi senza personalità giuridica', 12 => 'Associazioni non riconosciute e comitati',
        13 => 'Altre organizzazioni di persone o di beni senza personalità giuridica (escluse le comunioni)', 14 => 'Enti pubblici economici',
        15 => 'Enti pubblici non economici', 16 => 'Casse mutue e fondi di previdenza, assistenza, pensioni o simili',
        17 => 'Opere pie e società di mutuo soccorso', 18 => 'Enti ospedalieri', 19 => 'Enti ed istituti di previdenza e di assistenza sociale',
        20 => 'Aziende autonome di cura, soggiorno e turismo', 21 => 'Aziende regionali, provinciali, comunali e loro consorzi',
        22 => 'Società, organizzazioni ed enti costituiti all\'estero non altrimenti classificabili con sede dell\'amministrazione od oggetto principale in Italia',
        23 => 'Società semplici ed equiparate (art. 5, c. 3, lett. b), TUIR)', 24 => 'Società in nome collettivo ed equiparate (art. 5, c. 3, lett. b), TUIR)',
        25 => 'Società in accomandita semplice', 26 => 'Società di armamento', 27 => 'Associazione fra artisti e professionisti', 28 => 'Aziende coniugali',
        29 => 'GEIE (Gruppi europei di interesse economico)', 30 => 'Non residenti: società semplici, irregolari e di fatto', 31 => 'Non residenti: società in nome collettivo',
        32 => 'Non residenti: società in accomandita semplice', 33 => 'Non residenti: società di armamento', 34 => 'Non residenti: associazioni fra professionisti',
        35 => 'Non residenti: società in accomandita per azioni', 36 => 'Non residenti: società a responsabilità limitata', 37 => 'Non residenti: società per azioni',
        38 => 'Non residenti: consorzi', 39 => 'Non residenti: altri enti ed istituti', 40 => 'Non residenti: associazioni riconosciute, non riconosciute e di fatto',
        41 => 'Non residenti: fondazioni', 42 => 'Non residenti: opere pie e società di mutuo soccorso', 43 => 'Non residenti: altre organizzazioni di persone e di beni',
        44 => 'Non residenti: trust', 45 => 'Non residenti: GEIE',
        50 => 'Società per azioni, aziende speciali e consorzi degli enti locali (artt. 31, 113, 114, 115 e 116 D.Lgs. 267/2000)', 51 => 'Condomìni',
        52 => 'Depositi IVA', 53 => 'Società sportive dilettantistiche costituite in società di capitali senza fine di lucro', 54 => 'Trust',
        55 => 'Amministrazioni pubbliche', 56 => 'Fondazioni bancarie', 57 => 'Società europea', 58 => 'Società cooperativa europea', 59 => 'Rete di imprese',
        61 => 'Gruppo IVA',
    ];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Propone i dati del frontespizio di una nuova dichiarazione.
     */
    public function proponi(Dichiarazione $dichiarazione): void
    {
        $soggetto = $dichiarazione->soggetto;
        if ($soggetto->isPersonaFisica() === false) {
            $natura = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $dichiarazione->anno)?->naturaGiuridica;
            $dichiarazione->naturaGiuridica = $natura === null ? null : self::getCodiceNaturaGiuridica($natura);
            $legame = (new PreparazioneLipe($this->dataMapper))->getRappresentante($soggetto, new SismaDate());
            if ($legame !== null) {
                $dichiarazione->dichiarante = $legame->soggettoCollegato;
                $dichiarazione->codiceCarica = $legame->ruolo === RuoloLegame::liquidatore ? CodiceCaricaIva::liquidatore : CodiceCaricaIva::rappresentanteLegale;
            }
        }
        $intermediario = (new IntermediarioModel($this->dataMapper))->getPredefinito($dichiarazione->studio);
        if ($intermediario !== null) {
            $dichiarazione->intermediario = $intermediario;
            $dichiarazione->impegno = ImpegnoPresentazione::intermediario;
            $dichiarazione->dataImpegno = new SismaDate();
        }
    }

    /**
     * Organo di controllo in carica dai legami dell'Anagrafica: revisore
     * persona fisica (codice 1) o società di revisione (codice 3, il
     * responsabile della revisione con il codice 2 si aggiunge a mano),
     * sindaci (codice 4).
     *
     * @return list<array{0: int, 1: Soggetto}>
     */
    public function getOrganoControllo(Soggetto $soggetto): array
    {
        $oggi = new SismaDate();
        $componenti = [];
        foreach ((new LegameModel($this->dataMapper))->getCollegati($soggetto) as $legame) {
            if ($legame->isVigenteAl($oggi) === false) {
                continue;
            }
            if ($legame->ruolo === RuoloLegame::revisore) {
                $componenti[] = [$legame->soggettoCollegato->isPersonaFisica() ? 1 : 3, $legame->soggettoCollegato];
            } elseif ($legame->ruolo === RuoloLegame::sindaco) {
                $componenti[] = [4, $legame->soggettoCollegato];
            }
        }
        usort($componenti, fn(array $a, array $b) => $a[0] <=> $b[0]);
        return $componenti;
    }

    public static function getCodiceNaturaGiuridica(NaturaGiuridica $natura): ?int
    {
        return self::NATURE_GIURIDICHE[$natura->value] ?? null;
    }

    /**
     * Telefono (solo cifre, al più 12) ed email principali del soggetto.
     *
     * @return array{telefono: ?string, email: ?string}
     */
    public function getRecapiti(Soggetto $soggetto): array
    {
        $telefono = $email = null;
        foreach ((new RecapitoModel($this->dataMapper))->getElenco($soggetto) as $recapito) {
            if (in_array($recapito->tipoRecapito, [TipoRecapito::telefono, TipoRecapito::cellulare], true) && (($telefono === null) || $recapito->principale)) {
                $telefono = substr(preg_replace('/\D/', '', $recapito->valore), -12);
            }
            if (($recapito->tipoRecapito === TipoRecapito::email) && (($email === null) || $recapito->principale)) {
                $email = $recapito->valore;
            }
        }
        return ['telefono' => ($telefono === null) || (strlen($telefono) < 6) ? null : $telefono, 'email' => $email];
    }
}
