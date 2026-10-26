<?php

use Telonio\Application\HelperClasses\Html;
use Telonio\Application\HelperClasses\Layout;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\EsitoControllo;

$id = $delega->id;
$modificabile = $delega->isModificabile();
$originiDelega = [Ravvedimento::ORIGINE, SanzioneCivile::ORIGINE];
$campoCsrf = '<input type="hidden" name="' . Html::e(\Config\CSRF_TOKEN_KEY) . '" value="' . Html::e($csrfToken) . '">';
require __DIR__ . '/../commonParts/riferimentiVersamento.php';

require Layout::percorso('header');
?>
<div class="d-flex flex-wrap align-items-start gap-3 mb-3">
    <div class="me-auto">
        <h1 class="h3 mb-1">
            <i class="bi bi-credit-card-2-front me-2 text-primary"></i><?= Html::e($pageTitle) ?>
            <span class="badge <?= Html::e($delega->stato->getClasseBadge()) ?> align-middle fs-6 fw-normal ms-1"><?= Html::e($delega->stato->getLabel()) ?></span>
        </h1>
        <div class="text-body-secondary">
            <?= Html::e($contribuente) ?>: <a href="/contribuente/show/soggetto/<?= Html::e($delega->soggetto->id) ?>"><?= Html::e($delega->soggetto->nominativo) ?></a>
            <span class="font-monospace small"><?= Html::e($delega->soggetto->codiceFiscale) ?></span>
            <?= $delega->descrizione !== null ? ' · ' . Html::e($delega->descrizione) : '' ?>
        </div>
        <div class="small text-body-secondary">
            <?= Html::e($delega->modello->getLabel()) ?><?= count($esito->modelli) > 1 ? ' (' . count($esito->modelli) . ' ' . Html::e($modelliF24) . ')' : '' ?>
            <?php if ($delega->contoAddebito !== null): ?> · <?= Html::e($campi['contoAddebito']) ?>: <span class="font-monospace"><?= Html::e($delega->contoAddebito->getIbanFormattato()) ?></span><?php endif; ?>
            <?php if ($delega->invioTelematico !== null): ?> · <a href="/invio-telematico/show/invio/<?= Html::e($delega->invioTelematico->id) ?>"><?= Html::e($invio) ?> <?= Html::e($delega->invioTelematico->dataCreazione->format('d/m/Y H:i')) ?></a><?php endif; ?>
        </div>
        <?php if ($delega->codiceFiscaleCoobbligato !== null): ?>
            <div class="small text-body-secondary"><?= Html::e($campi['codiceIdentificativo']) ?> <?= Html::e($delega->codiceIdentificativo?->getLabel() ?? '') ?>: <span class="font-monospace"><?= Html::e($delega->codiceFiscaleCoobbligato) ?></span></div>
        <?php endif; ?>
        <?php if ($delega->dataQuietanza !== null): ?>
            <div class="small text-success"><i class="bi bi-check-circle me-1"></i><?= Html::e($quietanza) ?> <?= Html::data($delega->dataQuietanza) ?><?= $delega->protocollo !== null ? ' · ' . Html::e($delega->protocollo) : '' ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/delega/stampa/delega/<?= Html::e($id) ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer me-1"></i><?= Html::e($azioni['stampa']) ?></a>
        <a href="/delega/duplica/delega/<?= Html::e($id) ?>" class="btn btn-outline-secondary"><i class="bi bi-copy me-1"></i><?= Html::e($azioni['duplica']) ?></a>
        <?php if ($modificabile && ($delega->modello !== ModelloF24::elide)): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-clock-history me-1"></i><?= Html::e($azioni['ravvedimento']) ?></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ([SezioneF24::erario, SezioneF24::regioni, SezioneF24::tributiLocali] as $sezioneRavvedimento): ?>
                        <li><a class="dropdown-item" href="/ravvedimento/create/delega/<?= Html::e($id) ?>/sezione/<?= Html::e($sezioneRavvedimento->value) ?>"><?= Html::e($sezioneRavvedimento->getLabel()) ?></a></li>
                    <?php endforeach; ?>
                    <?php foreach ([SezioneF24::inps, SezioneF24::inail] as $sezioneSanzioni): ?>
                        <?php if ($delega->modello->ammetteSezione($sezioneSanzioni)): ?>
                            <li><a class="dropdown-item" href="/sanzione-civile/create/delega/<?= Html::e($id) ?>/sezione/<?= Html::e($sezioneSanzioni->value) ?>"><?= Html::e($sanzioniCiviliVoce) ?> <?= Html::e($sezioneSanzioni->getLabel()) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($modificabile): ?>
            <a href="/delega/update/delega/<?= Html::e($id) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= Html::e($azioni['modifica']) ?></a>
            <form method="post" action="/delega/delete/delega/<?= Html::e($id) ?>" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
        <?php endif; ?>
    </div>
</div>

<?php if (count($esito->errori) > 0): ?>
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1"><i class="bi bi-x-octagon me-1"></i><?= Html::e($errori) ?></div>
        <ul class="mb-0 small"><?php foreach ($esito->errori as $errore): ?><li><?= Html::e($errore) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if (count($esito->avvisi) > 0): ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i><?= Html::e($avvisi) ?></div>
        <ul class="mb-0 small"><?php foreach ($esito->avvisi as $avviso): ?><li><?= Html::e($avviso) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= Html::e($riepilogo) ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th><?= Html::e($campi['sezione']) ?></th><th class="text-end"><?= Html::e($campi['debito']) ?></th><th class="text-end"><?= Html::e($campi['credito']) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($sezioni as $sezione): ?>
                            <?php $totaliSezione = $esito->totaliSezione[$sezione->value]; ?>
                            <?php if ($totaliSezione['debito'] + $totaliSezione['credito'] > 0): ?>
                                <tr><td><?= Html::e($sezione->getLabel()) ?></td><td class="text-end"><?= EsitoControllo::euro($totaliSezione['debito']) ?></td><td class="text-end"><?= $totaliSezione['credito'] > 0 ? EsitoControllo::euro($totaliSezione['credito']) : '' ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td><?= Html::e($campi['saldoFinale']) ?></td><td class="text-end" colspan="2">€ <?= EsitoControllo::euro($esito->getSaldo()) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><?= Html::e($campi['stato']) ?></div>
            <div class="card-body d-flex flex-column gap-2">
                <?php foreach ($delega->stato->getPassaggi() as $passaggio): ?>
                    <form method="post" action="/delega/stato/delega/<?= Html::e($id) ?>/stato/<?= Html::e($passaggio->value) ?>" class="d-flex flex-wrap align-items-end gap-2">
                        <?= $campoCsrf ?>
                        <?php if ($passaggio === StatoDelega::quietanzata): ?>
                            <div><label class="form-label small mb-0" for="dataQuietanza"><?= Html::e($campi['dataQuietanza']) ?></label><input type="date" class="form-control form-control-sm" id="dataQuietanza" name="dataQuietanza" value="<?= Html::data($delega->scadenza, 'Y-m-d') ?>" required title="<?= Html::e($aiutoQuietanza) ?>"></div>
                            <div class="flex-grow-1"><label class="form-label small mb-0" for="protocollo"><?= Html::e($campi['protocollo']) ?></label><input type="text" class="form-control form-control-sm" id="protocollo" name="protocollo" maxlength="50"></div>
                        <?php endif; ?>
                        <?php $avanti = array_search($passaggio, StatoDelega::cases(), true) > array_search($delega->stato, StatoDelega::cases(), true); ?>
                        <button type="submit" class="btn btn-sm <?= $avanti ? 'btn-primary' : 'btn-outline-secondary' ?>" <?= ($passaggio === StatoDelega::confermata && $avanti && $esito->isConfermabile() === false) ? 'disabled' : '' ?>><?= Html::e($passaggio->getAzione($delega->stato)) ?></button>
                    </form>
                <?php endforeach; ?>
                <?php if ($delega->codiceUfficio !== null || $delega->codiceAtto !== null || $delega->identificativoOperazione !== null || $delega->detrazioneImu !== null || $delega->note !== null): ?>
                    <hr class="my-1">
                    <div class="small text-body-secondary">
                        <?php foreach (['codiceUfficio', 'codiceAtto', 'identificativoOperazione'] as $campo): ?>
                            <?= $delega->$campo !== null ? Html::e($campi[$campo]) . ': <span class="font-monospace">' . Html::e($delega->$campo) . '</span><br>' : '' ?>
                        <?php endforeach; ?>
                        <?= $delega->detrazioneImu !== null ? Html::e($campi['detrazioneImu']) . ': ' . Html::e(Importo::euro($delega->detrazioneImu)) . '<br>' : '' ?>
                        <?= $delega->note !== null ? nl2br(Html::e($delega->note)) : '' ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (count($daVersare) > 0): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
        <span class="me-auto"><i class="bi bi-inbox me-1"></i><?= Html::e(sprintf($testoDaVersare, count($daVersare), EsitoControllo::euro(array_sum(array_map(fn($versamento) => EsitoControllo::centesimi($versamento->importoDebito) - EsitoControllo::centesimi($versamento->importoCredito), $daVersare))))) ?></span>
        <form method="post" action="/delega/aggiungi-da-versare/delega/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungiDaVersare']) ?></button></form>
    </div>
<?php endif; ?>

<?php if (count($creditiDisponibili) > 0): ?>
    <div class="card shadow-sm mb-3 border-success">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><i class="bi bi-piggy-bank me-2 text-success"></i><?= Html::e($sezioneCreditiDisponibili) ?></span>
            <form method="post" action="/delega/compensa/delega/<?= Html::e($id) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-success"><?= Html::e($azioni['compensaTutti']) ?></button></form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($creditiDisponibili as ['credito' => $credito, 'residuo' => $residuo, 'capienza' => $capienza]): ?>
                        <tr>
                            <td><span class="badge text-bg-light border"><?= Html::e($credito->sezione->getLabel()) ?></span> <span class="font-monospace fw-semibold"><?= Html::e($credito->getCodice()) ?></span> <?= Html::e($credito->annoRiferimento ?? '') ?></td>
                            <td class="small"><?= Html::e($credito->descrizione ?? $credito->codiceTributo?->descrizione ?? '') ?></td>
                            <td class="text-end text-nowrap small"><?= Html::e($colonnaResiduo) ?> <strong>€ <?= EsitoControllo::euro($residuo) ?></strong></td>
                            <td class="text-end">
                                <form method="post" action="/credito/compensa/delega/<?= Html::e($id) ?>/credito/<?= Html::e($credito->id) ?>" class="d-inline-flex gap-1">
                                    <?= $campoCsrf ?>
                                    <input type="text" class="form-control form-control-sm text-end" name="importo" value="<?= EsitoControllo::euro(min($residuo, $capienza)) ?>" inputmode="decimal" style="width: 8rem" aria-label="<?= Html::e($campi['importoCredito']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"><?= Html::e($azioni['compensa']) ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (count($sanzioniCivili) > 0): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-exclamation me-2 text-primary"></i><?= Html::e($sezioneSanzioniCivili) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <tbody>
                    <?php foreach ($sanzioniCivili as $sanzioneCivile): ?>
                        <tr>
                            <td><span class="badge text-bg-light border"><?= Html::e($sanzioneCivile->sezione->getLabel()) ?></span> <?= Html::e($sanzioneCivile->riferimento) ?></td>
                            <td class="small text-body-secondary"><?= Html::data($sanzioneCivile->scadenzaOriginale) ?> · <?= Html::e($sanzioneCivile->giorniRitardo) ?> <?= Html::e($giorniRitardo) ?> · <?= Html::e($sanzioneCivile->regime->getLabel()) ?><?= $sanzioneCivile->tettoApplicato ? ' · ' . Html::e($tetto) : '' ?></td>
                            <td class="text-end text-nowrap" title="<?= Html::e(implode('; ', array_map(fn($p) => Html::data(new DateTime($p['dal'])) . '-' . Html::data(new DateTime($p['al'])) . ': ' . $p['giorni'] . ' gg al ' . $p['tasso'] . '%', $sanzioneCivile->getPeriodi()))) ?>">€ <?= Html::e(Importo::euro($sanzioneCivile->importoContributi)) ?> → <strong><?= Html::e(Importo::euro($sanzioneCivile->sanzione)) ?></strong></td>
                            <td class="small"><?= $sanzioneCivile->causale === null ? Html::e($soloProspetto) : Html::e($campi['causale']) . ' ' . Html::e($sanzioneCivile->causale) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($modificabile): ?>
                                    <a href="/sanzione-civile/update/sanzione-civile/<?= Html::e($sanzioneCivile->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/sanzione-civile/delete/sanzione-civile/<?= Html::e($sanzioneCivile->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (count($ravvedimenti) > 0): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i><?= Html::e($sezioneRavvedimenti) ?></div>
        <div class="table-responsive">
            <table class="table table-sm table-anagrafica mb-0">
                <thead><tr><th><?= Html::e($campi['codiceTributo']) ?></th><th><?= Html::e($campi['scadenzaOriginale']) ?></th><th class="text-end"><?= Html::e($campi['importoTributo']) ?></th><th></th><th class="text-end"><?= Html::e($sanzione) ?></th><th class="text-end"><?= Html::e($interessi) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($ravvedimenti as $ravvedimento): ?>
                        <tr>
                            <td class="font-monospace fw-semibold"><?= Html::e($ravvedimento->codiceTributo->codice) ?> <span class="small text-body-secondary"><?= Html::e($ravvedimento->annoRiferimento ?? '') ?></span></td>
                            <td><?= Html::data($ravvedimento->scadenzaOriginale) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($ravvedimento->importoTributo)) ?></td>
                            <td class="small text-body-secondary"><?= Html::e($ravvedimento->giorniRitardo) ?> <?= Html::e($giorniRitardo) ?> · <?= Html::e(number_format($ravvedimento->percentualeSanzione, 4, ',', '.')) ?>% · <?= Html::e($riduzione) ?> 1/<?= Html::e($ravvedimento->riduzione) ?></td>
                            <td class="text-end"><?= Html::e(Importo::euro($ravvedimento->sanzione)) ?></td>
                            <td class="text-end" title="<?= Html::e(implode('; ', array_map(fn($p) => Html::data(new DateTime($p['dal'])) . '-' . Html::data(new DateTime($p['al'])) . ': ' . $p['giorni'] . ' gg al ' . $p['tasso'] . '%', $ravvedimento->getPeriodiInteressi()))) ?>"><?= Html::e(Importo::euro($ravvedimento->interessi)) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($modificabile): ?>
                                    <a href="/ravvedimento/update/ravvedimento/<?= Html::e($ravvedimento->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                    <form method="post" action="/ravvedimento/delete/ravvedimento/<?= Html::e($ravvedimento->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($sezioni as $sezione): ?>
    <?php $righeSezione = $righe[$sezione->value]; ?>
    <?php if ((count($righeSezione) === 0) && (($modificabile === false) || ($delega->modello->ammetteSezione($sezione) === false))) continue; ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center">
            <span class="fw-semibold me-auto"><i class="bi <?= Html::e($sezione->getIcona()) ?> me-2 text-primary"></i><?= Html::e($sezione->getLabel()) ?></span>
            <?php if ($modificabile): ?>
                <a href="/versamento/aggiungi/delega/<?= Html::e($id) ?>/sezione/<?= Html::e($sezione->value) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i><?= Html::e($azioni['aggiungi']) ?></a>
            <?php endif; ?>
        </div>
        <?php if (count($righeSezione) === 0): ?>
            <div class="card-body small text-body-secondary py-2"><?= Html::e($nessunRigo) ?></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-anagrafica mb-0">
                    <thead>
                        <tr>
                            <th><?= Html::e($sezione->getSezioneCodici() !== null ? $campi['codiceTributo'] : $campi['causale']) ?></th>
                            <th><?= Html::e($campi['descrizione']) ?></th>
                            <th class="text-end"><?= Html::e($campi['debito']) ?></th>
                            <th class="text-end"><?= Html::e($campi['credito']) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($righeSezione as $riga): ?>
                            <tr>
                                <td class="font-monospace fw-semibold"><?= Html::e($riga->getCodice()) ?></td>
                                <td class="small">
                                    <?= Html::e($riga->descrizione ?? $riga->getDescrizione()) ?>
                                    <div class="text-body-secondary"><?= Html::e($riferimenti($riga)) ?></div>
                                    <?php if ($riga->credito !== null): ?>
                                        <span class="badge text-bg-success-subtle border border-success-subtle text-success-emphasis"><i class="bi bi-piggy-bank me-1"></i><?= Html::e($utilizzoCredito) ?></span>
                                    <?php endif; ?>
                                    <?php if ($riga->isGenerata()): ?>
                                        <span class="badge text-bg-light border" title="<?= Html::e($rigoGenerato . ' ' . $riga->origine . ' (' . $riga->riferimentoOrigine . ')') ?>"><i class="bi bi-gear me-1"></i><?= Html::e($riga->origine) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro($riga->importoDebito, true)) ?></td>
                                <td class="text-end text-nowrap"><?= Html::e(Importo::euro($riga->importoCredito, true)) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($riga->credito === null): ?>
                                        <?php require __DIR__ . '/../commonParts/pulsantiCopia.php'; ?>
                                    <?php endif; ?>
                                    <?php if ($modificabile): ?>
                                        <?php if (($riga->isGenerata() === false) && ($riga->credito === null)): ?>
                                            <a href="/versamento/update/versamento/<?= Html::e($riga->id) ?>" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['modifica']) ?>"><i class="bi bi-pencil"></i></a>
                                        <?php endif; ?>
                                        <?php if (($riga->credito === null) && (in_array($riga->origine, $originiDelega, true) === false)): ?>
                                            <form method="post" action="/versamento/rimuovi/versamento/<?= Html::e($riga->id) ?>" class="d-inline"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= Html::e($azioni['rimuovi']) ?>"><i class="bi bi-box-arrow-up"></i></button></form>
                                        <?php endif; ?>
                                        <form method="post" action="/versamento/delete/versamento/<?= Html::e($riga->id) ?>" class="d-inline" data-conferma="<?= Html::e($confermaElimina) ?>"><?= $campoCsrf ?><button type="submit" class="btn btn-sm btn-outline-danger" title="<?= Html::e($azioni['elimina']) ?>"><i class="bi bi-trash"></i></button></form>
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
<?php require Layout::percorso('footer'); ?>
