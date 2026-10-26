<?php

use Anagrafica\Application\Enumerations\TipoCatasto;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$id = $immobile->id;
$fabbricato = $immobile->tipoCatasto === TipoCatasto::fabbricati;
$euro = fn(?float $importo): string => $importo === null ? '' : '€ ' . number_format($importo, 2, ',', '.');
$numero = fn(?float $valore): string => $valore === null ? '' : rtrim(rtrim(number_format($valore, 2, ',', '.'), '0'), ',');
$periodo = fn($versione): string => Html::data($versione->validoDal) . ' → ' . ($versione->validoAl === null ? '…' : Html::data($versione->validoAl));
$classeRiga = fn($versione): string => $versione->isVigenteAl($dataRiferimento) ? 'table-success' : 'storico-non-vigente';
$badgeVigente = fn($versione): string => $versione->isVigenteAl($dataRiferimento) ? '<span class="badge text-bg-success">' . Html::e($vigente) . '</span>' : '';
$pulsanteModifica = fn(string $url): string => '<a href="' . Html::e($url) . '" class="btn btn-sm btn-outline-secondary" title="' . Html::e($azioni['rettifica']) . '"><i class="bi bi-pencil"></i></a>';
$pulsanteVariazione = fn(string $url): string => '<a href="' . Html::e($url) . '" class="btn btn-sm btn-outline-primary" title="' . Html::e($azioni['variazione']) . '"><i class="bi bi-plus-lg"></i></a>';
$pulsanteElimina = function (string $url) use ($csrfToken, $confermaElimina, $azioni): string {
    return '<form method="post" action="' . Html::e($url) . '" class="d-inline" data-conferma="' . Html::e($confermaElimina) . '">'
            . '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger" title="' . Html::e($azioni['elimina']) . '"><i class="bi bi-trash"></i></button></form>';
};

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><i class="bi <?= Html::e($immobile->tipoCatasto->getIcona()) ?> me-2 text-primary"></i><?= Html::e($immobile->tipoCatasto->getLabel()) ?> <?= Html::e($immobile->getIdentificativo()) ?></h1>
        <div class="text-body-secondary"><?= Html::e($immobile->getUbicazione()) ?><?= $immobile->descrizione !== null ? ' · ' . Html::e($immobile->descrizione) : '' ?></div>
        <?php if ($immobile->partitaTavolare !== null): ?>
            <div class="small text-body-secondary"><?= Html::e($campi['partitaTavolare']) ?> <?= Html::e($immobile->partitaTavolare) ?><?= $immobile->corpoTavolare !== null ? ' · ' . Html::e($campi['corpoTavolare']) . ' ' . Html::e($immobile->corpoTavolare) : '' ?><?= $immobile->comuneCatastale !== null ? ' · ' . Html::e($campi['comuneCatastale']) . ' ' . Html::e($immobile->comuneCatastale) : '' ?></div>
        <?php endif; ?>
    </div>
    <form method="get" action="/immobile/show/immobile/<?= Html::e($id) ?>" class="d-flex align-items-center gap-2">
        <label for="al" class="small text-body-secondary text-nowrap"><?= Html::e($situazioneAl) ?></label>
        <input type="date" class="form-control form-control-sm" id="al" name="al" value="<?= Html::e($dataRiferimento->format('Y-m-d')) ?>" onchange="this.form.submit()">
        <?php if ($isOggi === false): ?>
            <a class="btn btn-sm btn-outline-secondary" href="/immobile/show/immobile/<?= Html::e($id) ?>"><?= Html::e($oggi) ?></a>
        <?php endif; ?>
    </form>
    <div class="d-flex gap-2">
        <a href="/immobile/update/immobile/<?= Html::e($id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
        <form method="post" action="/immobile/delete/immobile/<?= Html::e($id) ?>" data-conferma="<?= Html::e($confermaEliminaImmobile) ?>">
            <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<?php if ($isOggi === false): ?>
    <div class="alert alert-info py-2"><i class="bi bi-clock-history me-2"></i><?= Html::e($storicoAvviso) ?> <strong><?= Html::data($dataRiferimento) ?></strong>.</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneDatiCatastali) ?></span>
        <a href="/dati-catastali/create/immobile/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['variazione']) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-anagrafica mb-0">
            <thead>
                <tr>
                    <th><?= Html::e($campi['validoDal']) ?></th>
                    <th><?= Html::e($campi['causale']) ?></th>
                    <?php if ($fabbricato): ?>
                        <th><?= Html::e($campi['categoria']) ?></th><th><?= Html::e($campi['classe']) ?></th><th><?= Html::e($campi['consistenza']) ?></th><th><?= Html::e($campi['superficieCatastale']) ?></th><th class="text-end"><?= Html::e($campi['rendita']) ?></th>
                    <?php else: ?>
                        <th><?= Html::e($campi['qualitaColtura']) ?></th><th><?= Html::e($campi['classe']) ?></th><th><?= Html::e($campi['superficieTerreno']) ?></th><th class="text-end"><?= Html::e($campi['redditoDominicale']) ?></th><th class="text-end"><?= Html::e($campi['redditoAgrario']) ?></th>
                    <?php endif; ?>
                    <th></th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datiCatastaliStorico as $versione): ?>
                    <tr class="<?= $classeRiga($versione) ?>">
                        <td class="text-nowrap"><?= $periodo($versione) ?></td>
                        <td title="<?= Html::e($versione->causale->getDescrizione()) ?>"><?= Html::e($versione->causale->getLabel()) ?></td>
                        <?php if ($fabbricato): ?>
                            <td title="<?= Html::e($versione->categoria?->getDescrizione() ?? '') ?>"><?= Html::e($versione->categoria?->value ?? '') ?></td>
                            <td><?= Html::e($versione->classe ?? '') ?></td>
                            <td><?= Html::e($numero($versione->consistenza)) ?></td>
                            <td><?= Html::e($numero($versione->superficie)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e($euro($versione->rendita)) ?></td>
                        <?php else: ?>
                            <td><?= Html::e($versione->qualitaColtura ?? '') ?></td>
                            <td><?= Html::e($versione->classe ?? '') ?></td>
                            <td><?= Html::e($numero($versione->superficie)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e($euro($versione->redditoDominicale)) ?></td>
                            <td class="text-end text-nowrap"><?= Html::e($euro($versione->redditoAgrario)) ?></td>
                        <?php endif; ?>
                        <td><?= $badgeVigente($versione) ?></td>
                        <td class="text-end text-nowrap"><?= $pulsanteModifica('/dati-catastali/update/dati-catastali/' . $versione->id) ?> <?= $pulsanteElimina('/dati-catastali/delete/dati-catastali/' . $versione->id) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneGraffati) ?></span>
        <a href="/identificativo-graffato/create/immobile/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
    </div>
    <?php if (count($graffati) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunGraffato) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($graffati as $graffato): ?>
                <li class="list-group-item d-flex align-items-center">
                    <span class="me-auto"><?= Html::e($graffato->getIdentificativo()) ?></span>
                    <?= $pulsanteModifica('/identificativo-graffato/update/identificativo-graffato/' . $graffato->id) ?>&nbsp;<?= $pulsanteElimina('/identificativo-graffato/delete/identificativo-graffato/' . $graffato->id) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezionePossessori) ?></span>
        <a href="/possesso/create/immobile/<?= Html::e($id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
    </div>
    <?php if (count($possessi) === 0): ?>
        <div class="card-body text-body-secondary"><?= Html::e($nessunDato) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead><tr><th><?= Html::e($campi['soggetto']) ?></th><th><?= Html::e($campi['titolo']) ?></th><th class="text-end"><?= Html::e($campi['quota']) ?></th><th><?= Html::e($campi['utilizzo']) ?></th><th><?= Html::e($campi['possessoDal']) ?></th><th></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($possessi as $possesso): ?>
                        <tr class="<?= $classeRiga($possesso) ?>">
                            <td><a href="/soggetto/show/soggetto/<?= Html::e($possesso->soggetto->id) ?>/scheda/immobili"><?= Html::e($possesso->soggetto->nominativo) ?></a> <span class="small text-body-secondary font-monospace"><?= Html::e($possesso->soggetto->codiceFiscale) ?></span></td>
                            <td><?= Html::e($possesso->titolo->getLabel()) ?></td>
                            <td class="text-end"><?= Html::e(number_format($possesso->quota, 2, ',', '.')) ?>%</td>
                            <td><?= Html::e($possesso->utilizzo?->getLabel() ?? '') ?></td>
                            <td class="text-nowrap"><?= $periodo($possesso) ?></td>
                            <td><?= $badgeVigente($possesso) ?></td>
                            <td class="text-end text-nowrap"><?= $possesso->isAperto() ? $pulsanteVariazione('/possesso/variazione/possesso/' . $possesso->id . '/ritorno/immobile') : '' ?> <?= $pulsanteModifica('/possesso/update/possesso/' . $possesso->id . '/ritorno/immobile') ?> <?= $pulsanteElimina('/possesso/delete/possesso/' . $possesso->id . '/ritorno/immobile') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php if ($immobile->note !== null): ?>
    <p class="small text-body-secondary"><?= Html::e($campi['note']) ?>: <?= nl2br(Html::e($immobile->note)) ?></p>
<?php endif; ?>
<?php require Layout::percorso('footer'); ?>
