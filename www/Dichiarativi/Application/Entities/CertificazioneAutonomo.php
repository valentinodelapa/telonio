<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Certificazione Unica - lavoro autonomo, provvigioni e redditi diversi
 * del contribuente per i redditi del quadro RL: causale (punto 1), importi
 * certificati e spese inerenti indicate dal contribuente (deducibili per i
 * redditi occasionali dei righi RL14, RL15 e RL16).
 *
 * Righi del quadro RL per causale (tabella di raccordo delle istruzioni
 * del quadro RL, fascicolo 2): B RL25, C e D RL27, E RL26 codice 1, F RL26
 * codice 2, L e L1 RL13, M, M2 e O RL15, M1 e O1 RL16, N RL21, V1 RL14. Le
 * altre causali (es. A, lavoro autonomo abituale) non vanno nel quadro RL.
 */
class CertificazioneAutonomo extends BaseEntity
{

    public const CAUSALI = [
        'B' => 'B - Opere dell\'ingegno e invenzioni dell\'autore o inventore (RL25)',
        'C' => 'C - Associazione in partecipazione con apporto di solo lavoro (RL27)',
        'D' => 'D - Utili dei soci promotori e fondatori (RL27)',
        'E' => 'E - Levata dei protesti dei segretari comunali (RL26)',
        'F' => 'F - Indennità dei giudici onorari di pace e vice procuratori onorari (RL26)',
        'L' => 'L - Opere dell\'ingegno percepite dagli aventi causa a titolo gratuito (RL13)',
        'L1' => 'L1 - Opere dell\'ingegno percepite da chi ha acquistato i diritti (RL13)',
        'M' => 'M - Lavoro autonomo occasionale (RL15)',
        'M1' => 'M1 - Obblighi di fare, non fare o permettere (RL16)',
        'M2' => 'M2 - Lavoro autonomo non abituale con iscrizione alla Gestione Separata ENPAPI (RL15)',
        'N' => 'N - Cori, bande, filodrammatiche e attività sportive dilettantistiche (RL21)',
        'O' => 'O - Lavoro autonomo occasionale senza iscrizione alla gestione separata (RL15)',
        'O1' => 'O1 - Obblighi di fare, non fare o permettere senza iscrizione alla gestione separata (RL16)',
        'V1' => 'V1 - Attività commerciali non abituali (RL14)',
    ];

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected string $codiceFiscaleSostituto = '';
    protected string $denominazioneSostituto = '';
    protected string $causale = 'M';
    protected float $ammontareLordo = 0.0;
    protected float $sommeNonSoggette = 0.0;
    protected float $ritenuteAcconto = 0.0;
    protected float $ritenuteImposta = 0.0;
    protected float $ritenuteSospese = 0.0;
    protected float $addizionaleRegionale = 0.0;
    protected float $addizionaleComunale = 0.0;
    protected float $speseInerenti = 0.0;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Imponibile: ammontare lordo meno le somme non soggette a ritenuta.
     */
    public function getImponibile(): float
    {
        return max(0.0, round($this->ammontareLordo - $this->sommeNonSoggette, 2));
    }
}
