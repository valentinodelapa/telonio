<?php

use CommSuite\Application\HelperClasses\Html;

?>
<div class="d-flex align-items-center gap-2 mt-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= Html::e($azioni['salva']) ?></button>
    <a href="<?= Html::e($urlAnnulla) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
    <span class="ms-auto small text-body-secondary"><?= Html::e($campiObbligatori) ?></span>
</div>
