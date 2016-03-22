<?php
require_once('../commons/base.inc.php');
try
{
        $HostManager = new HostManager();
        $hostname    = $_REQUEST['hostname'];
        $taskTypeID  = $_REQUEST['taskTypeID'];
        $taskName    = 'Custom Kernel';
        if (!$hostname)
                throw new Exception('error please define hostname example: {url}/fog/service/isi_queue_host.php?hostname={name}&taskTypeID={id}');
        if (!$taskTypeID)
                throw new Exception('error please define taskTypeID example: {url}/fog/service/isi_queue_host.php?hostname={name}&taskTypeID={id}');
        // Get the Host
        $Host = $HostManager->getHostByName($hostname);
        $Host->createImagePackage($taskTypeID, $taskName, false, false, -1, false, 'fog');
        print 'True';
}
catch (Exception $e)
{
        print $e->getMessage();
}
