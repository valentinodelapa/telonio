<?php

namespace Dichiarativi\Application\Services\Telematico;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Isa\PropostaIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Record del modello ISA secondo le specifiche tecniche ISA 2026
 * (allegato 1 al provvedimento del 27 febbraio 2026), d'ingresso al
 * software di calcolo:
 *
 * - testa comune (108 caratteri): tipo record, codice fiscale, progressivo
 *   modulo, spazi dell'utente, codice fiscale di chi assevera, codice
 *   attività, anno del modello, progressivo, modello di dichiarazione (1
 *   Redditi PF quadro RE, 2 quadro RF, 3 quadro RG, 4-6 lo stesso per
 *   Redditi SP, 7 Redditi SC) e codice dell'ISA;
 * - record P: cognome e nome (o denominazione) e identificativo del
 *   produttore del software;
 * - record Q: coppie codice campo (8) e valore (18), 68 per record, in
 *   ordine di codice, nei formati delle specifiche ISA;
 * - record R: note del contribuente (annotazioni del modello), se ci sono.
 *
 * Ogni record è di 1.900 caratteri con "A" e CR LF in fondo.
 */
class GeneratoreIsa
{

    public const LUNGHEZZA_RECORD = 1900;
    public const COPPIE_Q = 68;
    /** Modello di dichiarazione della testa per quadro contabile di Redditi PF */
    public const MODELLI_DICHIARAZIONE = ['RE' => 1, 'RF' => 2, 'RG' => 3];

    /**
     * Modello di dichiarazione della testa dei record ISA: 1-3 Redditi PF
     * (quadro RE, RF, RG), 4-6 Redditi SP, 7 Redditi SC, 8-10 Redditi ENC
     * (specifiche ISA 2026, campo "Codice modello").
     */
    public static function getModelloDichiarazione(ModelloDichiarativo $modello, string $quadro): int
    {
        return match ($modello) {
            ModelloDichiarativo::redditiSp => self::MODELLI_DICHIARAZIONE[$quadro] + 3,
            ModelloDichiarativo::redditiSc => 7,
            ModelloDichiarativo::redditiEnc => self::MODELLI_DICHIARAZIONE[$quadro] + 7,
            default => self::MODELLI_DICHIARAZIONE[$quadro],
        };
    }

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @param array<string, string|int>|null $soloValori solo questi campi (codice => valore) e nessuna nota, invece dei valori
     *        del modello: la comunicazione della revoca del concordato con i soli campi chiave del quadro P
     * @throws \InvalidArgumentException se manca il quadro contabile o un valore non rispetta il formato
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, ?QuadriDichiarazione $redditi = null, ?array $soloValori = null): string
    {
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $redditi ??= $dichiarazione->dichiarazioneRedditi === null ? null : new QuadriDichiarazione($dichiarazione->dichiarazioneRedditi, $this->dataMapper);
        $quadroRedditi = $redditi === null ? null : PropostaIsa::getQuadroRedditi($quadri, $redditi);
        if ($quadroRedditi === null) {
            throw new \InvalidArgumentException('Modello ISA ' . $dichiarazione->isa . ': nessun quadro contabile (RE, RF, RG) nella dichiarazione Redditi collegata.');
        }
        $soggetto = $dichiarazione->soggetto;
        $codiceAttivita = (string) $quadri->get('Y0100101', 1);
        $testa = 'X' . str_pad(strtoupper((string) $soggetto->codiceFiscale), 16) . '00000001' . str_repeat(' ', 3 + 25 + 8 + 12 + 16)
                . str_pad($codiceAttivita, 6) . $dichiarazione->getAnnoModello() . '01' . sprintf('%02d', self::getModelloDichiarazione($dichiarazione->dichiarazioneRedditi?->modello ?? ModelloDichiarativo::redditiPf, $quadroRedditi)) . str_pad((string) $dichiarazione->isa, 5);
        $record = fn(string $tipo, string $corpo) => str_pad($tipo . substr($testa, 1) . $corpo, 1876) . str_repeat(' ', 21) . "A\r\n";

        $persona = $soggetto->isPersonaFisica();
        $p = $record('P', str_pad(self::testo($persona ? (string) $soggetto->cognome : '', 30), 30) . str_pad(self::testo($persona ? (string) $soggetto->nome : '', 30), 30)
                . str_pad(self::testo($persona ? '' : $soggetto->nominativo, 60), 60) . str_pad('TELONIO', 16));

        $coppie = [];
        $valori = $soloValori === null ? ($quadri->getValori()[1] ?? []) : array_map(fn(string|int $valore) => ['valore' => $valore], $soloValori);
        ksort($valori);
        foreach ($valori as $codice => $dati) {
            if (($dati['valore'] === null) || ($dati['valore'] === '')) {
                continue;
            }
            $formato = $quadri->getCampo($codice)->formato;
            if ($formato === null) {
                continue;
            }
            foreach ($formato->formatta((string) $dati['valore'], FormatoCampo::LUNGHEZZA_ISA) as $valore) {
                $coppie[] = $codice . $valore;
            }
        }
        $q = '';
        foreach (array_chunk($coppie, self::COPPIE_Q) as $gruppo) {
            $q .= $record('Q', implode('', $gruppo));
        }
        $note = $soloValori === null ? self::testo((string) $dichiarazione->annotazioni, 1768) : '';
        return $p . $q . ($note === '' ? '' : $record('R', $note));
    }

    /**
     * Testo in maiuscolo senza accenti (le lettere accentate con
     * l'apostrofo, come chiedono le specifiche) troncato alla lunghezza.
     */
    private static function testo(string $testo, int $lunghezza): string
    {
        $testo = strtr(mb_strtoupper(trim($testo)), ['À' => "A'", 'Á' => "A'", 'È' => "E'", 'É' => "E'", 'Ì' => "I'", 'Í' => "I'", 'Ò' => "O'", 'Ó' => "O'", 'Ù' => "U'", 'Ú' => "U'"]);
        $traslitterato = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $testo);
        return substr((string) preg_replace('/[^\x20-\x7E]/', '', $traslitterato === false ? $testo : $traslitterato), 0, $lunghezza);
    }
}
