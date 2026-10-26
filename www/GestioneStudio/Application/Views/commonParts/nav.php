<?php

use CommSuite\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezione === 'scadenzario' ? 'active' : '' ?>" href="/scadenzario/index"><i class="bi bi-calendar-check me-1"></i><?= Html::e($navScadenzario) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezione === 'clienti' ? 'active' : '' ?>" href="/scadenzario/clienti"><i class="bi bi-people me-1"></i><?= Html::e($navClienti) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezione === 'adempimenti' ? 'active' : '' ?>" href="/adempimento/index"><i class="bi bi-calendar3 me-1"></i><?= Html::e($navAdempimenti) ?></a></li>
</ul>
