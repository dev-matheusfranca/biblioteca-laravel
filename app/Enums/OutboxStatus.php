<?php

namespace App\Enums;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Sent, self::Cancelled, self::Failed], true);
    }
}
