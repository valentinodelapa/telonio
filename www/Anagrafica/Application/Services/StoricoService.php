<?php

namespace Anagrafica\Application\Services;

use Anagrafica\Application\Interfaces\Storicizzabile;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Mantiene coerente la successione delle versioni di un dato storicizzato.
 *
 * L'"ambito" è l'insieme delle versioni che non possono sovrapporsi nel
 * tempo (es. le sedi legali di un soggetto, oppure i dati fiscali). Una
 * nuova versione può essere inserita anche nel passato: la precedente viene
 * chiusa il giorno prima della nuova decorrenza e, se esiste una versione
 * successiva, la nuova viene chiusa il giorno prima di quella.
 */
class StoricoService
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @param iterable<Storicizzabile&BaseEntity> $ambito
     * @return string|null messaggio d'errore, null se la versione è coerente
     */
    public function verifica(Storicizzabile&BaseEntity $versione, iterable $ambito): ?string
    {
        $decorrenza = self::giorno($versione->validoDal);
        if (($versione->validoAl !== null) && (self::giorno($versione->validoAl) < $decorrenza)) {
            return 'La fine della validità non può precedere la decorrenza.';
        }
        $altre = $this->altreVersioni($versione, $ambito);
        foreach ($altre as $altra) {
            if (self::giorno($altra->validoDal) === $decorrenza) {
                return 'Esiste già una versione con decorrenza ' . $altra->validoDal->format('d/m/Y') . ': modificala invece di crearne una nuova.';
            }
        }
        $successiva = $this->getSuccessiva($versione, $altre);
        if (($successiva !== null) && ($versione->validoAl !== null) && (self::giorno($versione->validoAl) >= self::giorno($successiva->validoDal))) {
            return 'La validità si sovrappone alla versione successiva, in vigore dal ' . $successiva->validoDal->format('d/m/Y') . '.';
        }
        return null;
    }

    /**
     * Salva la versione adeguando le date delle versioni adiacenti.
     *
     * @param iterable<Storicizzabile&BaseEntity> $ambito
     * @param SismaDate|null $decorrenzaOriginale in caso di rettifica, la
     *        decorrenza prima della modifica: se la versione precedente era
     *        contigua, resta contigua anche alla nuova decorrenza
     */
    public function registra(Storicizzabile&BaseEntity $versione, iterable $ambito, ?SismaDate $decorrenzaOriginale = null): void
    {
        $altre = $this->altreVersioni($versione, $ambito);
        $precedente = $this->getPrecedente($versione, $altre);
        $successiva = $this->getSuccessiva($versione, $altre);
        $primaTransazione = $this->dataMapper->startTransaction();
        if (($successiva !== null) && ($versione->validoAl === null)) {
            $versione->validoAl = self::giornoPrecedente($successiva->validoDal);
        }
        if (($precedente !== null) && $this->deveChiudersi($precedente, $versione, $decorrenzaOriginale)) {
            $precedente->validoAl = self::giornoPrecedente($versione->validoDal);
            $this->dataMapper->save($precedente);
        }
        $this->dataMapper->save($versione);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    private function deveChiudersi(BaseEntity $precedente, BaseEntity $versione, ?SismaDate $decorrenzaOriginale): bool
    {
        if (($precedente->validoAl === null) || (self::giorno($precedente->validoAl) >= self::giorno($versione->validoDal))) {
            return true;
        }
        return ($decorrenzaOriginale !== null) && (self::giorno($precedente->validoAl) === self::giorno(self::giornoPrecedente($decorrenzaOriginale)));
    }

    /**
     * Elimina la versione; se la precedente era contigua, ne estende la
     * validità fino alla fine di quella eliminata.
     *
     * @param iterable<Storicizzabile&BaseEntity> $ambito
     */
    public function annulla(Storicizzabile&BaseEntity $versione, iterable $ambito): void
    {
        $precedente = $this->getPrecedente($versione, $this->altreVersioni($versione, $ambito));
        $primaTransazione = $this->dataMapper->startTransaction();
        if (($precedente !== null) && ($precedente->validoAl !== null) && (self::giorno($precedente->validoAl) === self::giorno(self::giornoPrecedente($versione->validoDal)))) {
            $precedente->validoAl = $versione->validoAl;
            $this->dataMapper->save($precedente);
        }
        $this->dataMapper->delete($versione);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    private function altreVersioni(BaseEntity $versione, iterable $ambito): array
    {
        $altre = [];
        foreach ($ambito as $altra) {
            if ((isset($versione->id) === false) || ($altra->id !== $versione->id)) {
                $altre[] = $altra;
            }
        }
        return $altre;
    }

    private function getPrecedente(BaseEntity $versione, array $altre): ?BaseEntity
    {
        $precedente = null;
        foreach ($altre as $altra) {
            if ((self::giorno($altra->validoDal) < self::giorno($versione->validoDal)) &&
                    (($precedente === null) || (self::giorno($altra->validoDal) > self::giorno($precedente->validoDal)))) {
                $precedente = $altra;
            }
        }
        return $precedente;
    }

    private function getSuccessiva(BaseEntity $versione, array $altre): ?BaseEntity
    {
        $successiva = null;
        foreach ($altre as $altra) {
            if ((self::giorno($altra->validoDal) > self::giorno($versione->validoDal)) &&
                    (($successiva === null) || (self::giorno($altra->validoDal) < self::giorno($successiva->validoDal)))) {
                $successiva = $altra;
            }
        }
        return $successiva;
    }

    private static function giorno(\DateTimeInterface $data): string
    {
        return $data->format('Y-m-d');
    }

    private static function giornoPrecedente(\DateTimeInterface $data): SismaDate
    {
        return new SismaDate($data->format('Y-m-d') . ' -1 day');
    }
}
