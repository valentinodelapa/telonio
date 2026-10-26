<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlElenco = function (array $parametri) use ($testo, $sezioneFiltro, $soppressi): string {
    $parametri += ['testo' => $testo, 'sezione' => $sezioneFiltro?->value ?? '', 'soppressi' => $soppressi ? '1' : ''];
    return '/codice-tributo/index?' . http_build_query(array_filter($parametri, fn($valore) => ($valore !== '') && ($valore !== null)));
};
$sezioneDeleghe = 'codici';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navDeleghe.php';
?>
<h1 class="h3 mb-3"><?= Html::e($pageTitle) ?></h1>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/codice-tributo/index" class="row g-2 align-items-center">
            <div class="col-12 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" autofocus>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select class="form-select" name="sezione" aria-label="<?= Html::e($campi['sezione']) ?>">
                    <option value=""><?= Html::e($tutte) ?></option>
                    <?php foreach ($sezioni as $sezione): ?>
                        <option value="<?= Html::e($sezione->value) ?>" <?= $sezioneFiltro === $sezione ? 'selected' : '' ?>><?= Html::e($sezione->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="soppressi" value="1" id="soppressi" <?= $soppressi ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="soppressi"><?= Html::e($etichettaSoppressi) ?></label>
                </div>
            </div>
            <div class="col-12 col-lg-1 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
            </div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['codice']) ?></th>
                        <th><?= Html::e($campi['sezione']) ?></th>
                        <th><?= Html::e($campi['descrizione']) ?></th>
                        <th><?= Html::e($campi['tipoTributo']) ?></th>
                        <th><?= Html::e($campi['formalismi']) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codici as $codice): ?>
                        <tr class="<?= $codice->inUso ? '' : 'text-body-secondary' ?>">
                            <td class="font-monospace fw-semibold"><?= Html::e($codice->codice) ?></td>
                            <td class="small text-nowrap"><?= Html::e($codice->sezione->getLabel()) ?></td>
                            <td class="small">
                                <?= Html::e($codice->descrizione) ?>
                                <?php if ($codice->inUso === false): ?><span class="badge text-bg-secondary ms-1"><?= Html::e($nonInUso) ?></span><?php endif; ?>
                            </td>
                            <td class="small"><?= Html::e($codice->tipoTributo ?? '') ?></td>
                            <td class="small text-nowrap">
                                <?= Html::e(match ($codice->modalita->value) { 'D' => $soloDebito, 'R' => $soloCredito, default => $debitoCredito }) ?>
                                <?= $codice->annoRichiesto ? ' · ' . Html::e($anno) : '' ?>
                                <?= $codice->formatoRateazione !== '0000' ? ' · <span class="font-monospace">' . Html::e($codice->formatoRateazione) . '</span>' : '' ?>
                                <?= $codice->codiceUfficio->isAmmesso() ? ' · ' . Html::e($ufficio) : '' ?>
                                <?= $codice->codiceAtto->isAmmesso() ? ' · ' . Html::e($atto) : '' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center bg-white">
            <span class="small text-body-secondary me-auto"><?= Html::e($totale) ?> <?= Html::e($risultati) ?> · <?= Html::e($fonte) ?></span>
            <?php if ($pagine > 1): ?>
                <nav aria-label="Paginazione">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= Html::e($urlElenco(['pagina' => $pagina - 1])) ?>">&laquo;</a></li>
                        <?php for ($numero = max(1, $pagina - 3); $numero <= min($pagine, $pagina + 3); $numero++): ?>
                            <li class="page-item <?= $numero === $pagina ? 'active' : '' ?>"><a class="page-link" href="<?= Html::e($urlElenco(['pagina' => $numero])) ?>"><?= $numero ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagina === $pagine ? 'disabled' : '' ?>"><a class="page-link" href="<?= Html::e($urlElenco(['pagina' => $pagina + 1])) ?>">&raquo;</a></li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
