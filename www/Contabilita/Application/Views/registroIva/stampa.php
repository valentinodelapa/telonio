<?php

use Contabilita\Application\Enumerations\TipoRegistroIva;
use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Registro IVA del periodo da stampare: documenti per protocollo con le
 * righe IVA, totali per codice e integrazioni dell'imposta.
 */
$titoloStampa = $registro->getEtichetta();
$orizzontale = true;
$acquisti = $registro->tipo === TipoRegistroIva::acquisti;
$conRivendita = $acquisti && (count(array_filter($dati['totali'], fn(array $voce) => $voce['rivendita'] != 0)) > 0);
require __DIR__ . '/../commonParts/stampaTesta.php';
?>
<div class="tenue"><?= Html::e($registro->tipo->getNorma($dal)) ?></div>
<table style="margin-top: 3mm">
    <thead>
        <tr><th>Prot.</th><th>Registrazione</th><th>Documento</th><th>Competenza</th><th>Controparte</th><th>Codice IVA</th><th class="num">Imponibile</th><th class="num">Imposta</th><?php if ($acquisti): ?><th class="num">Detraibile</th><?php endif; ?><th class="num">Totale</th></tr>
    </thead>
    <tbody>
        <?php foreach ($dati['documenti'] as ['registrazione' => $registrazione, 'righe' => $righe]): ?>
            <?php foreach ($righe as $indice => $riga): ?>
                <tr class="<?= $indice === 0 ? 'inizio-registrazione' : '' ?>">
                    <?php if ($indice === 0): ?>
                        <td rowspan="<?= count($righe) ?>"><?= Html::e($registrazione->protocolloIva) ?></td>
                        <td rowspan="<?= count($righe) ?>"><?= Html::data($registrazione->dataRegistrazione) ?> n. <?= Html::e($registrazione->numero) ?></td>
                        <td rowspan="<?= count($righe) ?>"><?= Html::e($registrazione->causale->getLabel()) ?><?= $registrazione->numeroDocumento === null ? '' : ' n. ' . Html::e($registrazione->numeroDocumento) ?><?= $registrazione->dataDocumento === null ? '' : ' del ' . Html::data($registrazione->dataDocumento) ?></td>
                        <td rowspan="<?= count($righe) ?>"><?= Html::data($registrazione->dataCompetenzaIva) ?><?= $registrazione->esigibilita->value !== 'I' ? '<div class="tenue">' . Html::e($registrazione->esigibilita->getLabel($registrazione->dataCompetenzaIva)) . '</div>' : '' ?></td>
                        <td rowspan="<?= count($righe) ?>"><?= Html::e($registrazione->controparte?->denominazione ?? '') ?><div class="tenue"><?= Html::e($registrazione->controparte?->partitaIva ?? $registrazione->controparte?->codiceFiscale ?? '') ?></div></td>
                    <?php endif; ?>
                    <td><?= Html::e($riga->codiceIva->getEtichetta()) ?><?= $riga->rivendita ? ' <span class="tenue">(' . ($riga->altriProdotti ? 'rivendita, altri prodotti' : 'rivendita') . ')</span>' : '' ?></td>
                    <td class="num"><?= Html::e(Importo::euro($riga->imponibile)) ?></td>
                    <td class="num"><?= Html::e(Importo::euro($riga->imposta)) ?></td>
                    <?php if ($acquisti): ?><td class="num"><?= Html::e(Importo::euro($riga->getImpostaDetraibile())) ?></td><?php endif; ?>
                    <?php if ($indice === 0): ?><td class="num" rowspan="<?= count($righe) ?>"><?= Html::e(Importo::euro((float) $registrazione->totaleDocumento * $registrazione->causale->getSegno())) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if (count($dati['documenti']) === 0): ?><tr><td colspan="10" class="tenue">Nessun documento nel periodo.</td></tr><?php endif; ?>
    </tbody>
</table>

<?php foreach ($dati['documenti'] as ['registrazione' => $registrazione, 'giornalieri' => $giornalieri]): ?>
    <?php if (count($giornalieri) > 0): ?>
        <?php $codiciMese = array_unique(array_merge(...array_map('array_keys', array_values($giornalieri)))); sort($codiciMese); ?>
        <h2>Corrispettivi giornalieri (comprensivi dell'imposta) - registrazione n. <?= Html::e($registrazione->numero) ?>, protocollo <?= Html::e($registrazione->protocolloIva) ?></h2>
        <table style="width: auto; min-width: 50%">
            <thead><tr><th>Giorno</th><?php foreach ($codiciMese as $codice): ?><th class="num"><?= Html::e($codice) ?></th><?php endforeach; ?><th class="num">Totale</th></tr></thead>
            <tbody>
                <?php foreach ($giornalieri as $giorno => $perCodice): ?>
                    <?php $totaleGiorno = 0.0; ?>
                    <tr><td><?= Html::e((new \DateTimeImmutable($giorno))->format('d/m/Y')) ?></td>
                        <?php foreach ($codiciMese as $codice): ?><?php $totaleGiorno += ($perCodice[$codice] ?? null)?->importo ?? 0; ?><td class="num"><?= isset($perCodice[$codice]) ? Html::e(Importo::euro($perCodice[$codice]->importo)) : '' ?></td><?php endforeach; ?>
                        <td class="num"><?= Html::e(Importo::euro($totaleGiorno)) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

<h2>Totali per codice IVA</h2>
<table style="width: auto; min-width: 50%">
    <thead><tr><th>Codice IVA</th><th class="num">Imponibile</th><th class="num">Imposta</th><?php if ($acquisti): ?><th class="num">Detraibile</th><?php endif; ?><?php if ($conRivendita): ?><th class="num">Per rivendita (IVA compresa)</th><?php endif; ?></tr></thead>
    <tbody>
        <?php $imponibile = $imposta = $detraibile = 0.0; ?>
        <?php foreach ($dati['totali'] as $voce): ?>
            <?php $imponibile += $voce['imponibile']; $imposta += $voce['imposta']; $detraibile += $voce['detraibile']; ?>
            <tr><td><?= Html::e($voce['codice']) ?></td><td class="num"><?= Html::e(Importo::euro($voce['imponibile'])) ?></td><td class="num"><?= Html::e(Importo::euro($voce['imposta'])) ?></td><?php if ($acquisti): ?><td class="num"><?= Html::e(Importo::euro($voce['detraibile'])) ?></td><?php endif; ?><?php if ($conRivendita): ?><td class="num"><?= Html::e(Importo::euro($voce['rivendita'])) ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        <tr class="totale"><td>Totale</td><td class="num"><?= Html::e(Importo::euro($imponibile)) ?></td><td class="num"><?= Html::e(Importo::euro($imposta)) ?></td><?php if ($acquisti): ?><td class="num"><?= Html::e(Importo::euro($detraibile)) ?></td><?php endif; ?><?php if ($conRivendita): ?><td class="num"><?= Html::e(Importo::euro(array_sum(array_column($dati['totali'], 'rivendita')))) ?></td><?php endif; ?></tr>
    </tbody>
</table>

<?php if (count($dati['integrazioni']) > 0): ?>
    <h2>Integrazioni dell'imposta sugli acquisti (inversione contabile, intracomunitari, esteri)</h2>
    <table>
        <thead><tr><th>Registrazione</th><th>Documento</th><th>Fornitore</th><th>Codice IVA</th><th class="num">Imponibile</th><th class="num">Imposta</th></tr></thead>
        <tbody>
            <?php foreach ($dati['integrazioni'] as $riga): ?>
                <?php $registrazione = $riga->registrazioneContabile; ?>
                <tr>
                    <td><?= Html::data($registrazione->dataRegistrazione) ?> n. <?= Html::e($registrazione->numero) ?> (prot. acquisti <?= Html::e($registrazione->protocolloIva) ?>)</td>
                    <td><?= Html::e($registrazione->numeroDocumento ?? '') ?> <?= Html::data($registrazione->dataDocumento) ?></td>
                    <td><?= Html::e($registrazione->controparte?->denominazione ?? '') ?></td>
                    <td><?= Html::e($riga->codiceIva->getEtichetta()) ?></td>
                    <td class="num"><?= Html::e(Importo::euro($riga->imponibile)) ?></td>
                    <td class="num"><?= Html::e(Importo::euro($riga->imposta)) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="totale"><td colspan="4">Totale</td><td class="num"><?= Html::e(Importo::euro($dati['totaliIntegrazioni']['imponibile'])) ?></td><td class="num"><?= Html::e(Importo::euro($dati['totaliIntegrazioni']['imposta'])) ?></td></tr>
        </tbody>
    </table>
<?php endif; ?>
    </body>
</html>
