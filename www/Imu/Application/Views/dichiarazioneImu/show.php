<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Enumerations\StatoDichiarazione;

$id = $dichiarazione->id;
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$modificabile = $dichiarazione->isModificabile();
$quadri = $dichiarazione->isEnc() ? ['A' => $quadroAEnc, 'B' => $quadroBEnc] : ['A' => $quadroA];
$riduzioni = $dichiarazione->isEnc() ? RigoDichiarazioneImu::RIDUZIONI_ENC : RigoDichiarazioneImu::RIDUZIONI;
$saldo = $dichiarazione->getSaldoQuadroC();

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><?= Html::e($pageTitle) ?></h1>
        <div class="text-body-secondary">
            <?= Html::e($dichiarazione->soggetto->nominativo) ?> · <?= Html::e($dichiarazione->modello->getLabel()) ?> · <?= Html::e($dichiarazione->tipologia->getLabel()) ?>
            · <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
            <?php if ($dichiarazione->dataPresentazione !== null): ?> <?= Html::e($presentataIl) ?> <?= Html::data($dichiarazione->dataPresentazione) ?><?= $dichiarazione->protocollo !== null ? ' · ' . Html::e($dichiarazione->protocollo) : '' ?><?php endif; ?>
            · <?= Html::e($scadenza) ?> <?= Html::data($dichiarazione->getScadenza()) ?>
        </div>
    </div>
    <a href="/dichiarazione-imu/stampa/dichiarazione/<?= Html::e($id) ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></a>
    <?php if (count($errori) === 0): ?>
        <a href="/dichiarazione-imu/scarica/dichiarazione/<?= Html::e($id) ?>" class="btn btn-primary"><i class="bi bi-download me-1"></i><?= Html::e($azioni['fileTelematico']) ?></a>
    <?php endif; ?>
</div>

<?php if (count($errori) > 0): ?>
    <div class="alert alert-warning small"><div class="fw-semibold mb-1"><?= Html::e($datiMancanti) ?></div><ul class="mb-0"><?php foreach ($errori as $errore): ?><li><?= Html::e($errore) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<p class="small text-body-secondary"><?= Html::e($aiutoTelematico) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneFrontespizio) ?></span>
        <?php if ($modificabile): ?><a href="/dichiarazione-imu/update/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a><?php endif; ?>
    </div>
    <div class="card-body small row">
        <dl class="row mb-0 col-md-6">
            <dt class="col-sm-5"><?= Html::e($campi['dichiarante']) ?></dt>
            <dd class="col-sm-7"><?= $dichiarazione->dichiarante === null ? Html::e($ilContribuente) : Html::e($dichiarazione->dichiarante->nominativo . ' (' . $dichiarazione->codiceCarica?->getLabel() . ')') ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['intermediario']) ?></dt>
            <dd class="col-sm-7"><?= $dichiarazione->intermediario === null ? '—' : Html::e($dichiarazione->intermediario->soggetto->nominativo . ' · ' . $campi['dataImpegno'] . ' ' . Html::data($dichiarazione->dataImpegno)) ?></dd>
            <dt class="col-sm-5"><?= Html::e($campi['dataDichiarazione']) ?></dt>
            <dd class="col-sm-7"><?= Html::data($dichiarazione->dataDichiarazione) ?></dd>
        </dl>
        <div class="col-md-6"><?= $dichiarazione->annotazioni !== null ? '<div class="fw-semibold">' . Html::e($campi['annotazioni']) . '</div>' . nl2br(Html::e($dichiarazione->annotazioni)) : '' ?></div>
    </div>
</div>

<?php foreach ($quadri as $quadro => $titolo): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><?= Html::e($titolo) ?></span>
            <?php if ($modificabile && ($quadro === 'A')): ?><a href="/rigo-dichiarazione-imu/create/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiImmobile']) ?></a><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead class="table-light"><tr><th>N.</th><th><?= Html::e($campi['immobile']) ?></th><th><?= Html::e($campi['caratteristica']) ?></th><th class="text-end"><?= Html::e($campi['valore']) ?></th><th class="text-end">%</th><th><?= Html::e($campi['vicenda']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($righi as $indice => $rigo): ?>
                        <?php if ($rigo->quadro !== $quadro) continue; ?>
                        <tr>
                            <td class="text-nowrap"><?= Html::e($rigo->numeroOrdine) ?> <span class="text-body-secondary">(<?= Html::e($rigo->progressivoImmobile) ?>)</span></td>
                            <td class="small"><?= Html::e($rigo->immobile->getIdentificativo()) ?><div class="text-body-secondary"><?= Html::e($rigo->immobile->getUbicazione()) ?></div></td>
                            <td class="small"><?= Html::e($rigo->caratteristica->getLabel()) ?>
                                <?php if ($rigo->riduzione !== '0'): ?><div><span class="badge text-bg-light border"><?= Html::e($riduzioni[$rigo->riduzione] ?? '') ?></span></div><?php endif; ?>
                                <?php if ($rigo->esenzione !== '0'): ?><div><span class="badge text-bg-light border"><?= Html::e($dichiarazione->isEnc() && ($quadro === 'B') ? $esente : (RigoDichiarazioneImu::ESENZIONI[$rigo->esenzione] ?? '')) ?></span></div><?php endif; ?>
                                <?php if ($rigo->equiparazione !== null): ?><div><span class="badge text-bg-light border"><?= Html::e(RigoDichiarazioneImu::EQUIPARAZIONI[$rigo->equiparazione]) ?></span></div><?php endif; ?>
                                <?php if (($utilizzi[$indice] ?? null) !== null): ?><div class="text-body-secondary"><?= Html::e(implode(', ', array_map(fn($attivita) => $attivita->getLabel(), $utilizzi[$indice]->getAttivita()))) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($rigo->valore)) ?><?= $rigo->detrazione !== null ? '<div class="small text-body-secondary">' . Html::e($campi['detrazione']) . ' ' . Html::e(Importo::euro($rigo->detrazione)) . '</div>' : '' ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(rtrim(rtrim(number_format($rigo->percentualePossesso, 2, ',', ''), '0'), ',')) ?></td>
                            <td class="small"><?= Html::e($rigo->getVicenda()) ?><?= $rigo->dataVariazione !== null ? ' · ' . Html::data($rigo->dataVariazione) : '' ?><?= $rigo->motivo !== null ? '<div class="text-body-secondary fst-italic">' . Html::e($rigo->motivo) . '</div>' : '' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($modificabile): ?>
                                    <a href="/rigo-dichiarazione-imu/update/rigo/<?= Html::e($rigo->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/rigo-dichiarazione-imu/delete/rigo/<?= Html::e($rigo->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($dichiarazione->isEnc()): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><?= Html::e($quadroC) ?></span>
            <?php if ($modificabile): ?><form method="post" action="/dichiarazione-imu/quadro-c/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i><?= Html::e($azioni['ricalcolaQuadroC']) ?></button></form><?php endif; ?>
        </div>
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach (['impostaDovuta', 'eccedenzaPrecedente', 'eccedenzaCompensata', 'rateVersate'] as $campo): ?>
                    <tr><td class="small"><?= Html::e($campi[$campo]) ?></td><td class="text-end"><?= Html::e(Importo::euro((float) $dichiarazione->$campo)) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-semibold"><td><?= Html::e($saldo >= 0 ? $campi['impostaDebito'] : $campi['impostaCredito']) ?></td><td class="text-end"><?= Html::e(Importo::euro(abs($saldo))) ?></td></tr>
                <?php if ($saldo < 0): ?>
                    <tr><td class="small"><?= Html::e($campi['creditoRimborso']) ?></td><td class="text-end"><?= Html::e(Importo::euro((float) $dichiarazione->creditoRimborso)) ?></td></tr>
                    <tr><td class="small"><?= Html::e($campi['creditoCompensazione']) ?></td><td class="text-end"><?= Html::e(Importo::euro((float) $dichiarazione->creditoCompensazione)) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <?php if ($modificabile): ?>
            <form method="post" action="/dichiarazione-imu/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazione::presentata->value) ?>" class="d-flex flex-wrap align-items-end gap-2">
                <?= $campoCsrf ?>
                <div><label class="form-label small mb-0" for="dataPresentazione"><?= Html::e($campi['dataPresentazione']) ?></label><input type="date" class="form-control form-control-sm" id="dataPresentazione" name="dataPresentazione" value="<?= Html::e(date('Y-m-d')) ?>"></div>
                <div><label class="form-label small mb-0" for="protocollo"><?= Html::e($campi['protocollo']) ?></label><input type="text" class="form-control form-control-sm" id="protocollo" name="protocollo" maxlength="50"></div>
                <button type="submit" class="btn btn-sm btn-success" <?= count($errori) > 0 ? 'disabled' : '' ?>><i class="bi bi-check2-circle me-1"></i><?= Html::e($azioni['segnaPresentata']) ?></button>
            </form>
            <form method="post" action="/dichiarazione-imu/delete/dichiarazione/<?= Html::e($id) ?>" class="ms-auto" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i><?= Html::e($azioni['elimina']) ?></button></form>
        <?php else: ?>
            <form method="post" action="/dichiarazione-imu/sostitutiva/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-files me-1"></i><?= Html::e($azioni['sostitutiva']) ?></button></form>
            <form method="post" action="/dichiarazione-imu/stato/dichiarazione/<?= Html::e($id) ?>/stato/<?= Html::e(StatoDichiarazione::bozza->value) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['riportaBozza']) ?></button></form>
        <?php endif; ?>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
