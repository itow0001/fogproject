<?php
class WakeOnLan extends FOGBase {
    private $arrMAC;
    private $hwaddr;
    private $packet;
    public function __construct($mac) {
        parent::__construct();
        $this->arrMAC = $this->parseMacList($mac,true);
    }
    public function send() {
        if ($this->arrMAC === false || !count($this->arrMAC)) throw new Exception($this->foglang['InvalidMAC']);
        $BroadCast = array_merge((array)'255.255.255.255',$this->FOGCore->getBroadcast());
        $this->HookManager->processEvent('BROADCAST_ADDR',array('broadcast'=>&$BroadCast));
        foreach ((array)$this->arrMAC AS $i => &$MAC) {
            ob_start();
            foreach ((array)explode(':',$MAC) AS $i => &$hex) {
                echo chr(hexdec($hex));
                unset($hex);
            }
            $magicPacket = sprintf('%s%s',str_repeat(chr(255),6),str_repeat(ob_get_clean(),16));
            foreach ((array)$BroadCast AS $i => &$SendTo) {
                if (!($sock = socket_create(AF_INET,SOCK_DGRAM,SOL_UDP))) throw new Exception(_('Socket error'));
                socket_set_nonblock($sock);
                $options = socket_set_option($sock,SOL_SOCKET,SO_BROADCAST,true);
                if ($options >= 0 && socket_sendto($sock,$magicPacket,(int)strlen($magicPacket),0,$SendTo,9)) socket_close($sock);
                unset($SendTo);
            }
            unset($BroadCast,$MAC);
        }
    }
}