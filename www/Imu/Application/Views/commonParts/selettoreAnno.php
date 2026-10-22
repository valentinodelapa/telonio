<?php

use CommSuite\Application\HelperClasses\Html;

/**
 * Selettore dell'anno: $urlAnno è l'URL a cui si aggiunge l'anno.
 */
?>
<div class="dropdown">
    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($campi['anno']) ?> <?= Html::e($anno) ?></button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($anni as $voce): ?>
            <li><a class="dropdown-item <?= $voce === $anno ? 'active' : '' ?>" href="<?= Html::e($urlAnno . $voce) ?>"><?= Html::e($voce) ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
