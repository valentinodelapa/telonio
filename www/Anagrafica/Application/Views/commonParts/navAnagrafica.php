<?php

use CommSuite\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneAnagrafica === 'soggetti' ? 'active' : '' ?>" href="/soggetto/index"><i class="bi bi-people me-1"></i><?= Html::e($navSoggetti) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneAnagrafica === 'immobili' ? 'active' : '' ?>" href="/immobile/index"><i class="bi bi-buildings me-1"></i><?= Html::e($navImmobili) ?></a></li>
</ul>
