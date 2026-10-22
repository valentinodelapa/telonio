<?php

namespace Anagrafica\Application\Permissions;

use Anagrafica\Application\Voters\SoggettoVoter;
use SismaFramework\Security\BaseClasses\BasePermission;
use SismaFramework\Security\BaseClasses\BaseVoter;

class SoggettoPermission extends BasePermission
{

    #[\Override]
    protected function callParentPermissions(): void
    {

    }

    #[\Override]
    protected function getVoter(): BaseVoter
    {
        return new SoggettoVoter();
    }
}
