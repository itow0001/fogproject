<?php
require_once('../commons/base.inc.php');
try
{
	$hostname    = $_REQUEST['hostname'];
	$command = escapeshellcmd('/usr/bin/python /var/www/fog/service/isi_vm_reboot.py -n ').$hostname;
        $output = shell_exec($command);
        print $output;
}
catch (Exception $e)
{
	print $e->getMessage();
}
