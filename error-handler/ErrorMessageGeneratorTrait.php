<?php

namespace Fazland\RabbitdPlugins\ErrorHandler;

use Fazland\Rabbitd\Message\MessageInterface;

trait ErrorMessageGeneratorTrait
{
    private function generateBodyFor(MessageInterface $message, $attempt = 1)
    {
        switch ($attempt) {
            case 1:
                $time = 10 * 60;
                break;

            default:
                $time = 30 * 60;
                break;
        }

        $errorMessage = [
            'type' => 'faz_errored_message',
            'next' => time() + $time,
            'attempt' => $attempt,
            'message' => serialize($message->getBody()),
        ];

        return json_encode($errorMessage);
    }
}
