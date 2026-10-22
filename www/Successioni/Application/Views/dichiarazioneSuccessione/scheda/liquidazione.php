<?php

use CommSuite\Application\HelperClasses\Html;

$righeEF = [
    ['EF1', 'Imposta ipotecaria proporzionale (2%) su € ' . $euroIntero($risultato->ipotecariaImponibile), $risultato->ipotecariaProporzionale],
    ['EF2', 'Imposta ipotecaria fissa - agevolazione G', $risultato->agevolazioneGImposta],
    ['EF3', 'Imposta ipotecaria fissa - agevolazione M', $risultato->agevolazioneMImposta],
    ['EF4', 'Imposta ipotecaria fissa - prima casa (' . $risultato->primaCasaNumero . ' immobili)', $risultato->primaCasaImposta],
    ['EF8', 'Imposta ipotecaria da versare', $risultato->ipotecariaDaVersare],
    ['EF9', 'Imposta catastale proporzionale (1%)', $risultato->catastaleProporzionale],
    ['EF10', 'Imposta catastale fissa - prima casa', $risultato->catastaleFissa],
    ['EF14', 'Imposta catastale da versare', $risultato->catastaleDaVersare],
    ['EF15', 'Tassa ipotecaria (' . $risultato->circoscrizioniTassa . ' circoscrizioni)', $risultato->tassaIpotecaria],
    ['EF16', 'Imposta di bollo (' . $risultato->circoscrizioniBollo . ' circoscrizioni' . ($risultato->bolloCopiaConforme > 0 ? ' e copia conforme' : '') . ')', $risultato->bollo],
    ['EF17', 'Tributi speciali (copia conforme)', $risultato->tributiSpeciali],
];
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= Html::e($riepilogo) ?></div>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr><td>EE1 Immobili</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['immobili'])) ?></td></tr>
                    <tr><td>EE2 Aziende</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['aziende'])) ?></td></tr>
                    <tr><td>EE3 Azioni, obbligazioni, titoli e quote</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['titoli'])) ?></td></tr>
                    <tr><td>EE4 Aeromobili, navi e imbarcazioni</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['aereiNavi'])) ?></td></tr>
                    <tr><td>EE5 Rendite, crediti e altri beni</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['altriBeni'])) ?></td></tr>
                    <tr class="fw-semibold"><td>EE6 Totale attivo</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['attivo'])) ?></td></tr>
                    <tr><td>EE7 Totale passivo</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['passivo'])) ?></td></tr>
                    <tr class="fw-semibold"><td>EE8 Asse ereditario netto</td><td class="text-end"><?= Html::e($euroIntero($risultato->riepilogo['netto'])) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center">
                <span class="fw-semibold me-auto"><?= Html::e($quadroEF) ?></span>
                <?php if ($modificabile): ?><a href="/dichiarazione-successione/pagamento/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-wallet2 me-1"></i><?= Html::e($azioni['pagamento']) ?></a><?php endif; ?>
            </div>
            <table class="table table-sm mb-0">
                <tbody>
                    <?php foreach ($righeEF as [$rigo, $descrizione, $importo]): ?>
                        <?php if ($importo > 0): ?><tr><td class="text-body-secondary small"><?= Html::e($rigo) ?></td><td class="small"><?= Html::e($descrizione) ?></td><td class="text-end"><?= Html::e($euro($importo)) ?></td></tr><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($risultato->autoliquidazione): ?>
                        <tr><td class="text-body-secondary small">EF18bis</td><td class="small">Imposta di successione (da versare € <?= Html::e($euro($risultato->successioneDaVersare)) ?>)</td><td class="text-end"><?= Html::e($euro($risultato->successioneImposta)) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($risultato->getTotaleSanzioni() + $risultato->getTotaleInteressi() > 0): ?>
                        <tr><td class="text-body-secondary small">EF24</td><td class="small"><?= Html::e($sezioneVI) ?> (<?= Html::e($risultato->giorniRitardo) ?> gg)</td><td class="text-end"><?= Html::e($euro($risultato->getTotaleSanzioni())) ?> + <?= Html::e($euro($risultato->getTotaleInteressi())) ?></td></tr>
                    <?php endif; ?>
                    <tr class="fw-semibold"><td class="small">EF19</td><td><?= Html::e($campi['contoAddebito']) ?>: <?= Html::e($dichiarazione->contoAddebito?->getIbanFormattato() ?? '—') ?></td><td class="text-end"><?= Html::e($euro($risultato->getImportoDaVersare())) ?></td></tr>
                    <?php if ($risultato->sanzionePresentazione > 0): ?>
                        <tr><td class="text-body-secondary small">F24</td><td class="small"><?= Html::e($sanzionePresentazione) ?></td><td class="text-end"><?= Html::e($euro($risultato->sanzionePresentazione)) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><?= Html::e($impostaSuccessione) ?></div>
    <?php if ($risultato->autoliquidazione === false): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nonAutoliquidazione) ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th><?= Html::e($campi['beneficiario']) ?></th><th class="text-end">QE</th><th class="text-end">QDN</th><th class="text-end">QP</th><th class="text-end">Franchigia</th><th class="text-end">Presunzione</th><th class="text-end">Imponibile</th><th class="text-end">%</th><th class="text-end">Riduzioni</th><th class="text-end">Imposta</th></tr></thead>
                <tbody>
                    <?php foreach ($risultato->quote as $quota): ?>
                        <tr>
                            <td><?= Html::e($quota->beneficiario->soggetto->nominativo) ?><div class="text-body-secondary"><?= Html::e($quota->beneficiario->gradoParentela->getLabel()) ?></div></td>
                            <td class="text-end"><?= Html::e($euro($quota->quotaEreditaria)) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->quotaDenaro)) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->quotaPassivo)) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->franchigia)) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->presunzione)) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->imponibile)) ?></td>
                            <td class="text-end"><?= Html::e(rtrim(rtrim(number_format($quota->aliquota, 1, ',', ''), '0'), ',')) ?></td>
                            <td class="text-end"><?= Html::e($euro($quota->riduzioni + $quota->detrazioneEstero)) ?></td>
                            <td class="text-end fw-semibold"><?= Html::e($euro($quota->impostaNetta)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoSuccessione) ?></div>
    <?php endif; ?>
</div>

<?php if ($risultato->autoliquidazione && ($risultato->successioneDaVersare > 0)): ?>
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><?= Html::e($pianoVersamenti) ?> · <?= Html::e($dichiarazione->tempisticaPagamento?->getLabel() ?? '—') ?></span>
            <form method="post" action="/dichiarazione-successione/versamenti/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-credit-card-2-front me-1"></i><?= Html::e($azioni['registraVersamenti']) ?></button></form>
        </div>
        <table class="table table-sm mb-0 small">
            <thead class="table-light"><tr><th></th><th><?= Html::e($campi['scadenza']) ?></th><th class="text-end"><?= Html::e($campi['importo']) ?></th><th class="text-end">Interessi</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($rate as $rata): ?>
                    <tr>
                        <td><?= $rata['numero'] === 0 ? ($dichiarazione->numeroRate === null ? 'Unica soluzione' : 'Acconto') : 'Rata ' . Html::e($rata['numero'] . '/' . $dichiarazione->numeroRate) ?></td>
                        <td><?= Html::data($rata['scadenza']) ?></td>
                        <td class="text-end"><?= Html::e($euro($rata['importo'] / 100)) ?></td>
                        <td class="text-end"><?= Html::e($euro($rata['interessi'] / 100)) ?></td>
                        <td class="text-body-secondary"><?= $rata['conDichiarazione'] ? Html::e($addebito) : 'F24 1539 ' . Html::e($rata['rateazione']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="card-footer bg-white small text-body-secondary"><?= Html::e($aiutoVersamenti) ?></div>
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><?= Html::e($titoloVersamentiRegistrati) ?></div>
    <?php if (count($versamentiRegistrati) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunVersamento) ?></div>
    <?php else: ?>
        <table class="table table-sm mb-0 small">
            <tbody>
                <?php foreach ($versamentiRegistrati as $versamento): ?>
                    <tr>
                        <td><?= Html::e($versamento->soggetto->nominativo) ?></td>
                        <td><?= Html::data($versamento->scadenza) ?></td>
                        <td><?= Html::e($versamento->getCodice()) ?> <?= Html::e($versamento->rateazione ?? '') ?></td>
                        <td class="text-end"><?= Html::e($euro($versamento->importoDebito)) ?></td>
                        <td><?= $versamento->isDaVersare() ? 'da versare' : Html::e(mb_strtolower($versamento->delega->stato->getLabel())) ?></td>
                        <td class="text-end"><a href="/contribuente/show/soggetto/<?= Html::e($versamento->soggetto->id) ?>" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['deleghe']) ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
