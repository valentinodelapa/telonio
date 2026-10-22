<?php

namespace CommSuite\Application\Permissions;

use CommSuite\Application\Voters\StudioVoter;
use SismaFramework\Security\BaseClasses\BasePermission;
use SismaFramework\Security\BaseClasses\BaseVoter;

class StudioPermission extends BasePermission
{

    #[\Override]
    protected function callParentPermissions(): void
    {

    }

    #[\Override]
    protected function getVoter(): BaseVoter
    {
        return new StudioVoter();
    }
}
