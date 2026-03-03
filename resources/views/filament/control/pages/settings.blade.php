<x-filament-panels::page>
    <div style="display:grid; gap:1rem; max-width:920px;">
        <div style="border:1px solid rgba(148,163,184,0.25); border-radius:16px; padding:1rem; background:color-mix(in srgb, var(--gray-50, #f8fafc) 88%, transparent);">
            <h2 style="margin:0; font-size:1rem; font-weight:800; color:var(--gray-900, #0f172a);">⚙️ إعدادات التشغيل العامة</h2>
            <p style="margin:0.45rem 0 0; font-size:0.8rem; color:var(--gray-500, #64748b);">القيم التالية تعمل على مستوى كامل المنصة، وتؤثر على المستخدمين الجدد ولوحات التحكم.</p>
        </div>

        <div style="border:1px solid rgba(148,163,184,0.2); border-radius:16px; padding:1rem; background:color-mix(in srgb, var(--gray-50, #f8fafc) 90%, transparent);">
            <h3 style="margin:0 0 0.75rem; font-size:0.92rem; font-weight:800; color:var(--gray-900, #0f172a);">إعدادات الخطة الافتراضية</h3>
            <div style="display:grid; gap:0.85rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <label style="display:grid; gap:0.35rem;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">الورد اليومي الافتراضي (صفحات)</span>
                    <input type="number" min="1" max="604" wire:model.live="globalDefaultDailyPages" style="border:1px solid rgba(148,163,184,0.35); border-radius:10px; padding:0.58rem 0.72rem; font-size:0.85rem; background:var(--gray-50, #f8fafc); color:var(--gray-900, #0f172a);">
                    @error('globalDefaultDailyPages')
                        <span style="font-size:0.75rem; color:#dc2626;">{{ $message }}</span>
                    @enderror
                </label>

                <label style="display:flex; align-items:center; justify-content:space-between; gap:0.8rem; border:1px solid rgba(148,163,184,0.2); border-radius:12px; padding:0.72rem; background:rgba(148,163,184,0.06);">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">تعويض تلقائي افتراضي عند فوات الأيام</span>
                    <input type="checkbox" wire:model.live="globalDefaultAutoCompensateMissedDays" style="width:18px; height:18px; accent-color:#6d28d9;">
                </label>
            </div>
        </div>

        <div style="border:1px solid rgba(148,163,184,0.2); border-radius:16px; padding:1rem; background:color-mix(in srgb, var(--gray-50, #f8fafc) 90%, transparent);">
            <h3 style="margin:0 0 0.75rem; font-size:0.92rem; font-weight:800; color:var(--gray-900, #0f172a);">إعدادات لوحات التحكم</h3>
            <div style="display:grid; gap:0.85rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <label style="display:grid; gap:0.35rem;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">حد عناصر النشاط في لوحة الأدمن</span>
                    <input type="number" min="5" max="100" wire:model.live="controlDashboardActivityLimit" style="border:1px solid rgba(148,163,184,0.35); border-radius:10px; padding:0.58rem 0.72rem; font-size:0.85rem; background:var(--gray-50, #f8fafc); color:var(--gray-900, #0f172a);">
                    @error('controlDashboardActivityLimit')
                        <span style="font-size:0.75rem; color:#dc2626;">{{ $message }}</span>
                    @enderror
                </label>

                <label style="display:grid; gap:0.35rem;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">عرض سجل الإنجاز الافتراضي</span>
                    <select wire:model.live="historyDefaultRecordsView" style="border:1px solid rgba(148,163,184,0.35); border-radius:10px; padding:0.58rem 0.72rem; font-size:0.85rem; background:var(--gray-50, #f8fafc); color:var(--gray-900, #0f172a);">
                        <option value="7_days">آخر 7 أيام</option>
                        <option value="30_days">آخر 30 يوم</option>
                        <option value="100_records">آخر 100 سجل</option>
                    </select>
                    @error('historyDefaultRecordsView')
                        <span style="font-size:0.75rem; color:#dc2626;">{{ $message }}</span>
                    @enderror
                </label>
            </div>
        </div>

        <div style="border:1px solid rgba(148,163,184,0.2); border-radius:16px; padding:1rem; background:color-mix(in srgb, var(--gray-50, #f8fafc) 90%, transparent);">
            <h3 style="margin:0 0 0.75rem; font-size:0.92rem; font-weight:800; color:var(--gray-900, #0f172a);">إعدادات صفحة الهبوط</h3>
            <div style="display:grid; gap:0.85rem; grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <label style="display:grid; gap:0.35rem;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">بريد التواصل</span>
                    <input type="email" wire:model.live="landingContactEmail" placeholder="contact@khatma.app" style="border:1px solid rgba(148,163,184,0.35); border-radius:10px; padding:0.58rem 0.72rem; font-size:0.85rem; background:var(--gray-50, #f8fafc); color:var(--gray-900, #0f172a);">
                    @error('landingContactEmail')
                        <span style="font-size:0.75rem; color:#dc2626;">{{ $message }}</span>
                    @enderror
                </label>

                <label style="display:grid; gap:0.35rem;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">رابط حساب X</span>
                    <input type="url" wire:model.live="landingXUrl" placeholder="https://x.com/khatma_app" style="border:1px solid rgba(148,163,184,0.35); border-radius:10px; padding:0.58rem 0.72rem; font-size:0.85rem; background:var(--gray-50, #f8fafc); color:var(--gray-900, #0f172a);">
                    @error('landingXUrl')
                        <span style="font-size:0.75rem; color:#dc2626;">{{ $message }}</span>
                    @enderror
                </label>

                <label style="display:flex; align-items:center; justify-content:space-between; gap:0.8rem; border:1px solid rgba(148,163,184,0.2); border-radius:12px; padding:0.72rem; background:rgba(148,163,184,0.06);">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--gray-700, #334155);">إظهار عداد الزيارات في صفحة الهبوط</span>
                    <input type="checkbox" wire:model.live="landingShowVisitCounter" style="width:18px; height:18px; accent-color:#6d28d9;">
                </label>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end;">
            <button wire:click="saveSettings" style="border:none; border-radius:12px; background:linear-gradient(135deg,#6d28d9,#4c1d95); color:#fff; font-size:0.86rem; font-weight:700; padding:0.58rem 1rem; cursor:pointer;">
                حفظ إعدادات النظام
            </button>
        </div>
    </div>
</x-filament-panels::page>
