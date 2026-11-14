<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Models\AbbinamentoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * IVA sospesa delle fatture a esigibilità differita e per cassa: quota del
 * documento divenuta esigibile (vendite) o detraibile (acquisti per cassa)
 * fino a una data.
 *
 * - Esigibilità differita (art. 6, c. 5, D.P.R. 633/1972; dal 2027 art.
 *   24, c. 5, D.Lgs. 10/2026): l'imposta delle vendite diventa esigibile
 *   all'atto del pagamento del corrispettivo, senza limite di tempo.
 * - IVA per cassa (art. 32-bis D.L. 83/2012; dal 2027 art. 132 D.Lgs.
 *   10/2026; D.M. 11 ottobre 2012, artt. 4 e 5): l'imposta delle vendite
 *   diventa esigibile e quella degli acquisti detraibile al pagamento, in
 *   proporzione all'importo pagato sul totale, e comunque decorso un anno
 *   dall'effettuazione dell'operazione (data del documento). Il limite
 *   annuale non vale se il cliente è assoggettato a una procedura
 *   concorsuale prima del termine: la procedura non lo verifica.
 *
 * I pagamenti sono gli abbinamenti delle partite del documento (e degli
 * insoluti che le riaprono), di ogni modo salvo abbuoni e perdite, come
 * per il reddito per cassa (TipoChiusura::isIncasso): la ritenuta è parte
 * del compenso, la compensazione e la nota di credito estinguono il
 * credito, l'acconto conta alla data in cui è stato pagato. La quota non
 * scende con gli insoluti: l'imposta divenuta esigibile resta tale. Non
 * matura prima della data di competenza della registrazione.
 *
 * Le note di credito restano immediate: riducono l'imposta quando si
 * registrano, anche se il documento originario è sospeso (la loro
 * chiusura matura la quota corrispondente del documento).
 */
class IvaSospesa
{

    /** @var array<int, array<int, array{data: string, importo: float}>> pagamenti per registrazione, in ordine di data */
    private array $pagamenti = [];

    /** @var array<int, float> totale delle partite per registrazione */
    private array $totali = [];

    /** @var array<int, true> soggetti caricati */
    private array $caricati = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * La registrazione ha l'imposta sospesa: vendite a esigibilità
     * differita o per cassa, acquisti per cassa.
     */
    public static function isSospesa(RegistrazioneContabile $registrazione): bool
    {
        return match ($registrazione->causale) {
            CausaleContabile::fatturaVendita => $registrazione->esigibilita->isSospesa(),
            CausaleContabile::fatturaAcquisto => $registrazione->esigibilita === EsigibilitaIva::perCassa,
            default => false,
        };
    }

    /**
     * Data in cui l'imposta per cassa diventa comunque esigibile o
     * detraibile (un anno dall'effettuazione); null per la differita.
     */
    public static function getDataLimite(RegistrazioneContabile $registrazione): ?\DateTimeImmutable
    {
        if ($registrazione->esigibilita !== EsigibilitaIva::perCassa) {
            return null;
        }
        $effettuazione = $registrazione->dataDocumento ?? $registrazione->dataCompetenzaIva ?? $registrazione->dataRegistrazione;
        return (new \DateTimeImmutable($effettuazione->format('Y-m-d')))->modify('+1 year');
    }

    /**
     * Quota dell'imposta del documento divenuta esigibile o detraibile fino
     * alla data compresa (da 0 a 1); 1 per le registrazioni non sospese
     * dalla data di competenza.
     */
    public function getQuota(RegistrazioneContabile $registrazione, \DateTimeInterface $al): float
    {
        $data = $al->format('Y-m-d');
        if ($data < ($registrazione->dataCompetenzaIva ?? $registrazione->dataRegistrazione)->format('Y-m-d')) {
            return 0.0;
        }
        if (self::isSospesa($registrazione) === false) {
            return 1.0;
        }
        $limite = self::getDataLimite($registrazione);
        if (($limite !== null) && ($data >= $limite->format('Y-m-d'))) {
            return 1.0;
        }
        $this->carica($registrazione->soggetto);
        $totale = $this->totali[$registrazione->id] ?? 0.0;
        if ($totale <= 0) {
            return 0.0;
        }
        $pagato = 0.0;
        foreach ($this->pagamenti[$registrazione->id] ?? [] as $pagamento) {
            if ($pagamento['data'] > $data) {
                break;
            }
            $pagato += $pagamento['importo'];
        }
        return max(0.0, min(1.0, round($pagato / $totale, 6)));
    }

    /**
     * Il documento ha le partite da cui si leggono i pagamenti.
     */
    public function hasPartite(RegistrazioneContabile $registrazione): bool
    {
        $this->carica($registrazione->soggetto);
        return ($this->totali[$registrazione->id] ?? 0.0) > 0;
    }

    /**
     * Registrazioni sospese dell'azienda con data di competenza fino alla
     * data compresa.
     *
     * @return RegistrazioneContabile[]
     */
    public function getSospese(Soggetto $soggetto, \DateTimeInterface $al): array
    {
        $model = new RegistrazioneContabileModel($this->dataMapper);
        $sospese = [];
        foreach ([EsigibilitaIva::differita, EsigibilitaIva::perCassa] as $esigibilita) {
            foreach ($model->getBySoggettoAndEsigibilita($soggetto, $esigibilita) as $registrazione) {
                if (self::isSospesa($registrazione) && (($registrazione->dataCompetenzaIva ?? $registrazione->dataRegistrazione)->format('Y-m-d') <= $al->format('Y-m-d'))) {
                    $sospese[$registrazione->id] = $registrazione;
                }
            }
        }
        $sospese = array_values($sospese);
        usort($sospese, fn(RegistrazioneContabile $prima, RegistrazioneContabile $seconda) => [$prima->dataCompetenzaIva?->format('Y-m-d'), $prima->id] <=> [$seconda->dataCompetenzaIva?->format('Y-m-d'), $seconda->id]);
        return $sospese;
    }

    private function carica(Soggetto $soggetto): void
    {
        if (isset($this->caricati[$soggetto->id])) {
            return;
        }
        $this->caricati[$soggetto->id] = true;
        $documenti = [];
        foreach ((new PartitaModel($this->dataMapper))->getPerSoggetto($soggetto) as $partita) {
            if ($partita->tipo === TipoPartita::documento) {
                $id = $partita->registrazioneContabile->id;
                $this->totali[$id] = ($this->totali[$id] ?? 0.0) + $partita->importo;
                $documenti[$partita->id] = $id;
            } elseif (($partita->tipo === TipoPartita::insoluto) && ($partita->partitaOrigine !== null)) {
                $documenti[$partita->id] = $partita->partitaOrigine->registrazioneContabile->id;
            }
        }
        foreach ((new AbbinamentoModel($this->dataMapper))->getPerSoggetto($soggetto) as $abbinamento) {
            $id = $documenti[$abbinamento->partita->id] ?? null;
            if (($id === null) || ($abbinamento->tipo->isIncasso() === false)) {
                continue;
            }
            // l'acconto conta alla data in cui è stato incassato o pagato
            $data = (($abbinamento->tipo === TipoChiusura::acconto) && ($abbinamento->partitaCollegata !== null)) ? $abbinamento->partitaCollegata->data : $abbinamento->data;
            $this->pagamenti[$id][] = ['data' => $data->format('Y-m-d'), 'importo' => $abbinamento->importo];
        }
        foreach ($this->pagamenti as &$pagamenti) {
            usort($pagamenti, fn(array $primo, array $secondo) => $primo['data'] <=> $secondo['data']);
        }
        unset($pagamenti);
    }
}
