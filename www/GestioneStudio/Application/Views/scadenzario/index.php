<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use GestioneStudio\Application\Services\Scadenzario;

$mostraSoggetto = true;

require Layout::percorso('header');
require __DIR__ . '/../commonParts/nav.php';
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="get" action="/scadenzario/index" class="row g-2 align-items-end">
            <div class="col-6 col-lg-2">
                <label for="dal" class="form-label small mb-1"><?= Html::e($campi['dal']) ?></label>
                <input type="date" class="form-control" id="dal" name="dal" value="<?= Html::e($dal->format('Y-m-d')) ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label for="al" class="form-label small mb-1"><?= Html::e($campi['al']) ?></label>
                <input type="date" class="form-control" id="al" name="al" value="<?= Html::e($al->format('Y-m-d')) ?>">
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label for="stato" class="form-label small mb-1"><?= Html::e($campi['stato']) ?></label>
                <select class="form-select" id="stato" name="stato">
                    <?php foreach ($filtri as $voceFiltro): ?>
                        <option value="<?= Html::e($voceFiltro->value) ?>" <?= $voceFiltro === $filtro ? 'selected' : '' ?>><?= Html::e($voceFiltro->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label for="responsabile" class="form-label small mb-1"><?= Html::e($campi['responsabile']) ?></label>
                <select class="form-select" id="responsabile" name="responsabile">
                    <option value="<?= Scadenzario::RESPONSABILE_TUTTI ?>"><?= Html::e($tutti) ?></option>
                    <option value="<?= Scadenzario::RESPONSABILE_NESSUNO ?>" <?= $responsabile === Scadenzario::RESPONSABILE_NESSUNO ? 'selected' : '' ?>><?= Html::e($nonAssegnate) ?></option>
                    <?php foreach ($operatori as $id => $nome): ?>
                        <option value="<?= Html::e($id) ?>" <?= $responsabile === $id ? 'selected' : '' ?>><?= Html::e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label for="modulo" class="form-label small mb-1"><?= Html::e($campi['modulo']) ?></label>
                <select class="form-select" id="modulo" name="modulo">
                    <option value=""><?= Html::e($tuttiModuli) ?></option>
                    <?php foreach ($moduliFonte as $voceModulo): ?>
                        <option value="<?= Html::e($voceModulo->value) ?>" <?= $voceModulo === $moduloScelto ? 'selected' : '' ?>><?= Html::e($voceModulo->getLabel()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i><?= Html::e($azioni['filtra']) ?></button>
            </div>
        </form>
    </div>
    <?php if (count($voci) === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><i class="bi bi-calendar-check fs-1 d-block mb-2"></i><?= Html::e($nessunaScadenza) ?></div>
    <?php else: ?>
        <?php require __DIR__ . '/../commonParts/tabellaVoci.php'; ?>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e(count($voci)) ?> <?= Html::e($scadenze) ?></div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
