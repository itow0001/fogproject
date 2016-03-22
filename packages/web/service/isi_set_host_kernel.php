<?php
require_once('../commons/base.inc.php');
try
{
        $HostManager = new HostManager();
        $hostname    = $_REQUEST['hostname'];
        $kernel_path = $_REQUEST['kernel_path']
        if (!$hostname)
        {
        	throw new Exception('error please define hostname example: {url}/fog/service/isi_queue_host.php?hostname={name}&kernel_path={url path}');
        }
        if (!$kernel_path)
        {
        	throw new Exception('error please define kernel_path example: {url}/fog/service/isi_queue_host.php?hostname={name}&kernel_path={url path}');
        }
        // Get the Host
        $Host = $HostManager->getHostByName($hostname);
        if (!$Host)
        {
        	throw new Exception('error host not found');
        }
        $Host->set('kernel', $kernel_path)
        print 'True';
}
catch (Exception $e)
{
        print $e->getMessage();
}
