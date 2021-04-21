<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;

class LiveCreation
{
    protected $coreApi;
    protected $db;

    public function __construct(Db $db, CoreApi $coreApi)
    {
        $this->coreApi = $coreApi;
        $this->db = $db;
    }

    /**
     * @return IcingaModifiedAttribute[]
     */
    public function fetchPendingModifications()
    {
        return IcingaModifiedAttribute::loadAll(
            $this->db,
            $this->db->getDbAdapter()
                ->select()->from('icinga_modified_attribute')
                ->where('state != ?', 'applied')
                ->order('state')
                ->order('id')
        );
    }

    public function applyModification(IcingaModifiedAttribute $modifiedAttribute)
    {
        try {
            return $this->coreApi->sendModification($modifiedAttribute);
        } catch (Exception $e) {
            return false;
        }
    }

    public function run()
    {
        //while (true) {
        foreach ($this->fetchPendingModifications() as $modification) {
            // DirectorActivityLog::loadWithAutoIncId($modification->get('id'));
            // TODO update live_modification
            if ($this->applyModification($modification)) {
                if ($modification->get('state') === 'scheduled_for_reset') {
                    $modification->delete();
                } else {
                    $modification->set('state', 'applied');
                    $modification->set('ts_applied', DaemonUtil::timestampWithMilliseconds());
                    $modification->store();
                }
            } else {
                $modification->delete();
            }
        }
    }
}
