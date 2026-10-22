<?php

/**
 * Download del file telematico della dichiarazione (fornitura XML SUC13).
 */
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . basename($nomeFile) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
echo $contenuto;
