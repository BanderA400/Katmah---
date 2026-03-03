<?php

namespace App\Filament\Control\Resources\UserResource\Pages;

use App\Filament\Control\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('مستخدم جديد')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
