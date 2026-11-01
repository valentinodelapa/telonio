<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;

/**
 * Prospetto della liquidazione da stampare o consegnare al cliente.
 */
$titoloStampa = $pageTitle;
require __DIR__ . '/../commonParts/stampaTesta.php';
$classeTabella = '';
$classeNumero = 'num';
$classeTotale = 'totale';
?>
<div style="width: 60%"><?php require __DIR__ . '/prospetto.php'; ?></div>
<?php if (($ventilazione !== null) && ($ventilazione->corrispettivi != 0)): ?>
    <h2><?= Html::e($titoloVentilazione) ?></h2>
    <?php $classeTotale = 'totale'; require __DIR__ . '/ventilazione.php'; ?>
    <p class="tenue"><?= Html::e($aiutoVentilazione) ?></p>
<?php endif; ?>
<h2><?= Html::e($titoloRiepilogo) ?></h2>
<table>
    <thead><tr><th><?= Html::e($campi['registroIva']) ?></th><th><?= Html::e($campi['codiceIva']) ?></th><th class="num"><?= Html::e($campi['imponibile']) ?></th><th class="num"><?= Html::e($campi['imposta']) ?></th><th class="num"><?= Html::e($campi['ivaEsigibile']) ?></th><th class="num"><?= Html::e($campi['ivaDetraibile']) ?></th></tr></thead>
    <tbody>
        <?php foreach ($riepilogo as $voce): ?>
            <tr><td><?= Html::e($voce['registro']) ?></td><td><?= Html::e($voce['codice']) ?></td><td class="num"><?= Html::e(Importo::euro($voce['imponibile'])) ?></td><td class="num"><?= Html::e(Importo::euro($voce['imposta'])) ?></td><td class="num"><?= Html::e(Importo::euro($voce['esigibile'], true)) ?></td><td class="num"><?= Html::e(Importo::euro($voce['detraibile'], true)) ?></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php foreach (array_filter(explode("\n", (string) $liquidazione->avvisi)) as $avviso): ?><p class="tenue"><?= Html::e($avviso) ?></p><?php endforeach; ?>
    </body>
</html>
