<?php

use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;
use Successioni\Application\Entities\EstremoVoltura;

$urlAnnulla = '/dichiarazione-successione/show/dichiarazione/' . $dichiarazione->id . '/scheda/attivo';
$tipi = [EstremoVoltura::TRASCRIZIONE => $trascrizione, EstremoVoltura::REGISTRAZIONE => $registrazione];

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<form method="post" novalidate>
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th><?= Html::e($campi['tipoAtto']) ?></th><th><?= Html::e($campi['codiceUfficio']) ?></th><th><?= Html::e($campi['anno']) ?></th><th><?= Html::e($campi['volume']) ?></th><th><?= Html::e($campi['numeroAtto']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($righe as $indice => $riga): ?>
                        <?php $nome = 'righe[' . $indice . ']'; ?>
                        <tr class="<?= isset($erroriRighe[$indice]) ? 'table-danger' : '' ?>">
                            <td>
                                <select class="form-select form-select-sm" name="<?= Html::e($nome) ?>[tipo]"><?php foreach ($tipi as $valore => $etichetta): ?><option value="<?= Html::e($valore) ?>" <?= $riga['tipo'] === $valore ? 'selected' : '' ?>><?= Html::e($etichetta) ?></option><?php endforeach; ?></select>
                                <?php if (isset($erroriRighe[$indice])): ?><div class="small text-danger"><?= Html::e($erroriRighe[$indice]) ?></div><?php endif; ?>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" style="width:7em" name="<?= Html::e($nome) ?>[ufficio]" value="<?= Html::e($riga['ufficio']) ?>" maxlength="5"></td>
                            <td><input type="text" class="form-control form-control-sm" style="width:6em" name="<?= Html::e($nome) ?>[anno]" value="<?= Html::e($riga['anno']) ?>" maxlength="4" inputmode="numeric"></td>
                            <td><input type="text" class="form-control form-control-sm" style="width:6em" name="<?= Html::e($nome) ?>[volume]" value="<?= Html::e($riga['volume']) ?>" maxlength="5" inputmode="numeric"></td>
                            <td><input type="text" class="form-control form-control-sm" style="width:8em" name="<?= Html::e($nome) ?>[numero]" value="<?= Html::e($riga['numero']) ?>" maxlength="6" inputmode="numeric"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/../commonParts/pulsantiForm.php'; ?>
</form>
<?php require Layout::percorso('footer'); ?>
