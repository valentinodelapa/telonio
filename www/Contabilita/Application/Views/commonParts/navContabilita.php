<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Sezioni del modulo a livello di studio: $sezioneContabilita.
 */
?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneContabilita === 'aziende' ? 'active' : '' ?>" href="/azienda/index"><i class="bi bi-buildings me-1"></i><?= Html::e($navAziende) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneContabilita === 'piani' ? 'active' : '' ?>" href="/piano-conti/index"><i class="bi bi-diagram-3 me-1"></i><?= Html::e($navPiani) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneContabilita === 'codiciIva' ? 'active' : '' ?>" href="/codice-iva/index"><i class="bi bi-percent me-1"></i><?= Html::e($navCodiciIva) ?></a></li>
</ul>
