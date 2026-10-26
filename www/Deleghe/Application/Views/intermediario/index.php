<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$sezioneDeleghe = 'intermediari';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navDeleghe.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <a href="/intermediario/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovoIntermediario']) ?></a>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <?php if (count($intermediari) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessuno) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['intermediario']) ?></th><th><?= Html::e($campi['telefono']) ?></th><th><?= Html::e($campi['email']) ?></th><th><?= Html::e($campi['contoIntermediario']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($intermediari as $intermediario): ?>
                        <tr>
                            <td>
                                <a href="/intermediario/update/intermediario/<?= Html::e($intermediario->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($intermediario->soggetto->nominativo) ?></a>
                                <?php if ($intermediario->predefinito): ?><i class="bi bi-star-fill text-warning ms-1"></i><?php endif; ?>
                                <div class="small text-body-secondary font-monospace"><?= Html::e($intermediario->soggetto->codiceFiscale) ?></div>
                            </td>
                            <td class="small"><?= Html::e($intermediario->telefono ?? '') ?></td>
                            <td class="small"><?= Html::e($intermediario->email ?? '') ?></td>
                            <td class="small font-monospace"><?= Html::e($intermediario->contoAddebito?->getIbanFormattato() ?? '') ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/intermediario/update/intermediario/<?= Html::e($intermediario->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="/intermediario/delete/intermediario/<?= Html::e($intermediario->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>">
                                    <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
