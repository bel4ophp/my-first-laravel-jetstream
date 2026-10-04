<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A service refusing an action because a business rule forbids it.
 *
 * Services throw these instead of ValidationExceptions keyed to a Livewire
 * field, so each caller decides how to show the refusal: the components map it
 * to a form field or a flash message, and an API request gets a 422.
 *
 * The message is shown to the person as-is, so it is already translated. These
 * are expected outcomes, not faults, so they are never written to the log.
 */
abstract class BusinessRuleException extends RuntimeException implements ShouldntReport
{
    /**
     * JSON callers (the API) get a 422 with the message; anything else is left
     * to the caller, which is expected to catch the exception itself.
     */
    public function render(Request $request): ?JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 422)
            : null;
    }
}
