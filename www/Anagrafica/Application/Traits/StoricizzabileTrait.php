<?php

namespace Anagrafica\Application\Traits;

trait StoricizzabileTrait
{

    public function isVigenteAl(\DateTimeInterface $data): bool
    {
        $giorno = $data->format('Y-m-d');
        return ($this->validoDal->format('Y-m-d') <= $giorno) &&
                (($this->validoAl === null) || ($this->validoAl->format('Y-m-d') >= $giorno));
    }

    public function isAperto(): bool
    {
        return $this->validoAl === null;
    }
}
