<?php

namespace App\Filament\Control\Resources\UserResource\Pages;

use App\Filament\Control\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $isAdminAfterSave = (bool) ($data['is_admin'] ?? false);

        if ((int) $this->record->id === (int) auth()->id() && ! $isAdminAfterSave) {
            throw ValidationException::withMessages([
                'is_admin' => 'لا يمكنك سحب صلاحية الأدمن من حسابك الحالي.',
            ]);
        }

        if ((bool) $this->record->is_admin && ! $isAdminAfterSave) {
            $adminsCount = User::query()->where('is_admin', true)->count();

            if ($adminsCount <= 1) {
                throw ValidationException::withMessages([
                    'is_admin' => 'لا يمكن إزالة آخر حساب أدمن في النظام.',
                ]);
            }
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
