<?php

use Telonio\Application\HelperClasses\Html;

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= Html::e($pageTitle ?? 'Telonio') ?> · Telonio</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="/css/suite.css">
    </head>
    <body class="bg-body-tertiary">
        <nav class="navbar navbar-expand navbar-dark bg-dark suite-navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-semibold" href="/home/index"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Telonio</a>
                <?php if ($utente->isAmministratore()): ?>
                    <a class="navbar-text text-white-50 text-decoration-none d-none d-md-inline" href="/studio/update"><i class="bi bi-building me-1"></i><?= Html::e($studio->denominazione) ?></a>
                <?php else: ?>
                    <span class="navbar-text text-white-50 d-none d-md-inline"><i class="bi bi-building me-1"></i><?= Html::e($studio->denominazione) ?></span>
                <?php endif; ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?= Html::e($utente->getNomeCompleto()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text small text-body-secondary">
                                    <?= Html::e($utente->email) ?>
                                    <span class="d-block"><?= Html::e($utente->ruolo->getLabel()) ?></span>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/profilo/update"><i class="bi bi-person-gear me-2"></i>Profilo personale</a></li>
                            <li><a class="dropdown-item" href="/profilo/password"><i class="bi bi-key me-2"></i>Cambio password</a></li>
                            <?php if ($utente->isAmministratore()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/studio/update"><i class="bi bi-building-gear me-2"></i>Dati dello studio</a></li>
                                <li><a class="dropdown-item" href="/utente/index"><i class="bi bi-people me-2"></i>Utenti dello studio</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="/auth/logout">
                                    <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Esci</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="d-flex suite-wrapper">
            <aside class="suite-sidebar bg-white border-end">
                <nav class="nav flex-column p-2">
                    <a class="nav-link <?= $moduloAttivo === null ? 'active' : '' ?>" href="/home/index"><i class="bi bi-house me-2"></i>Scrivania</a>
                    <div class="suite-sidebar-title">Applicativi</div>
                    <?php foreach ($moduli as $modulo): ?>
                        <?php if ($modulo->isDisponibile()): ?>
                            <a class="nav-link <?= $moduloAttivo === $modulo ? 'active' : '' ?>" href="<?= Html::e($modulo->getUrl()) ?>"><i class="bi <?= Html::e($modulo->getIcona()) ?> me-2"></i><?= Html::e($modulo->getLabel()) ?></a>
                        <?php else: ?>
                            <span class="nav-link disabled" title="In arrivo"><i class="bi <?= Html::e($modulo->getIcona()) ?> me-2"></i><?= Html::e($modulo->getLabel()) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <main class="flex-grow-1 p-4 suite-main">
                <?php if (empty($briciole) === false): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb small mb-2">
                            <?php foreach ($briciole as $etichetta => $url): ?>
                                <?php if ($url === null): ?>
                                    <li class="breadcrumb-item active" aria-current="page"><?= Html::e($etichetta) ?></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item"><a href="<?= Html::e($url) ?>"><?= Html::e($etichetta) ?></a></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                <?php endif; ?>
                <?php foreach ($messaggiFlash as $messaggio): ?>
                    <div class="alert alert-<?= Html::e($messaggio['tipo']) ?> alert-dismissible fade show" role="alert">
                        <?= Html::e($messaggio['testo']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
                    </div>
                <?php endforeach; ?>
