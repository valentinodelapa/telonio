<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlElenco = function (array $parametri) use ($testo, $tipoSoggetto, $tipoAttivita, $soloClienti): string {
    $parametri += ['testo' => $testo, 'tipo' => $tipoSoggetto?->value ?? '', 'attivita' => $tipoAttivita?->value ?? '', 'clienti' => $soloClienti ? '1' : ''];
    return '/soggetto/index?' . http_build_query(array_filter($parametri, fn($valore) => ($valore !== '') && ($valore !== null)));
};

$sezioneAnagrafica = 'soggetti';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAnagrafica.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <div class="dropdown">
        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovoSoggetto) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($tipiSoggetto as $tipo): ?>
                <li><a class="dropdown-item" href="/soggetto/create/tipo/<?= Html::e($tipo->value) ?>"><i class="bi <?= Html::e($tipo->getIcona()) ?> me-2"></i><?= Html::e($tipo->getLabel()) ?></a></li>
                <?php if ($tipo->isPersonaFisica()): ?>
                    <?php foreach ($tipiAttivita as $attivita): ?>
                        <li><a class="dropdown-item ps-5" href="/soggetto/create/tipo/<?= Html::e($tipo->value) ?>/attivita/<?= Html::e($attivita->value) ?>"><i class="bi <?= Html::e($attivita->getIcona()) ?> me-2"></i><?= Html::e($attivita->getLabel()) ?></a></li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/soggetto/index" class="row g-2 align-items-center">
            <div class="col-12 col-lg-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" name="testo" value="<?= Html::e($testo) ?>" placeholder="<?= Html::e($segnapostoRicerca) ?>" autofocus>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <select class="form-select" name="tipo" aria-label="<?= Html::e($campi['tipoSoggetto']) ?>">
                    <option value=""><?= Html::e($tutti) ?></option>
                    <?php foreach ($tipiSoggetto as $tipo): ?>
                        <option value="<?= Html::e($tipo->value) ?>" <?= $tipoSoggetto === $tipo ? 'selected' : '' ?>><?= Html::e($tipo->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <select class="form-select" name="attivita" aria-label="<?= Html::e($campi['tipoAttivita']) ?>">
                    <option value=""><?= Html::e($tutteAttivita) ?></option>
                    <?php foreach ($tipiAttivita as $attivita): ?>
                        <option value="<?= Html::e($attivita->value) ?>" <?= $tipoAttivita === $attivita ? 'selected' : '' ?>><?= Html::e($attivita->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="clienti" value="1" id="clienti" <?= $soloClienti ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="clienti"><?= Html::e($etichettaSoloClienti) ?></label>
                </div>
            </div>
            <div class="col-12 col-lg-1 d-grid">
                <button type="submit" class="btn btn-outline-primary"><?= Html::e($azioni['cerca']) ?></button>
            </div>
        </form>
    </div>
    <?php if ($totale === 0): ?>
        <div class="card-body text-center text-body-secondary py-5">
            <i class="bi bi-person-x fs-1 d-block mb-2"></i><?= Html::e($nessunRisultato) ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['codice']) ?></th>
                        <th><?= Html::e($campi['nominativo']) ?></th>
                        <th><?= Html::e($campi['tipoSoggetto']) ?></th>
                        <th><?= Html::e($campi['codiceFiscale']) ?></th>
                        <th><?= Html::e($campi['partitaIva']) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($soggetti as $soggetto): ?>
                        <tr>
                            <td class="text-body-secondary small"><?= Html::e($soggetto->codice ?? '') ?></td>
                            <td>
                                <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($soggetto->nominativo) ?></a>
                                <?php if ($soggetto->clienteStudio): ?>
                                    <span class="badge text-bg-primary-subtle text-primary-emphasis ms-1"><?= Html::e($cliente) ?></span>
                                <?php endif; ?>
                                <?php if ($soggetto->isCessato()): ?>
                                    <span class="badge text-bg-secondary ms-1"><?= Html::e($cessato) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><i class="bi <?= Html::e(($soggetto->tipoAttivita ?? $soggetto->tipoSoggetto)->getIcona()) ?> me-1"></i><?= Html::e($soggetto->getDescrizioneTipo()) ?></td>
                            <td class="font-monospace small"><?= Html::e($soggetto->codiceFiscale) ?></td>
                            <td class="font-monospace small"><?= Html::e($soggetto->partitaIva ?? '') ?></td>
                            <td class="text-end"><a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></td>
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
