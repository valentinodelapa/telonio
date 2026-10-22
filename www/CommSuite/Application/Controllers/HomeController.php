<?php

namespace CommSuite\Application\Controllers;

use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Enumerations\ModuloSuite;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Scrivania della suite: punto di accesso ai moduli applicativi.
 */
class HomeController extends SuiteController
{

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function index(): Response
    {
        $this->vars['riepilogoModuli'] = $this->generaRiepilogoModuli();
        return $this->render->generateView('home/index', $this->vars);
    }

    /**
     * I moduli disponibili possono esporre un riepilogo per la scrivania
     * con una classe Services\RiepilogoScrivania e metodo getIndicatori(Studio).
     */
    private function generaRiepilogoModuli(): array
    {
        $riepilogo = [];
        foreach (ModuloSuite::cases() as $modulo) {
            $classeRiepilogo = $modulo->value . '\\Application\\Services\\RiepilogoScrivania';
            if ($modulo->isDisponibile() && class_exists($classeRiepilogo)) {
                $riepilogo[$modulo->value] = (new $classeRiepilogo($this->dataMapper))->getIndicatori($this->studio);
            }
        }
        return $riepilogo;
    }
}
