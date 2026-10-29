<?php

namespace Telonio\Application\Controllers;

use Telonio\Application\BaseClasses\SuiteController;
use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\Services\Aggiornamenti\ControlloAggiornamenti;
use SismaFramework\Core\HelperClasses\Debugger;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Scrivania della suite: punto di accesso ai moduli applicativi.
 */
class HomeController extends SuiteController
{

    public function __construct(
            DataMapper $dataMapper = new DataMapper(),
            ?Debugger $debugger = null,
            private ControlloAggiornamenti $controlloAggiornamenti = new ControlloAggiornamenti(),
    ) {
        parent::__construct($dataMapper, $debugger);
    }

    #[\Override]
    protected function getModulo(): ?ModuloSuite
    {
        return null;
    }

    public function index(): Response
    {
        $this->vars['riepilogoModuli'] = $this->generaRiepilogoModuli();
        $this->vars['aggiornamento'] = $this->utente->isAmministratore() ? $this->controlloAggiornamenti->verificaConCache() : null;
        $this->vars['canaleAggiornamenti'] = $this->controlloAggiornamenti->getCanale();
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
