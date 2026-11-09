<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$urlAnno = '/contribuente-dichiarativi/show/soggetto/' . $soggetto->id . '/anno/';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto"><?= Html::e($soggetto->nominativo) ?></h1>
    <a href="/soggetto/show/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><i class="bi bi-person-vcard me-1"></i><?= Html::e($schedaAnagrafica) ?></a>
    <?php require __DIR__ . '/../commonParts/selettoreAnno.php'; ?>
</div>
<p class="text-body-secondary"><?= Html::e($soggetto->codiceFiscale) ?> · <?= Html::e($campi['annoImposta']) ?> <?= Html::e($anno) ?></p>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneDichiarazioni) ?></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr><th><?= Html::e($campi['modello']) ?></th><th><?= Html::e($campi['stato']) ?></th><th><?= Html::e($campi['scadenza']) ?></th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($modelli as ['modello' => $modello, 'trimestre' => $trimestre, 'motivo' => $motivo, 'dichiarazione' => $dichiarazione, 'catalogo' => $catalogo]): ?>
                    <tr>
                        <td class="fw-semibold"><?= Html::e($trimestre === null ? $modello->getSigla() . ' ' . ($anno + 1) : $modello->getSigla() . ' ' . $trimestre . '° ' . $campi['trimestre'] . ' ' . $anno) ?><div class="small text-body-secondary fw-normal"><?= Html::e($modello->getLabel()) ?></div></td>
                        <td class="small">
                            <?php if ($dichiarazione !== null): ?>
                                <span class="badge <?= Html::e($dichiarazione->stato->getClasseBadge()) ?>"><?= Html::e($dichiarazione->stato->getLabel()) ?></span>
                                <?= Html::e($dichiarazione->tipologia->getLabel()) ?>
                                <?php if ($dichiarazione->dataPresentazione !== null): ?> · <?= Html::data($dichiarazione->dataPresentazione) ?><?php endif; ?>
                            <?php elseif ($motivo !== null): ?>
                                <span class="text-body-secondary"><?= Html::e($nonDovuta) ?>: <?= Html::e($motivo) ?></span>
                            <?php elseif ($modello->isGestito() === false): ?>
                                <span class="text-body-secondary"><?= Html::e($modelloInArrivo) ?></span>
                            <?php else: ?>
                                <span class="text-body-secondary"><?= Html::e($daPredisporre) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= Html::data($modello->getScadenza($anno, $trimestre)) ?></td>
                        <td class="text-end">
                            <?php if ($dichiarazione !== null): ?>
                                <a href="/dichiarazione/show/dichiarazione/<?= Html::e($dichiarazione->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['apri']) ?> <i class="bi bi-chevron-right"></i></a>
                            <?php elseif (($motivo === null) && $modello->isGestito() && $catalogo): ?>
                                <form method="post" action="/dichiarazione/crea/soggetto/<?= Html::e($soggetto->id) ?>/modello/<?= Html::e($modello->value) ?>/anno/<?= Html::e($anno) ?><?= $trimestre === null ? '' : '/trimestre/' . Html::e($trimestre) ?>"><?= $campoCsrf ?>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-magic me-1"></i><?= Html::e($azioni['proponi']) ?></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoDichiarazioni) ?></div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= Html::e($sezioneVersamenti) ?></div>
    <?php if (count($versamenti) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunVersamento) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush small">
            <?php foreach ($versamenti as $versamento): ?>
                <li class="list-group-item d-flex gap-2">
                    <span class="me-auto"><?= Html::data($versamento->scadenza) ?> · <?= Html::e($versamento->codiceTributo?->codice ?? '') ?> · <?= Html::e($versamento->descrizione ?? '') ?></span>
                    <span class="text-nowrap"><?= Html::e(Importo::euro($versamento->importoDebito)) ?></span>
                    <span class="text-body-secondary"><?= Html::e($versamento->delega === null ? $daVersare : $versamento->delega->stato->getLabel()) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($soggetto->isPersonaFisica()): ?>
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold d-flex align-items-center">
            <span class="me-auto"><?= Html::e($sezioneCertificazioni) ?></span>
            <a href="/certificazione-unica/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
        </div>
        <?php if (count($certificazioni) === 0): ?>
            <div class="card-body small text-body-secondary"><?= Html::e($nessunaCertificazione) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0 align-middle small">
                    <thead class="table-light">
                        <tr><th><?= Html::e($campi['denominazioneSostituto']) ?></th><th><?= Html::e($campi['codiceFiscaleSostituto']) ?></th><th class="text-end"><?= Html::e($redditiCertificati) ?></th><th class="text-end"><?= Html::e($ritenuteCertificate) ?></th><th></th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificazioni as ['certificazione' => $certificazione, 'valori' => $valoriCu]): ?>
                            <tr>
                                <td><?= Html::e($certificazione->denominazioneSostituto) ?></td>
                                <td><?= Html::e($certificazione->codiceFiscaleSostituto) ?></td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro(($valoriCu[1] ?? 0) + ($valoriCu[2] ?? 0) + ($valoriCu[3] ?? 0) + ($valoriCu[4] ?? 0) + ($valoriCu[5] ?? 0))) ?></td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro($valoriCu[21] ?? 0)) ?></td>
                                <td><?php if ($certificazione->conguagliata): ?><span class="badge text-bg-secondary"><?= Html::e($campi['conguagliata']) ?></span><?php endif; ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="/certificazione-unica/update/certificazione-unica/<?= Html::e($certificazione->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/certificazione-unica/delete/certificazione-unica/<?= Html::e($certificazione->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoCertificazioni) ?></div>
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center">
        <span class="me-auto"><?= Html::e($sezioneAccertamenti) ?></span>
        <a href="/atto-accertamento/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
    </div>
    <?php if (count($atti) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunAtto) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0 align-middle small">
                <thead class="table-light">
                    <tr><th><?= Html::e($campi['dataNotifica']) ?></th><th><?= Html::e($campi['annoImposta']) ?></th><th><?= Html::e($campi['estremiAtto']) ?></th><th class="text-end"><?= Html::e($campi['importoDichiarato']) ?></th><th class="text-end"><?= Html::e($campi['importoAccertato']) ?></th><th></th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($atti as $atto): ?>
                        <tr>
                            <td><?= Html::data($atto->dataNotifica) ?></td>
                            <td><?= Html::e($atto->annoImposta) ?></td>
                            <td><?= Html::e($atto->estremi ?? '') ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($atto->importoDichiarato)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($atto->importoAccertato)) ?></td>
                            <td><?php if ($atto->isOltreSoglia()): ?><span class="badge text-bg-warning"><?= Html::e($oltreSoglia) ?></span><?php endif; ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/atto-accertamento/update/atto/<?= Html::e($atto->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="/atto-accertamento/delete/atto/<?= Html::e($atto->id) ?>/anno/<?= Html::e($anno) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoAccertamenti) ?></div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center">
        <span class="me-auto"><?= Html::e($sezioneGaranzie) ?></span>
        <a href="/garanzia-rimborso/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
    </div>
    <?php if (count($garanzie) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaGaranzia) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th><?= Html::e($campi['annoImposta']) ?></th><th><?= Html::e($campi['tipoGaranzia']) ?></th><th><?= Html::e($campi['garante']) ?></th><th class="text-end"><?= Html::e($campi['importoGarantito']) ?></th><th><?= Html::e($campi['dataScadenzaGaranzia']) ?></th><th></th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($garanzie as $garanzia): ?>
                        <tr>
                            <td><?= Html::e($garanzia->annoImposta) ?></td>
                            <td class="small"><?= Html::e($garanzia->tipo->getLabel()) ?></td>
                            <td><?= Html::e($garanzia->garante ?? '') ?></td>
                            <td class="text-end text-nowrap"><?= Html::e(Importo::euro($garanzia->importo)) ?></td>
                            <td><?= $garanzia->dataScadenza === null ? '' : Html::data($garanzia->dataScadenza) ?></td>
                            <td><?php if ($garanzia->isSvincolata()): ?><span class="badge text-bg-light border"><?= Html::e($svincolata) ?></span><?php endif; ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/garanzia-rimborso/update/garanzia/<?= Html::e($garanzia->id) ?>/anno/<?= Html::e($anno) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="/garanzia-rimborso/delete/garanzia/<?= Html::e($garanzia->id) ?>/anno/<?= Html::e($anno) ?>" class="d-inline" data-conferma="<?= Html::e($confermaEliminaGaranzia) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoGaranzie) ?></div>
</div>
<?php require Layout::percorso('footer'); ?>
