<?php

/**
 * Download del file telematico della dichiarazione (testo ASCII a record
 * fissi).
 */
header('Content-Type: text/plain; charset=US-ASCII');
header('Content-Disposition: attachment; filename="' . basename($nomeFile) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
echo $contenuto;
