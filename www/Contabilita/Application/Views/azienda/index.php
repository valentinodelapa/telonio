<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlPagina = function (int $numero) use ($testo, $inclusiTutti): string {
    return '/azienda/index?' . http_build_query(array_filter(['testo' => $testo, 'tutti' => $inclusiTutti ? 1 : '', 'pagina' => $numero], fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navContabilita.php';
?>
<h1 class="h3 mb-1">Contabilità</h1>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/azienda/index" class="row g-2 align-items-center">
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
        <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-buildings fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['azienda']) ?></th>
                        <th><?= Html::e($campi['partitaIva']) ?></th>
                        <th><?= Html::e($campi['stato']) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aziende as ['soggetto' => $soggetto, 'aperta' => $aperta, 'daRegistrare' => $numero]): ?>
                        <?php $url = '/azienda/show/soggetto/' . $soggetto->id; ?>
                        <tr>
                            <td>
                                <a href="<?= Html::e($url) ?>" class="fw-semibold text-decoration-none"><?= Html::e($soggetto->nominativo) ?></a>
                                <div class="small text-body-secondary"><?= Html::e($soggetto->getDescrizioneTipo()) ?></div>
                            </td>
                            <td class="font-monospace small"><?= Html::e($soggetto->partitaIva ?? '') ?></td>
                            <td>
                                <?php if ($aperta): ?>
                                    <span class="badge text-bg-success"><?= Html::e($etichettaAperta) ?></span>
                                    <?php if ($numero > 0): ?><span class="badge text-bg-warning"><?= Html::e($numero) ?> <?= Html::e($daRegistrare) ?></span><?php endif; ?>
                                <?php else: ?>
                                    <span class="badge text-bg-light border"><?= Html::e($nonAperta) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><a href="<?= Html::e($url) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($aperta ? $azioni['apri'] : $azioni['apriContabilita']) ?> <i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../commonParts/paginazione.php'; ?>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
