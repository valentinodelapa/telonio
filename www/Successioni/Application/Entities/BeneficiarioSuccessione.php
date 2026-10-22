<?php

namespace Successioni\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Rigo del quadro EA: erede, legatario, chiamato o altro beneficiario, con
 * il grado di parentela con il defunto (aliquota e franchigia
 * dell'imposta), la disabilità grave (art. 3, c. 3, L. 104/1992: franchigia
 * di 1,5 milioni, certificatore nel quadro EH), la rinuncia all'eredità
 * con gli estremi di registrazione (quadro EH) e la successione per
 * rappresentazione.
 *
 * Il soggetto è dell'Anagrafica: le specifiche richiedono codici fiscali
 * registrati in Anagrafe tributaria e i dati anagrafici completi. L'ordine
 * determina rigo e modulo del quadro (tre righi per modulo), con cui i beni
 * e le passività richiamano il beneficiario.
 */
class BeneficiarioSuccessione extends ReferencedEntity
{

    public const RIGHI_PER_MODULO = 3;

    protected int $id;
    protected DichiarazioneSuccessione $dichiarazioneSuccessione;
    protected Soggetto $soggetto;
    protected int $ordine = 1;
    protected TipoBeneficiario $tipoBeneficiario = TipoBeneficiario::erede;
    protected GradoParentela $gradoParentela;
    protected bool $disabile = false;
    protected ?string $certificatoreDisabilita = null;
    protected bool $rinuncia = false;
    protected ?UfficioRegistrazione $rinunciaUfficio = null;
    protected ?string $rinunciaSerie = null;
    protected ?string $rinunciaNumero = null;
    protected ?string $rinunciaSottonumero = null;
    protected ?SismaDate $rinunciaData = null;
    protected ?Soggetto $rappresentato = null;
    protected ?GradoParentela $gradoParentelaRappresentato = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getModulo(): int
    {
        return intdiv($this->ordine - 1, self::RIGHI_PER_MODULO) + 1;
    }

    public function getRigo(): int
    {
        return ($this->ordine - 1) % self::RIGHI_PER_MODULO + 1;
    }

    /**
     * Riferimento del quadro EA, es. "EA1 mod. 2".
     */
    public function getRiferimento(): string
    {
        return 'EA' . $this->getRigo() . ' mod. ' . $this->getModulo();
    }

    /**
     * Partecipa alla devoluzione dei beni: i rinunciatari no (il coniuge
     * rinunciatario conserva solo il diritto di abitazione, indicato sul
     * fabbricato).
     */
    public function isDestinatario(): bool
    {
        return ($this->rinuncia === false) && ($this->tipoBeneficiario !== TipoBeneficiario::coniugeRinunciatario);
    }
}
