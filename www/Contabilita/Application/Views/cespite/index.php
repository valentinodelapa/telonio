<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['cespiti']) ?><?= $esercizio === null ? '' : ' · ' . Html::e($campi['esercizio']) . ' ' . Html::e($esercizio->getEtichetta()) ?></h1>
    <a href="/cespite/create/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovoCespite) ?></a>
    <?php if ($esercizio !== null): ?>
        <a href="/cespite/stampa/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-printer me-1"></i><?= Html::e($stampaRegistro) ?></a>
    <?php endif; ?>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>

<?php if (count($acquistiSenzaCespite) > 0): ?>
    <div class="alert alert-info small">
        <div class="fw-semibold mb-1"><?= Html::e($titoloAcquisti) ?></div>
        <?php foreach ($acquistiSenzaCespite as $acquisto): ?>
            <div class="d-flex align-items-center gap-2 mb-1"><span class="me-auto"><a href="/registrazione/show/registrazione/<?= Html::e($acquisto['registrazione']->id) ?>"><?= Html::e($acquisto['registrazione']->getDescrizioneCompleta()) ?></a> · € <?= Html::e(Importo::euro($acquisto['importo'])) ?></span><a href="/cespite/create/soggetto/<?= Html::e($soggetto->id) ?>/registrazione/<?= Html::e($acquisto['registrazione']->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($creaCespite) ?></a></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead class="table-light"><tr><th><?= Html::e($campi['codice']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['dataAcquisto']) ?></th><th class="text-end"><?= Html::e($campi['costo']) ?></th><th class="text-end">%</th><th class="text-end"><?= Html::e($fondoInizio) ?></th><th class="text-end"><?= Html::e($quotaEsercizio) ?></th><th class="text-end"><?= Html::e($fondoFine) ?></th><th class="text-end"><?= Html::e($residuo) ?></th></tr></thead>
            <tbody>
                <?php if (count($cespiti) === 0): ?>
                    <tr><td colspan="9" class="text-body-secondary"><?= Html::e($nessunCespite) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($cespiti as ['cespite' => $cespite, 'voce' => $voce]): ?>
                    <tr>
                        <td class="font-monospace"><a href="/cespite/update/cespite/<?= Html::e($cespite->id) ?>"><?= Html::e($cespite->codice) ?></a></td>
                        <td><?= Html::e($cespite->descrizione) ?><div class="text-body-secondary"><?= Html::e($cespite->contoCespite->codice . ' ' . $cespite->contoCespite->descrizione) ?></div>
                            <?php if ($voce !== null && $voce['primo']): ?><span class="badge text-bg-light border"><?= Html::e($cespite->deduzioneIntegrale ? $badgeIntegrale : ($cespite->riduzionePrimoEsercizio ? $badgePrimo : $badgePrimoIntero)) ?></span><?php endif; ?>
                            <?php if ($voce !== null && $voce['dismesso']): ?><span class="badge text-bg-warning"><?= Html::e($badgeDismesso) ?> <?= Html::data($cespite->dataDismissione) ?>: <?= Html::e($voce['plusvalenza'] >= 0 ? $plusvalenza : $minusvalenza) ?> € <?= Html::e(Importo::euro(abs($voce['plusvalenza']))) ?></span><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= Html::data($cespite->dataAcquisto) ?></td>
                        <td class="text-end"><?= Html::e(Importo::euro($cespite->costo)) ?></td>
                        <td class="text-end"><?= Html::e(number_format($cespite->percentuale, 2, ',', '.')) ?></td>
                        <?php if ($voce === null): ?>
                            <td colspan="4" class="text-body-secondary"><?= Html::e($fuoriEsercizio) ?></td>
                        <?php else: ?>
                            <td class="text-end"><?= Html::e(Importo::euro($voce['fondoInizio'])) ?></td>
                            <td class="text-end fw-semibold"><?= Html::e(Importo::euro($voce['quota'])) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($voce['fondoFine'])) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($voce['residuo'])) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($esercizio !== null): ?>
        <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2 small">
            <span class="me-auto"><?= Html::e($totaleAmmortamenti) ?> € <span class="fw-semibold"><?= Html::e(Importo::euro($totaleQuote)) ?></span>
                <?php if ($scrittura !== null): ?> · <a href="/registrazione/show/registrazione/<?= Html::e($scrittura->id) ?>"><?= Html::e($scritturaRegistrata) ?> n. <?= Html::e($scrittura->numero) ?></a><?php endif; ?></span>
            <form method="post" action="/cespite/elabora/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e($scrittura === null ? $registraAmmortamenti : $aggiornaAmmortamenti) ?></button></form>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
