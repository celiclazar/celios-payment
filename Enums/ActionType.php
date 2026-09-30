<?php

namespace Modules\Payment\Enums;

enum ActionType: string
{
    case REDIRECT = 'redirect';
    case POST_FORM = 'post_form';
    case DISPLAY_INSTRUCTIONS = 'display_instructions';
    case QR_CODE = 'qr_code';
}
