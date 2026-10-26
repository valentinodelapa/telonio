<?php

use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$urlAnnulla = '/contribuente-imu/show/soggetto/' . $soggetto->id . '/anno/' . $anno;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$totale = array_sum(array_map(fn($costo) => $costo->importo, $costi));

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary"><?= Html::e($immobile->getIdentificativo()) ?> · <?= Html::e($immobile->getUbicazione()) ?></p>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneCosti) ?></span>
        <span class="small"><?= Html::e($valoreAnno) ?> <?= Html::e($anno) ?>: <strong>€ <?= Html::e(Importo::euro($valore['valore'])) ?></strong>
            <?= $valore['annoDecreto'] !== null ? '<span class="text-body-secondary">(' . Html::e($coefficientiDel) . ' ' . Html::e($valore['annoDecreto']) . ')</span>' : '<span class="text-warning-emphasis">' . Html::e($coefficientiMancanti) . '</span>' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-anagrafica mb-0">
            <thead class="table-light"><tr><th><?= Html::e($campi['annoCosto']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th class="text-end"><?= Html::e($campi['importo']) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($costi as $costo): ?>
                    <tr>
                        <td><?= Html::e($costo->anno) ?></td>
                        <td class="small"><?= Html::e($costo->descrizione ?? '') ?></td>
                        <td class="text-end text-nowrap"><?= Html::e(Importo::euro($costo->importo)) ?></td>
                        <td class="text-end"><form method="post" action="/costo-contabile-imu/delete/costo/<?= Html::e($costo->id) ?>/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr><td colspan="2"><?= Html::e($totaleCosti) ?></td><td class="text-end text-nowrap"><?= Html::e(Importo::euro($totale)) ?></td><td></td></tr></tfoot>
        </table>
    </div>
</div>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><?= Html::e($nuovoCosto) ?></div>
        <div class="card-body row g-3">
            <?= Campo::testo('anno', $campi['annoCosto'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-2', 'attributi' => 'inputmode="numeric" maxlength="4"']) ?>
            <?= Campo::testo('importo', $campi['importo'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3', 'attributi' => 'inputmode="decimal"']) ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => 'col-md-7', 'attributi' => 'maxlength="150"']) ?>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
