<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;

$urlMese = fn(int $anno, int $mese): string => '/corrispettivi/mese/registro/' . $registro->id . '/anno/' . $anno . '/mese/' . $mese;
$precedente = $meseCorrispettivi === 1 ? [$annoCorrispettivi - 1, 12] : [$annoCorrispettivi, $meseCorrispettivi - 1];
$successivo = $meseCorrispettivi === 12 ? [$annoCorrispettivi + 1, 1] : [$annoCorrispettivi, $meseCorrispettivi + 1];
$valore = fn(string $data, int $idCodice): string => isset($importi[$data][$idCodice]) ? number_format($importi[$data][$idCodice], 2, ',', '') : '';
$nomiMesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
$idColonne = array_map(fn($codice) => $codice->id, $colonne);

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a href="<?= Html::e($urlMese(...$precedente)) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($mesePrecedente) ?>"><i class="bi bi-chevron-left"></i></a>
    <h1 class="h5 mb-0"><?= Html::e($titolo) ?> <?= Html::e($nomiMesi[$meseCorrispettivi - 1] . ' ' . $annoCorrispettivi) ?></h1>
    <a href="<?= Html::e($urlMese(...$successivo)) ?>" class="btn btn-sm btn-outline-secondary me-auto" title="<?= Html::e($meseSuccessivo) ?>"><i class="bi bi-chevron-right"></i></a>
    <?php if (count($registri) > 1): ?>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= Html::e($registro->getEtichetta()) ?></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($registri as $voce): ?><li><a class="dropdown-item <?= $voce->id === $registro->id ? 'active' : '' ?>" href="/corrispettivi/mese/registro/<?= Html::e($voce->id) ?>/anno/<?= Html::e($annoCorrispettivi) ?>/mese/<?= Html::e($meseCorrispettivi) ?>"><?= Html::e($voce->getEtichetta()) ?></a></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<p class="small text-body-secondary"><?= Html::e($aiuto) ?></p>
<?php if ($ventilazione): ?><p class="small text-body-secondary"><i class="bi bi-info-circle me-1"></i><?= Html::e($aiutoVentilazione) ?></p><?php endif; ?>
<?php if ($errore !== null): ?><div class="alert alert-danger small py-2"><?= Html::e($errore) ?></div><?php endif; ?>
<?php if ($registrazione !== null): ?>
    <div class="alert alert-light border small py-2"><?= Html::e($registrata) ?> <a href="/registrazione/show/registrazione/<?= Html::e($registrazione->id) ?>"><?= Html::e($campi['numero']) ?> <?= Html::e($registrazione->numero) ?></a> · <?= Html::e($campi['protocolloIva']) ?> <?= Html::e($registrazione->protocolloIva) ?> · € <?= Html::e(Importo::euro((float) $registrazione->totaleDocumento)) ?></div>
<?php endif; ?>

<form method="post" novalidate id="form-corrispettivi">
    <?php require __DIR__ . '/../commonParts/campiNascosti.php'; ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle" data-griglia-corrispettivi>
                <thead class="table-light">
                    <tr>
                        <th><?= Html::e($campi['data']) ?></th>
                        <?php foreach ($colonne as $codice): ?><th class="text-end" style="min-width: 9rem" title="<?= Html::e($codice->descrizione) ?>"><?= Html::e($codice->codice) ?></th><?php endforeach; ?>
                        <th class="text-end"><?= Html::e($totale) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($giorni as $giorno): ?>
                        <tr class="<?= $giorno['festivo'] ? 'table-light' : '' ?>">
                            <td class="text-nowrap small"><?= Html::e($giorno['etichetta']) ?></td>
                            <?php foreach ($colonne as $codice): ?>
                                <td><input type="text" class="form-control form-control-sm text-end" name="importi[<?= Html::e($giorno['data']) ?>][<?= Html::e($codice->id) ?>]" value="<?= Html::e($valore($giorno['data'], $codice->id)) ?>" inputmode="decimal" data-colonna="<?= Html::e($codice->id) ?>" aria-label="<?= Html::e($giorno['etichetta'] . ' ' . $codice->codice) ?>"></td>
                            <?php endforeach; ?>
                            <td class="text-end text-nowrap small" data-totale-giorno></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-semibold"><td><?= Html::e($totale) ?></td><?php foreach ($colonne as $codice): ?><td class="text-end" data-totale-colonna="<?= Html::e($codice->id) ?>" data-aliquota="<?= Html::e($codice->aliquota) ?>"></td><?php endforeach; ?><td class="text-end" data-totale-mese></td></tr>
                    <tr class="small"><td><?= Html::e($campi['imponibile']) ?></td><?php foreach ($colonne as $codice): ?><td class="text-end" data-imponibile-colonna="<?= Html::e($codice->id) ?>"></td><?php endforeach; ?><td></td></tr>
                    <tr class="small"><td><?= Html::e($campi['imposta']) ?></td><?php foreach ($colonne as $codice): ?><td class="text-end" data-imposta-colonna="<?= Html::e($codice->id) ?>"></td><?php endforeach; ?><td class="text-end" data-imposta-mese></td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
            <select class="form-select form-select-sm w-auto" name="nuovoCodice" aria-label="<?= Html::e($aggiungiCodice) ?>">
                <option value=""><?= Html::e($aggiungiCodice) ?></option>
                <?php foreach ($codiciDisponibili as $codice): ?><?php if (in_array($codice->id, $idColonne, true) === false): ?><option value="<?= Html::e($codice->id) ?>"><?= Html::e($codice->getEtichetta()) ?></option><?php endif; ?><?php endforeach; ?>
            </select>
            <button type="submit" name="azione" value="aggiungi" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($aggiungiColonna) ?></button>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 mt-4">
        <button type="submit" name="azione" value="salva" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= Html::e($azioni['salva']) ?></button>
        <a href="/registro-iva/index/soggetto/<?= Html::e($soggetto->id) ?>" class="btn btn-outline-secondary"><?= Html::e($azioni['annulla']) ?></a>
    </div>
</form>
<script src="/javascript/contabilita.js"></script>
<?php require Layout::percorso('footer'); ?>
