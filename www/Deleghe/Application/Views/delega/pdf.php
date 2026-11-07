<?php

/**
 * PDF della delega (PdfF24) da scaricare.
 */
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($nomeFile)) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $contenuto;
