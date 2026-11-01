<?php

/**
 * Download del file della comunicazione (XML in UTF-8, da firmare e
 * trasmettere).
 */
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . basename($nomeFile) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
echo $contenuto;
