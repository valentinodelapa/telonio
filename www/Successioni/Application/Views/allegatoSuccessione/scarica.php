<?php

/**
 * Download di un allegato della dichiarazione (PDF/A o TIFF).
 */
header('Content-Type: ' . $tipoMime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($nomeFile)) . '"');
header('Content-Length: ' . strlen($contenuto));
header('X-Content-Type-Options: nosniff');
echo $contenuto;
