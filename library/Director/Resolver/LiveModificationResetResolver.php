<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;
use Icinga\Module\Director\DirectorObject\IcingaObjectModifications;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
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
     * @param IcingaModifiedAttribute[] $modifiedAttributes
     * @return IcingaObjectModifications[]
     */
    public function cleanModifications(array $modifiedAttributes)
    {
        $modifications = [];
        foreach ($modifiedAttributes as $modification) {
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
            $this->db->getDbAdapter()->select()->from('icinga_modified_attribute')
                ->where('state = ?', 'applied')
                ->where('activity_id <= ?', $id)
                ->order('id')
        );
    }

    /**
     * @param IcingaModifiedAttribute[] $modifiedAttributes
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function scheduleAttributesToBeResetted(array $modifiedAttributes)
    {
        $cleanupModifications = [];
        $performedModifications = $this->cleanModifications($modifiedAttributes);
        foreach ($performedModifications as $modification) {
            switch ($modification->objectType) {
                case 'Host':
                    $object = IcingaHost::load($modification->objectName, $this->db);
                    $dummy = IcingaObject::createByType('host', [
                        'object_name' => $object->get('object_name'),
                    ], $this->db);
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
            if ($dummy->getModifiedProperties()) {
                // TODO: api props only -> toApiObject -> remove object_name
                $attribute = IcingaModifiedAttribute::prepareIcingaModifiedAttributeForSingleObject($dummy);
                $attribute->set('state', 'scheduled_for_reset');
                $attribute->set('action', 'modify');
                $cleanupModifications[] = $attribute;
            }
        }

        foreach ($cleanupModifications as $modification) {
            $modification->store($this->db);
        }
    }
}
