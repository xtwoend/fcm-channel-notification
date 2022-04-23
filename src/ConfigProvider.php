<?php

namespace Xtwoend\FcmChannelNotification;

use Xtwoend\FcmChannelNotification\FcmManagerFactory;

class ConfigProvider
{
    public function __invoke()
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'dependencies' => [
                \Kreait\Firebase\Contract\Messaging::class => FcmManagerFactory::class
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for gii project.',
                    'source' => __DIR__ . '/../publish/fcm.php',
                    'destination' => BASE_PATH . '/config/autoload/fcm.php',
                ]
            ],
        ];
    }
}