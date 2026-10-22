<?php

namespace Anagrafica\Application\Permissions;

use Anagrafica\Application\Voters\ImmobileVoter;
use SismaFramework\Security\BaseClasses\BasePermission;
use SismaFramework\Security\BaseClasses\BaseVoter;

class ImmobilePermission extends BasePermission
{

    #[\Override]
    protected function callParentPermissions(): void
    {

    }

    #[\Override]
    protected function getVoter(): BaseVoter
    {
        return new ImmobileVoter();
    }
}
