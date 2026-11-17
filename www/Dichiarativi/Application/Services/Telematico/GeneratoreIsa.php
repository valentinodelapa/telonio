<?php

namespace Dichiarativi\Application\Services\Telematico;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
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
 *   Redditi PF quadro RE, 2 quadro RF, 3 quadro RG) e codice dell'ISA;
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

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws \InvalidArgumentException se manca il quadro contabile o un valore non rispetta il formato
     */
    public function genera(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, ?QuadriDichiarazione $redditi = null): string
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
                . str_pad($codiceAttivita, 6) . $dichiarazione->getAnnoModello() . '01' . sprintf('%02d', self::MODELLI_DICHIARAZIONE[$quadroRedditi]) . str_pad((string) $dichiarazione->isa, 5);
        $record = fn(string $tipo, string $corpo) => str_pad($tipo . substr($testa, 1) . $corpo, 1876) . str_repeat(' ', 21) . "A\r\n";

        $persona = $soggetto->isPersonaFisica();
        $p = $record('P', str_pad(self::testo($persona ? (string) $soggetto->cognome : '', 30), 30) . str_pad(self::testo($persona ? (string) $soggetto->nome : '', 30), 30)
                . str_pad(self::testo($persona ? '' : $soggetto->nominativo, 60), 60) . str_pad('TELONIO', 16));

        $coppie = [];
        $valori = $quadri->getValori()[1] ?? [];
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
        $note = self::testo((string) $dichiarazione->annotazioni, 1768);
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
