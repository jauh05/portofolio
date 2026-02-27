<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Projects - Jauhar Fauzi</title>
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
                radial-gradient(at 0% 0%, rgba(var(--primary-rgb), 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(147, 51, 234, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(var(--primary-rgb), 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(147, 51, 234, 0.15) 0px, transparent 50%);
        }
    </style>
</head>

<body x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark',
    selectedProject: null,
    currentColor: localStorage.getItem('color') || '59, 130, 246',
    projects: [
        {
            id: 1,
            tag: 'JAIC PUBLICATION',
            title: 'Recommendation System Research',
            desc: 'Publikasi ilmiah resmi pada Journal of Applied Informatics and Computing (JAIC).',
            bg: 'Penelitian ini didorong oleh kebutuhan untuk meningkatkan sistem rekomendasi pariwisata yang lebih akurat dan personal bagi wisatawan di Yogyakarta.',
            problem: 'Wisatawan seringkali kesulitan menemukan destinasi yang sesuai dengan preferensi mereka di tengah banyaknya pilihan objek wisata.',
            result: 'Optimasi algoritma TF-IDF dan Cosine Similarity yang berhasil meningkatkan akurasi rekomendasi hingga 85% dalam pengujian dataset lokal.',
            image: '{{ asset("projects/journal.png") }}'
        },
        {
            id: 2,
            tag: 'FINTECH ADMIN',
            title: 'KPRI Bakti Mulia',
            desc: 'Digitalisasi simpan pinjam & analisis kredit menggunakan C4.5 Algorithm.',
            bg: 'Koperasi memerlukan sistem otomatis untuk mengelola data anggota dan menganalisis kelayakan kredit secara objektif.',
            problem: 'Proses manual dalam pengelolaan simpan pinjam sering menimbulkan kesalahan data dan subjektivitas dalam persetujuan kredit.',
            result: 'Aplikasi dashboard admin yang mengintegrasikan pengolahan data C4.5 untuk memberikan rekomendasi keputusan kredit yang lebih cepat dan transparan.',
            image: '{{ asset("projects/kpri.png") }}'
        },
        {
            id: 3,
            tag: 'AI RECOMMENDATION',
            title: 'JaugjaKita App',
            desc: 'Sistem cerdas rekomendasi wisata di Yogyakarta berbasis Machine Learning.',
            bg: 'Inisiatif untuk mendukung pariwisata lokal melalui platform aplikasi mobile yang cerdas dan interaktif.',
            problem: 'Kurangnya platform yang mengintegrasikan Machine Learning untuk memberikan rekomendasi berbasis lokasi dan preferensi real-time.',
            result: 'Aplikasi mobile (Android) yang memungkinkan pengguna mendapatkan rekomendasi rute dan destinasi terbaik secara instan.',
            image: '{{ asset("projects/jaugjakita.png") }}'
        },
        {
            id: 4,
            tag: 'ED-TECH SAAS',
            title: 'Kauiz Ai Platform',
            desc: 'Automatisasi pembuatan kuis cerdas berbasis AI untuk pengajar modern.',
            bg: 'Pemanfaatan Generative AI untuk membantu efisiensi pembuatan materi evaluasi bagi pendidik.',
            problem: 'Membuat kuis secara manual membutuhkan waktu yang lama, terutama untuk bank soal yang bervariasi.',
            result: 'Platform SaaS yang dapat menghasilkan soal kuis secara otomatis dari dokumen PDF atau teks hanya dalam hitungan detik.',
            image: '{{ asset("projects/kauiz.png") }}'
        },
        {
            id: 5,
            tag: 'REAL-TIME NLP',
            title: 'LiveChat Interaction',
            desc: 'Filtering kata kasar real-time berbasis pemrosesan bahasa alami.',
            bg: 'Kebutuhan akan lingkungan komunikasi online yang sehat dan bebas dari konten toksik.',
            problem: 'Sistem filter konvensional seringkali mudah diakali dengan variasi kata kasar atau slang.',
            result: 'Engine NLP yang mampu mendeteksi dan menyensor kata-kata kasar secara real-time dengan tingkat presisi tinggi.',
            image: '{{ asset("projects/livechat.png") }}'
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
            <p class="text-primary-500 font-black text-xs uppercase tracking-[0.4em] mb-4">03 / PORTFOLIO</p>
            <h1 class="text-5xl md:text-7xl font-black dark:text-white tracking-tighter mb-6">
                Featured <span class="text-primary-500">Projects</span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-xl max-w-2xl leading-relaxed font-medium">
                Kumpulan solusi digital dan publikasi ilmiah yang berfokus pada inovasi teknologi.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <template x-for="project in projects" :key="project.id">
                <div @click="selectedProject = project"
                    class="group relative h-[450px] rounded-[2.5rem] overflow-hidden cursor-pointer shadow-2xl transition-all duration-500 hover:scale-[1.02] border border-white/5">

                    <!-- Image -->
                    <img :src="project.image"
                        class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">

                    <!-- Overlay -->
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent opacity-90 transition-opacity group-hover:opacity-100">
                    </div>

                    <!-- Content -->
                    <div class="absolute inset-0 p-8 flex flex-col justify-end">
                        <div class="translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                            <span
                                class="inline-block px-4 py-1.5 rounded-full bg-primary-600 text-[10px] font-black tracking-widest text-white mb-4 uppercase"
                                x-text="project.tag"></span>
                            <h3 class="text-2xl font-black text-white mb-2 leading-tight" x-text="project.title"></h3>
                            <p class="text-gray-300 text-sm font-medium line-clamp-2 mb-6" x-text="project.desc"></p>

                            <div
                                class="flex items-center gap-3 text-primary-400 font-black text-[10px] uppercase tracking-widest">
                                <span>Detail Project</span>
                                <div class="w-10 h-[2px] bg-primary-500/50"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <!-- Modal Project Detail -->
    <div x-show="selectedProject" style="display: none;"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 md:p-10"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

        <!-- Backdrop -->
        <div @click="selectedProject = null" class="absolute inset-0 bg-black/80 backdrop-blur-md"></div>

        <!-- Modal Content -->
        <div
            class="relative w-full max-w-4xl max-h-full overflow-y-auto bg-white dark:bg-zinc-950 rounded-[3rem] shadow-2xl border border-white/10 custom-scrollbar">
            <!-- Close Button -->
            <button @click="selectedProject = null"
                class="absolute top-8 right-8 z-10 p-3 rounded-full bg-white/10 dark:bg-black/20 text-white hover:bg-white/20 transition backdrop-blur-md">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Content Grid -->
            <div class="flex flex-col md:flex-row">
                <!-- Project Poster -->
                <div class="w-full md:w-[40%] h-[300px] md:h-auto overflow-hidden relative">
                    <img :src="selectedProject?.image" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-950 via-transparent to-transparent"></div>
                    <div class="absolute bottom-10 left-10">
                        <span
                            class="inline-block px-4 py-2 rounded-full bg-primary-600 text-[10px] font-black tracking-widest text-white mb-4 uppercase"
                            x-text="selectedProject?.tag"></span>
                        <h2 class="text-3xl font-black text-white" x-text="selectedProject?.title"></h2>
                    </div>
                </div>

                <!-- Info Details -->
                <div class="w-full md:w-[60%] p-8 md:p-12">
                    <div class="space-y-10">
                        <!-- Latar Belakang -->
                        <div class="group">
                            <div class="flex items-center gap-4 mb-4">
                                <div
                                    class="w-2 h-8 bg-primary-600 rounded-full group-hover:scale-y-125 transition-transform origin-top">
                                </div>
                                <h4 class="text-xs font-black uppercase tracking-[0.3em] text-primary-500">Latar
                                    Belakang</h4>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed font-medium"
                                x-text="selectedProject?.bg"></p>
                        </div>

                        <!-- Masalah -->
                        <div class="group">
                            <div class="flex items-center gap-4 mb-4">
                                <div
                                    class="w-2 h-8 bg-pink-600 rounded-full group-hover:scale-y-125 transition-transform origin-top">
                                </div>
                                <h4 class="text-xs font-black uppercase tracking-[0.3em] text-pink-500">Masalah yang
                                    Diselesaikan</h4>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed font-medium"
                                x-text="selectedProject?.problem"></p>
                        </div>

                        <!-- Hasil -->
                        <div class="group">
                            <div class="flex items-center gap-4 mb-4">
                                <div
                                    class="w-2 h-8 bg-emerald-600 rounded-full group-hover:scale-y-125 transition-transform origin-top">
                                </div>
                                <h4 class="text-xs font-black uppercase tracking-[0.3em] text-emerald-500">Apa yang
                                    Dihasilkan</h4>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 text-lg leading-relaxed font-medium"
                                x-text="selectedProject?.result"></p>
                        </div>
                    </div>

                    <div class="mt-12 pt-10 border-t border-gray-100 dark:border-white/5 flex flex-wrap gap-4">
                        <button
                            class="px-8 py-3 bg-zinc-100 dark:bg-white/5 text-zinc-900 dark:text-white rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-zinc-200 dark:hover:bg-white/10 transition">
                            View Live Demo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(var(--primary-rgb), 0.2);
            border-radius: 10px;
        }
    </style>
</body>

</html>