<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$euro = fn(float $valore): string => number_format($valore, 2, ',', '.');
$base = '/partita/index/soggetto/' . $soggetto->id . ($filtroControparte === null ? '' : '/controparte/' . $filtroControparte->id);

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<p class="text-body-secondary small"><?= Html::e($aiuto) ?></p>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?= Html::e($base) ?>" class="btn btn-sm <?= $tutte ? 'btn-outline-primary' : 'btn-primary' ?>"><?= Html::e($soloAperte) ?></a>
    <a href="<?= Html::e($base . '/tutte/1') ?>" class="btn btn-sm <?= $tutte ? 'btn-primary' : 'btn-outline-primary' ?>"><?= Html::e($tutteLePartite) ?></a>
    <?php if ($filtroControparte !== null): ?>
        <a href="/partita/index/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= Html::e($filtroControparte->denominazione) ?></a>
    <?php endif; ?>
</div>
<?php if (count($scadenzario) === 0): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-body-secondary py-5"><?= Html::e($nessunaPartita) ?></div></div>
<?php endif; ?>
<?php foreach ($scadenzario as $voce): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <a href="/partita/index/soggetto/<?= Html::e($soggetto->id) ?>/controparte/<?= Html::e($voce['controparte']->id) ?><?= $tutte ? '/tutte/1' : '' ?>" class="fw-semibold text-decoration-none"><?= Html::e($voce['controparte']->denominazione) ?></a>
            <span class="badge text-bg-light border"><?= Html::e($voce['cliente'] ? $campi['cliente'] : $campi['fornitore']) ?></span>
            <a href="/stampa-contabile/scheda/soggetto/<?= Html::e($soggetto->id) ?>/conto/<?= Html::e($voce['conto']->id) ?>" class="small font-monospace text-decoration-none"><?= Html::e($voce['conto']->codice) ?></a>
            <span class="ms-auto small"><?= Html::e($residuoTotale) ?> <strong><?= Html::e($euro($voce['residuo'])) ?></strong> · <?= Html::e($saldoConto) ?> <?= Html::e($euro($voce['saldo'])) ?></span>
            <?php if (abs($voce['differenza']) > 0.005): ?><span class="badge text-bg-danger" title="<?= Html::e($aiutoQuadratura) ?>"><?= Html::e($nonQuadra) ?> <?= Html::e($euro($voce['differenza'])) ?></span><?php endif; ?>
            <?php if ($voce['daVerificare'] > 0): ?>
                <span class="badge text-bg-warning"><?= Html::e($voce['daVerificare']) ?> <?= Html::e($daVerificare) ?></span>
                <form method="post" action="/partita/conferma/controparte/<?= Html::e($voce['controparte']->id) ?>" class="d-inline"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-success"><?= Html::e($confermaAbbinamenti) ?></button></form>
            <?php endif; ?>
        </div>
        <?php if (count($voce['partite']) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th><?= Html::e($colonnaPartita) ?></th><th><?= Html::e($scadenza) ?></th><th class="text-end"><?= Html::e($importo) ?></th><th><?= Html::e($colonnaAbbinamenti) ?></th><th class="text-end"><?= Html::e($residuo) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($voce['partite'] as $riga): ?>
                            <?php $partita = $riga['partita']; $segno = $partita->tipo->isApertura() ? '' : '-'; ?>
                            <tr>
                                <td><a href="/registrazione/show/registrazione/<?= Html::e($partita->registrazioneContabile->id) ?>" class="text-decoration-none"><?= Html::e($partita->getEtichetta()) ?></a>
                                    <?php if ($partita->partitaOrigine !== null): ?><div class="small text-body-secondary"><?= Html::e($riapre) ?> <?= Html::e($partita->partitaOrigine->getEtichetta()) ?></div><?php endif; ?></td>
                                <td><?= Html::data($riga['prossimaScadenza']) ?>
                                    <?php if (count($riga['rate']) > 1 || ((count($riga['rate']) === 1) && ($riga['rate'][0]['scadenza']->modalita !== null))): ?>
                                        <?php foreach ($riga['rate'] as $rata): ?>
                                            <div class="small <?= $rata['residuo'] > 0.005 ? '' : 'text-body-secondary text-decoration-line-through' ?>"><?= Html::e($rataNumero) ?> <?= Html::e($rata['scadenza']->numero) ?>: <?= Html::data($rata['scadenza']->dataScadenza) ?> · <?= Html::e($euro($rata['scadenza']->importo)) ?><?= $rata['scadenza']->modalita === null ? '' : ' · ' . Html::e($rata['scadenza']->modalita->getLabelBreve()) ?><?= ($rata['residuo'] > 0.005) && ($rata['residuo'] < $rata['scadenza']->importo - 0.005) ? ' · ' . Html::e($residuo) . ' ' . Html::e($euro($rata['residuo'])) : '' ?></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?></td>
                                <td class="text-end"><?= Html::e($segno . $euro($partita->importo)) ?></td>
                                <td class="small">
                                    <?php foreach ($riga['abbinamenti'] as $abbinamento): ?>
                                        <div><a href="/registrazione/show/registrazione/<?= Html::e($abbinamento->registrazioneContabile->id) ?>" class="text-decoration-none"><?= Html::data($abbinamento->data) ?></a> · <?= Html::e($abbinamento->tipo->getEtichettaBreve()) ?> <?= Html::e($euro($abbinamento->importo)) ?><?php if ($abbinamento->automatico): ?> <span class="badge text-bg-warning"><?= Html::e($automatico) ?></span><?php endif; ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end fw-semibold"><?= Html::e($segno . $euro($riga['residuo'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php require Layout::percorso('footer'); ?>
