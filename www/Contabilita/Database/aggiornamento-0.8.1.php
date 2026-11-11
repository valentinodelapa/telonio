<?php

/**
 * Contabilita 0.8.1 - migrazione dei dati, eseguita da "sisma aggiorna-database"
 * dopo gli script SQL di tutti i moduli: partite aperte delle registrazioni
 * già presenti, con gli abbinamenti dalla più vecchia segnati come da
 * verificare nello scadenzario (Services\MigrazionePartite). Idempotente:
 * le registrazioni che hanno già partite o abbinamenti non sono toccate.
 */

use Contabilita\Application\Services\MigrazionePartite;

return function (callable $messaggio): void {
    (new MigrazionePartite())->eseguiTutte($messaggio);
};
