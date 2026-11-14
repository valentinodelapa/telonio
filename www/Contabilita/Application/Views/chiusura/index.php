<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$link = fn($registrazione, string $testo) => $registrazione === null ? '' : '<a href="/registrazione/show/registrazione/' . Html::e($registrazione->id) . '">' . Html::e($testo) . ' n. ' . Html::e($registrazione->numero) . '</a>';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<h1 class="h5 mb-1"><?= Html::e($navAzienda['chiusura']) ?> · <?= Html::e($campi['esercizio']) ?> <?= Html::e($esercizio->getEtichetta()) ?></h1>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($esercizio->chiuso): ?><div class="alert alert-warning small py-2"><?= Html::e($esercizioChiuso) ?></div><?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <div class="fw-semibold">1. <?= Html::e($titoloAmmortamenti) ?></div>
            <div class="small"><?= Html::e($totaleQuote) ?> € <?= Html::e(Importo::euro($totaleAmmortamenti)) ?><?= $scritturaAmmortamenti === null ? ' · ' . Html::e($nonRegistrata) : ' · ' . $link($scritturaAmmortamenti, $registrazione) ?></div>
        </div>
        <a href="/cespite/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($vaiCespiti) ?></a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold me-auto">2. <?= Html::e($titoloRatei) ?></span>
        <a href="/chiusura/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovaVoce) ?></a>
        <form method="post" action="/chiusura/ratei/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($scritturaRatei === null ? $registraRatei : $aggiornaRatei) ?></button></form>
    </div>
    <div class="card-body small py-2 text-body-secondary"><?= Html::e($aiutoRatei) ?></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle small">
            <thead class="table-light"><tr><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['conto']) ?></th><th><?= Html::e($competenza) ?></th><th class="text-end"><?= Html::e($campi['importo']) ?></th><th><?= Html::e($tipoAssestamento) ?></th><th class="text-end"><?= Html::e($quota) ?></th></tr></thead>
            <tbody>
                <?php if (count($ratei) === 0): ?><tr><td colspan="6" class="text-body-secondary"><?= Html::e($nessunaVoce) ?></td></tr><?php endif; ?>
                <?php foreach ($ratei as ['voce' => $voce, 'quota' => $importoQuota, 'funzione' => $funzione]): ?>
                    <tr>
                        <td><a href="/chiusura/update/voce/<?= Html::e($voce->id) ?>"><?= Html::e($voce->descrizione) ?></a></td>
                        <td><?= Html::e($voce->conto->codice . ' ' . $voce->conto->descrizione) ?></td>
                        <td class="text-nowrap"><?= Html::data($voce->competenzaDal) ?> - <?= Html::data($voce->competenzaAl) ?></td>
                        <td class="text-end"><?= Html::e(Importo::euro($voce->importo)) ?></td>
                        <td><?= Html::e(mb_strtolower(explode(' (', $funzione->getLabel())[0])) ?></td>
                        <td class="text-end fw-semibold"><?= Html::e(Importo::euro($importoQuota)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (($scritturaRatei !== null) || ($scritturaStorno !== null)): ?>
        <div class="card-footer bg-white small"><?= $link($scritturaRatei, $assestamento) ?><?= $scritturaStorno === null ? '' : ' · ' . $link($scritturaStorno, $storno) ?></div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <div class="fw-semibold">3. <?= Html::e($titoloChiusura) ?></div>
            <div class="small"><?= Html::e($risultato >= 0 ? $utile : $perdita) ?> € <span class="fw-semibold"><?= Html::e(Importo::euro(abs($risultato))) ?></span> <span class="text-body-secondary">(<?= Html::e($risultatoPrima) ?>)</span></div>
            <div class="small">
                <?= implode(' · ', array_filter([$link($scrittureChiusura['epilogo'], $epilogo), $link($scrittureChiusura['chiusura'], $chiusuraConti), $link($scrittureChiusura['riapertura'], $riapertura)])) ?: Html::e($nonRegistrata) ?>
                <?php if ($successivo === null): ?><div class="text-warning-emphasis"><?= Html::e($senzaSuccessivo) ?></div><?php endif; ?>
            </div>
        </div>
        <form method="post" action="/chiusura/chiudi/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" data-conferma="<?= Html::e($confermaChiusura) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-primary"><?= Html::e($scrittureChiusura['epilogo'] === null ? $generaChiusura : $aggiornaChiusura) ?></button></form>
    </div>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoChiusura) ?></div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <div class="fw-semibold">4. <?= Html::e($titoloDestinazione) ?></div>
            <div class="small"><?= $scritturaDestinazione === null ? Html::e($nonRegistrata) : $link($scritturaDestinazione, $registrazione) . ' · ' . Html::data($scritturaDestinazione->dataRegistrazione) ?></div>
        </div>
        <a href="/chiusura/destinazione/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($scritturaDestinazione === null ? $destina : $aggiornaDestinazione) ?></a>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
