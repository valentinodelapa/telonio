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
 * (specifiche tecniche, sezioni II-IV del quadro RH), senza il concordato
 * preventivo biennale.
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
        // totali di tutti i moduli, anche dei righi inseriti a mano
        $impresa = $perdite = $associazioni = $societaSemplici = $ritenute = $crediti = $oneri = 0;
        $presenti = false;
        foreach ($quadri->getModuli() as $modulo) {
            for ($numero = 1; $numero <= 6; $numero++) {
                $codice = fn(string $colonna) => sprintf('RH%03d%s', $numero, $colonna);
                if ($quadri->get($codice('001'), $modulo) === null) {
                    continue;
                }
                $presenti = true;
                $tipo = $quadri->getImporto($codice('002'), $modulo);
                $reddito = $quadri->getImporto($codice('004'), $modulo);
                $nonCompensate = $quadri->getImporto($codice('0A4'), $modulo);
                if (($numero >= 5) || in_array($tipo, [1, 5], true)) {
                    $impresa += max(0, $reddito);
                    $perdite += max(0, -$reddito) + $nonCompensate;
                } elseif (in_array($tipo, [2, 7], true)) {
                    $associazioni += $reddito - $nonCompensate;
                } else {
                    $societaSemplici += max(0, $reddito);
                }
                $ritenute += $quadri->getImporto($codice('009'), $modulo);
                $crediti += $quadri->getImporto($codice('010'), $modulo);
                $oneri += $quadri->getImporto($codice('012'), $modulo);
            }
        }
        if ($presenti === false) {
            return;
        }
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $quadri->proponi('RH007002', $impresa ?: null);
        $quadri->proponi('RH008001', $perdite ?: null);
        $quadri->proponi('RH009001', max(0, $perdite - ($impresa - $q('RH007001'))) ?: null);
        $quadri->proponi('RH009002', max($q('RH007001'), $impresa - $perdite) ?: null);
        $quadri->proponi('RH011001', max(0, $q('RH009002') - $q('RH010001')) ?: null);
        $quadri->proponi('RH014001', max($q('RH011001') - $q('RH012001') - $q('RH012002'), $q('RH007001')) ?: null);
        $quadri->proponi('RH015001', $associazioni ?: null);
        $quadri->proponi('RH017001', ($q('RH015001') - $q('RH016001')) ?: null);
        $quadri->proponi('RH018001', $societaSemplici ?: null);
        $quadri->proponi('RH019001', $ritenute ?: null);
        $quadri->proponi('RH020002', $crediti ?: null);
        $quadri->proponi('RH022001', $oneri ?: null);
    }
}
