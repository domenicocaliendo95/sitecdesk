<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creato_da'] = auth()->id();
        $data['stato'] = 'nuovo';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('index');
    }
}
