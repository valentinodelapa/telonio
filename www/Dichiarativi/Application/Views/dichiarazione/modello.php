<?php

/**
 * Dichiarazione sul modello ufficiale in PDF (PdfDichiarazione), aperta nel
 * browser.
 */
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename($nomeFile)) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $contenuto;
