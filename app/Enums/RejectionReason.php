<?php

namespace App\Enums;

/**
 * Why a claim attempt was refused. Stored on the rejected claim so the
 * history can explain itself, and echoed to the client as a machine
 * readable `reason` next to the human message.
 */
enum RejectionReason: string
{
    case NotFound = 'not_found';
    case Expired = 'expired';
    case AlreadyUsed = 'already_used';

    public function message(): string
    {
        return match ($this) {
            self::NotFound => 'Такого промокоду не існує.',
            self::Expired => 'Термін дії промокоду вичерпано.',
            self::AlreadyUsed => 'Ви вже використовували цей промокод.',
        };
    }
}
