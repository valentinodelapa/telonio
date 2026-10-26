<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi bi-send me-2 text-primary"></i><?= Html::e($pageTitle) ?></h1>
        <div class="text-body-secondary"><?= Html::e($invio->intermediario->getEtichetta()) ?> · <?= Html::e($invio->nomeFile) ?><?= $invio->addebitoIntermediario ? ' · ' . Html::e($campi['addebitoIntermediario']) : '' ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/invio-telematico/scarica/invio/<?= Html::e($invio->id) ?>" class="btn btn-primary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['scarica']) ?></a>
        <form method="post" action="/invio-telematico/delete/invio/<?= Html::e($invio->id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>">
            <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger"><?= Html::e($azioni['annullaInvio']) ?></button>
        </form>
    </div>
</div>
<p class="small text-body-secondary"><?= Html::e($aiutoScarto) ?></p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-anagrafica mb-0">
            <thead class="table-light"><tr><th><?= Html::e($campi['scadenza']) ?></th><th><?= Html::e($campi['soggetto']) ?></th><th><?= Html::e($campi['stato']) ?></th></tr></thead>
            <tbody>
                <?php foreach ($deleghe as $delega): ?>
                    <tr>
                        <td><?= Html::data($delega->scadenza) ?></td>
                        <td><a href="/delega/show/delega/<?= Html::e($delega->id) ?>"><?= Html::e($delega->soggetto->nominativo) ?></a> <span class="small text-body-secondary font-monospace"><?= Html::e($delega->soggetto->codiceFiscale) ?></span></td>
                        <td><span class="badge <?= Html::e($delega->stato->getClasseBadge()) ?>"><?= Html::e($delega->stato->getLabel()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-semibold"><td colspan="2"><?= Html::e($campi['totale']) ?></td><td>€ <?= Html::e(Importo::euro($invio->totale)) ?></td></tr></tfoot>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
