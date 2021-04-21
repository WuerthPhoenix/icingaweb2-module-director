<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;
use Icinga\Module\Director\DirectorObject\IcingaObjectModifications;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;

class LiveModificationResetResolver
{
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param string $lastActivityChecksum
     * @return IcingaObjectModifications[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function cleanModificationsForDeployment($lastActivityChecksum)
    {
        $activityId = $this->db->fetchActivityLogIdByChecksum($lastActivityChecksum);
        $modifications = [];
        foreach ($this->fetchAppliedModificationsBeforeAndIncludingActivityId($activityId) as $modification) {
            $key = $modification->getUniqueKey();
            if (!isset($modifications[$key])) {
                $modifications[$key] = new IcingaObjectModifications(
                    $modification->get('icinga_object_type'),
                    $modification->get('icinga_object_name')
                );
            }
            $modifications[$key]->addModification($modification);
        }

        return $modifications;
    }

    /**
     * @return IcingaModifiedAttribute[]
     */
    public function fetchAppliedModificationsBeforeAndIncludingActivityId($id)
    {
        return IcingaModifiedAttribute::loadAll(
            $this->db,
            $this->db->select()->from('icinga_modified_attributes')
                ->where('applied = ?', 'y')
                ->where('activity_id <= ?', $id)
                ->order('id')
        );
    }

    public function createModifiedAttributesToReset(IcingaConfig $config)
    {
        $cleanupModifications = [];
        $performedModifications = $this->cleanModificationsForDeployment($config->getLastActivityChecksum());
        foreach ($performedModifications as $modification) {
            switch ($modification->objectType) {
                case 'Host':
                    $object = IcingaHost::load($modification->objectName, $this->db);
                    $dummy = IcingaObject::createByType('host');
                    break;

                default:
                    throw new \RuntimeException(sprintf('Resetting attribute for %s is not supported', $modification->objectType));
            }

            /** @var IcingaObject $object */
            foreach ($modification->modifications as $key => $value) {
                $currentValue = $object->getResolvedProperty($key);
                if ($currentValue !== $value) {
                    $dummy->set($key, $currentValue);
                }
            }
            if ($dummy->hasBeenModified()) {
                $attribute = IcingaModifiedAttribute::prepareIcingaModifiedAttributeForSingleObject($dummy);
                $attribute->set('state', 'scheduled_for_reset');
                $cleanupModifications[] = $attribute;
            }
        }

        foreach ($cleanupModifications as $modification) {
            $modification->store($this->db);
        }
    }
}
