<?php

namespace Contabilita\Application\Services\FatturaPa;

/**
 * Estrae il documento XML dalla fattura elettronica firmata in formato
 * CAdES (.p7m), in binario DER/BER o codificata in base64: il contenuto
 * firmato è l'eContent della struttura SignedData (RFC 5652, par. 5.2),
 * eventualmente spezzato in più OCTET STRING (codifica BER costruita). La
 * firma non è verificata: la fattura è già stata accettata dal Sistema di
 * Interscambio.
 */
class EstrazioneP7m
{

    private const TAG_SEQUENCE = 0x30;
    private const TAG_OCTET_STRING = 0x04;
    private const TAG_CONTESTO_0 = 0xA0;

    /**
     * @throws \UnexpectedValueException se il file non è un XML né una busta CAdES leggibile
     */
    public function estrai(string $contenuto): string
    {
        $senzaBom = str_starts_with($contenuto, "\xEF\xBB\xBF") ? substr($contenuto, 3) : $contenuto;
        if (str_starts_with(ltrim($senzaBom), '<')) {
            return ltrim($senzaBom);
        }
        $compatto = preg_replace('/\s+/', '', $contenuto);
        if (($compatto !== '') && (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $compatto) === 1)) {
            $decodificato = base64_decode($compatto, true);
            if ($decodificato !== false) {
                $contenuto = $decodificato;
            }
        }
        $xml = $this->estraiContenuto($contenuto);
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            $xml = substr($xml, 3);
        }
        if (str_starts_with(ltrim($xml), '<') === false) {
            throw new \UnexpectedValueException('Il contenuto firmato non è un documento XML.');
        }
        return ltrim($xml);
    }

    private function estraiContenuto(string $der): string
    {
        $radice = $this->leggiElemento($der, 0);
        if ($radice['tag'] !== self::TAG_SEQUENCE) {
            throw new \UnexpectedValueException('File non riconosciuto: né XML né busta firmata .p7m.');
        }
        $contentInfo = $this->figli($der, $radice);
        $signedData = $this->figli($der, $this->richiedi($contentInfo, 1, self::TAG_CONTESTO_0));
        $campiSignedData = $this->figli($der, $this->richiedi($signedData, 0, self::TAG_SEQUENCE));
        $encapContentInfo = $this->figli($der, $this->richiedi($campiSignedData, 2, self::TAG_SEQUENCE));
        $eContent = $this->figli($der, $this->richiedi($encapContentInfo, 1, self::TAG_CONTESTO_0));
        return $this->ottetti($der, $this->richiedi($eContent, 0, self::TAG_OCTET_STRING));
    }

    private function richiedi(array $elementi, int $indice, int $tag): array
    {
        if ((isset($elementi[$indice]) === false) || (($elementi[$indice]['tag'] | 0x20) !== ($tag | 0x20))) {
            throw new \UnexpectedValueException('Busta firmata .p7m non valida o senza il documento.');
        }
        return $elementi[$indice];
    }

    /**
     * Contenuto di una OCTET STRING, primitiva o costruita.
     */
    private function ottetti(string $der, array $elemento): string
    {
        if ($elemento['costruito'] === false) {
            return substr($der, $elemento['inizio'], $elemento['fine'] - $elemento['inizio']);
        }
        $contenuto = '';
        foreach ($this->figli($der, $elemento) as $figlio) {
            $contenuto .= $this->ottetti($der, $figlio);
        }
        return $contenuto;
    }

    /**
     * @return array<int, array{tag: int, costruito: bool, inizio: int, fine: int, prossimo: int}>
     */
    private function figli(string $der, array $elemento): array
    {
        $figli = [];
        $posizione = $elemento['inizio'];
        while ($posizione < $elemento['fine']) {
            if (($elemento['indefinito'] ?? false) && (substr($der, $posizione, 2) === "\x00\x00")) {
                break;
            }
            $figlio = $this->leggiElemento($der, $posizione);
            $figli[] = $figlio;
            $posizione = $figlio['prossimo'];
        }
        return $figli;
    }

    /**
     * Legge intestazione e limiti di un elemento BER alla posizione.
     */
    private function leggiElemento(string $der, int $posizione): array
    {
        $lunghezzaTotale = strlen($der);
        if ($posizione + 2 > $lunghezzaTotale) {
            throw new \UnexpectedValueException('Busta firmata .p7m troncata.');
        }
        $tag = ord($der[$posizione]);
        $costruito = ($tag & 0x20) === 0x20;
        if (($tag & 0x1F) === 0x1F) {
            throw new \UnexpectedValueException('Busta firmata .p7m non supportata (tag esteso).');
        }
        $byteLunghezza = ord($der[$posizione + 1]);
        $inizio = $posizione + 2;
        if ($byteLunghezza === 0x80) {
            $elemento = ['tag' => $tag, 'costruito' => $costruito, 'inizio' => $inizio, 'fine' => $lunghezzaTotale, 'indefinito' => true];
            $fine = $inizio;
            foreach ($this->figli($der, $elemento) as $figlio) {
                $fine = $figlio['prossimo'];
            }
            return ['tag' => $tag, 'costruito' => $costruito, 'inizio' => $inizio, 'fine' => $fine, 'indefinito' => true, 'prossimo' => $fine + 2];
        }
        if ($byteLunghezza > 0x80) {
            $numeroByte = $byteLunghezza & 0x7F;
            if (($numeroByte > 4) || ($inizio + $numeroByte > $lunghezzaTotale)) {
                throw new \UnexpectedValueException('Busta firmata .p7m non valida (lunghezza).');
            }
            $lunghezza = 0;
            for ($indice = 0; $indice < $numeroByte; $indice++) {
                $lunghezza = ($lunghezza << 8) | ord($der[$inizio + $indice]);
            }
            $inizio += $numeroByte;
        } else {
            $lunghezza = $byteLunghezza;
        }
        if ($inizio + $lunghezza > $lunghezzaTotale) {
            throw new \UnexpectedValueException('Busta firmata .p7m troncata.');
        }
        return ['tag' => $tag, 'costruito' => $costruito, 'inizio' => $inizio, 'fine' => $inizio + $lunghezza, 'indefinito' => false, 'prossimo' => $inizio + $lunghezza];
    }
}
