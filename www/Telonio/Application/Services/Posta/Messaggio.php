<?php

namespace Telonio\Application\Services\Posta;

/**
 * Messaggio in testo semplice a un destinatario, con eventuali allegati.
 */
final class Messaggio
{

    /** @var array<int, array{nome: string, contenuto: string, tipo: string}> */
    private array $allegati = [];

    public function __construct(
        public readonly string $destinatario,
        public readonly string $oggetto,
        public readonly string $testo,
        public readonly ?string $rispondiA = null,
    ) {

    }

    public function allega(string $nome, string $contenuto, string $tipo = 'application/octet-stream'): self
    {
        $this->allegati[] = ['nome' => $nome, 'contenuto' => $contenuto, 'tipo' => $tipo];
        return $this;
    }

    /**
     * @return array<int, array{nome: string, contenuto: string, tipo: string}>
     */
    public function getAllegati(): array
    {
        return $this->allegati;
    }
}
