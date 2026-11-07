<?php

use Telonio\Application\Enumerations\TipoCasellaPosta;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$sezioneStudio = 'posta';
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navStudio.php';
?>
<h1 class="h3 mb-2"><i class="bi bi-send me-2"></i><?= Html::e($pageTitle) ?></h1>
<p class="small text-body-secondary mb-3"><?= Html::e($aiuto) ?></p>

<div class="row g-3">
    <?php foreach (TipoCasellaPosta::cases() as $tipo): ?>
        <?php $casella = $caselle[$tipo->value]; ?>
        <?php $ambiente = ($casella === null) && ($tipo === TipoCasellaPosta::email) ? $casellaAmbiente : null; ?>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi <?= Html::e($tipo->getIcona()) ?> me-1"></i><?= Html::e($tipo->getLabel()) ?></div>
                <div class="card-body">
                    <?php if ($casella !== null): ?>
                        <dl class="row small mb-0">
                            <dt class="col-sm-4"><?= Html::e($campi['indirizzoMittente']) ?></dt><dd class="col-sm-8"><?= Html::e($casella->getMittente()) ?> &lt;<?= Html::e($casella->indirizzo) ?>&gt;</dd>
                            <dt class="col-sm-4"><?= Html::e($campi['hostSmtp']) ?></dt><dd class="col-sm-8 font-monospace"><?= Html::e($casella->host) ?>:<?= Html::e($casella->porta) ?> · <?= Html::e($casella->cifratura->getLabel()) ?></dd>
                            <dt class="col-sm-4"><?= Html::e($campi['utenteSmtp']) ?></dt><dd class="col-sm-8"><?= Html::e($casella->utente ?? $senzaAutenticazione) ?></dd>
                        </dl>
                    <?php elseif ($ambiente !== null): ?>
                        <p class="small mb-1"><?= Html::e($usaAmbiente) ?></p>
                        <p class="small font-monospace mb-0"><?= Html::e($ambiente->indirizzo) ?> · <?= Html::e($ambiente->host) ?>:<?= Html::e($ambiente->porta) ?></p>
                    <?php else: ?>
                        <p class="small text-body-secondary mb-0"><?= Html::e($tipo === TipoCasellaPosta::pec ? $pecNonConfigurata : $nonConfigurata) ?></p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <a href="/casella-posta/update/tipo/<?= Html::e($tipo->value) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($casella !== null ? $azioni['modifica'] : $azioni['configura']) ?></a>
                    <?php if (($casella !== null) || ($ambiente !== null)): ?>
                        <form method="post" action="/casella-posta/prova/tipo/<?= Html::e($tipo->value) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= Html::e(sprintf($aiutoProva, $utente->email)) ?>"><i class="bi bi-send-check me-1"></i><?= Html::e($azioni['inviaProva']) ?></button></form>
                    <?php endif; ?>
                    <?php if ($casella !== null): ?>
                        <form method="post" action="/casella-posta/delete/tipo/<?= Html::e($tipo->value) ?>" class="ms-auto" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require Layout::percorso('footer'); ?>
