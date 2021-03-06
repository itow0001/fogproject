<?php
class RegisterClient extends FOGClient implements FOGClientSend {
    public function send() {
        try {
            $maxPending = 0;
            $MACs = array();
            $maxPending = $this->getSetting('FOG_QUICKREG_MAX_PENDING_MACS');
            $MACs = $this->getHostItem(true,false,false,true);
            if ($this->newService) {
                $_REQUEST['hostname'] = trim($_REQUEST['hostname']);
                if (!($this->Host instanceof Host && $this->Host->isValid())) {
                    $this->Host = self::getClass('HostManager')->find(array('name'=>$_REQUEST['hostname']));
                    $this->Host = @array_shift($this->Host);
                    if (!($this->Host instanceof Host && $this->Host->isValid() && !$this->Host->get('pending'))) {
                        if (!self::getClass('Host')->isHostnameSafe($_REQUEST['hostname'])) throw new Exception('#!ih');
                        $PriMAC = @array_shift($MACs);
                        $this->Host = self::getClass('Host')
                            ->set('name',$_REQUEST['hostname'])
                            ->set('description',_('Pending Registration created by FOG_CLIENT'))
                            ->set('pending',1)
                            ->set('enforce',(int)$this->getSetting('FOG_ENFORCE_HOST_CHANGES'))
                            ->addModule($this->getSubObjectIDs('Module',array('isDefault'=>1),'id'))
                            ->addPriMAC($PriMAC)
                            ->addAddMAC($MACs);
                        if (!$this->Host->save()) throw new Exception('#!db');
                        throw new Exception('#!ok');
                    }
                }
            }
            if (count($MACs) > $maxPending + 1) throw new Exception('#!er: Too many MACs');
            foreach ($MACs AS $i => &$MAC) $AllMACs[] = strtolower($MAC);
            unset($MAC);
            $KnownMACs = $this->Host->getMyMacs(false);
            $MACs = array_unique(array_diff((array)$AllMACs,(array)$KnownMACs));
            if (count($MACs)) {
                $this->Host->addPendMAC($MACs);
                if (!$this->Host->save()) throw new Exception('#!db');
                throw new Exception('#!ok');
            }
            throw new Exception('#!ig');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
