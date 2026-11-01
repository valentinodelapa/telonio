<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\CorrispettiviMensili;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Corrispettivi del mese in una sola maschera: griglia dei giorni per
 * codice IVA con gli importi comprensivi dell'imposta, salvata come
 * un'unica registrazione mensile (Services\CorrispettiviMensili).
 */
class CorrispettiviController extends ContabilitaController
{

    /** Codici proposti come colonne se il mese non ne ha. */
    private const CODICI_PROPOSTI = ['22', '10', '4'];

    /**
     * Ingresso dall'azienda: registro dei corrispettivi principale.
     */
    public function index(Soggetto $soggetto): Response
    {
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        $registro = (new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, TipoRegistroIva::corrispettivi)[0] ?? null;
        if ($registro === null) {
            $this->aggiungiMessaggio('L\'azienda non ha un registro dei corrispettivi attivo.', 'warning');
            return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
        }
        return $this->router->redirect('corrispettivi/mese/registro/' . $registro->id);
    }

    public function mese(Request $request, RegistroIva $registro, int $anno = 0, int $mese = 0): Response
    {
        $soggetto = $registro->soggetto;
        if ($this->caricaAzienda($soggetto) === null) {
            return $this->contabilitaNonAperta($soggetto);
        }
        if ($registro->tipo !== TipoRegistroIva::corrispettivi) {
            $this->aggiungiMessaggio('Scegliere un registro dei corrispettivi.', 'warning');
            return $this->router->redirect('registro-iva/index/soggetto/' . $soggetto->id);
        }
        [$anno, $mese] = self::normalizzaMese($anno, $mese);
        $servizio = new CorrispettiviMensili($this->dataMapper);
        $codiciDisponibili = [];
        foreach ((new CodiceIvaModel($this->dataMapper))->getElenco($this->studio) as $codice) {
            if ($codice->inversioneContabile === false) {
                $codiciDisponibili[$codice->id] = $codice;
            }
        }
        $registrazione = $servizio->getRegistrazione($registro, $anno, $mese);
        $importi = $servizio->getImporti($registrazione);
        $colonne = $this->colonne($servizio, $registro, $anno, $mese, $importi, $codiciDisponibili);
        $this->vars['ventilazione'] = (new RegimeIva($this->dataMapper))->isVentilazione($soggetto, $anno);
        if ($this->vars['ventilazione']) {
            foreach ($codiciDisponibili as $codice) {
                if ($codice->ventilazione && ($codice->studio === null)) {
                    $colonne = [$codice->id => true] + $colonne;
                }
            }
        }
        if (isset($request->input['submitted'])) {
            $this->verificaCsrf($request);
            $importi = self::leggiImporti($request->input['importi'] ?? []);
            foreach ($importi as $perCodice) {
                foreach (array_keys($perCodice) as $idCodice) {
                    $colonne[$idCodice] = true;
                }
            }
            $aggiunto = (int) ($request->input['nuovoCodice'] ?? 0);
            if (isset($codiciDisponibili[$aggiunto])) {
                $colonne[$aggiunto] = true;
            }
            if (($request->input['azione'] ?? 'salva') === 'salva') {
                try {
                    $registrazione = $servizio->registra($registro, $anno, $mese, $importi, $codiciDisponibili);
                    $this->aggiungiMessaggio($registrazione === null
                            ? 'Nessun corrispettivo nel mese: la registrazione è stata eliminata (o non era presente).'
                            : 'Corrispettivi registrati con la registrazione n. ' . $registrazione->numero . ' (protocollo ' . $registrazione->protocolloIva . '): totale € ' . Importo::euro((float) $registrazione->totaleDocumento) . '.');
                    return $this->router->redirect('corrispettivi/mese/registro/' . $registro->id . '/anno/' . $anno . '/mese/' . $mese);
                } catch (\DomainException $errore) {
                    $this->vars['errore'] = $errore->getMessage();
                }
            }
        }
        $this->getEsercizio($soggetto, $anno);
        $this->vars['registro'] = $registro;
        $this->vars['registri'] = iterator_to_array((new RegistroIvaModel($this->dataMapper))->getPerTipo($soggetto, TipoRegistroIva::corrispettivi), false);
        $this->vars['annoCorrispettivi'] = $anno;
        $this->vars['meseCorrispettivi'] = $mese;
        $this->vars['giorni'] = self::giorni($anno, $mese);
        $this->vars['colonne'] = array_values(array_filter(array_map(fn($id) => $codiciDisponibili[$id] ?? null, array_keys($colonne))));
        $this->vars['codiciDisponibili'] = $codiciDisponibili;
        $this->vars['importi'] = $importi;
        $this->vars['registrazione'] = $registrazione;
        $this->vars['errore'] ??= null;
        $this->vars['sezioneAzienda'] = 'registri';
        $this->vars['pageTitle'] = 'Corrispettivi ' . sprintf('%02d/%04d', $mese, $anno);
        $this->impostaBriciole($soggetto, null, $this->vars['pageTitle'], ['Registri IVA' => '/registro-iva/index/soggetto/' . $soggetto->id]);
        return $this->render->generateView('corrispettivi/mese', $this->vars);
    }

    /**
     * Mese richiesto o, di default, quello precedente fino al 15 (termine
     * dell'annotazione mensile) e quello in corso dopo.
     *
     * @return array{0: int, 1: int}
     */
    public static function normalizzaMese(int $anno, int $mese, ?\DateTimeImmutable $oggi = null): array
    {
        if (($anno >= 2000) && ($mese >= 1) && ($mese <= 12)) {
            return [$anno, $mese];
        }
        $oggi ??= new \DateTimeImmutable();
        $riferimento = (int) $oggi->format('j') <= 15 ? $oggi->modify('first day of last month') : $oggi;
        return [(int) $riferimento->format('Y'), (int) $riferimento->format('n')];
    }

    /**
     * Codici IVA delle colonne: le aliquote più comuni, quelle già usate
     * nel mese e nel mese precedente (in quest'ordine).
     *
     * @return array<int, true>
     */
    private function colonne(CorrispettiviMensili $servizio, RegistroIva $registro, int $anno, int $mese, array $importi, array $codiciDisponibili): array
    {
        $colonne = [];
        foreach (self::CODICI_PROPOSTI as $proposto) {
            foreach ($codiciDisponibili as $codice) {
                if (($codice->studio === null) && ($codice->codice === $proposto)) {
                    $colonne[$codice->id] = true;
                }
            }
        }
        $precedente = $mese === 1 ? [$anno - 1, 12] : [$anno, $mese - 1];
        foreach ([$importi, $servizio->getImporti($servizio->getRegistrazione($registro, ...$precedente))] as $fonte) {
            foreach ($fonte as $perCodice) {
                foreach (array_keys($perCodice) as $idCodice) {
                    $colonne[$idCodice] = true;
                }
            }
        }
        return $colonne;
    }

    /**
     * @return array<string, array<int, float>> importi non vuoti per data e codice
     */
    public static function leggiImporti(mixed $input): array
    {
        $importi = [];
        if (is_array($input) === false) {
            return [];
        }
        foreach ($input as $data => $perCodice) {
            if (is_array($perCodice) === false) {
                continue;
            }
            foreach ($perCodice as $idCodice => $valore) {
                $testo = is_string($valore) ? Importo::normalizza($valore) : '';
                if (($testo !== '') && is_numeric($testo) && ctype_digit((string) $idCodice)) {
                    $importi[(string) $data][(int) $idCodice] = round((float) $testo, 2);
                }
            }
        }
        return $importi;
    }

    /**
     * @return array<int, array{data: string, etichetta: string, festivo: bool}>
     */
    private static function giorni(int $anno, int $mese): array
    {
        $nomi = ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'];
        $giorni = [];
        $giorno = new \DateTimeImmutable(sprintf('%04d-%02d-01', $anno, $mese));
        while ((int) $giorno->format('n') === $mese) {
            $giorni[] = ['data' => $giorno->format('Y-m-d'), 'etichetta' => $giorno->format('d') . ' ' . $nomi[(int) $giorno->format('w')], 'festivo' => (int) $giorno->format('w') === 0];
            $giorno = $giorno->modify('+1 day');
        }
        return $giorni;
    }
}
