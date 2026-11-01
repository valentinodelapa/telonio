<?php

/**
 * Download del documento XML della fattura elettronica.
 */
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', basename($nomeFile)) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
echo $contenuto;
