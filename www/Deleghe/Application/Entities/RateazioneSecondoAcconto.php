<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Enumerations\TipoSoggetto;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Norma straordinaria che per un periodo d'imposta ha differito il
 * versamento del secondo acconto dal 30 novembre al 16 gennaio dell'anno
 * successivo, in unica soluzione o in rate mensili il 16 di ogni mese, con
 * gli interessi dell'art. 20, c. 2, D.Lgs. 241/1997 dalla seconda rata (D.L.
 * 145/2023 per il 2023, D.L. 155/2024 per il 2024).
 *
 * Curata a mano in Database/sorgenti/rateazione-secondo-acconto.csv: se il
 * legislatore ripropone la misura basta aggiungere una riga. I requisiti
 * (persone fisiche titolari di partita IVA con ricavi o compensi entro la
 * soglia) sono indicati nella norma: la soglia non è verificabile dai dati
 * del modulo e resta a carico dell'utente.
 */
class RateazioneSecondoAcconto extends BaseEntity
{

    protected int $id;
    protected int $periodoImposta;
    protected SismaDate $primaRata;
    protected int $numeroRate;
    protected string $codici;
    protected string $norma;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * @return string[] codici tributo del secondo acconto (sezione erario)
     */
    public function getCodici(): array
    {
        return preg_split('/\s+/', $this->codici, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Se il versamento è un secondo acconto del periodo d'imposta, a debito,
     * di una persona fisica, dovuto prima del termine differito.
     */
    public function comprende(Versamento $versamento): bool
    {
        return ($versamento->sezione === SezioneF24::erario) && in_array($versamento->codiceTributo?->codice, $this->getCodici(), true)
                && ($versamento->annoRiferimento === $this->periodoImposta) && ($versamento->importoDebito > 0) && ($versamento->importoCredito <= 0)
                && ($versamento->soggetto->tipoSoggetto === TipoSoggetto::personaFisica)
                && ($versamento->scadenza->format('Y-m-d') < $this->primaRata->format('Y-m-d'));
    }
}
