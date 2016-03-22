<?php
require_once('../commons/base.inc.php');
try
{
        $HostManager = new HostManager();
        $MACs = $_REQUEST['mac'];
        if (!$MACs)
                throw new Exception('Error unable to get description example: {url}/fog/service/host_desc.php?mac={mac address}');
        // Get the Host
        $Host = $HostManager->getHostByMacAddresses($MACs);
        print ''.$Host->get('description');
}
catch (Exception $e)
{
        print $e->getMessage();
}