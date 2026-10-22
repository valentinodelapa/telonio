<?php

namespace Deleghe\Application\Services\Telematico;

/**
 * Record a lunghezza fissa del flusso telematico F24 (1.900 caratteri:
 * 1.897 di dati, "A" e CR LF): campi posizionali NU (numerici, allineati a
 * destra con zeri) e AN (alfanumerici, allineati a sinistra con spazi). I
 * campi non valorizzati sono inizializzati a zeri o spazi secondo il tipo.
 */
class RecordF24
{

    public const LUNGHEZZA_DATI = 1897;

    private string $dati;

    /**
     * @param array<string, array{0: int, 1: int, 2: string}> $campi nome => [posizione (da 1), lunghezza, AN|NU]
     */
    public function __construct(private array $campi)
    {
        $this->dati = str_repeat(' ', self::LUNGHEZZA_DATI);
        foreach ($campi as $nome => [, , $tipo]) {
            if ($tipo === 'NU') {
                $this->imposta($nome, 0);
            }
        }
    }

    public function imposta(string $nome, string|int|null $valore): self
    {
        [$posizione, $lunghezza, $tipo] = $this->campi[$nome] ?? throw new \InvalidArgumentException('Campo sconosciuto nel tracciato: ' . $nome);
        if ($tipo === 'NU') {
            $testo = (string) ($valore ?? 0);
            if (ctype_digit($testo) === false) {
                throw new \InvalidArgumentException(sprintf('Campo %s: valore numerico non valido "%s".', $nome, $testo));
            }
            $testo = str_pad($testo, $lunghezza, '0', STR_PAD_LEFT);
        } else {
            $testo = str_pad(self::ascii((string) $valore), $lunghezza, ' ');
        }
        if (strlen($testo) > $lunghezza) {
            throw new \LengthException(sprintf('Campo %s: "%s" supera i %d caratteri.', $nome, trim($testo), $lunghezza));
        }
        $this->dati = substr_replace($this->dati, $testo, $posizione - 1, $lunghezza);
        return $this;
    }

    /**
     * Testo alfanumerico: maiuscolo, senza accenti né caratteri fuori da
     * ASCII stampabile.
     */
    public static function ascii(string $testo): string
    {
        $testo = mb_strtoupper(trim($testo));
        $traslitterato = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $testo);
        return preg_replace('/[^\x20-\x7E]/', '', $traslitterato === false ? $testo : $traslitterato);
    }

    /**
     * Testo da troncare alla lunghezza del campo (denominazioni, indirizzi).
     */
    public function impostaTroncato(string $nome, ?string $valore): self
    {
        return $this->imposta($nome, substr(self::ascii((string) $valore), 0, $this->campi[$nome][1]));
    }

    public function __toString(): string
    {
        return $this->dati . "A\r\n";
    }
}
