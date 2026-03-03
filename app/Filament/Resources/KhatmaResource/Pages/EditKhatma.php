<?php

namespace App\Filament\Resources\KhatmaResource\Pages;

use App\Enums\KhatmaDirection;
use App\Enums\PlanningMethod;
use App\Filament\Resources\KhatmaResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditKhatma extends EditRecord
{
    protected static string $resource = KhatmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف الختمة'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ((int) ($this->record->completed_pages ?? 0) > 0) {
            $data['direction'] = static::stateValue($this->record->direction);
            $data['scope'] = static::stateValue($this->record->scope);
            $data['start_page'] = (int) $this->record->start_page;
            $data['end_page'] = (int) $this->record->end_page;
            $data['total_pages'] = (int) $this->record->total_pages;
        }

        $startPage = (int) ($data['start_page'] ?? 1);
        $endPage = (int) ($data['end_page'] ?? 604);
        $planningMethod = static::stateValue($data['planning_method'] ?? null);

        if ($endPage < $startPage) {
            throw ValidationException::withMessages([
                'end_page' => 'صفحة النهاية يجب أن تكون أكبر من أو تساوي صفحة البداية.',
            ]);
        }

        $data['total_pages'] = $endPage - $startPage + 1;

        if ($planningMethod === PlanningMethod::ByDuration->value) {
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['expected_end_date'] ?? null;

            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->startOfDay();

                if ($end->lt($start)) {
                    throw ValidationException::withMessages([
                        'expected_end_date' => 'تاريخ الختم يجب أن يكون في نفس تاريخ البداية أو بعده.',
                    ]);
                }

                $days = $start->diffInDays($end) + 1;
                $data['daily_pages'] = (int) ceil($data['total_pages'] / $days);
            }
        }

        if ($planningMethod === PlanningMethod::ByWird->value) {
            $dailyPages = (int) ($data['daily_pages'] ?? 0);
            $startDate = $data['start_date'] ?? null;

            if ($dailyPages > 0 && $startDate) {
                $days = (int) ceil($data['total_pages'] / $dailyPages);
                $data['expected_end_date'] = Carbon::parse($startDate)->addDays(max($days - 1, 0))->format('Y-m-d');
            }
        }

        $direction = static::stateValue($data['direction'] ?? KhatmaDirection::Forward->value);

        if ((int) ($data['completed_pages'] ?? 0) === 0) {
            $data['current_page'] = $direction === KhatmaDirection::Backward->value
                ? $endPage
                : $startPage;
        }

        return $data;
    }

    protected static function stateValue(mixed $state): mixed
    {
        return $state instanceof \BackedEnum ? $state->value : $state;
    }
}
