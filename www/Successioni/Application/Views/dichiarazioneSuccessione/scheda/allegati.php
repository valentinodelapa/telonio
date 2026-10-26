<?php

use Telonio\Application\HelperClasses\Html;
use Successioni\Application\Enumerations\TipoAllegato;

?>
<?php if ($modificabile): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="post" action="/allegato-successione/create/dichiarazione/<?= Html::e($id) ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                <?= $campoCsrf ?>
                <div class="col-md-4">
                    <label class="form-label" for="tipoAllegato"><?= Html::e($campi['tipoAllegato']) ?></label>
                    <select class="form-select" id="tipoAllegato" name="tipoAllegato" required>
                        <?php foreach (TipoAllegato::cases() as $tipo): ?><option value="<?= Html::e($tipo->value) ?>"><?= Html::e($tipo->getLabel()) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="file"><?= Html::e($campi['file']) ?></label><input type="file" class="form-control" id="file" name="file" accept=".pdf,.tif,.tiff" required></div>
                <div class="col-md-3"><label class="form-label" for="descrizione"><?= Html::e($campi['descrizione']) ?></label><input type="text" class="form-control" id="descrizione" name="descrizione" maxlength="100"></div>
                <div class="col-md-1 d-grid"><button type="submit" class="btn btn-primary"><?= Html::e($azioni['carica']) ?></button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
<div class="card shadow-sm">
    <?php if (count($dati->allegati) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessunAllegato) ?></div>
    <?php else: ?>
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($dati->allegati as $allegato): ?>
                    <tr>
                        <td class="small"><?= Html::e($allegato->tipoAllegato->getLabel()) ?></td>
                        <td><?= Html::e($allegato->nomeFile) ?><div class="small text-body-secondary"><?= Html::e($allegato->descrizione ?? '') ?></div></td>
                        <td class="small text-end text-nowrap"><?= Html::e(number_format($allegato->dimensione / 1024, 0, ',', '.')) ?> KB</td>
                        <td class="text-end text-nowrap">
                            <a href="/allegato-successione/scarica/allegato/<?= Html::e($allegato->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['scarica']) ?>"><i class="bi bi-download"></i></a>
                            <?php if ($modificabile): ?>
                                <form method="post" action="/allegato-successione/delete/allegato/<?= Html::e($allegato->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoAllegati) ?></div>
</div>
