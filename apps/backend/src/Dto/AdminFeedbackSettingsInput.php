<?php

declare(strict_types=1);

namespace App\Dto;

final class AdminFeedbackSettingsInput
{
    public bool $globalEnabled = false;
    public bool $clientEnabled = false;
    public bool $merchantEnabled = false;
    public bool $adminEnabled = false;
    public bool $clientAreaEnabled = false;
    public bool $merchantAreaEnabled = false;
    public bool $adminAreaEnabled = false;
    public bool $requireAuthenticatedUser = true;
}
