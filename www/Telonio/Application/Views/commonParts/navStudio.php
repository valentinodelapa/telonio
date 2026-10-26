<?php

use Telonio\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneStudio === 'dati' ? 'active' : '' ?>" href="/studio/update"><i class="bi bi-building-gear me-1"></i><?= Html::e($navDatiStudio) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneStudio === 'utenti' ? 'active' : '' ?>" href="/utente/index"><i class="bi bi-people me-1"></i><?= Html::e($navUtentiStudio) ?></a></li>
</ul>
