<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$parametri = ['soggetto' => $soggetto->id, 'anno' => $esercizio->anno, 'causale' => $filtroCausale?->value, 'testo' => $testo, 'dal' => $dal?->format('Y-m-d'), 'al' => $al?->format('Y-m-d')];
$urlPagina = function (int $numero) use ($parametri): string {
    return '/registrazione/index?' . http_build_query(array_filter($parametri + ['pagina' => $numero], fn($valore) => ($valore !== '') && ($valore !== null)));
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/registrazione/index" class="row g-2 align-items-end">
            <input type="hidden" name="soggetto" value="<?= Html::e($soggetto->id) ?>">
            <input type="hidden" name="anno" value="<?= Html::e($esercizio->anno) ?>">
            <div class="col-12 col-lg-3">
                <select class="form-select form-select-sm" name="causale" aria-label="<?= Html::e($campi['causale']) ?>">
                    <option value=""><?= Html::e($campi['causale']) ?>: <?= Html::e($tutte) ?></option>
                    <?php foreach ($causali as $causale): ?>
                        <option value="<?= Html::e($causale->value) ?>" <?= $filtroCausale === $causale ? 'selected' : '' ?>><?= Html::e($causale->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-3"><input type="search" class="form-control form-control-sm" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>"></div>
            <div class="col-6 col-lg-2"><input type="date" class="form-control form-control-sm" name="dal" value="<?= Html::e($dal?->format('Y-m-d') ?? '') ?>" aria-label="<?= Html::e($campi['dal']) ?>"></div>
            <div class="col-6 col-lg-2"><input type="date" class="form-control form-control-sm" name="al" value="<?= Html::e($al?->format('Y-m-d') ?? '') ?>" aria-label="<?= Html::e($campi['al']) ?>"></div>
            <div class="col-6 col-lg-1 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button></div>
            <?php if ($esercizio->chiuso === false): ?>
                <div class="col-6 col-lg-1 d-grid dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= Html::e($nuova) ?>"><i class="bi bi-plus-lg"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($causaliManuali as $causale): ?>
                            <li><a class="dropdown-item" href="/registrazione/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>/causale/<?= Html::e($causale->value) ?>"><?= Html::e($causale->getLabel()) ?></a></li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/corrispettivi/index/soggetto/<?= Html::e($soggetto->id) ?>"><i class="bi bi-calendar3-week me-1"></i><?= Html::e($corrispettiviMese) ?></a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-journal-text fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th><?= Html::e($campi['numero']) ?></th><th><?= Html::e($campi['data']) ?></th><th><?= Html::e($campi['causale']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['controparte']) ?></th><th><?= Html::e($campi['protocolloIva']) ?></th><th class="text-end"><?= Html::e($campi['totaleDocumento']) ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registrazioni as $registrazione): ?>
                        <tr>
                            <td><a href="/registrazione/show/registrazione/<?= Html::e($registrazione->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($registrazione->numero) ?></a></td>
                            <td class="text-nowrap"><?= Html::data($registrazione->dataRegistrazione) ?></td>
                            <td class="small"><?= Html::e($registrazione->causale->getLabel()) ?><?php if ($registrazione->isGenerata()): ?> <span class="badge text-bg-light border"><?= Html::e($generata) ?></span><?php endif; ?></td>
                            <td class="small"><?= Html::e($registrazione->getDescrizioneCompleta()) ?></td>
                            <td class="small"><?= Html::e($registrazione->controparte?->denominazione ?? '') ?></td>
                            <td class="small text-nowrap"><?= $registrazione->protocolloIva === null ? '' : Html::e($registrazione->registroIva->tipo->getLabelBreve() . ($registrazione->registroIva->sezionale === '' ? '' : '/' . $registrazione->registroIva->sezionale) . ' ' . $registrazione->protocolloIva) ?></td>
                            <td class="text-end text-nowrap"><?= $registrazione->totaleDocumento === null ? '' : Html::e(Importo::euro($registrazione->totaleDocumento)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../commonParts/paginazione.php'; ?>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
