<?php

use CommSuite\Application\HelperClasses\Html;

?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link <?= $sezioneImu === 'contribuenti' ? 'active' : '' ?>" href="/contribuente-imu/index/anno/<?= Html::e($anno) ?>"><i class="bi bi-people me-1"></i><?= Html::e($navContribuenti) ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $sezioneImu === 'aliquote' ? 'active' : '' ?>" href="/prospetto-aliquote/index/anno/<?= Html::e($anno) ?>"><i class="bi bi-percent me-1"></i><?= Html::e($navAliquote) ?></a></li>
</ul>
