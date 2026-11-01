<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlPagina = function (int $numero) use ($testo, $soggetto): string {
    return '/controparte/index?' . http_build_query(array_filter(['soggetto' => $soggetto->id, 'testo' => $testo, 'pagina' => $numero], fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<p class="text-body-secondary small"><?= Html::e($aiuto) ?></p>
<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/controparte/index" class="row g-2 align-items-center">
            <input type="hidden" name="soggetto" value="<?= Html::e($soggetto->id) ?>">
            <div class="col-12 col-lg-7"><input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" autofocus></div>
            <div class="col-6 col-lg-2 d-grid"><button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button></div>
            <div class="col-6 col-lg-3 d-grid"><a href="/controparte/create/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovo']) ?></a></div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['denominazione']) ?></th><th><?= Html::e($campi['partitaIva']) ?></th><th><?= Html::e($campi['comune']) ?></th><th><?= Html::e($campi['conto']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($controparti as $controparte): ?>
                        <tr>
                            <td>
                                <a href="/controparte/update/controparte/<?= Html::e($controparte->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($controparte->denominazione) ?></a>
                                <div class="small">
                                    <?php if ($controparte->cliente): ?><span class="badge text-bg-light border"><?= Html::e($campi['cliente']) ?></span><?php endif; ?>
                                    <?php if ($controparte->fornitore): ?><span class="badge text-bg-light border"><?= Html::e($campi['fornitore']) ?></span><?php endif; ?>
                                    <?php if ($controparte->pubblicaAmministrazione): ?><span class="badge text-bg-info">PA</span><?php endif; ?>
                                </div>
                            </td>
                            <td class="font-monospace small"><?= Html::e(($controparte->nazione?->codiceIso ?? '') . ($controparte->partitaIva ?? '')) ?><div class="text-body-secondary"><?= Html::e($controparte->codiceFiscale ?? '') ?></div></td>
                            <td class="small"><?= Html::e(trim(($controparte->comune ?? '') . ($controparte->provincia === null ? '' : ' (' . $controparte->provincia . ')'))) ?></td>
                            <td class="small">
                                <?php foreach ([$controparte->contoCliente, $controparte->contoFornitore] as $conto): ?>
                                    <?php if ($conto !== null): ?><a href="/stampa-contabile/scheda/soggetto/<?= Html::e($soggetto->id) ?>/conto/<?= Html::e($conto->id) ?>" class="d-block font-monospace text-decoration-none"><?= Html::e($conto->codice) ?></a><?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end"><a href="/controparte/update/controparte/<?= Html::e($controparte->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['modifica']) ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../commonParts/paginazione.php'; ?>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
