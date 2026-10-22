<?php

/**
 * Download del file telematico (testo ASCII a record fissi).
 */
header('Content-Type: text/plain; charset=US-ASCII');
header('Content-Disposition: attachment; filename="' . basename($invio->nomeFile) . '"');
header('Content-Length: ' . strlen($invio->contenuto));
header('X-Content-Type-Options: nosniff');
echo $invio->contenuto;
