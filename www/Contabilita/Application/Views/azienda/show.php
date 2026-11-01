<?php

use Deleghe\Application\HelperClasses\Importo;
use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Enumerations\CausaleContabile;

$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';

require Layout::percorso('header');
require __DIR__ . '/../commonParts/navAzienda.php';
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold me-auto"><?= Html::e($ultimeRegistrazioni) ?></span>
                <?php if (($esercizio !== null) && ($esercizio->chiuso === false)): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i><?= Html::e($nuovaRegistrazione) ?></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach (CausaleContabile::getManuali() as $causale): ?>
                                <li><a class="dropdown-item" href="/registrazione/create/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>/causale/<?= Html::e($causale->value) ?>"><?= Html::e($causale->getLabel()) ?></a></li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/corrispettivi/index/soggetto/<?= Html::e($soggetto->id) ?>"><i class="bi bi-calendar3-week me-1"></i><?= Html::e($corrispettiviMese) ?></a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($esercizio === null): ?>
                <div class="card-body text-body-secondary"><?= Html::e($nessunEsercizio) ?> <a href="/azienda/esercizio/soggetto/<?= Html::e($soggetto->id) ?>"><?= Html::e($azioni['nuovoEsercizio']) ?></a></div>
            <?php elseif (count($registrazioni) === 0): ?>
                <div class="card-body text-body-secondary"><?= Html::e($nessunRisultato) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th><?= Html::e($campi['numero']) ?></th><th><?= Html::e($campi['data']) ?></th><th><?= Html::e($campi['causale']) ?></th><th><?= Html::e($campi['descrizione']) ?></th><th class="text-end"><?= Html::e($campi['totaleDocumento']) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($registrazioni as $registrazione): ?>
                                <tr>
                                    <td><a href="/registrazione/show/registrazione/<?= Html::e($registrazione->id) ?>"><?= Html::e($registrazione->numero) ?></a></td>
                                    <td class="text-nowrap"><?= Html::data($registrazione->dataRegistrazione) ?></td>
                                    <td class="small"><?= Html::e($registrazione->causale->getLabel()) ?></td>
                                    <td class="small"><?= Html::e($registrazione->getDescrizioneCompleta()) ?></td>
                                    <td class="text-end text-nowrap"><?= $registrazione->totaleDocumento === null ? '' : Html::e(Importo::euro($registrazione->totaleDocumento)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($esercizio !== null): ?>
                <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
                    <a href="/registrazione/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($esercizio->anno) ?>" class="small me-auto"><?= Html::e($tutteRegistrazioni) ?> (<?= Html::e($totaleRegistrazioni) ?>)</a>
                    <?php if ($esercizio->chiuso): ?>
                        <span class="small text-body-secondary"><i class="bi bi-lock me-1"></i><?= Html::e($esercizioChiuso) ?></span>
                        <form method="post" action="/azienda/chiudi/esercizio/<?= Html::e($esercizio->id) ?>/riapri/1"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['riapriEsercizio']) ?></button></form>
                    <?php else: ?>
                        <form method="post" action="/azienda/chiudi/esercizio/<?= Html::e($esercizio->id) ?>" data-conferma="<?= Html::e($esercizioChiuso) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-lock me-1"></i><?= Html::e($azioni['chiudiEsercizio']) ?></button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body small">
                <?php if ($datiFiscali === null): ?>
                    <span class="text-warning"><?= Html::e($datiFiscaliMancanti) ?></span>
                <?php else: ?>
                    <div><span class="text-body-secondary"><?= Html::e($campi['regime']) ?>:</span> <?= Html::e($datiFiscali->regimeFiscale?->getLabel() ?? '-') ?></div>
                    <div><span class="text-body-secondary"><?= Html::e($campi['periodicita']) ?>:</span> <?= Html::e($periodicita?->getLabel() ?? '-') ?></div>
                <?php endif; ?>
                <?php if ($erroreIva !== null): ?><div class="text-body-secondary mt-1"><?= Html::e($erroreIva) ?></div><?php endif; ?>
            </div>
        </div>
        <a href="/fattura-elettronica/index/soggetto/<?= Html::e($soggetto->id) ?>/stato/D" class="card shadow-sm mb-3 text-decoration-none suite-tile">
            <div class="card-body d-flex align-items-center">
                <i class="bi bi-file-earmark-code me-3 text-primary"></i>
                <div class="me-auto text-body"><?= Html::e($titoloFattureDaRegistrare) ?></div>
                <span class="badge <?= $fattureDaRegistrare > 0 ? 'text-bg-warning' : 'text-bg-light border' ?> fs-6"><?= Html::e($fattureDaRegistrare) ?></span>
            </div>
        </a>
        <?php if ($periodicita !== null): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><a href="/liquidazione-iva/index/soggetto/<?= Html::e($soggetto->id) ?>/anno/<?= Html::e($annoIva) ?>" class="text-decoration-none"><?= Html::e($liquidazioniAnno) ?> <?= Html::e($annoIva) ?></a></div>
                <ul class="list-group list-group-flush small">
                    <?php for ($periodo = 1; $periodo <= $numeroPeriodi; $periodo++): ?>
                        <?php $liquidazione = $liquidazioni[$periodo] ?? null; ?>
                        <li class="list-group-item d-flex">
                            <span class="me-auto"><?= Html::e(LiquidazioneIva::descriviPeriodo($periodicita, $annoIva, $periodo)) ?></span>
                            <?php if ($liquidazione === null): ?>
                                <span class="text-body-secondary"><?= Html::e($nonElaborata) ?></span>
                            <?php else: ?>
                                <a href="/liquidazione-iva/show/liquidazione/<?= Html::e($liquidazione->id) ?>" class="text-decoration-none"><?= $liquidazione->importoDovuto > 0 ? Html::e(Importo::euro($liquidazione->importoDovuto)) : '<span class="text-success">' . Html::e($liquidazione->creditoFinale > 0 ? '- ' . Importo::euro($liquidazione->creditoFinale) : Importo::euro($liquidazione->debitoRinviato)) . '</span>' ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require Layout::percorso('footer'); ?>
