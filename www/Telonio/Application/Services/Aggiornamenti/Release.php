<?php

namespace Telonio\Application\Services\Aggiornamenti;

/**
 * Release pubblicata su GitHub: archivio zip della suite (framework e
 * dipendenze inclusi), impronta SHA-256 e attestazione SLSA della provenienza.
 */
final class Release
{

    public function __construct(
            public readonly Versione $versione,
            public readonly string $tag,
            public readonly string $urlNote,
            public readonly string $dataPubblicazione,
            public readonly string $urlArchivio,
            public readonly string $sha256,
            public readonly ?string $urlAttestazione,
    ) {

    }

    public function toArray(): array
    {
        return [
            'versione' => (string) $this->versione,
            'tag' => $this->tag,
            'urlNote' => $this->urlNote,
            'dataPubblicazione' => $this->dataPubblicazione,
            'urlArchivio' => $this->urlArchivio,
            'sha256' => $this->sha256,
            'urlAttestazione' => $this->urlAttestazione,
        ];
    }

    public static function daArray(array $dati): self
    {
        return new self(Versione::daTesto($dati['versione']), $dati['tag'], $dati['urlNote'], $dati['dataPubblicazione'],
                $dati['urlArchivio'], $dati['sha256'], $dati['urlAttestazione'] ?? null);
    }
}
