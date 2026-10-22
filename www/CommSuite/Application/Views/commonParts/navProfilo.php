<?php

use CommSuite\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneProfilo === 'dati' ? 'active' : '' ?>" href="/profilo/update"><i class="bi bi-person-gear me-1"></i><?= Html::e($navDatiProfilo) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneProfilo === 'password' ? 'active' : '' ?>" href="/profilo/password"><i class="bi bi-key me-1"></i><?= Html::e($navCambioPassword) ?></a></li>
</ul>
