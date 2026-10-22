<?php

use CommSuite\Application\HelperClasses\Html;

?>
<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= Html::e($pageTitle) ?> · CommSuite</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="/css/suite.css">
    </head>
    <body class="bg-body-tertiary">
        <div class="container login-wrapper d-flex align-items-center justify-content-center">
            <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1"><i class="bi bi-grid-3x3-gap-fill me-2 text-primary"></i>CommSuite</h1>
                    <p class="text-body-secondary small mb-4"><?= Html::e($sottotitolo) ?></p>
                    <?php if ($accessoFallito): ?>
                        <div class="alert alert-danger py-2 small"><?= Html::e($messaggioAccessoFallito) ?></div>
                    <?php endif; ?>
                    <form method="post" action="/auth/login">
                        <input type="hidden" name="submitted" value="1">
                        <input type="hidden" name="<?= Html::e(\Config\CSRF_TOKEN_KEY) ?>" value="<?= Html::e($csrfToken) ?>">
                        <div class="mb-3">
                            <label for="identificativo" class="form-label"><?= Html::e($etichettaUtente) ?></label>
                            <input type="text" class="form-control" id="identificativo" name="<?= Html::e(\Config\AUTHENTICATION_IDENTIFIER_FIELD) ?>" value="<?= Html::e($identificativo) ?>" autocomplete="username" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label"><?= Html::e($etichettaPassword) ?></label>
                            <input type="password" class="form-control" id="password" name="<?= Html::e(\Config\AUTHENTICATION_PASSWORD_FIELD) ?>" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= Html::e($pulsanteAccedi) ?></button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
