<?php

namespace Modules\Payment\Contracts;

use Illuminate\Http\Request;
use Modules\Payment\DTO\WebhookResult;

interface WebhookGatewayInterface
{
    /**
     * Verify the authenticity/signature of incoming webhook request.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Parse and process the incoming webhook payload.
     */
    public function processWebhook(Request $request): WebhookResult;
}
