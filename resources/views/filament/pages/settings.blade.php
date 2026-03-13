<x-filament-panels::page>
    <div style="max-width: 760px; background: var(--khatma-surface); border-radius: 16px; padding: 1.2rem; box-shadow: var(--khatma-shadow); border: 1px solid var(--khatma-border);">
        <h2 style="font-family: 'Amiri', serif; font-size: 1.35rem; font-weight: 700; color: var(--khatma-title); margin-bottom: 0.4rem;">
            ⚙️ الإعدادات الافتراضية للخطة
        </h2>
        <p style="font-size: 0.88rem; color: var(--khatma-muted); margin-bottom: 1rem;">
            هذه القيم تستخدم تلقائيًا عند إنشاء ختمة جديدة. يمكنك تعديل كل ختمة لاحقًا بشكل مستقل.
        </p>

        <div style="display: grid; gap: 0.95rem;">
            <label style="display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.85rem; border-radius: 12px; border: 1px solid var(--khatma-border); background: var(--khatma-surface-soft);">
                <div>
                    <div style="font-weight: 700; color: var(--khatma-text); font-size: 0.9rem;">تعويض تلقائي عند فوات الأيام</div>
                    <div style="font-size: 0.78rem; color: var(--khatma-muted);">إذا فاتك يوم، يعاد توزيع المتبقي على الأيام القادمة.</div>
                </div>
                <input type="checkbox" wire:model.live="defaultAutoCompensateMissedDays" style="width: 18px; height: 18px; accent-color: var(--khatma-title);">
            </label>

            <label style="display: grid; gap: 0.35rem;">
                <span style="font-size: 0.84rem; font-weight: 700; color: var(--khatma-text);">الورد اليومي الافتراضي (بالصفحات)</span>
                <input
                    type="number"
                    min="1"
                    max="604"
                    wire:model.live="defaultDailyPages"
                    style="border: 1px solid var(--khatma-border); border-radius: 10px; padding: 0.58rem 0.72rem; font-size: 0.88rem; background: var(--khatma-surface); color: var(--khatma-text); width: 180px;"
                >
                @error('defaultDailyPages')
                    <span style="font-size: 0.75rem; color: #dc2626;">{{ $message }}</span>
                @enderror
            </label>

            <label style="display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.85rem; border-radius: 12px; border: 1px solid var(--khatma-border); background: var(--khatma-surface-soft);">
                <div>
                    <div style="font-weight: 700; color: var(--khatma-text); font-size: 0.9rem;">تفعيل تذكيرات تأخر الورد</div>
                    <div style="font-size: 0.78rem; color: var(--khatma-muted);">تصلك داخل النظام + على الإيميل عند وجود ورد متأخر.</div>
                </div>
                <input type="checkbox" wire:model.live="wirdRemindersEnabled" style="width: 18px; height: 18px; accent-color: var(--khatma-title);">
            </label>

            <label style="display: grid; gap: 0.35rem;">
                <span style="font-size: 0.84rem; font-weight: 700; color: var(--khatma-text);">وقت تذكير تأخر الورد</span>
                <input
                    type="time"
                    wire:model.live="wirdRemindersTime"
                    style="border: 1px solid var(--khatma-border); border-radius: 10px; padding: 0.58rem 0.72rem; font-size: 0.88rem; background: var(--khatma-surface); color: var(--khatma-text); width: 180px;"
                >
                @error('wirdRemindersTime')
                    <span style="font-size: 0.75rem; color: #dc2626;">{{ $message }}</span>
                @enderror
            </label>

            <label style="display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.85rem; border-radius: 12px; border: 1px solid var(--khatma-border); background: var(--khatma-surface-soft);">
                <div>
                    <div style="font-weight: 700; color: var(--khatma-text); font-size: 0.9rem;">تفعيل التقرير الأسبوعي</div>
                    <div style="font-size: 0.78rem; color: var(--khatma-muted);">يصلك ملخص أسبوعي داخل النظام + على الإيميل.</div>
                </div>
                <input type="checkbox" wire:model.live="weeklyReportsEnabled" style="width: 18px; height: 18px; accent-color: var(--khatma-title);">
            </label>

            <label style="display: flex; align-items: center; justify-content: space-between; gap: 0.8rem; padding: 0.85rem; border-radius: 12px; border: 1px solid var(--khatma-border); background: var(--khatma-surface-soft);">
                <div>
                    <div style="font-weight: 700; color: var(--khatma-text); font-size: 0.9rem;">تفعيل التقرير الشهري</div>
                    <div style="font-size: 0.78rem; color: var(--khatma-muted);">يصلك ملخص شهري حتى عند عدم وجود إنجاز.</div>
                </div>
                <input type="checkbox" wire:model.live="monthlyReportsEnabled" style="width: 18px; height: 18px; accent-color: var(--khatma-title);">
            </label>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <button
                wire:click="saveDefaults"
                style="border: none; border-radius: 12px; background: linear-gradient(135deg, var(--khatma-hifz-from), var(--khatma-hifz-to)); color: #fff; font-size: 0.88rem; font-weight: 700; padding: 0.58rem 1rem; cursor: pointer;"
            >
                حفظ الإعدادات
            </button>
        </div>
    </div>
</x-filament-panels::page>
