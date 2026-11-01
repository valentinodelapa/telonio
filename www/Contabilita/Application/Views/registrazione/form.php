<?php

use Contabilita\Application\Enumerations\TipoRegistroIva;
use Telonio\Application\HelperClasses\Campo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$causale = $registrazione->causale;
$iva = $causale->isIva();
$acquisti = $causale->getTipoRegistroIva() === TipoRegistroIva::acquisti;
$anno = $registrazione->esercizioContabile->anno;
$urlAnnulla = isset($registrazione->id) ? '/registrazione/show/registrazione/' . $registrazione->id : ($fattura === null ? '/registrazione/index/soggetto/' . $soggetto->id . '/anno/' . $anno : '/fattura-elettronica/show/fattura/' . $fattura->id);
$urlConti = '/conto/cerca/soggetto/' . $soggetto->id;
$urlControparti = '/controparte/cerca/soggetto/' . $soggetto->id;
$sceltaRegistri = [];
foreach ($registri as $registro) {
    $sceltaRegistri[$registro->id] = $registro->getEtichetta();
}
$autocompleta = function (string $nome, string $url, ?string $valore, ?string $etichetta, string $segnaposto = ''): string {
    return sprintf('<div class="autocompleta" data-url="%s"><input type="text" class="form-control form-control-sm autocompleta-testo" value="%s" placeholder="%s" autocomplete="off"><input type="hidden" class="autocompleta-valore" name="%s" value="%s"></div>',
            Html::e($url), Html::e($etichetta ?? ''), Html::e($segnaposto), Html::e($nome), Html::e($valore ?? ''));
};

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0 me-auto"><?= Html::e($pageTitle) ?></h1>
    <span class="badge text-bg-primary fs-6"><?= Html::e($causale->getLabel()) ?></span>
</div>
<p class="text-body-secondary"><?= Html::e($soggetto->nominativo) ?> · <?= Html::e($campi['esercizio']) ?> <?= Html::e($registrazione->esercizioContabile->getEtichetta()) ?><?= isset($registrazione->id) ? ' · ' . Html::e($campi['numero']) . ' ' . Html::e($registrazione->numero) : '' ?></p>

<?php if ($fattura !== null): ?>
    <div class="alert alert-info small py-2">
        <i class="bi bi-file-earmark-code me-1"></i><?= Html::e($proposta) ?>: <?= Html::e($fattura->tipoDocumento->getLabel()) ?> n. <?= Html::e($fattura->numero) ?> del <?= Html::data($fattura->data) ?> - <?= Html::e($fattura->denominazioneControparte) ?>
        <?php foreach ($avvisiProposta as $avviso): ?><div class="mt-1"><i class="bi bi-exclamation-triangle me-1"></i><?= Html::e($avviso) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php foreach ($erroriRighe as $errore): ?>
    <div class="alert alert-danger small py-2"><?= Html::e($errore) ?></div>
<?php endforeach; ?>
<?php if ($messaggioProposta !== null): ?><div class="alert alert-success small py-2"><?= Html::e($messaggioProposta) ?></div><?php endif; ?>

<form method="post" novalidate id="form-registrazione">
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <?= Campo::data('dataRegistrazione', $campi['dataRegistrazione'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
            <?php if ($causale->isControparteRichiesta()): ?>
                <?= Campo::autocompleta('controparte', $campi['controparte'], $formEntity, $errors, $urlControparti, $etichettaControparte, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
                <div class="col-md-3 d-flex align-items-end"><a href="/controparte/create/soggetto/<?= Html::e($soggetto->id) ?>" target="_blank" class="small"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['nuovo']) ?> <?= Html::e(mb_strtolower($causale->isControparteCliente() ? $campi['cliente'] : $campi['fornitore'])) ?></a></div>
            <?php endif; ?>
            <?php if ($causale->isDocumentoRichiesto() || $iva): ?>
                <?= Campo::testo('numeroDocumento', $campi['numeroDocumento'], $formEntity, $errors, ['richiesto' => $causale->isDocumentoRichiesto(), 'colonna' => 'col-md-3', 'attributi' => 'maxlength="30"']) ?>
                <?= Campo::data('dataDocumento', $campi['dataDocumento'], $formEntity, $errors, ['richiesto' => $causale->isDocumentoRichiesto(), 'colonna' => 'col-md-3']) ?>
            <?php endif; ?>
            <?php if ($iva): ?>
                <?= Campo::selezione('registroIva', $campi['registroIva'], $formEntity, $errors, $sceltaRegistri, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
                <?= Campo::data('dataCompetenzaIva', $campi['dataCompetenzaIva'], $formEntity, $errors, ['richiesto' => true, 'colonna' => 'col-md-3']) ?>
                <?php if ($causale->getTipoRegistroIva() === TipoRegistroIva::vendite): ?>
                    <?= Campo::selezioneEnum('esigibilita', $campi['esigibilita'], $formEntity, $errors, $esigibilita, ['richiesto' => true, 'colonna' => 'col-md-6']) ?>
                <?php else: ?>
                    <input type="hidden" name="esigibilita" value="<?= Html::valore($formEntity, 'esigibilita') ?: 'I' ?>">
                <?php endif; ?>
            <?php endif; ?>
            <?= Campo::testo('descrizione', $campi['descrizione'], $formEntity, $errors, ['colonna' => 'col-md-9', 'attributi' => 'maxlength="255"']) ?>
            <?= Campo::testo('totaleDocumento', $campi['totaleDocumento'], $formEntity, $errors, ['colonna' => 'col-md-3', 'richiesto' => in_array($causale->value, ['INC', 'PAG', 'VIV'], true), 'attributi' => 'inputmode="decimal"']) ?>
        </div>
    </div>

    <?php if ($iva): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($righeIva) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($aiutoIva) ?></span></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle" data-righe-iva>
                    <thead class="table-light"><tr><th style="min-width: 14rem"><?= Html::e($campi['codiceIva']) ?></th><th class="text-end"><?= Html::e($campi['imponibile']) ?></th><th class="text-end"><?= Html::e($campi['imposta']) ?></th><?php if ($acquisti): ?><th class="text-end"><?= Html::e($campi['detraibilita']) ?></th><?php endif; ?><?php if ($acquisti && $ventilazione): ?><th class="text-center" title="<?= Html::e($aiutoRivendita) ?>"><?= Html::e($campi['rivendita']) ?></th><?php endif; ?><th style="min-width: 16rem"><?= Html::e($contropartita) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($datiIva as $indice => $riga): ?>
                            <?php $prefisso = 'righeIva[' . $indice . ']'; ?>
                            <tr>
                                <td>
                                    <select class="form-select form-select-sm" name="<?= Html::e($prefisso) ?>[codiceIva]" data-codice-iva>
                                        <option value=""><?= Html::e($selezionare) ?></option>
                                        <?php foreach ($codiciIva as $codiceIva): ?>
                                            <option value="<?= Html::e($codiceIva->id) ?>" data-aliquota="<?= Html::e($codiceIva->aliquota) ?>" data-detraibilita="<?= Html::e($codiceIva->detraibilita) ?>" <?= (string) ($riga['codiceIva'] ?? '') === (string) $codiceIva->id ? 'selected' : '' ?>><?= Html::e($codiceIva->getEtichetta()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm text-end" name="<?= Html::e($prefisso) ?>[imponibile]" value="<?= Html::e($riga['imponibile'] ?? '') ?>" inputmode="decimal" data-imponibile></td>
                                <td><input type="text" class="form-control form-control-sm text-end" name="<?= Html::e($prefisso) ?>[imposta]" value="<?= Html::e($riga['imposta'] ?? '') ?>" inputmode="decimal" data-imposta></td>
                                <?php if ($acquisti): ?>
                                    <td style="width: 6rem"><input type="text" class="form-control form-control-sm text-end" name="<?= Html::e($prefisso) ?>[detraibilita]" value="<?= Html::e($riga['detraibilita'] ?? '') ?>" inputmode="decimal" placeholder="100" data-detraibilita></td>
                                <?php endif; ?>
                                <?php if ($acquisti && $ventilazione): ?>
                                    <td class="text-center"><input class="form-check-input" type="checkbox" name="<?= Html::e($prefisso) ?>[rivendita]" value="1" <?= ($riga['rivendita'] ?? '') === '1' ? 'checked' : '' ?> aria-label="<?= Html::e($campi['rivendita']) ?>"></td>
                                <?php endif; ?>
                                <td><?= $autocompleta($prefisso . '[conto]', $urlConti, $riga['conto'] ?? null, $riga['contoEtichetta'] ?? null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><span class="fw-semibold"><?= Html::e($righe) ?></span> <span class="small text-body-secondary ms-2"><?= Html::e($aiutoRighe) ?></span></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle" data-righe>
                <thead class="table-light"><tr><th style="min-width: 18rem"><?= Html::e($campi['conto']) ?></th><th style="min-width: 12rem"><?= Html::e($campi['controparte']) ?></th><th class="text-end"><?= Html::e($campi['dare']) ?></th><th class="text-end"><?= Html::e($campi['avere']) ?></th><th><?= Html::e($campi['descrizione']) ?></th></tr></thead>
                <tbody>
                    <?php foreach ($datiRighe as $indice => $riga): ?>
                        <?php $prefisso = 'righe[' . $indice . ']'; ?>
                        <tr>
                            <td><?= $autocompleta($prefisso . '[conto]', $urlConti, $riga['conto'] ?? null, $riga['contoEtichetta'] ?? null) ?></td>
                            <td><?= $autocompleta($prefisso . '[controparte]', $urlControparti, $riga['controparte'] ?? null, $riga['controparteEtichetta'] ?? null) ?></td>
                            <td style="width: 9rem"><input type="text" class="form-control form-control-sm text-end" name="<?= Html::e($prefisso) ?>[dare]" value="<?= Html::e($riga['dare'] ?? '') ?>" inputmode="decimal" data-dare></td>
                            <td style="width: 9rem"><input type="text" class="form-control form-control-sm text-end" name="<?= Html::e($prefisso) ?>[avere]" value="<?= Html::e($riga['avere'] ?? '') ?>" inputmode="decimal" data-avere></td>
                            <td><input type="text" class="form-control form-control-sm" name="<?= Html::e($prefisso) ?>[descrizione]" value="<?= Html::e($riga['descrizione'] ?? '') ?>" maxlength="255"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr><th class="text-end" colspan="2"><?= Html::e($totale) ?> · <span class="fw-normal"><?= Html::e($differenza) ?>:</span> <span data-differenza>0,00</span></th><th class="text-end" data-totale-dare>0,00</th><th class="text-end" data-totale-avere>0,00</th><th></th></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-2">
            <button type="submit" name="azione" value="proponi" class="btn btn-sm btn-outline-primary"><i class="bi bi-magic me-1"></i><?= Html::e($azioni['proponi']) ?></button>
            <button type="submit" name="azione" value="aggiungi" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiRighe']) ?></button>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4">
        <button type="submit" name="azione" value="salva" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= Html::e($azioni['salva']) ?></button>
        <?php if ((isset($registrazione->id) === false) && ($fattura === null)): ?>
            <button type="submit" name="azione" value="salvaNuova" class="btn btn-outline-primary"><?= Html::e($azioni['salvaNuova']) ?></button>
        <?php endif; ?>
        <a href="<?= Html::e($urlAnnulla) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
        <span class="ms-auto small text-body-secondary"><?= Html::e($campiObbligatori) ?></span>
    </div>
</form>
<script src="/javascript/contabilita.js"></script>
<?php require Layout::percorso('footer'); ?>
