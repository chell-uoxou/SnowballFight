<?php
/**
 * Created by PhpStorm.
 * User: chell_uoxou
 * Date: 2017/06/24
 * Time: 午後 5:36
 */

namespace SnowballFight;

use pocketmine\network\protocol\DataPacket;

class BossEventPacket extends DataPacket
{
    const NETWORK_ID = 0x4b;
    public $eid;
    public $state;

    public function decode()
    {
        $this->eid = $this->getUUID();
        $this->state = $this->getUnsignedVarInt();
    }

    public function encode()
    {
        $this->reset();
        $this->putEntityUniqueId($this->eid);
        $this->putEntityRuntimeId($this->eid);
        $this->putUnsignedVarInt($this->state);
    }
}