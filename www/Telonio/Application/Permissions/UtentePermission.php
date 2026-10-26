<?php

namespace Telonio\Application\Permissions;

use Telonio\Application\Voters\UtenteVoter;
use SismaFramework\Security\BaseClasses\BasePermission;
use SismaFramework\Security\BaseClasses\BaseVoter;

class UtentePermission extends BasePermission
{

    #[\Override]
    protected function callParentPermissions(): void
    {

    }

    #[\Override]
    protected function getVoter(): BaseVoter
    {
        return new UtenteVoter();
    }
}
