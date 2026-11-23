<?php

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;
}
