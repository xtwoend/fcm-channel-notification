<?php

namespace Xtwoend\FcmChannelNotification;

use Kreait\Firebase\Factory;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;


class FcmManagerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $configPath =  config('fcm.file');
        if(! file_exists($configPath)) {
            throw new \Exception('FCM file not found, please check your config.'); 
        }
        $factory = (new Factory)->withServiceAccount($configPath);
        
        return $factory->createMessaging();
    }
}