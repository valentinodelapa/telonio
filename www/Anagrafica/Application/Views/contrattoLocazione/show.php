<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$id = $contratto->id;
$euro = fn(?float $importo): string => $importo === null ? '' : '€ ' . number_format($importo, 2, ',', '.');
$si = fn(bool $valore): string => Html::e($valore ? $testoSi : $testoNo);
$pulsanteElimina = function (string $url) use ($csrfToken, $confermaElimina, $azioni): string {
    return '<form method="post" action="' . Html::e($url) . '" class="d-inline" data-conferma="' . Html::e($confermaElimina) . '">'
            . '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">'
            . '<button type="submit" class="btn btn-sm btn-outline-danger" title="' . Html::e($azioni['elimina']) . '"><i class="bi bi-trash"></i></button></form>';
};
$registrazione = $contratto->codiceIdentificativo ?? trim(implode(' ', array_filter([
    $contratto->dataRegistrazione !== null ? Html::data($contratto->dataRegistrazione) : null,
    $contratto->serieRegistrazione !== null ? $campi['serieRegistrazione'] . ' ' . $contratto->serieRegistrazione : null,
    $contratto->numeroRegistrazione !== null ? 'n. ' . $contratto->numeroRegistrazione : null,
    $contratto->codiceUfficio !== null ? $campi['codiceUfficio'] . ' ' . $contratto->codiceUfficio : null,
])));

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1"><?= Html::e($contratto->tipo->getLabel()) ?></h1>
        <div class="text-body-secondary"><?= Html::e($immobile->tipoCatasto->getLabel() . ' ' . $immobile->getIdentificativo() . ' - ' . $immobile->getUbicazione()) ?></div>
    </div>
    <a href="/contratto-locazione/update/contratto-locazione/<?= Html::e($id) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
    <?= $pulsanteElimina('/contratto-locazione/delete/contratto-locazione/' . $id) ?>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <dl class="row mb-0 small">
            <dt class="col-sm-3"><?= Html::e($campi['dataInizioContratto']) ?></dt><dd class="col-sm-3"><?= Html::data($contratto->dataInizio) ?></dd>
            <dt class="col-sm-3"><?= Html::e($campi['dataFineContratto']) ?></dt><dd class="col-sm-3"><?= $contratto->dataFine !== null ? Html::data($contratto->dataFine) : '…' ?></dd>
            <dt class="col-sm-3"><?= Html::e($campi['canoneAnnuo']) ?></dt><dd class="col-sm-3"><?= $euro($contratto->canoneAnnuo) ?></dd>
            <dt class="col-sm-3"><?= Html::e($campi['conduttore']) ?></dt><dd class="col-sm-3"><?= Html::e(trim(($contratto->conduttore ?? '') . ' ' . ($contratto->codiceFiscaleConduttore ?? ''))) ?></dd>
            <?php if ($contratto->tipo->ammetteCedolare()): ?>
                <dt class="col-sm-3"><?= Html::e($campi['cedolareSecca']) ?></dt><dd class="col-sm-3"><?= $contratto->cedolareSecca ? Html::e($contratto->getAliquotaCedolare() . '%') . ($contratto->cedolareRevocataDal !== null ? ' · ' . Html::e($campi['cedolareRevocataDal']) . ' ' . Html::data($contratto->cedolareRevocataDal) : '') : $si(false) ?></dd>
                <dt class="col-sm-3"><?= Html::e($campi['cin']) ?></dt><dd class="col-sm-3"><?= Html::e($contratto->cin ?? '') ?></dd>
            <?php endif; ?>
            <dt class="col-sm-3"><?= Html::e($sezioneRegistrazione) ?></dt><dd class="col-sm-9"><?= Html::e($registrazione) ?></dd>
            <?php if ($contratto->note !== null): ?>
                <dt class="col-sm-3"><?= Html::e($campi['note']) ?></dt><dd class="col-sm-9"><?= Html::e($contratto->note) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if ($contratto->tipo->isTerreno() === false): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><?= Html::e($sezionePertinenze) ?></span>
            <a href="/contratto-locazione/pertinenza/contratto-locazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
        </div>
        <?php if (count($pertinenze) === 0): ?>
            <div class="card-body small text-body-secondary"><?= Html::e($nessunaPertinenza) ?></div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($pertinenze as $pertinenza): ?>
                    <li class="list-group-item d-flex align-items-center">
                        <a class="me-auto" href="/immobile/show/immobile/<?= Html::e($pertinenza->immobile->id) ?>"><?= Html::e($pertinenza->immobile->getIdentificativo() . ' - ' . $pertinenza->immobile->getUbicazione()) ?></a>
                        <?= $pulsanteElimina('/contratto-locazione/elimina-pertinenza/pertinenza-locata/' . $pertinenza->id) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezioneVariazioni) ?></span>
        <a href="/contratto-locazione/variazione/contratto-locazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
    </div>
    <?php if (count($variazioni) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaVariazione) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th><?= Html::e($campi['validoDal']) ?></th><th class="text-end"><?= Html::e($campi['canoneAnnuo']) ?></th><th><?= Html::e($campi['rinegoziazione']) ?></th><th><?= Html::e($campi['note']) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($variazioni as $variazione): ?>
                        <tr>
                            <td><?= Html::data($variazione->validoDal) ?></td>
                            <td class="text-end"><?= $euro($variazione->canoneAnnuo) ?></td>
                            <td><?= $si($variazione->rinegoziazione) ?></td>
                            <td><?= Html::e($variazione->note ?? '') ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/contratto-locazione/modifica-variazione/variazione-canone/<?= Html::e($variazione->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                <?= $pulsanteElimina('/contratto-locazione/elimina-variazione/variazione-canone/' . $variazione->id) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require Layout::percorso('footer'); ?>
