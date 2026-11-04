<?php

namespace Dichiarativi\Application\Services\Iva;

use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\CreditoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Credito IVA trimestrale del modello IVA TR nell'archivio dei crediti di
 * Deleghe: la parte da utilizzare in compensazione (TD7, o TE51 del gruppo)
 * con il codice 6036, 6037 o 6038 del trimestre, registrata alla
 * presentazione dell'istanza e compensabile da quel giorno o, oltre 5.000
 * euro di crediti trimestrali nell'anno, dal decimo giorno successivo (art.
 * 17, c. 1, D.Lgs. 241/1997). Il rimborso (TD6, TE50) lo eroga l'Agenzia.
 * Con la presentazione il credito chiesto (rimborso e compensazione) esce
 * dal riporto delle liquidazioni: si annota sulla liquidazione che chiude
 * il trimestre, della società o del gruppo.
 */
class VersamentiTr
{

    public const CODICI = [1 => '6036', 2 => '6037', 3 => '6038'];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] messaggi per l'utente
     */
    public function registraCredito(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $chiesto = $dichiarazione->dataPresentazione === null ? 0.0 : $quadri->getDecimale('TD006001', 1) + $quadri->getDecimale('TD007001', 1) + $quadri->getDecimale('TE050001', 1) + $quadri->getDecimale('TE051001', 1);
        return [...$this->aggiornaLiquidazione($dichiarazione, $chiesto), ...$this->registraCompensazione($dichiarazione, $quadri)];
    }

    /**
     * @return string[] messaggi per l'utente
     */
    private function registraCompensazione(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $importo = round($quadri->getDecimale('TD007001', 1) + $quadri->getDecimale('TE051001', 1), 2);
        $credito = $this->getCredito($dichiarazione);
        if (($credito !== null) && ((new CreditoModel($this->dataMapper))->getUtilizzato($credito) > 0)) {
            return abs($credito->importo - $importo) < 0.005 ? [] : ['Il credito trimestrale già registrato è stato utilizzato in F24: non è aggiornato.'];
        }
        if (($importo <= 0) || ($dichiarazione->dataPresentazione === null)) {
            if ($credito !== null) {
                $this->dataMapper->delete($credito);
            }
            return $importo > 0 ? [sprintf('Credito trimestrale di € %s da compensare: sarà registrato in Deleghe alla presentazione dell\'istanza.', number_format($importo, 2, ',', '.'))] : [];
        }
        $codice = self::CODICI[$dichiarazione->trimestre] ?? throw new \InvalidArgumentException('Trimestre non valido per il credito IVA trimestrale.');
        $credito ??= new Credito($this->dataMapper);
        $credito->studio = $dichiarazione->studio;
        $credito->soggetto = $dichiarazione->soggetto;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::erario, $codice)
                ?? throw new \InvalidArgumentException('Codice tributo ' . $codice . ' assente nelle tabelle di Deleghe F24.');
        $credito->annoRiferimento = $dichiarazione->anno;
        $credito->importo = $importo;
        $oltre = (new ControlliTr($this->dataMapper))->getCompensazioniAnno($dichiarazione) + $importo > ControlliTr::LIMITE_VISTO;
        $credito->utilizzabileDal = new SismaDate((new \DateTimeImmutable($dichiarazione->dataPresentazione->format('Y-m-d')))->modify($oltre ? '+10 days' : '+0 days')->format('Y-m-d'));
        $credito->descrizione = 'Credito IVA ' . $dichiarazione->trimestre . '° trimestre ' . $dichiarazione->anno . ' (modello IVA TR)';
        $credito->origine = Dichiarazione::ORIGINE;
        $credito->riferimentoOrigine = $dichiarazione->getRiferimento();
        $this->dataMapper->save($credito);
        return [sprintf('Credito IVA del %d° trimestre di € %s registrato in Deleghe (codice %s), compensabile dal %s.', $dichiarazione->trimestre, number_format($importo, 2, ',', '.'), $codice,
                    $credito->utilizzabileDal->format('d/m/Y'))];
    }

    /**
     * @return string[] messaggi per l'utente
     */
    public function annullaCredito(Dichiarazione $dichiarazione): array
    {
        $credito = $this->getCredito($dichiarazione);
        if ($credito === null) {
            return $this->aggiornaLiquidazione($dichiarazione, 0.0);
        }
        if ((new CreditoModel($this->dataMapper))->getUtilizzato($credito) > 0) {
            return ['Il credito trimestrale registrato in Deleghe è già stato utilizzato e resta nell\'archivio.'];
        }
        $this->dataMapper->delete($credito);
        return [...$this->aggiornaLiquidazione($dichiarazione, 0.0), 'Credito trimestrale eliminato dall\'archivio di Deleghe.'];
    }

    /**
     * Credito chiesto sulla liquidazione che chiude il trimestre.
     *
     * @return string[] messaggi per l'utente
     */
    private function aggiornaLiquidazione(Dichiarazione $dichiarazione, float $importo): array
    {
        $gruppo = (new GruppoIvaModel($this->dataMapper))->getPerControllante($dichiarazione->soggetto, $dichiarazione->anno);
        $liquidazione = $gruppo !== null ? (new LiquidazioneGruppo($this->dataMapper))->getLiquidazioneTrimestre($gruppo, $dichiarazione->anno, $dichiarazione->trimestre)
                : (new ElaborazioneLiquidazione($this->dataMapper))->getLiquidazioneTrimestre($dichiarazione->soggetto, $dichiarazione->anno, $dichiarazione->trimestre);
        if ($liquidazione === null) {
            return $importo > 0 ? [sprintf('Liquidazione del %d° trimestre non elaborata: il credito chiesto non è stato annotato, il riporto va corretto dopo l\'elaborazione.', $dichiarazione->trimestre)] : [];
        }
        $importo = round($importo, 2);
        if (abs($liquidazione->creditoTrimestrale - $importo) < 0.005) {
            return [];
        }
        $liquidazione->creditoTrimestrale = $importo;
        $this->dataMapper->save($liquidazione);
        return [sprintf('Credito chiesto con il modello IVA TR (€ %s) annotato sulla liquidazione di %s: rielaborare le liquidazioni successive per aggiornare il riporto.', number_format($importo, 2, ',', '.'), $liquidazione->getDescrizionePeriodo())];
    }

    public function getCredito(Dichiarazione $dichiarazione): ?Credito
    {
        foreach ((new CreditoModel($this->dataMapper))->getElenco($dichiarazione->soggetto) as $credito) {
            if (($credito->origine === Dichiarazione::ORIGINE) && ($credito->riferimentoOrigine === $dichiarazione->getRiferimento())) {
                return $credito;
            }
        }
        return null;
    }
}
