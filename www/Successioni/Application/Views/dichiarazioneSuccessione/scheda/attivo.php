<?php

use CommSuite\Application\HelperClasses\Html;
use Successioni\Application\Enumerations\QuadroBene;

?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if ($modificabile): ?>
        <form method="post" action="/dichiarazione-successione/importa-immobili/dichiarazione/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-buildings me-1"></i><?= Html::e($azioni['importaImmobili']) ?></button></form>
        <form method="post" action="/dichiarazione-successione/proponi-devoluzioni/dichiarazione/<?= Html::e($id) ?>" data-conferma="<?= Html::e($azioni['proponiDevoluzioni']) ?>?"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i><?= Html::e($azioni['proponiDevoluzioni']) ?></button></form>
    <?php endif; ?>
</div>
<p class="small text-body-secondary"><?= Html::e($aiutoAttivo) ?></p>
<?php if ($modificabile): ?>
    <div class="dropdown mb-3">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiBene']) ?></button>
        <ul class="dropdown-menu">
            <?php foreach (QuadroBene::cases() as $quadro): ?>
                <li><a class="dropdown-item" href="/bene-successione/create/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($quadro->value) ?>"><?= Html::e($quadro->getLabel()) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php foreach (QuadroBene::cases() as $quadro): ?>
    <?php $beni = $dati->getBeniQuadro($quadro); ?>
    <?php if ((count($beni) === 0) && (in_array($quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true) === false)) continue; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><?= Html::e($quadro->getLabel()) ?></span>
            <?php if ($modificabile): ?><a href="/bene-successione/create/dichiarazione/<?= Html::e($id) ?>/quadro/<?= Html::e($quadro->value) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiBene']) ?></a><?php endif; ?>
        </div>
        <?php if (count($beni) === 0): ?>
            <div class="card-body small text-body-secondary"><?= Html::e($nessunBene) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead class="table-light"><tr><th>N.</th><th><?= Html::e($campi['descrizione']) ?></th><th><?= Html::e($campi['diritto']) ?></th><th class="text-end"><?= Html::e($campi['importo']) ?></th><th><?= Html::e($campi['beneficiario']) ?></th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($beni as $numero => $bene): ?>
                            <?php $catasto = $dati->getCatasto($bene); ?>
                            <tr>
                                <td><?= Html::e($numero + 1) ?></td>
                                <td class="small">
                                    <?php if ($bene->immobile !== null): ?>
                                        <?= Html::e($bene->immobile->getIdentificativo()) ?><div class="text-body-secondary"><?= Html::e($bene->immobile->getUbicazione()) ?></div>
                                        <?php if ($bene->isAziendale()): ?><span class="badge text-bg-light border"><?= Html::e($campi['azienda']) ?>: <?= Html::e($bene->azienda->descrizione) ?></span><?php endif; ?>
                                        <?php if ($bene->discordanzaIntestatario || $bene->passaggiSenzaAtti): ?><span class="badge text-bg-warning">EI</span><?php endif; ?>
                                        <?php if ($catasto?->categoria !== null): ?><div class="text-body-secondary"><?= Html::e($catasto->categoria->value) ?> cl. <?= Html::e($catasto->classe ?? '') ?> · rendita <?= Html::e($euro((float) $catasto->rendita)) ?></div><?php elseif ($catasto?->redditoDominicale !== null): ?><div class="text-body-secondary">R.D. <?= Html::e($euro($catasto->redditoDominicale)) ?></div><?php endif; ?>
                                    <?php elseif ($bene->isImmobileEstero()): ?>
                                        <?= Html::e($bene->getDescrizioneBreve()) ?> <span class="badge text-bg-light border">EE</span>
                                    <?php else: ?>
                                        <?= Html::e($bene->getDescrizioneBreve()) ?><div class="text-body-secondary"><?= Html::e($bene->tipoCespite?->getLabel() ?? $bene->tipoTitolo?->getLabel() ?? $bene->tipoUnita?->getLabel() ?? '') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-nowrap"><?= Html::e($bene->codiceDiritto->getLabel()) ?><div class="text-body-secondary"><?= Html::e(rtrim(rtrim(number_format($bene->quotaNumeratore, 2, ',', ''), '0'), ',')) ?>/<?= Html::e($bene->quotaDenominatore) ?></div></td>
                                <td class="text-end text-nowrap"><?= Html::e($euroIntero($bene->getValoreTotale())) ?></td>
                                <td class="small">
                                    <?php $devoluzioni = $dati->getDevoluzioni($bene); ?>
                                    <?php if (count($devoluzioni) === 0): ?><span class="text-danger"><?= Html::e($senzaDevoluzione) ?></span><?php endif; ?>
                                    <?php foreach ($devoluzioni as $devoluzione): ?>
                                        <div><?= Html::e($devoluzione->beneficiarioSuccessione->soggetto->nominativo) ?> <?= Html::e($devoluzione->getQuotaTesto()) ?> <span class="text-body-secondary"><?= Html::e($devoluzione->codiceDiritto->value) ?></span>
                                            <?php if ($devoluzione->agevolazione !== null): ?><span class="badge text-bg-light border" title="<?= Html::e($devoluzione->agevolazione->getLabel()) ?>"><?= Html::e($devoluzione->agevolazione->value) ?></span><?php endif; ?>
                                            <span class="text-body-secondary">€ <?= Html::e($euro((float) $devoluzione->valoreQuota)) ?></span></div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($modificabile): ?>
                                        <?php if ($bene->discordanzaIntestatario): ?><a href="/bene-successione/estremi/bene/<?= Html::e($bene->id) ?>" class="btn btn-sm btn-outline-secondary"><?= Html::e($azioni['provenienza']) ?></a><?php endif; ?>
                                        <a href="/bene-successione/devoluzioni/bene/<?= Html::e($bene->id) ?>" class="btn btn-sm btn-outline-primary"><?= Html::e($azioni['devoluzione']) ?></a>
                                        <a href="/bene-successione/update/bene/<?= Html::e($bene->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="/bene-successione/delete/bene/<?= Html::e($bene->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center">
        <span class="fw-semibold me-auto"><?= Html::e($sezionePassivita) ?></span>
        <?php if ($modificabile): ?><a href="/passivita-successione/create/dichiarazione/<?= Html::e($id) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiPassivita']) ?></a><?php endif; ?>
    </div>
    <?php if (count($dati->passivita) === 0): ?>
        <div class="card-body small text-body-secondary"><?= Html::e($nessunaPassivita) ?></div>
    <?php else: ?>
        <table class="table table-sm table-anagrafica mb-0">
            <thead class="table-light"><tr><th><?= Html::e($campi['tipoPassivita']) ?></th><th><?= Html::e($campi['estremiDocumento']) ?></th><th class="text-end"><?= Html::e($campi['importo']) ?></th><th><?= Html::e($campi['beneficiario']) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($dati->passivita as $passivita): ?>
                    <tr>
                        <td class="small"><?= Html::e($passivita->descrizione) ?><div class="text-body-secondary"><?= Html::e($passivita->tipoPassivita->getLabel()) ?></div></td>
                        <td class="small"><?= Html::e($passivita->estremiDocumento) ?> · <?= Html::data($passivita->dataDocumento) ?></td>
                        <td class="text-end text-nowrap"><?= Html::e($euroIntero($passivita->valore)) ?></td>
                        <td class="small"><?php foreach ($dati->getRipartizioni($passivita) as $ripartizione): ?><div><?= Html::e($ripartizione->beneficiarioSuccessione->soggetto->nominativo) ?> <?= Html::e($ripartizione->quotaNumeratore . '/' . $ripartizione->quotaDenominatore) ?></div><?php endforeach; ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($modificabile): ?>
                                <a href="/passivita-successione/update/passivita/<?= Html::e($passivita->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" action="/passivita-successione/delete/passivita/<?= Html::e($passivita->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
