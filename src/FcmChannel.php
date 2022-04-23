<?php

namespace Xtwoend\FcmChannelNotification;

use Throwable;
use ReflectionException;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\Message;
use Kreait\Firebase\Messaging\CloudMessage;
use Xtwoend\FcmChannelNotification\Exception\CouldNotSendNotification;
use Kreait\Firebase\Exception\MessagingException;
use Psr\EventDispatcher\EventDispatcherInterface;
use HyperfExt\Notifications\Contracts\Notification;
use HyperfExt\Notifications\Events\NotificationFailed;
use HyperfExt\Notifications\Contracts\ChannelInterface;

class FcmChannel implements ChannelInterface
{
    const MAX_TOKEN_PER_REQUEST = 500;

    /**
     * @var string|null
     */
    protected $fcmProject = null;

    /**
     * Event dispacher
     */
    protected $events;

    /**
     * 
     */
    public function __construct(EventDispatcherInterface $events) 
    {
        $this->events = $events;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \HyperfExt\Notifications\Notification  $notification
     * @return array
     *
     * @throws \Xtwoend\FcmChannelNotification\Exception\CouldNotSendNotification
     * @throws \Kreait\Firebase\Exception\FirebaseException
     */
    public function send($notifiable, Notification $notification)
    {
        $token = $notifiable->routeNotificationFor('fcm', $notification);

        if (empty($token)) {
            return [];
        }

        // Get the message from the notification class
        $fcmMessage = $notification->toFcm($notifiable);

        if (! $fcmMessage instanceof Message) {
            throw CouldNotSendNotification::invalidMessage();
        }

        $this->fcmProject = null;
        if (method_exists($notification, 'fcmProject')) {
            $this->fcmProject = $notification->fcmProject($notifiable, $fcmMessage);
        }

        $responses = [];

        try {
            if (is_array($token)) {
                // Use multicast when there are multiple recipients
                $partialTokens = array_chunk($token, self::MAX_TOKEN_PER_REQUEST, false);
                foreach ($partialTokens as $tokens) {
                    $responses[] = $this->sendToFcmMulticast($fcmMessage, $tokens);
                }
            } else {
                $responses[] = $this->sendToFcm($fcmMessage, $token);
            }
        } catch (MessagingException $exception) {
            $this->failedNotification($notifiable, $notification, $exception);
            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        }

        return $responses;
    }

    /**
     * @return \Kreait\Firebase\Messaging
     */
    protected function messaging()
    {
        $messaging = make(\Kreait\Firebase\Contract\Messaging::class);

        return $messaging;
    }

    /**
     * @param  \Kreait\Firebase\Messaging\Message  $fcmMessage
     * @param $token
     * @return array
     *
     * @throws \Kreait\Firebase\Exception\MessagingException
     * @throws \Kreait\Firebase\Exception\FirebaseException
     */
    protected function sendToFcm(Message $fcmMessage, $token)
    {
        if ($fcmMessage instanceof CloudMessage) {
            $fcmMessage = $fcmMessage->withChangedTarget('token', $token);
        }

        if ($fcmMessage instanceof FcmMessage) {
            $fcmMessage->setToken($token);
        }

        return $this->messaging()->send($fcmMessage);
    }

    /**
     * @param $fcmMessage
     * @param  array  $tokens
     * @return \Kreait\Firebase\Messaging\MulticastSendReport
     *
     * @throws \Kreait\Firebase\Exception\MessagingException
     * @throws \Kreait\Firebase\Exception\FirebaseException
     */
    protected function sendToFcmMulticast($fcmMessage, array $tokens)
    {
        return $this->messaging()->sendMulticast($fcmMessage, $tokens);
    }

    /**
     * Dispatch failed event.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @param  \Throwable  $exception
     * @return array|null
     */
    protected function failedNotification($notifiable, Notification $notification, Throwable $exception)
    {
        return $this->events->dispatch(new NotificationFailed(
            $notifiable,
            $notification,
            self::class,
            [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]
        ));
    }
}