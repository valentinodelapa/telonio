<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$nomiMesi = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
$statoVersamenti = function (array $versamenti) use ($daVersare, $inDelega): string {
    $voci = [];
    foreach ($versamenti as $versamento) {
        $voci[] = Html::e($versamento->codiceTributo->codice) . ' € ' . Html::e(Importo::euro($versamento->importoDebito)) . ' · '
                . ($versamento->delega === null ? Html::e($daVersare) : '<a href="/delega/show/delega/' . Html::e($versamento->delega->id) . '">' . Html::e($inDelega) . ' ' . Html::data($versamento->delega->scadenza) . '</a> (' . Html::e(mb_strtolower($versamento->delega->stato->getLabel())) . ')');
    }
    return implode('<br>', $voci);
};

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h5 mb-0 me-auto"><?= Html::e($navAzienda['ritenuteBollo']) ?> <?= Html::e($annoTributi) ?></h1>
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-calendar3 me-1"></i><?= Html::e($annoTributi) ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($anniTributi as $voce): ?><li><a class="dropdown-item <?= $voce === $annoTributi ? 'active' : '' ?>" href="/ritenute-bollo/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($voce) ?>"><?= Html::e($voce) ?></a></li><?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($titoloRitenute) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e(sprintf($aiutoRitenute, $codiceRitenute)) ?></span></div>
    <?php if (count($mesiRitenute) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaRitenuta) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th><?= Html::e($mese) ?></th><th><?= Html::e($percipienti) ?></th><th class="text-end"><?= Html::e($ritenuteOperate) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th><?= Html::e($registrati) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($mesiRitenute as $numero => $dati): ?>
                        <tr>
                            <td><?= Html::e($nomiMesi[$numero]) ?></td>
                            <td class="small">
                                <?php foreach ($dati['righe'] as $voce): ?>
                                    <?php $registrazione = $voce['riga']->registrazioneContabile; ?>
                                    <div><a href="/registrazione/show/registrazione/<?= Html::e($registrazione->id) ?>"><?= Html::data($registrazione->dataRegistrazione) ?> n. <?= Html::e($registrazione->numero) ?></a> <?= Html::e($registrazione->controparte->denominazione) ?> · € <?= Html::e(Importo::euro($voce['importo'])) ?></div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= Html::e(Importo::euro($dati['importo'])) ?></td>
                            <td class="small text-nowrap"><?= Html::data($dati['scadenza']) ?></td>
                            <td class="small"><?= $statoVersamenti($dati['registrati']) ?></td>
                            <td class="text-end">
                                <form method="post" action="/ritenute-bollo/ritenute/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoTributi) ?>/mese/<?= Html::e($numero) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><?= Html::e(count($dati['registrati']) > 0 ? $aggiornaVersamento : $registraVersamento) ?></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><?= Html::e($titoloBollo) ?></span>
        <span class="small text-body-secondary me-auto"><?= Html::e($aiutoBolloAnnuale) ?></span>
        <form method="post" action="/ritenute-bollo/bollo/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoTributi) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><?= Html::e(count($bolloRegistrati) > 0 ? $aggiornaVersamenti : $registraVersamenti) ?></button></form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light"><tr><th><?= Html::e($trimestre) ?></th><th class="text-end"><?= Html::e($fatture) ?></th><th class="text-end"><?= Html::e($imposta) ?></th><th><?= Html::e($codiceTributo) ?></th><th><?= Html::e($campi['scadenza']) ?></th></tr></thead>
            <tbody>
                <?php foreach ($trimestriBollo as $numero => $dati): ?>
                    <?php $versamento = $pianoBollo[$numero] ?? null; ?>
                    <tr>
                        <td><?= Html::e($numero) ?>° <?= Html::e(mb_strtolower($trimestre)) ?></td>
                        <td class="text-end"><?= Html::e($dati['fatture']) ?></td>
                        <td class="text-end fw-semibold"><?= Html::e(Importo::euro($dati['importo'])) ?></td>
                        <td class="font-monospace"><?= $versamento === null ? '' : Html::e($versamento['codice']) ?></td>
                        <td class="small"><?= $versamento === null ? '' : Html::data($versamento['scadenza']) . ($versamento['differito'] ? ' <span class="badge text-bg-light border" title="' . Html::e($aiutoDifferito) . '">' . Html::e($differito) . '</span>' : '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($bolloRegistrati) > 0): ?>
        <div class="card-body small border-top"><span class="text-body-secondary"><?= Html::e($registrati) ?>:</span><br><?= $statoVersamenti($bolloRegistrati) ?></div>
    <?php endif; ?>
    <?php if (count($bolloDaVerificare) > 0): ?>
        <div class="card-body small border-top">
            <div class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i><?= Html::e($titoloDaVerificare) ?></div>
            <div class="text-body-secondary mb-1"><?= Html::e($aiutoDaVerificare) ?></div>
            <?php foreach ($bolloDaVerificare as $voce): ?>
                <?php $registrazione = $voce['registrazione']; ?>
                <div><a href="/registrazione/show/registrazione/<?= Html::e($registrazione->id) ?>"><?= Html::e($registrazione->numeroDocumento ?? '') ?> del <?= Html::data($registrazione->dataDocumento ?? $registrazione->dataRegistrazione) ?></a> <?= Html::e($registrazione->controparte?->denominazione ?? '') ?> · <?= Html::e($senzaIva) ?> € <?= Html::e(Importo::euro($voce['senzaIva'])) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
