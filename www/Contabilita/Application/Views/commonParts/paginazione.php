<?php

use Telonio\Application\HelperClasses\Html;

/**
 * Piede delle tabelle con totale e paginazione: $totale, $pagina, $pagine
 * e $urlPagina (funzione dal numero di pagina all'URL).
 */
?>
<div class="card-footer d-flex align-items-center bg-white">
    <span class="small text-body-secondary me-auto"><?= Html::e($totale) ?> <?= Html::e($risultati) ?></span>
    <?php if ($pagine > 1): ?>
        <nav aria-label="Paginazione">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= Html::e($urlPagina($pagina - 1)) ?>">&laquo;</a></li>
                <?php for ($numero = max(1, $pagina - 3); $numero <= min($pagine, $pagina + 3); $numero++): ?>
                    <li class="page-item <?= $numero === $pagina ? 'active' : '' ?>"><a class="page-link" href="<?= Html::e($urlPagina($numero)) ?>"><?= $numero ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina === $pagine ? 'disabled' : '' ?>"><a class="page-link" href="<?= Html::e($urlPagina($pagina + 1)) ?>">&raquo;</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
