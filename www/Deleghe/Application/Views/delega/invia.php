<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Services\EsitoControllo;

$id = $delega->id;
$classeErrore = fn(string $campo): string => isset($erroriInvio[$campo]) ? 'is-invalid' : '';
$errore = fn(string $campo): string => isset($erroriInvio[$campo]) ? '<div class="invalid-feedback d-block">' . Html::e($erroriInvio[$campo]) . '</div>' : '';

require Layout::percorso('header');
?>
<h1 class="h3 mb-1"><i class="bi bi-send me-2 text-primary"></i><?= Html::e($pageTitle) ?></h1>
<p class="text-body-secondary mb-3">
    <?= Html::e($delega->soggetto->nominativo) ?> · <?= Html::e($delega->modello->getLabel()) ?> · <?= Html::e($campi['scadenza']) ?> <?= Html::data($delega->scadenza) ?> · € <?= EsitoControllo::euro($esito->getSaldo()) ?>
</p>

<?php if (isset($erroriInvio['invio'])): ?>
    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i><?= Html::e($erroriInvio['invio']) ?></div>
<?php endif; ?>
<?php if ($delega->stato === StatoDelega::consegnata): ?>
    <div class="alert alert-warning small"><i class="bi bi-info-circle me-1"></i><?= Html::e(sprintf($avvisoGiaConsegnata, Html::data($delega->dataConsegna), $delega->canaleConsegna?->getLabel() ?? '', $delega->destinatarioConsegna ?? '')) ?></div>
<?php endif; ?>

<?php if (count($caselle) === 0): ?>
    <div class="alert alert-warning">
        <?= Html::e($nessunaCasella) ?>
        <?php if ($utente->isAmministratore()): ?><a href="/casella-posta/index" class="alert-link"><?= Html::e($configuraCaselle) ?></a><?php endif; ?>
    </div>
    <a href="/delega/show/delega/<?= Html::e($id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
<?php else: ?>
    <form method="post" action="/delega/invia/delega/<?= Html::e($id) ?>" novalidate>
        <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
        <div class="card shadow-sm">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?= Html::e($campi['tipoCasella']) ?> *</label>
                    <div>
                        <?php foreach (TipoCasellaPosta::cases() as $tipo): ?>
                            <?php $disponibile = in_array($tipo, $caselle, true); ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input <?= $classeErrore('tipoCasella') ?>" type="radio" name="tipoCasella" id="tipo-<?= Html::e($tipo->value) ?>" value="<?= Html::e($tipo->value) ?>"
                                       data-proponi="destinatario" data-valore="<?= Html::e($indirizzi[$tipo->value][0] ?? '') ?>" <?= $dati['tipoCasella'] === $tipo ? 'checked' : '' ?> <?= $disponibile ? '' : 'disabled' ?>>
                                <label class="form-check-label" for="tipo-<?= Html::e($tipo->value) ?>" <?= $disponibile ? '' : 'title="' . Html::e($casellaNonConfigurata) . '"' ?>><i class="bi <?= Html::e($tipo->getIcona()) ?> me-1"></i><?= Html::e($tipo->getLabel()) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?= $errore('tipoCasella') ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="destinatario"><?= Html::e($campi['destinatario']) ?> *</label>
                    <input type="email" class="form-control <?= $classeErrore('destinatario') ?>" id="destinatario" name="destinatario" value="<?= Html::e($dati['destinatario']) ?>" maxlength="255" list="indirizzi-cliente" required autocomplete="off">
                    <datalist id="indirizzi-cliente">
                        <?php foreach (TipoCasellaPosta::cases() as $tipo): ?>
                            <?php foreach ($indirizzi[$tipo->value] as $indirizzo): ?><option value="<?= Html::e($indirizzo) ?>"><?= Html::e($tipo->getLabel()) ?></option><?php endforeach; ?>
                        <?php endforeach; ?>
                    </datalist>
                    <?= $errore('destinatario') ?>
                    <div class="form-text"><?= Html::e(count($indirizzi[TipoCasellaPosta::email->value]) + count($indirizzi[TipoCasellaPosta::pec->value]) > 0 ? $aiutoDestinatario : $aiutoSenzaRecapiti) ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="oggetto"><?= Html::e($campi['oggetto']) ?> *</label>
                    <input type="text" class="form-control <?= $classeErrore('oggetto') ?>" id="oggetto" name="oggetto" value="<?= Html::e($dati['oggetto']) ?>" maxlength="255" required>
                    <?= $errore('oggetto') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="testo"><?= Html::e($campi['testo']) ?> *</label>
                    <textarea class="form-control <?= $classeErrore('testo') ?>" id="testo" name="testo" rows="10" required><?= Html::e($dati['testo']) ?></textarea>
                    <?= $errore('testo') ?>
                </div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-3 small">
                    <span><i class="bi bi-paperclip me-1"></i><a href="/delega/pdf/delega/<?= Html::e($id) ?><?= $dati['copie'] ? '/copie/1' : '' ?>"><?= Html::e($nomeFile) ?></a></span>
                    <?php if ($delega->modello !== ModelloF24::semplificato): ?>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="copie" value="1" id="copie" <?= $dati['copie'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="copie"><?= Html::e($tutteLeCopie) ?></label>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="copie" value="1">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= Html::e($azioni['invia']) ?></button>
            <a href="/delega/show/delega/<?= Html::e($id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
            <span class="ms-auto small text-body-secondary"><?= Html::e($aiutoInvio) ?></span>
        </div>
    </form>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
