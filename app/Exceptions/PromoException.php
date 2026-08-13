<?php

namespace App\Exceptions;

use App\Enums\RejectionReason;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A business rule refused the operation. The input was well formed, so this
 * is not a 422: the request is understood and simply not allowed.
 *
 * `reason` is a stable machine readable code. The client picks its wording
 * from it instead of matching on the human message.
 */
class PromoException extends Exception
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function rejected(RejectionReason $reason): self
    {
        return new self($reason->value, $reason->message());
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'reason' => $this->reason,
        ], 409);
    }
}
