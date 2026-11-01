<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlPeriodo = '/stampa-contabile/scheda';
$parametriPeriodo = ['soggetto' => $soggetto->id, 'conto' => $conto->id];
$urlStampa = $urlPeriodo . '/soggetto/' . $soggetto->id . '/conto/' . $conto->id . '/anno/' . $esercizio->anno . '/dal/' . $dal->format('Y-m-d') . '/al/' . $al->format('Y-m-d') . '/stampa/1';
$classeTabella = 'table table-sm table-hover mb-0';
$classeNumero = 'text-end text-nowrap';
$collegamenti = true;

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<h1 class="h5 mb-1"><?= Html::e($titoloStampa) ?></h1>
<?php require __DIR__ . '/../commonParts/filtroPeriodo.php'; ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <?php require __DIR__ . '/parti/scheda.php'; ?>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
