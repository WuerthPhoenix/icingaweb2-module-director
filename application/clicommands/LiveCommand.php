<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectCommand;
use Icinga\Module\Director\Daemon\DaemonUtil;
use Icinga\Module\Director\Daemon\LiveCreation;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;

class LiveCommand extends ObjectCommand
{
    public function sendAction()
    {
        $liveCreation = new LiveCreation($this->db(), $this->api());
        $liveCreation->run();
    }
}
