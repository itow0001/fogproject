<?php
class Group extends FOGController {
    protected $databaseTable = 'groups';
    protected $databaseFields = array(
        'id' => 'groupID',
        'name' => 'groupName',
        'description' => 'groupDesc',
        'createdBy' => 'groupCreateBy',
        'createdTime' => 'groupDateTime',
        'building' => 'groupBuilding',
        'kernel' => 'groupKernel',
        'kernelArgs' => 'groupKernelArgs',
        'kernelDevice' => 'groupPrimaryDisk',
    );
    protected $databaseFieldsRequired = array(
        'name',
    );
    protected $additionalFields = array(
        'hosts',
        'hostsnotinme',
    );
    public function destroy($field = 'id') {
        self::getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id')));
        return parent::destroy($field);
    }
    public function save($mainObject = true) {
        if ($mainObject) parent::save();
        switch ($this->get('id')) {
        case 0:
        case null:
        case false:
        case '0':
        case '':
            $this->destroy();
            throw new Exception(_('Group ID was not set, or unable to be created'));
            break;
        case ($this->isLoaded('hosts')):
            $DBHostIDs = $this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
            $RemoveHostIDs = array_diff((array)$DBHostIDs,(array)$this->get('hosts'));
            if (count($RemoveHostIDs)) {
                self::getClass('GroupAssociationManager')->destroy(array('groupID'=>$this->get('id'),'hostID'=>$RemoveHostIDs));
                $DBHostIDs = $this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
                unset($RemoveHostIDs);
            }
            foreach ((array)self::getClass('HostManager')->find(array('id'=>array_diff((array)$this->get('hosts'),(array)$DBHostIDs))) AS $i => &$Host) {
                if (!$Host->isValid()) {
                    $Host->destroy();
                    continue;
                }
                self::getClass('GroupAssociation')
                    ->set('hostID',$Host->get('id'))
                    ->set('groupID',$this->get('id'))
                    ->save();
                unset($Host);
            }
            unset($DBHostIDs,$RemoveHostIDs);
            break;
        }
        return $this;
    }
    public function getHostCount() {
        $GroupHostIDs = $this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID');
        $ValidHostIDs = $this->getSubObjectIDs('Host','','id');
        $notValid = array_diff((array)$GroupHostIDs,(array)$ValidHostIDs);
        if (count($notValid)) self::getClass('GroupAssociationManager')->destroy(array('hostID'=>$notValid));
        return self::getClass('GroupAssociationManager')->count(array('groupID'=>$this->get('id')));
    }
    public function addPrinter($printerAdd, $printerDel, $level = 0) {
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('printerLevel'=>$level));
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            if ($printerAdd) $Host->addPrinter($printerAdd);
            if ($printerDel) $Host->removePrinter($printerDel);
            $Host->save();
            unset($Host);
        }
        return $this;
    }
    public function addSnapin($addArray) {
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $Host->addSnapin($addArray)->save();
            unset($Host);
        }
        return $this;
    }
    public function removeSnapin($removeArray) {
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $Host->removeSnapin($removeArray)->save();
            unset($Host);
        }
        return $this;
    }
    public function addModule($addArray) {
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $Host->addModule($addArray)->save();
            unset($Host);
        }
        return $this;
    }
    public function removeModule($removeArray) {
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $Host->removeModule($removeArray)->save();
            unset($Host);
        }
        return $this;
    }
    public function addHost($addArray) {
        $this->set('hosts',array_unique(array_merge((array)$this->get('hosts'),(array)$addArray)));
        return $this;
    }
    public function removeHost($removeArray) {
        $this->set('hosts',array_unique(array_diff((array)$this->get('hosts'),(array)$removeArray)));
        return $this;
    }
    public function addImage($imageID) {
        if (!$imageID) throw new Exception(_('Select an image'));
        if (!self::getClass('Image',$imageID)->isValid()) throw new Exception(_('Select a valid image'));
        if (self::getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('imageID'=>$imageID));
        return $this;
    }
    public function createImagePackage($taskTypeID, $taskName = '', $shutdown = false, $debug = false, $deploySnapins = false, $isGroupTask = false, $username = '', $passreset = '',$sessionjoin = false) {
        if (self::getClass('TaskManager')->count(array('hostID'=>$this->get('hosts'),'stateID'=>array_merge($this->getQueuedStates(),(array)$this->getProgressState())))) throw new Exception(_('There is a host in a tasking'));
        $success = array();
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $success[] = $Host->createImagePackage($taskTypeID,$taskName,$shutdown, $debug,$deploySnapins,$isGroupTask,$_SESSION['FOG_USERNAME'],$passreset,$sessionjoin);
            unset($Host);
        }
        return $success;
    }
    public function setAD($useAD, $domain, $ou, $user, $pass, $legacy, $enforce) {
        $pass = trim($this->encryptpw($pass));
        self::getClass('HostManager')->update(array('id'=>$this->get('hosts')),'',array('useAD'=>$useAD,'ADDomain'=>trim($domain),'ADOU'=>trim($ou),'ADUser'=>trim($user),'ADPass'=>$pass,'ADPassLegacy'=>$legacy,'enforce'=>$enforce));
        return $this;
    }
    public function doMembersHaveUniformImages() {
        $imageID = $this->getSubObjectIDs('Host',array('id'=>$this->get('hosts')),'imageID','','','','','array_count_values');
        $imageID = count($imageID) == 1 ? array_shift($imageID) : 0;
        return $imageID == $this->getHostCount();
    }
    public function updateDefault($printerid) {
        foreach ((array)self::getClass('HostManager')->find(array('id'=>$this->get('hosts'))) AS $i => &$Host) {
            if (!$Host->isValid()) continue;
            $Host->updateDefault($printerid,true);
            unset($Host);
        }
        return $this;
    }
    protected function loadHosts() {
        if ($this->get('id')) $this->set('hosts',$this->getSubObjectIDs('GroupAssociation',array('groupID'=>$this->get('id')),'hostID'));
    }
    protected function loadHostsnotinme() {
        if ($this->get('id')) {
            $find = array('id'=>$this->get('hosts'));
            $this->set('hostsnotinme',$this->getSubObjectIDs('Host',$find,'',true));
            unset($find);
        }
    }
}
