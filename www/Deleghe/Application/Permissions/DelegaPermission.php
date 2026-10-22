<?php

namespace Deleghe\Application\Permissions;

use Deleghe\Application\Voters\DelegaVoter;
use SismaFramework\Security\BaseClasses\BasePermission;
use SismaFramework\Security\BaseClasses\BaseVoter;

class DelegaPermission extends BasePermission
{

    #[\Override]
    protected function callParentPermissions(): void
    {

    }

    #[\Override]
    protected function getVoter(): BaseVoter
    {
        return new DelegaVoter();
    }
}
