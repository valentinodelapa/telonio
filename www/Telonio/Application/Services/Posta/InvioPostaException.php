<?php

namespace Telonio\Application\Services\Posta;

/**
 * Invio non riuscito: casella non configurata, destinatario non valido o
 * errore del server SMTP (il messaggio è quello da mostrare all'utente).
 */
class InvioPostaException extends \RuntimeException
{

}
