<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Registrazione diretta di un documento semplice con le righe in partita
 * doppia proposte e i controlli del form della prima nota: documento IVA
 * (un imponibile per codice IVA, con l'importo del documento e il segno
 * della causale, imposta calcolata, documento e competenza alla data) o
 * incasso, pagamento e versamento per un totale. Usata dalle fixture e dai
 * test.
 */
final class RegistrazioneDocumentoIva
{

    /**
     * @param array<string, float> $imponibili codice IVA => imponibile (positivo anche nelle note di credito)
     * @param array{esigibilita?: EsigibilitaIva, rivendita?: bool, altriProdotti?: bool, registro?: RegistroIva, totale?: float} $opzioni
     * @throws \DomainException se la registrazione non supera i controlli
     */
    public static function registra(DataMapper $dataMapper, EsercizioContabile $esercizio, ?Controparte $controparte, CausaleContabile $causale, string $data, ?string $numero, array $imponibili, array $opzioni = []): RegistrazioneContabile
    {
        $soggetto = $esercizio->soggetto;
        $registrazione = new RegistrazioneContabile($dataMapper);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->causale = $causale;
        $registrazione->dataRegistrazione = new SismaDate($data);
        $registrazione->numeroDocumento = $numero;
        $registrazione->dataDocumento = $numero === null ? null : new SismaDate($data);
        $registrazione->controparte = $controparte;
        $registrazione->descrizione = trim($causale->getLabel() . ' ' . ($controparte?->denominazione ?? ''));
        $righeIva = [];
        if ($causale->isIva()) {
            $registrazione->registroIva = $opzioni['registro'] ?? (new RegistroIvaModel($dataMapper))->getPerTipo($soggetto, $causale->getTipoRegistroIva())[0];
            $registrazione->dataCompetenzaIva = new SismaDate($data);
            $registrazione->esigibilita = $opzioni['esigibilita'] ?? EsigibilitaIva::immediata;
            $segno = $causale->getSegno();
            foreach ($imponibili as $codice => $imponibile) {
                $riga = new RigaIva($dataMapper);
                $riga->codiceIva = (new CodiceIvaModel($dataMapper))->getPerCodice($soggetto->studio, (string) $codice)
                        ?? throw new \DomainException('Codice IVA ' . $codice . ' inesistente.');
                $riga->imponibile = round($imponibile * $segno, 2);
                $riga->imposta = $riga->codiceIva->calcolaImposta($riga->imponibile);
                $riga->detraibilita = $riga->codiceIva->detraibilita;
                $riga->rivendita = ($opzioni['rivendita'] ?? false) || ($opzioni['altriProdotti'] ?? false);
                $riga->altriProdotti = $opzioni['altriProdotti'] ?? false;
                $righeIva[] = $riga;
            }
            $registrazione->totaleDocumento = round(abs(PropostaRighe::totaleDocumento($righeIva)), 2);
        } else {
            $registrazione->totaleDocumento = $opzioni['totale'] ?? null;
        }
        $piano = (new PianoContiModel($dataMapper))->getPerAzienda($soggetto);
        $righe = (new PropostaRighe($dataMapper))->proponi($piano, $registrazione, $righeIva);
        $errori = (new ControlloRegistrazione())->verifica($piano, $registrazione, $righeIva, $righe);
        if (count($errori) > 0) {
            throw new \DomainException('Registrazione non valida: ' . implode(' ', $errori));
        }
        return (new SalvataggioRegistrazione($dataMapper))->salva($registrazione, $righeIva, $righe);
    }
}
