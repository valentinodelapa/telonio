<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$urlElenco = function (array $parametri) use ($testo, $inclusiTutti): string {
    $parametri += ['testo' => $testo, 'tutti' => $inclusiTutti ? 1 : ''];
    return '/contribuente/index?' . http_build_query(array_filter($parametri, fn($valore) => ($valore !== '') && ($valore !== null)));
};
$sezioneDeleghe = 'contribuenti';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navDeleghe.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
</div>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/contribuente/index" class="row g-2 align-items-center">
            <div class="col-12 col-lg-6">
                <input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" aria-label="<?= Html::e($segnapostoRicerca) ?>" autofocus>
            </div>
            <div class="col-8 col-lg-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="tutti" name="tutti" value="1" <?= $inclusiTutti ? "checked" : "" ?>>
                    <label class="form-check-label" for="tutti"><?= Html::e($etichettaTutti) ?></label>
                </div>
            </div>
            <div class="col-4 col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
            </div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5">
            <i class="bi bi-people fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['soggetto']) ?></th>
                        <th><?= Html::e($colonnaDaVersare) ?></th>
                        <th><?= Html::e($colonnaDeleghe) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contribuenti as ['soggetto' => $soggetto, 'daVersare' => $numeroDaVersare, 'prossimaScadenza' => $scadenza, 'bozze' => $bozze, 'confermate' => $confermate]): ?>
                        <tr>
                            <td>
                                <a href="/contribuente/show/soggetto/<?= Html::e($soggetto->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($soggetto->nominativo) ?></a>
                                <div class="small text-body-secondary font-monospace"><?= Html::e($soggetto->codiceFiscale) ?></div>
                            </td>
                            <td class="small">
                                <?php if ($numeroDaVersare > 0): ?>
                                    <?= Html::e($numeroDaVersare) ?> · <?= Html::e($dal) ?> <?= Html::data($scadenza) ?>
                                    <?php if ($scadenza->format('Y-m-d') < $oggi): ?><span class="badge text-bg-danger ms-1"><?= Html::e($etichettaScaduti) ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($bozze > 0): ?><span class="badge text-bg-secondary"><?= Html::e($bozze) ?> <?= Html::e($etichettaBozze) ?></span><?php endif; ?>
                                <?php if ($confermate > 0): ?><span class="badge text-bg-primary"><?= Html::e($confermate) ?> <?= Html::e($etichettaConfermate) ?></span><?php endif; ?>
                            </td>
                            <td class="text-end"><a href="/contribuente/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['apri']) ?> <i class="bi bi-chevron-right"></i></a></td>
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
