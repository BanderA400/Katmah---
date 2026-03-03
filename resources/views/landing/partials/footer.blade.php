@php
    $contactEmail = trim((string) ($landingContactEmail ?? 'contact@khatma.app'));
    $xUrl = trim((string) ($landingXUrl ?? 'https://x.com/khatma_app'));
    $showVisitCounter = (bool) ($landingShowVisitCounter ?? true);
    $xPath = (string) (parse_url($xUrl, PHP_URL_PATH) ?? '');
    $xHandle = $xPath !== '' ? '@' . ltrim($xPath, '/@') : '@khatma_app';
@endphp

<footer class="relative bg-neutral-900 overflow-hidden">
    <!-- Decorative gradient overlay at the top -->
    <div class="absolute top-0 left-0 right-0 h-40 pointer-events-none" style="background: linear-gradient(180deg, rgba(109,40,217,0.08) 0%, transparent 100%)"></div>
    
    <!-- 3-color top border -->
    <div class="h-1 w-full bg-gradient-to-r from-primary-700 via-secondary-600 to-accent-400 relative z-10"></div>
    
    <div class="max-w-6xl mx-auto py-16 px-6 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 text-center md:text-right">
            
            <!-- Right Column: Identity -->
            <div class="flex flex-col items-center md:items-start">
                <div class="flex items-center gap-3 justify-center md:justify-start">
                    <div class="relative flex items-center justify-center w-10 h-10">
                        <div class="absolute inset-0 bg-primary-500 rounded-xl rotate-45 opacity-20"></div>
                        <div class="absolute inset-0 bg-primary-500 rounded-xl -rotate-12 opacity-40"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white relative z-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <span class="font-amiri text-2xl text-white">ختمة</span>
                </div>
                <p class="mt-4 text-neutral-400 text-sm leading-relaxed max-w-xs text-center md:text-right">
                    منصة تتبع حفظ القرآن الكريم.
                </p>
                @if($showVisitCounter)
                    <div class="mt-4 flex flex-wrap items-center justify-center md:justify-start gap-2 text-xs text-neutral-500">
                        <span>إجمالي الزيارات: {{ number_format($totalVisits ?? 0) }}</span>
                        <span class="text-neutral-700">•</span>
                        <span>زوار اليوم: {{ number_format($todayUniqueVisitors ?? 0) }}</span>
                    </div>
                @endif
                <div class="mt-6 flex items-center justify-center md:justify-start gap-3">
                    @if($contactEmail !== '')
                        <a href="mailto:{{ $contactEmail }}" aria-label="البريد الإلكتروني" class="w-10 h-10 rounded-full bg-neutral-800 flex items-center justify-center text-neutral-400 hover:bg-primary-600 hover:text-white transition-all duration-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                        </a>
                    @endif
                    @if($xUrl !== '')
                        <a href="{{ $xUrl }}" target="_blank" rel="noopener noreferrer" aria-label="حساب X" class="w-10 h-10 rounded-full bg-neutral-800 flex items-center justify-center text-neutral-400 hover:bg-primary-600 hover:text-white transition-all duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                        </a>
                    @endif
                    <a href="https://github.com/" target="_blank" rel="noopener noreferrer" aria-label="GitHub" class="w-10 h-10 rounded-full bg-neutral-800 flex items-center justify-center text-neutral-400 hover:bg-primary-600 hover:text-white transition-all duration-300">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Middle Column: Links -->
            <div class="flex flex-col items-center md:items-start">
                <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-2">روابط سريعة</h3>
                <div class="w-8 h-0.5 bg-primary-500 rounded-full mb-6"></div>
                <ul class="space-y-1 w-full flex flex-col items-center md:items-start">
                    <li><a href="/" class="text-neutral-400 hover:text-primary-300 hover:pr-2 transition-all duration-300 block py-1.5">الرئيسية</a></li>
                    <li><a href="/register" class="text-neutral-400 hover:text-primary-300 hover:pr-2 transition-all duration-300 block py-1.5">سجّل الآن</a></li>
                    <li><a href="/login" class="text-neutral-400 hover:text-primary-300 hover:pr-2 transition-all duration-300 block py-1.5">سجّل دخولك</a></li>
                </ul>
            </div>

            <!-- Left Column: Contact -->
            <div class="flex flex-col items-center md:items-start">
                <h3 class="text-white font-bold text-sm uppercase tracking-wider mb-2">تواصل معنا</h3>
                <div class="w-8 h-0.5 bg-secondary-500 rounded-full mb-6"></div>
                <ul class="space-y-1 w-full flex flex-col items-center md:items-start">
                    @if($contactEmail !== '')
                        <li>
                            <a href="mailto:{{ $contactEmail }}" class="flex items-center gap-3 py-2 group justify-center md:justify-start w-full">
                                <svg class="w-5 h-5 text-neutral-500 group-hover:text-primary-400 transition" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                </svg>
                                <span class="text-neutral-400 group-hover:text-white transition" dir="ltr">{{ $contactEmail }}</span>
                            </a>
                        </li>
                    @endif
                    @if($xUrl !== '')
                        <li>
                            <a href="{{ $xUrl }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 py-2 group justify-center md:justify-start w-full">
                                <svg class="w-5 h-5 text-neutral-500 group-hover:text-primary-400 transition" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                                <span class="text-neutral-400 group-hover:text-white transition" dir="ltr">{{ $xHandle }}</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

        </div>

        <!-- Bottom -->
        <div class="border-t border-neutral-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-neutral-500 text-sm">
                صُنع بـ <span class="text-primary-400 text-lg">♥</span> لخدمة كتاب الله
            </p>
            <p class="text-neutral-600 text-xs">
                © {{ date('Y') }} ختمة — جميع الحقوق محفوظة
            </p>
        </div>
    </div>
</footer>
