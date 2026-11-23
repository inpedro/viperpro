<?php

namespace App\Filament\Platform\Resources\AuditLogResource\Pages;

use App\Filament\Platform\Resources\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
