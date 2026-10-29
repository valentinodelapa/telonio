<?php

namespace Telonio\Application\Services\Aggiornamenti;

use Telonio\Application\Enumerations\CanaleAggiornamenti;

/**
 * Versione semantica della suite (MAJOR.MINOR.PATCH con suffisso facoltativo
 * alpha, beta o rc, eventualmente numerato: 0.9.0-alpha, 1.0.0-rc.2),
 * con l'ordinamento di semver: a parità di numeri una versione preliminare
 * precede la definitiva, alpha < beta < rc.
 */
final class Versione implements \Stringable
{

    private const FORMATO = '/^v?(\d+)\.(\d+)\.(\d+)(?:-(alpha|beta|rc)(?:\.(\d+))?)?$/';

    private function __construct(
            private readonly int $major,
            private readonly int $minor,
            private readonly int $patch,
            private readonly CanaleAggiornamenti $canale,
            private readonly int $numeroPreliminare,
            private readonly string $testo,
    ) {

    }

    public static function daTesto(string $testo): self
    {
        $versione = self::tenta($testo);
        if ($versione === null) {
            throw new \InvalidArgumentException('Versione non valida: ' . $testo);
        }
        return $versione;
    }

    public static function tenta(string $testo): ?self
    {
        if (preg_match(self::FORMATO, trim($testo), $parti) !== 1) {
            return null;
        }
        $canale = ($parti[4] ?? '') === '' ? CanaleAggiornamenti::stabile : CanaleAggiornamenti::from($parti[4]);
        return new self((int) $parti[1], (int) $parti[2], (int) $parti[3], $canale, (int) ($parti[5] ?? 0), ltrim(trim($testo), 'v'));
    }

    public function getCanale(): CanaleAggiornamenti
    {
        return $this->canale;
    }

    /**
     * @return int negativo, zero o positivo se questa versione precede, è uguale o segue l'altra
     */
    public function confronta(self $altra): int
    {
        return [$this->major, $this->minor, $this->patch, $this->canale->getStabilita(), $this->numeroPreliminare]
                <=> [$altra->major, $altra->minor, $altra->patch, $altra->canale->getStabilita(), $altra->numeroPreliminare];
    }

    public function isSuccessivaA(self $altra): bool
    {
        return $this->confronta($altra) > 0;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->testo;
    }
}
