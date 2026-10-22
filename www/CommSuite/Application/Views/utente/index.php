<?php

use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\HelperClasses\Html;
use CommSuite\Application\HelperClasses\Layout;

$sezioneStudio = 'utenti';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navStudio.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto"><i class="bi bi-people me-2"></i><?= Html::e($pageTitle) ?></h1>
    <a href="/utente/create" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i><?= Html::e($azioni['nuovoUtente']) ?></a>
</div>
<p class="small text-body-secondary mb-3"><?= Html::e($aiuto) ?></p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-anagrafica mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= Html::e($campi['nominativo']) ?></th>
                    <th><?= Html::e($campi['username']) ?></th>
                    <th><?= Html::e($campi['email']) ?></th>
                    <th><?= Html::e($campi['ruolo']) ?></th>
                    <th><?= Html::e($campi['stato']) ?></th>
                    <th><?= Html::e($campi['ultimoAccesso']) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utenti as $voce): ?>
                    <tr>
                        <td>
                            <a href="/utente/update/utente/<?= Html::e($voce->id) ?>" class="fw-semibold text-decoration-none"><?= Html::e($voce->getNomeCompleto()) ?></a>
                            <?php if ($voce->id === $utente->id): ?>
                                <span class="badge text-bg-light border ms-1"><?= Html::e($etichettaTu) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="font-monospace small"><?= Html::e($voce->username) ?></td>
                        <td class="small"><?= Html::e($voce->email) ?></td>
                        <td>
                            <?php if ($voce->ruolo === RuoloUtente::amministratore): ?>
                                <span class="badge text-bg-primary-subtle text-primary-emphasis"><?= Html::e($voce->ruolo->getLabel()) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary-subtle text-secondary-emphasis"><?= Html::e($voce->ruolo->getLabel()) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($voce->attivo): ?>
                                <span class="badge text-bg-success-subtle text-success-emphasis"><?= Html::e($statoAttivo) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary"><?= Html::e($statoDisattivato) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-body-secondary"><?= $voce->ultimoAccesso !== null ? Html::e(Html::data($voce->ultimoAccesso, 'd/m/Y H:i')) : '—' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="/utente/update/utente/<?= Html::e($voce->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                            <a href="/utente/password/utente/<?= Html::e($voce->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['reimpostaPassword']) ?>"><i class="bi bi-key"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
