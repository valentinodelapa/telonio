<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$sezioneAnagrafica = 'immobili';
$urlElenco = function (array $parametri) use ($testo, $tipoCatasto): string {
    $parametri += ['testo' => $testo, 'tipo' => $tipoCatasto?->value ?? ''];
    return '/immobile/index?' . http_build_query(array_filter($parametri, fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAnagrafica.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <?php foreach ($tipiCatasto as $tipo): ?>
        <a href="/immobile/create/tipo/<?= Html::e($tipo->value) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($tipo->getLabel()) ?></a>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/immobile/index" class="row g-2 align-items-center">
            <div class="col-12 col-lg-7">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" autofocus>
                </div>
            </div>
            <div class="col-8 col-lg-3">
                <select class="form-select" name="tipo" aria-label="<?= Html::e($campi['tipoCatasto']) ?>">
                    <option value=""><?= Html::e($tutti) ?></option>
                    <?php foreach ($tipiCatasto as $tipo): ?>
                        <option value="<?= Html::e($tipo->value) ?>" <?= $tipoCatasto === $tipo ? 'selected' : '' ?>><?= Html::e($tipo->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
            </div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5">
            <i class="bi bi-buildings fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['identificativo']) ?></th>
                        <th><?= Html::e($campi['ubicazione']) ?></th>
                        <th><?= Html::e($campi['classamento']) ?></th>
                        <th><?= Html::e($campi['possessori']) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($righe as $riga): ?>
                        <?php $immobile = $riga['immobile']; $dati = $riga['datiCatastali']; ?>
                        <tr>
                            <td class="text-nowrap">
                                <i class="bi <?= Html::e($immobile->tipoCatasto->getIcona()) ?> me-1"></i>
                                <a href="/immobile/show/immobile/<?= Html::e($immobile->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($immobile->getIdentificativo()) ?></a>
                            </td>
                            <td class="small"><?= Html::e($immobile->getUbicazione()) ?><?php if ($immobile->descrizione !== null): ?><div class="text-body-secondary"><?= Html::e($immobile->descrizione) ?></div><?php endif; ?></td>
                            <td class="small text-nowrap">
                                <?php if ($dati !== null): ?>
                                    <?= Html::e($dati->categoria?->value ?? $dati->qualitaColtura ?? '') ?>
                                    <?php if ($dati->rendita !== null): ?><span class="text-body-secondary">· € <?= Html::e(number_format($dati->rendita, 2, ',', '.')) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php foreach ($riga['possessi'] as $possesso): ?>
                                    <div><a href="/soggetto/show/soggetto/<?= Html::e($possesso->soggetto->id) ?>/scheda/immobili" class="text-decoration-none"><?= Html::e($possesso->soggetto->nominativo) ?></a> <span class="text-body-secondary"><?= Html::e($possesso->titolo->getLabel()) ?> <?= Html::e(number_format($possesso->quota, 2, ',', '.')) ?>%</span></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end"><a href="/immobile/show/immobile/<?= Html::e($immobile->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex align-items-center bg-white">
            <span class="small text-body-secondary me-auto"><?= Html::e($totale) ?> <?= Html::e($risultati) ?></span>
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
