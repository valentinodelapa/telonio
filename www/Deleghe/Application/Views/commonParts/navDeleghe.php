<?php

use CommSuite\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneDeleghe === 'contribuenti' ? 'active' : '' ?>" href="/contribuente/index"><i class="bi bi-people me-1"></i><?= Html::e($navContribuenti) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneDeleghe === 'invii' ? 'active' : '' ?>" href="/invio-telematico/index"><i class="bi bi-send me-1"></i><?= Html::e($navInvii) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneDeleghe === 'intermediari' ? 'active' : '' ?>" href="/intermediario/index"><i class="bi bi-person-badge me-1"></i><?= Html::e($navIntermediari) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneDeleghe === 'codici' ? 'active' : '' ?>" href="/codice-tributo/index"><i class="bi bi-list-ol me-1"></i><?= Html::e($navCodiciTributo) ?></a></li>
</ul>
