<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$parametri = ['soggetto' => $soggetto->id, 'stato' => $filtroStato?->value, 'direzione' => $filtroDirezione?->value, 'testo' => $testo];
$urlPagina = function (int $numero) use ($parametri): string {
    return '/fattura-elettronica/index?' . http_build_query(array_filter($parametri + ['pagina' => $numero], fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="/fattura-elettronica/carica/soggetto/<?= Html::e($soggetto->id) ?>" enctype="multipart/form-data" class="row g-2 align-items-center">
            <?= $campoCsrf ?>
            <div class="col-md-9"><input type="file" class="form-control" name="file[]" multiple accept=".xml,.p7m,.zip" required aria-label="<?= Html::e($campi['file']) ?>"></div>
            <div class="col-md-3 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i><?= Html::e($azioni['carica']) ?></button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/fattura-elettronica/index" class="row g-2 align-items-center">
            <input type="hidden" name="soggetto" value="<?= Html::e($soggetto->id) ?>">
            <div class="col-6 col-lg-3">
                <select class="form-select form-select-sm" name="stato" aria-label="<?= Html::e($campi['stato']) ?>">
                    <option value=""><?= Html::e($campi['stato']) ?>: <?= Html::e($tutti) ?></option>
                    <?php foreach ($stati as $stato): ?><option value="<?= Html::e($stato->value) ?>" <?= $filtroStato === $stato ? 'selected' : '' ?>><?= Html::e($stato->getLabel()) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <select class="form-select form-select-sm" name="direzione" aria-label="<?= Html::e($campi['direzione']) ?>">
                    <option value=""><?= Html::e($campi['direzione']) ?>: <?= Html::e($tutte) ?></option>
                    <?php foreach ($direzioni as $direzione): ?><option value="<?= Html::e($direzione->value) ?>" <?= $filtroDirezione === $direzione ? 'selected' : '' ?>><?= Html::e($direzione->getLabel()) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-lg-4"><input type="search" class="form-control form-control-sm" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>"></div>
            <div class="col-4 col-lg-2 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button></div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-file-earmark-code fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th><?= Html::e($campi['data']) ?></th><th><?= Html::e($campi['numero']) ?></th><th><?= Html::e($campi['tipo']) ?></th><th><?= Html::e($campi['controparte']) ?></th><th class="text-end"><?= Html::e($campi['totaleDocumento']) ?></th><th><?= Html::e($campi['stato']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($fatture as $fattura): ?>
                        <tr>
                            <td class="text-nowrap"><?= Html::data($fattura->data) ?></td>
                            <td><a href="/fattura-elettronica/show/fattura/<?= Html::e($fattura->id) ?>" class="text-decoration-none fw-semibold"><?= Html::e($fattura->numero) ?></a></td>
                            <td class="small"><span class="badge <?= $fattura->direzione->value === 'E' ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= Html::e($fattura->direzione->getLabel()) ?></span> <?= Html::e($fattura->tipoDocumento->value) ?></td>
                            <td class="small"><?= Html::e($fattura->denominazioneControparte) ?><?= $fattura->avvisi === null ? '' : ' <i class="bi bi-exclamation-triangle text-warning" title="' . Html::e($fattura->avvisi) . '"></i>' ?></td>
                            <td class="text-end text-nowrap"><?= $fattura->importoTotale === null ? '' : Html::e(Importo::euro($fattura->importoTotale)) ?></td>
                            <td><span class="badge text-bg-<?= Html::e($fattura->stato->getColore()) ?>"><?= Html::e($fattura->stato->getLabel()) ?></span></td>
                            <td class="text-end text-nowrap">
                                <?php if ($fattura->stato->value === 'D'): ?>
                                    <a href="/registrazione/fattura/fattura/<?= Html::e($fattura->id) ?>" class="btn btn-sm btn-primary"><?= Html::e($azioni['registra']) ?></a>
                                <?php elseif ($fattura->registrazioneContabile !== null): ?>
                                    <a href="/registrazione/show/registrazione/<?= Html::e($fattura->registrazioneContabile->id) ?>" class="btn btn-sm btn-outline-secondary"><?= Html::e($campi['numero']) ?> <?= Html::e($fattura->registrazioneContabile->numero) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../commonParts/paginazione.php'; ?>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
