<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlElenco = function (array $parametri) use ($testo, $inclusiTutti): string {
    $parametri += ['testo' => $testo, 'tutti' => $inclusiTutti ? 1 : ''];
    return '/defunto/index?' . http_build_query(array_filter($parametri, fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/defunto/index" class="row g-2 align-items-center">
            <div class="col-12 col-lg-6">
                <input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" aria-label="<?= Html::e($segnapostoRicerca) ?>" autofocus>
            </div>
            <div class="col-8 col-lg-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tutti" name="tutti" value="1" <?= $inclusiTutti ? 'checked' : '' ?>>
                    <label class="form-check-label" for="tutti"><?= Html::e($etichettaTutti) ?></label>
                </div>
            </div>
            <div class="col-4 col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
            </div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-diagram-3 fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['defunto']) ?></th>
                        <th><?= Html::e($campi['dataDecesso']) ?></th>
                        <th><?= Html::e($colonnaDichiarazioni) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($defunti as ['soggetto' => $soggetto, 'dichiarazioni' => $dichiarazioni]): ?>
                        <?php $url = '/defunto/show/soggetto/' . $soggetto->id; ?>
                        <tr>
                            <td>
                                <a href="<?= Html::e($url) ?>" class="fw-semibold text-decoration-none"><?= Html::e($soggetto->nominativo) ?></a>
                                <div class="small text-body-secondary font-monospace"><?= Html::e($soggetto->codiceFiscale) ?></div>
                            </td>
                            <td class="text-nowrap"><?= Html::data($soggetto->dataDecesso) ?></td>
                            <td class="small">
                                <?php foreach ($dichiarazioni as $dichiarazione): ?>
                                    <div><span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span> <?= Html::e($dichiarazione->tipoDichiarazione->getLabel()) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end"><a href="<?= Html::e($url) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['apri']) ?> <i class="bi bi-chevron-right"></i></a></td>
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
