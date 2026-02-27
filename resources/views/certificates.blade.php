<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikasi - Jauhar Fauzi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: 'rgba(var(--primary-rgb), 0.05)',
                            100: 'rgba(var(--primary-rgb), 0.1)',
                            200: 'rgba(var(--primary-rgb), 0.2)',
                            300: 'rgba(var(--primary-rgb), 0.3)',
                            400: 'rgba(var(--primary-rgb), 0.6)',
                            500: 'rgba(var(--primary-rgb), 0.8)',
                            600: 'rgba(var(--primary-rgb), 1)',
                            700: 'rgba(var(--primary-rgb), 0.9)',
                            800: 'rgba(var(--primary-rgb), 0.95)',
                            900: 'rgba(var(--primary-rgb), 1)',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --primary-rgb: 59, 130, 246;
        }

        .bg-mesh {
            background-image:
                radial-gradient(at 0% 0%, rgba(var(--primary-rgb), 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(147, 51, 234, 0.1) 0px, transparent 50%);
        }
    </style>
</head>

<body x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark',
    selectedCert: null,
    currentColor: localStorage.getItem('color') || '59, 130, 246',
    certs: [
        {
            id: 1,
            title: 'ASISTEN PRAKTIKUM',
            organization: 'AMIKOM CREATIVE ECONOMY PARK',
            role: 'Asisten Praktikum Mata Kuliah Struktur Data',
            date: '25-02-2026',
            number: 'NO. 0486',
            predicate: 'BAIK',
            file: 'Sertifikat Asisten JAUHAR FAUZI ULUL ALBAB-Struktur Data.pdf'
        },
        {
            id: 2,
            title: 'FISCREATION',
            organization: 'UNIVERSITAS NEGERI YOGYAKARTA',
            role: 'Peserta FISCREATION 2023: Futuristic Exploration Workshop',
            date: '18 November 2023',
            number: 'NO:014/Pan-FISCREATION/MEDINFO/BEM FISHIPOL/XI/2023',
            predicate: 'Peserta',
            file: 'JAUHAR FAUZI ULUL ALBAB.pdf'
        },
        {
            id: 3,
            title: 'Karya Tulis Islami',
            organization: 'KANTOR KEMENTERIAN AGAMA KOTA YOGYAKARTA',
            role: 'Juara III Lomba Karya Tulis Islami Putra - MTQ XXXI',
            date: '29 September 2025',
            number: 'Nomor: 2670/Kk.12.05/6/BA.00/09/2025',
            predicate: 'JUARA III',
            file: 'Jauhar Fauzi Ulul Albab (1).pdf'
        }
    ],
    init() {
        document.documentElement.style.setProperty('--primary-rgb', this.currentColor);
    }
}" x-init="init()" :class="darkMode ? 'dark bg-[#050505]' : 'bg-slate-50'"
    class="min-h-screen transition-colors duration-300">

    <div class="bg-mesh fixed inset-0 pointer-events-none opacity-50"></div>

    <!-- Header Navigation -->
    <nav class="sticky top-0 z-40 backdrop-blur-xl border-b border-gray-200 dark:border-white/10 px-6 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <a href="{{ url('/') }}" class="flex items-center gap-4 group">
                <div
                    class="bg-primary-600 rounded-full w-10 h-10 flex items-center justify-center shadow-lg shadow-primary-500/30 group-hover:scale-110 transition">
                    <span class="font-bold text-white tracking-tighter">JF</span>
                </div>
                <span
                    class="font-black dark:text-white group-hover:text-primary-500 transition uppercase tracking-widest text-sm">Kembali</span>
            </a>
            <button @click="darkMode = !darkMode; localStorage.setItem('theme', darkMode ? 'dark' : 'light')"
                class="p-3 rounded-2xl bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/10 text-zinc-900 dark:text-white shadow-xl hover:scale-110 transition">
                <template x-if="darkMode">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h1M4 12H3m15.364-6.364l.707-.707M6.343 17.657l.707-.707m0-11.314l-.707-.707M17.657 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </template>
                <template x-if="!darkMode">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </template>
            </button>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-16">
        <div class="mb-16">
            <p class="text-primary-500 font-black text-xs uppercase tracking-[0.4em] mb-4">Recognitions & Awards</p>
            <h1 class="text-5xl md:text-7xl font-black dark:text-white tracking-tighter mb-6">
                Official <span class="text-primary-500">Certifications</span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-xl max-w-2xl leading-relaxed font-medium">
                Pencapaian dan sertifikasi profesional dalam bidang teknologi dan akademik.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <template x-for="cert in certs" :key="cert.id">
                <div @click="selectedCert = cert"
                    class="group bg-white dark:bg-zinc-900 rounded-[2.5rem] p-4 border border-gray-200 dark:border-white/10 shadow-xl hover:scale-105 transition-all duration-500 cursor-pointer">

                    <!-- PDF Preview Container -->
                    <div
                        class="w-full aspect-[4/3] rounded-[1.5rem] overflow-hidden bg-gray-100 dark:bg-zinc-800 relative mb-6">
                        <iframe :src="'{{ asset('serti') }}/' + cert.file + '#toolbar=0&navpanes=0&scrollbar=0'"
                            class="w-full h-full pointer-events-none" frameborder="0"></iframe>
                        <div class="absolute inset-0 bg-transparent"></div> <!-- Blocking interaction with iframe -->
                        <div
                            class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/60 to-transparent flex items-end justify-center pb-6 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span
                                class="text-white text-[10px] font-black tracking-widest uppercase bg-primary-600 px-4 py-2 rounded-full shadow-lg">View
                                Full Protocol</span>
                        </div>
                    </div>

                    <div class="px-4 pb-4">
                        <span class="text-[10px] font-black tracking-[0.2em] text-primary-500 uppercase mb-2 block"
                            x-text="cert.predicate"></span>
                        <h3 class="text-xl font-bold dark:text-white mb-2 line-clamp-1" x-text="cert.title"></h3>
                        <p class="text-gray-500 text-sm mb-4 line-clamp-2" x-text="cert.organization"></p>
                        <div
                            class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/5">
                            <span class="text-xs font-mono text-gray-400" x-text="cert.date"></span>
                            <svg class="w-5 h-5 text-primary-500 transform group-hover:translate-x-1 transition"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <!-- Modal Cert Detail -->
    <div x-show="selectedCert" style="display: none;"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 md:p-10"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

        <!-- Backdrop -->
        <div @click="selectedCert = null" class="absolute inset-0 bg-black/90 backdrop-blur-md"></div>

        <!-- Content -->
        <div
            class="relative w-full max-w-6xl h-full bg-white dark:bg-zinc-950 rounded-[3rem] shadow-2xl flex flex-col md:flex-row overflow-hidden border border-white/10">
            <!-- Left Side: Digital Certificate -->
            <div class="w-full md:w-[70%] h-[50vh] md:h-auto bg-zinc-800 relative">
                <iframe :src="'{{ asset('serti') }}/' + selectedCert?.file" class="w-full h-full"
                    frameborder="0"></iframe>
                <button @click="selectedCert = null"
                    class="absolute top-6 left-6 p-3 rounded-full bg-black/40 text-white hover:bg-black/60 transition md:hidden">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <!-- Right Side: Details -->
            <div
                class="w-full md:w-[30%] p-8 md:p-10 flex flex-col justify-center bg-white dark:bg-zinc-900 border-l border-white/10">
                <button @click="selectedCert = null"
                    class="absolute top-8 right-8 p-3 rounded-full bg-gray-100 dark:bg-white/10 text-zinc-900 dark:text-white hover:bg-gray-200 dark:hover:bg-white/20 transition hidden md:block">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="space-y-8">
                    <div>
                        <span
                            class="inline-block px-4 py-2 rounded-full bg-primary-600/20 text-primary-500 text-[10px] font-black tracking-widest uppercase mb-6"
                            x-text="selectedCert?.predicate"></span>
                        <h2 class="text-3xl font-black dark:text-white leading-tight mb-4" x-text="selectedCert?.title">
                        </h2>
                        <p class="text-primary-500 font-bold mb-2" x-text="selectedCert?.organization"></p>
                    </div>

                    <div class="space-y-6">
                        <div
                            class="p-6 rounded-3xl bg-gray-50 dark:bg-black/20 border border-gray-100 dark:border-white/5">
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
                                Role/Achievement</h4>
                            <p class="text-gray-700 dark:text-gray-200 font-bold" x-text="selectedCert?.role"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div
                                class="p-6 rounded-3xl bg-gray-50 dark:bg-black/20 border border-gray-100 dark:border-white/5">
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Date
                                </h4>
                                <p class="text-gray-700 dark:text-gray-200 font-bold" x-text="selectedCert?.date"></p>
                            </div>
                            <div
                                class="p-6 rounded-3xl bg-gray-50 dark:bg-black/20 border border-gray-100 dark:border-white/5">
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Ref ID
                                </h4>
                                <p class="text-gray-700 dark:text-gray-200 font-bold text-[8px]"
                                    x-text="selectedCert?.number"></p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 flex flex-col gap-4">
                        <a :href="'{{ asset('serti') }}/' + selectedCert?.file" download
                            class="w-full py-4 bg-primary-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest text-center hover:bg-primary-700 transition shadow-xl shadow-primary-500/20">
                            Download PDF
                        </a>
                        <button @click="selectedCert = null"
                            class="w-full py-4 bg-zinc-100 dark:bg-white/5 text-zinc-900 dark:text-white rounded-2xl font-black text-xs uppercase tracking-widest text-center border border-gray-200 dark:border-white/10 hover:bg-zinc-200 dark:hover:bg-white/10 transition">
                            Tutup Detail
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>