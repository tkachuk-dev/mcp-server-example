<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditResult: string
{
    case Success = 'success';
    case ToolError = 'tool_error';
    case InternalError = 'internal_error';
}
