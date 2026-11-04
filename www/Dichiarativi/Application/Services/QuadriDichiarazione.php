<?php

namespace Dichiarativi\Application\Services;

use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreDichiarazione;
use Dichiarativi\Application\Models\CampoModelloModel;
use Dichiarativi\Application\Models\ValoreDichiarazioneModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Dati dei quadri di una dichiarazione, per modulo e codice del campo del
 * catalogo del modello, con la distinzione tra valori proposti (dai dati
 * dei moduli della suite) e valori inseriti o corretti a mano.
 *
 * La proposta lavora per quadri: avviaProposta() segna come da eliminare
 * i valori proposti dei quadri ricalcolati, proponi() imposta i nuovi
 * valori senza toccare quelli manuali (restituiti da get(), così i totali
 * si calcolano sui valori effettivi) e salva() scrive le differenze.
 * Ogni valore è validato e ridotto in forma canonica con il formato del
 * campo (FormatoCampo): i codici fuori catalogo sono rifiutati.
 */
class QuadriDichiarazione
{

    /** @var array<int, array<string, array{valore: string, manuale: bool, entita: ?ValoreDichiarazione}>> */
    private array $valori = [];

    /** @var array<string, CampoModello> */
    private array $catalogo;

    /** @var array<int, array<string, ValoreDichiarazione>> valori salvati da eliminare */
    private array $daEliminare = [];

    /** @var string[] quadri in proposta */
    private array $quadriInProposta = [];

    /** @var string[] valori manuali diversi da quelli proposti */
    private array $differenzeManuali = [];

    /** Modulo su cui lavorano i metodi senza il modulo indicato */
    private int $moduloCorrente = 1;

    public function __construct(private Dichiarazione $dichiarazione, private DataMapper $dataMapper = new DataMapper(), ?array $catalogo = null)
    {
        $this->catalogo = $catalogo ?? (new CampoModelloModel($dataMapper))->getCatalogo($dichiarazione->modello, $dichiarazione->getAnnoModello());
        if (isset($dichiarazione->id)) {
            foreach ((new ValoreDichiarazioneModel($dataMapper))->getValori($dichiarazione) as $valore) {
                $this->valori[$valore->modulo][$valore->codice] = ['valore' => $valore->valore, 'manuale' => $valore->manuale, 'entita' => $valore];
            }
        }
    }

    public function getDichiarazione(): Dichiarazione
    {
        return $this->dichiarazione;
    }

    /**
     * @return array<string, CampoModello>
     */
    public function getCatalogo(): array
    {
        return $this->catalogo;
    }

    public function getCampo(string $codice): CampoModello
    {
        return $this->catalogo[$codice] ?? throw new \InvalidArgumentException(sprintf('Il campo %s non è nel catalogo del modello %s %d.', $codice,
                        $this->dichiarazione->modello->getSigla(), $this->dichiarazione->getAnnoModello()));
    }

    /**
     * Sceglie il modulo su cui lavorano i metodi chiamati senza modulo
     * (la proposta di ogni modulo della dichiarazione).
     */
    public function usaModulo(int $modulo): self
    {
        $this->moduloCorrente = max(1, $modulo);
        return $this;
    }

    public function getModuloCorrente(): int
    {
        return $this->moduloCorrente;
    }

    /**
     * Moduli con almeno un valore, in ordine.
     *
     * @return int[]
     */
    public function getModuli(): array
    {
        $moduli = array_keys(array_filter($this->valori, fn(array $campi) => count($campi) > 0));
        sort($moduli);
        return $moduli;
    }

    /**
     * Elimina i valori di un modulo (al salvataggio), salvo quelli dei
     * quadri esclusi.
     */
    public function eliminaModulo(int $modulo, string ...$quadriEsclusi): void
    {
        foreach (array_keys($this->valori[$modulo] ?? []) as $codice) {
            if (in_array(substr($codice, 0, 2), $quadriEsclusi, true) === false) {
                $this->rimuovi($codice, $modulo);
            }
        }
    }

    public function get(string $codice, ?int $modulo = null): ?string
    {
        return $this->valori[$modulo ?? $this->moduloCorrente][$codice]['valore'] ?? null;
    }

    /**
     * Valore di un campo numerico (0 se assente).
     */
    public function getImporto(string $codice, ?int $modulo = null): int
    {
        return (int) ($this->get($codice, $modulo) ?? 0);
    }

    /**
     * Somma degli importi dei campi indicati.
     */
    /**
     * Importo con i centesimi (formati VP e VN del modello IVA TR).
     */
    public function getDecimale(string $codice, ?int $modulo = null): float
    {
        return (float) ($this->get($codice, $modulo) ?? 0);
    }

    public function somma(string ...$codici): int
    {
        return array_sum(array_map(fn(string $codice) => $this->getImporto($codice), $codici));
    }

    public function isManuale(string $codice, ?int $modulo = null): bool
    {
        return $this->valori[$modulo ?? $this->moduloCorrente][$codice]['manuale'] ?? false;
    }

    /**
     * @return array<int, array<string, array{valore: string, manuale: bool}>> per modulo e codice
     */
    public function getValori(): array
    {
        $valori = [];
        foreach ($this->valori as $modulo => $campi) {
            ksort($campi);
            foreach ($campi as $codice => $dati) {
                $valori[$modulo][$codice] = ['valore' => $dati['valore'], 'manuale' => $dati['manuale']];
            }
        }
        ksort($valori);
        return $valori;
    }

    /**
     * Quadri con almeno un valore nel modulo.
     *
     * @return string[]
     */
    public function getQuadriCompilati(?int $modulo = null): array
    {
        $quadri = array_unique(array_map(fn(string $codice) => substr($codice, 0, 2), array_keys($this->valori[$modulo ?? $this->moduloCorrente] ?? [])));
        sort($quadri);
        return $quadri;
    }

    /**
     * Inizio della proposta dei quadri indicati nel modulo corrente: i
     * loro valori proposti non riproposti saranno eliminati al salvataggio.
     */
    public function avviaProposta(string ...$quadri): void
    {
        $this->quadriInProposta = array_values(array_unique(array_merge($this->quadriInProposta, $quadri)));
        foreach ($this->valori[$this->moduloCorrente] ?? [] as $codice => $dati) {
            if (($dati['manuale'] === false) && in_array(substr($codice, 0, 2), $quadri, true)) {
                $this->rimuovi($codice, $this->moduloCorrente);
            }
        }
    }

    /**
     * Valore proposto: non sostituisce un valore inserito a mano (la
     * differenza è annotata, vedi getDifferenzeManuali()).
     */
    public function proponi(string $codice, string|int|float|bool|null $valore, ?int $modulo = null): void
    {
        $modulo ??= $this->moduloCorrente;
        $normalizzato = $this->normalizza($codice, $valore);
        if ($this->isManuale($codice, $modulo)) {
            if ($this->get($codice, $modulo) !== $normalizzato) {
                $campo = $this->getCampo($codice);
                $this->differenzeManuali[] = sprintf(($modulo > 1 ? 'Modulo ' . $modulo . ', ' : '') . '%s %s: mantenuto il valore inserito a mano (%s); proposto %s.', $codice, $campo->descrizione,
                        $campo->formato?->mostra($this->get($codice, $modulo)) ?? $this->get($codice, $modulo), $normalizzato === null ? 'nessun valore' : ($campo->formato?->mostra($normalizzato) ?? $normalizzato));
            }
            return;
        }
        $this->scrivi($codice, $normalizzato, $modulo, false);
    }

    /**
     * Valore inserito o corretto a mano; null lo cancella. Con $manuale
     * false il campo torna a essere proposto (alla prossima proposta).
     *
     * @throws \InvalidArgumentException se il valore non rispetta il formato del campo
     */
    public function imposta(string $codice, string|int|float|bool|null $valore, ?int $modulo = null, bool $manuale = true): void
    {
        $modulo ??= $this->moduloCorrente;
        $normalizzato = $this->normalizza($codice, $valore);
        if ($normalizzato === null && $manuale) {
            // un campo svuotato a mano resta vuoto anche alle proposte successive
            $this->scrivi($codice, '', $modulo, true);
            return;
        }
        $this->scrivi($codice, $normalizzato, $modulo, $manuale);
    }

    /**
     * Toglie il segno di valore manuale: il campo sarà ricalcolato.
     */
    public function ripristina(string $codice, ?int $modulo = null): void
    {
        $modulo ??= $this->moduloCorrente;
        if (isset($this->valori[$modulo][$codice])) {
            $this->rimuovi($codice, $modulo);
        }
    }

    /**
     * @return string[]
     */
    public function getDifferenzeManuali(): array
    {
        return $this->differenzeManuali;
    }

    public function salva(): void
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($this->daEliminare as $modulo => $campi) {
            foreach ($campi as $codice => $entita) {
                if (isset($this->valori[$modulo][$codice]['entita']) && ($this->valori[$modulo][$codice]['entita'] === $entita)) {
                    continue;
                }
                $this->dataMapper->delete($entita);
            }
        }
        $this->daEliminare = [];
        foreach ($this->valori as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                $entita = $dati['entita'] ?? new ValoreDichiarazione($this->dataMapper);
                if (isset($entita->id) && ($entita->valore === $dati['valore']) && ($entita->manuale === $dati['manuale'])) {
                    continue;
                }
                $entita->dichiarazione = $this->dichiarazione;
                $entita->modulo = $modulo;
                $entita->codice = $codice;
                $entita->valore = $dati['valore'];
                $entita->manuale = $dati['manuale'];
                $this->dataMapper->save($entita);
                $this->valori[$modulo][$codice]['entita'] = $entita;
            }
        }
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    private function normalizza(string $codice, string|int|float|bool|null $valore): ?string
    {
        $campo = $this->getCampo($codice);
        if ($campo->formato === null) {
            if (($valore === null) || ($valore === '') || ($valore === 0)) {
                return null;
            }
            throw new \InvalidArgumentException(sprintf('Il campo %s non ha un formato nelle specifiche tecniche e non si può compilare.', $codice));
        }
        try {
            return $campo->formato->normalizza($valore);
        } catch (\InvalidArgumentException $errore) {
            throw new \InvalidArgumentException($codice . ' (' . $campo->descrizione . '): ' . $errore->getMessage(), 0, $errore);
        }
    }

    private function scrivi(string $codice, ?string $valore, int $modulo, bool $manuale): void
    {
        if ($valore === null) {
            $this->rimuovi($codice, $modulo);
            return;
        }
        $entita = $this->valori[$modulo][$codice]['entita'] ?? $this->daEliminare[$modulo][$codice] ?? null;
        unset($this->daEliminare[$modulo][$codice]);
        $this->valori[$modulo][$codice] = ['valore' => $valore, 'manuale' => $manuale, 'entita' => $entita];
    }

    private function rimuovi(string $codice, int $modulo): void
    {
        $entita = $this->valori[$modulo][$codice]['entita'] ?? null;
        if ($entita !== null) {
            $this->daEliminare[$modulo][$codice] = $entita;
        }
        unset($this->valori[$modulo][$codice]);
    }
}
