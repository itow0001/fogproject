<?php
/****************************************************
 *  Called when imaging fails
 *	Author:		Jbob
 ***/
class ImageFail_PushBullet extends Event {
    // Class variables
    var $name = 'ImageFail_PushBullet';
    var $description = 'Triggers when a host fails imaging';
    var $author = 'Jbob';
    var $active = true;
    public function onEvent($event, $data) {
        foreach ((array)self::getClass('PushbulletManager')->find() AS $Token)
            self::getClass('PushbulletHandler',$Token->get('token'))->pushNote('', $data['HostName'].' Failed', 'This host has failed to image');
    }
}
$EventManager->register('HOST_IMAGE_FAIL', new ImageFail_PushBullet());
