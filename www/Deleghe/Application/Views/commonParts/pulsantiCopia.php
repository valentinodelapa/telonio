<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Copia e ripetizione del versamento $riga (CopiaVersamenti).
 */
?>
<div class="btn-group btn-group-sm">
    <a href="/versamento/copia/versamento/<?= Html::e($riga->id) ?>" class="btn btn-outline-secondary" title="<?= Html::e($azioni['copia']) ?>"><i class="bi bi-copy"></i></a>
    <a href="/versamento/ripeti/versamento/<?= Html::e($riga->id) ?>" class="btn btn-outline-secondary" title="<?= Html::e($azioni['ripeti']) ?>"><i class="bi bi-arrow-repeat"></i></a>
</div>
