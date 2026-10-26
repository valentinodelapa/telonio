<?php

use Telonio\Application\HelperClasses\Html;
use GestioneStudio\Application\Enumerations\StatoLavorazione;

/**
 * Tabella delle scadenze: $voci (VoceScadenzario[]), $oggi, $ritorno
 * (pagina di ritorno codificata), $mostraSoggetto.
 */
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
$meseCorrente = null;
$mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
?>
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th><?= Html::e($campi['data']) ?></th>
                <?php if ($mostraSoggetto): ?><th><?= Html::e($campi['cliente']) ?></th><?php endif; ?>
                <th><?= Html::e($campi['descrizione']) ?></th>
                <th class="text-end"><?= Html::e($campi['importo']) ?></th>
                <th><?= Html::e($campi['responsabile']) ?></th>
                <th><?= Html::e($campi['stato']) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($voci as $voce): ?>
                <?php
                $scadenza = $voce->scadenza;
                $mese = $scadenza->data->format('Y-m');
                $urlAzione = $voce->getPercorso() . '/ritorno/' . $ritorno;
                ?>
                <?php if ($mese !== $meseCorrente): ?>
                    <?php $meseCorrente = $mese; ?>
                    <tr class="table-group-divider"><th colspan="<?= $mostraSoggetto ? 7 : 6 ?>" class="small text-uppercase text-body-secondary bg-body-tertiary"><?= Html::e(ucfirst($mesi[(int) $scadenza->data->format('n') - 1]) . ' ' . $scadenza->data->format('Y')) ?></th></tr>
                <?php endif; ?>
                <tr>
                    <td class="text-nowrap">
                        <?= Html::data($scadenza->data) ?>
                        <?php if ($voce->isScaduta($oggi)): ?><span class="badge text-bg-danger ms-1"><?= Html::e($scaduta) ?></span><?php endif; ?>
                    </td>
                    <?php if ($mostraSoggetto): ?>
                        <td>
                            <?php if ($scadenza->soggetto !== null): ?>
                                <a href="/scadenzario/soggetto/soggetto/<?= Html::e($scadenza->soggetto->id) ?>" class="text-decoration-none"><?= Html::e($scadenza->soggetto->nominativo) ?></a>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <span class="badge text-bg-light border me-1"><?= Html::e($scadenza->modulo->getLabel()) ?></span>
                        <?php if ($scadenza->url !== null): ?>
                            <a href="<?= Html::e($scadenza->url) ?>" class="text-decoration-none"><?= Html::e($scadenza->descrizione) ?></a>
                        <?php else: ?>
                            <?= Html::e($scadenza->descrizione) ?>
                        <?php endif; ?>
                        <?php if ($scadenza->dettaglio !== null): ?><div class="small text-body-secondary"><?= Html::e($scadenza->dettaglio) ?></div><?php endif; ?>
                        <?php if ($voce->lavorazione?->note !== null): ?><div class="small fst-italic"><i class="bi bi-chat-left-text me-1"></i><?= Html::e($voce->lavorazione->note) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap"><?= $scadenza->importo === null ? '' : Html::e(number_format($scadenza->importo, 2, ',', '.')) ?></td>
                    <td class="small"><?= Html::e($voce->responsabile?->getNomeCompleto() ?? '') ?></td>
                    <td>
                        <span class="badge <?= Html::e($voce->stato->getClasseBadge()) ?>"><?= Html::e($voce->stato->getLabel()) ?></span>
                        <?php if ($voce->isCompletataDalModulo()): ?><div class="small text-body-secondary"><?= Html::e($completataDalModulo) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($voce->isCompletataDalModulo() === false): ?>
                            <?php if ($voce->stato->isAperta()): ?>
                                <form method="post" action="/scadenzario/stato/<?= Html::e($urlAzione) ?>/nuovo/<?= Html::e(StatoLavorazione::completata->value) ?>" class="d-inline"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-success" title="<?= Html::e($azioni['completa']) ?>"><i class="bi bi-check-lg"></i></button></form>
                            <?php else: ?>
                                <form method="post" action="/scadenzario/stato/<?= Html::e($urlAzione) ?>/nuovo/<?= Html::e(StatoLavorazione::daFare->value) ?>" class="d-inline"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['riapri']) ?>"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="/scadenzario/lavorazione/<?= Html::e($urlAzione) ?>" class="btn btn-sm btn-outline-primary" title="<?= Html::e($azioni['lavorazione']) ?>"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
