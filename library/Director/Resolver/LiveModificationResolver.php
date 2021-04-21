<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;

class LiveModificationResolver
{
    const HOST_DENIED_PROPERTIES = [
        'groups',
        'zone'
    ];

    protected $db;

    public function __construct(Db $db = null) {
        $this->db = $db;
    }

    public function canBeAppliedLive(IcingaObject $object)
    {
        if ($object->hasBeenModified()) {
            if ($object->isObject() && $object instanceof IcingaHost) {
                $modifiedProperties = $object->getModifiedProperties();
                foreach (self::HOST_DENIED_PROPERTIES as $deniedProperty) {
                    if (in_array($deniedProperty, $modifiedProperties)) {
                        return false;
                    }
                }
                return true;
            } elseif ($object->isObject() && $object instanceof IcingaService) {
                return true;
            }

            return false;
        }
        return true;
    }

    /**
     * @param IcingaObject $object
     * @return IcingaModifiedAttribute[]
     */
    public function insertSingleObjectModification(IcingaModifiedAttribute $object)
    {
        $object->set('activity_id', $this->getActivityId());
        $object->store($this->db);
    }

    public function getActivityId() {
        $activityLog = DirectorActivityLog::loadLatest($this->db);
        return $activityLog->getId();
    }


}
