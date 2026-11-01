<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navContabilita.php';
?>
<h1 class="h3 mb-1"><?= Html::e($navPiani) ?></h1>
<p class="text-body-secondary"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <?php if (count($modelli) === 0): ?>
        <div class="card-body text-center text-body-secondary py-5"><?= Html::e($nessunRisultato) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($modelli as $modello): ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                    <div class="me-auto">
                        <a href="/piano-conti/show/piano/<?= Html::e($modello->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($modello->denominazione) ?></a>
                        <span class="badge <?= $modello->isDellaSuite() ? 'text-bg-light border' : 'text-bg-primary' ?> ms-1"><?= Html::e($modello->isDellaSuite() ? $dellaSuite : $delloStudio) ?></span>
                        <?php if ($modello->descrizione !== null): ?><div class="small text-body-secondary"><?= Html::e($modello->descrizione) ?></div><?php endif; ?>
                    </div>
                    <form method="post" action="/piano-conti/copia/piano/<?= Html::e($modello->id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-copy me-1"></i><?= Html::e($azioni['copia']) ?></button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
