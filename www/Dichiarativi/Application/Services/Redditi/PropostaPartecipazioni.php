<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\LegameModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\PartiteAperte;
use Contabilita\Application\Services\RighiRedditi;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RH dalle partecipazioni del contribuente in società di persone
 * dell'Anagrafica (legami di socio con la quota) che tengono la
 * contabilità nella suite: reddito della società dalla Contabilità secondo
 * il regime (ordinaria: utile con le variazioni del quadro RF proposte dai
 * conti; semplificata e professionisti: ricavi meno costi per cassa) e
 * ritenute d'acconto subite dagli abbinamenti delle partite, imputati per
 * la quota. Tipo della colonna 2: 2 per gli studi associati, 3 o 4 per le
 * società semplici (3 se esercitano lavoro autonomo), 1 per le altre.
 *
 * Le partecipazioni in società che non sono nella suite si inseriscono a
 * mano con il prospetto rilasciato dalla società; i totali dei righi da
 * RH7 a RH22 comprendono i righi inseriti a mano di tutti i moduli
 * (specifiche tecniche, sezioni II-IV del quadro RH), anche con il
 * concordato preventivo biennale: per le partecipazioni con le colonne
 * del concordato contano la quota di reddito, di perdita e la soglia
 * minima indicate dalla società.
 */
class PropostaPartecipazioni
{

    public const RIGHI_RH = 4;
    private const RUOLI = [RuoloLegame::socio, RuoloLegame::socioAccomandatario, RuoloLegame::socioAccomandante];

    /** @var list<array<int, int|float|string>> */
    private array $righi = [];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    public function getModuliNecessari(): int
    {
        return (int) ceil(count($this->righi) / self::RIGHI_RH);
    }

    public function carica(Soggetto $socio, int $anno): self
    {
        $this->righi = $this->avvisi = [];
        $fine = new SismaDate($anno . '-12-31');
        foreach ((new LegameModel($this->dataMapper))->getIncarichi($socio) as $legame) {
            $societa = $legame->soggetto;
            if ((in_array($legame->ruolo, self::RUOLI, true) === false) || ($societa->tipoSoggetto !== TipoSoggetto::societaPersone)
                    || ($legame->validoDal->format('Y-m-d') > $anno . '-12-31') || (($legame->validoAl !== null) && ($legame->validoAl->format('Y') < (string) $anno))) {
                continue;
            }
            if ($legame->quotaPartecipazione === null) {
                $this->avvisi[] = 'Quadro RH: quota di partecipazione in ' . $societa->nominativo . ' non indicata nel legame di socio, rigo da compilare a mano.';
                continue;
            }
            if ((new PianoContiModel($this->dataMapper))->getPerAzienda($societa) === null) {
                $this->avvisi[] = 'Quadro RH: ' . $societa->nominativo . ' non tiene la contabilità nella suite, rigo da compilare con il prospetto della società.';
                continue;
            }
            $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($societa, $fine);
            $professionisti = ($dati?->naturaGiuridica === NaturaGiuridica::studioAssociato) || ($dati?->tipoAttivita === TipoAttivita::lavoroAutonomo);
            $tipo = match (true) {
                $dati?->naturaGiuridica === NaturaGiuridica::studioAssociato => 2,
                $dati?->naturaGiuridica === NaturaGiuridica::societaSemplice => $professionisti ? 3 : 4,
                default => 1,
            };
            $reddito = $this->getReddito($societa, $anno, $dati?->regimeFiscale, $professionisti);
            $quota = $legame->quotaPartecipazione;
            $ritenute = (new PartiteAperte($this->dataMapper))->getRitenute($societa, $anno);
            $this->righi[] = [1 => $societa->codiceFiscale, 2 => $tipo, 3 => round($quota, 2), 4 => (int) round($reddito * $quota / 100),
                9 => (int) round($ritenute * $quota / 100)];
            $this->avvisi[] = 'Quadro RH: quota di ' . $societa->nominativo . ' calcolata dalla contabilità della società (' . ($dati?->regimeFiscale === RegimeFiscale::ordinario ? 'utile con le variazioni proposte dai conti' : 'ricavi e costi per cassa')
                    . '); verifica con il prospetto della società variazioni fiscali, perdite, crediti e oneri.';
        }
        return $this;
    }

    /**
     * Reddito della società dalla Contabilità secondo il regime.
     */
    private function getReddito(Soggetto $societa, int $anno, ?RegimeFiscale $regime, bool $professionisti): float
    {
        $componenti = new ComponentiReddito($this->dataMapper);
        if ($regime === RegimeFiscale::ordinario) {
            $competenza = $componenti->perCompetenza($societa, $anno);
            $reddito = $competenza['utile'];
            foreach ($competenza['variazioni'] as $rigo => $importo) {
                $reddito += $rigo === RighiRedditi::VARIAZIONE_DIMINUZIONE ? -$importo : $importo;
            }
            return $reddito;
        }
        $componenti->perCassa($societa, $anno, $professionisti);
        $totali = $componenti->getTotaliCassa();
        return $totali['ricavi'] - $totali['costi'];
    }

    public function proponi(QuadriDichiarazione $quadri): void
    {
        foreach ($this->righi as $indice => $rigo) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_RH) + 1);
            foreach ($rigo as $colonna => $valore) {
                $quadri->proponi(sprintf('RH%03d%03d', $indice % self::RIGHI_RH + 1, $colonna), $valore === 0 ? null : $valore);
            }
        }
        $quadri->usaModulo(1);
        // totali di tutti i moduli, anche dei righi inseriti a mano; con il concordato preventivo biennale (colonne 13A-16 dei righi
        // RH1-RH4, 14A-17 dei righi RH5 e RH6) contano la quota di reddito, di perdita e la soglia del concordato
        $impresa = $perdite = $sogliaImpresa = $associazioni = $sogliaAssociazioni = $societaSemplici = $sogliaSemplici = 0;
        $ritenute = $crediti = $oneri = $nonImponibili = 0;
        $presenti = $concordato = false;
        foreach ($quadri->getModuli() as $modulo) {
            for ($numero = 1; $numero <= 6; $numero++) {
                $codice = fn(string $colonna) => sprintf('RH%03d%s', $numero, $colonna);
                $v = fn(string $colonna) => $quadri->getImporto($codice($colonna), $modulo);
                if ($quadri->get($codice('001'), $modulo) === null) {
                    continue;
                }
                $presenti = true;
                $tipo = $v('002');
                $reddito = $v('004');
                $nonCompensate = $v('0A4');
                // colonne del concordato: righi RH1-RH4 13A, 14, 15, 16; righi RH5 e RH6 14A, 15, 16, 17
                [$casella, $redditoCpb, $perditaCpb, $sogliaCpb] = $numero >= 5 ? ['14A', '015', '016', '017'] : ['13A', '014', '015', '016'];
                $cpb = ($quadri->get($codice($casella), $modulo) !== null) || ($v($redditoCpb) + $v($perditaCpb) + $v($sogliaCpb) > 0);
                $concordato = $concordato || $cpb;
                if (($numero >= 5) || in_array($tipo, [1, 5], true)) {
                    $impresa += $cpb ? $v($redditoCpb) : max(0, $reddito);
                    $perdite += $cpb ? $v($perditaCpb) : max(0, -$reddito) + $nonCompensate;
                    $sogliaImpresa += $cpb ? $v($sogliaCpb) : 0;
                } elseif (in_array($tipo, [2, 7], true)) {
                    $associazioni += $cpb ? $v($redditoCpb) - $v($perditaCpb) : $reddito - $nonCompensate;
                    $sogliaAssociazioni += $cpb ? $v($sogliaCpb) : 0;
                } else {
                    $societaSemplici += $cpb ? $v($redditoCpb) : max(0, $reddito);
                    $sogliaSemplici += $cpb ? $v($sogliaCpb) : 0;
                }
                $ritenute += $v('009');
                $crediti += $v('010');
                $oneri += $v('012');
                $nonImponibili += $numero <= 4 ? $v('013') : 0;
            }
        }
        if ($presenti === false) {
            return;
        }
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $quadri->proponi('RH007002', $impresa ?: null);
        $quadri->proponi('RH008001', $perdite ?: null);
        $minimo = $q('RH007001') + $sogliaImpresa;
        $quadri->proponi('RH009001', max(0, $perdite - ($impresa - $minimo)) ?: null);
        $quadri->proponi('RH009002', max($minimo, $impresa - $perdite) ?: null);
        $quadri->proponi('RH011001', max(0, $q('RH009002') - $q('RH010001')) ?: null);
        $quadri->proponi('RH014001', max($q('RH011001') - $q('RH012001') - $q('RH012002'), $minimo) ?: null);
        $quadri->proponi('RH015001', ($concordato ? max($sogliaAssociazioni, $associazioni) : $associazioni) ?: null);
        $quadri->proponi('RH017001', ($q('RH015001') - $q('RH016001')) ?: null);
        $quadri->proponi('RH018001', ($concordato ? max($sogliaSemplici, $societaSemplici) : $societaSemplici) ?: null);
        $quadri->proponi('RH018002', $nonImponibili ?: null);
        $quadri->proponi('RH019001', $ritenute ?: null);
        $quadri->proponi('RH020002', $crediti ?: null);
        $quadri->proponi('RH022001', $oneri ?: null);
    }
}
