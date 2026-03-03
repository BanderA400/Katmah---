<?php

namespace App\Filament\Control\Resources\KhatmaResource\Pages;

use App\Enums\PlanningMethod;
use App\Filament\Control\Resources\KhatmaResource;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditKhatma extends EditRecord
{
    protected static string $resource = KhatmaResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $planningMethod = $this->stateValue($data['planning_method'] ?? $this->record->planning_method);
        $totalPages = (int) ($this->record->total_pages ?? 0);

        $startDate = $data['start_date'] ?? $this->record->start_date?->toDateString();
        $expectedEndDate = $data['expected_end_date'] ?? $this->record->expected_end_date?->toDateString();

        if ($planningMethod === PlanningMethod::ByDuration->value) {
            if (blank($startDate) || blank($expectedEndDate)) {
                throw ValidationException::withMessages([
                    'expected_end_date' => 'تاريخ البداية والنهاية مطلوبان لتخطيط بالمدة.',
                ]);
            }

            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($expectedEndDate)->startOfDay();

            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'expected_end_date' => 'تاريخ الختم يجب أن يكون بعد البداية أو مساويًا لها.',
                ]);
            }

            $days = $start->diffInDays($end) + 1;
            $data['daily_pages'] = max((int) ceil($totalPages / max($days, 1)), 1);
        }

        if ($planningMethod === PlanningMethod::ByWird->value) {
            if (blank($startDate)) {
                throw ValidationException::withMessages([
                    'start_date' => 'تاريخ البداية مطلوب لتخطيط بالورد.',
                ]);
            }

            $dailyPages = max((int) ($data['daily_pages'] ?? 0), 1);
            $days = (int) ceil($totalPages / $dailyPages);

            $data['daily_pages'] = $dailyPages;
            $data['expected_end_date'] = Carbon::parse($startDate)
                ->startOfDay()
                ->addDays(max($days - 1, 0))
                ->toDateString();
        }

        return $data;
    }

    private function stateValue(mixed $state): mixed
    {
        return $state instanceof \BackedEnum ? $state->value : $state;
    }
}
