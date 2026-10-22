<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

$sezioneDeleghe = 'invii';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navDeleghe.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <a href="/invio-telematico/create" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= Html::e($azioni['nuovoInvio']) ?></a>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <?php if (count($invii) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessuno) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-anagrafica mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['file']) ?></th><th><?= Html::e($campi['intermediario']) ?></th><th class="text-end"><?= Html::e($campi['deleghe']) ?></th><th class="text-end"><?= Html::e($campi['totale']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($invii as $invio): ?>
                        <tr>
                            <td><a href="/invio-telematico/show/invio/<?= Html::e($invio->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($invio->nomeFile) ?></a><div class="small text-body-secondary"><?= Html::e($invio->dataCreazione->format('d/m/Y H:i')) ?><?= $invio->addebitoIntermediario ? ' · Y' : '' ?></div></td>
                            <td class="small"><?= Html::e($invio->intermediario->soggetto->nominativo) ?></td>
                            <td class="text-end"><?= Html::e($invio->numeroDeleghe) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($invio->totale)) ?></td>
                            <td class="text-end"><a href="/invio-telematico/scarica/invio/<?= Html::e($invio->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['scarica']) ?>"><i class="bi bi-download"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
