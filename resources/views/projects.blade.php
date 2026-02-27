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

        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark',
    view: 'grid',
    selectedProject: null,
    currentColor: localStorage.getItem('color') || '59, 130, 246',
    projects: [
        {
            id: 'journal',
            tag: 'JAIC PUBLICATION',
            title: 'Recommendation System Research',
            desc: 'Publikasi ilmiah resmi pada Journal of Applied Informatics and Computing (JAIC).',
            bg: 'Penelitian ini didorong oleh kebutuhan untuk meningkatkan sistem rekomendasi pariwisata yang lebih akurat dan personal bagi wisatawan di Yogyakarta.',
            problem: 'Wisatawan seringkali kesulitan menemukan destinasi yang sesuai dengan preferensi mereka di tengah banyaknya pilihan objek wisata.',
            solution: 'Mengimplementasikan Content-Based Filtering menggunakan algoritma TF-IDF dan Cosine Similarity untuk akurasi data yang lebih baik.',
            tech: ['Python', 'Scikit-Learn', 'Pandas', 'Flask'],
            image: '{{ asset('project-media/journal.png') }}',
            link: '#'
        },
        {
            id: 'kpri',
            tag: 'FINTECH ADMIN',
            title: 'KPRI Bakti Mulia',
            desc: 'Digitalisasi simpan pinjam & analisis kredit menggunakan C4.5 Algorithm.',
            bg: 'Koperasi memerlukan sistem otomatis untuk mengelola data anggota dan menganalisis kelayakan kredit secara objektif.',
            problem: 'Proses manual dalam pengelolaan simpan pinjam sering menimbulkan kesalahan data dan subjektivitas dalam persetujuan kredit.',
            solution: 'Dashboard manajemen simpan pinjam terintegrasi dengan mesin inferensi pohon keputusan C4.5 untuk otomasi kelayakan kredit.',
            tech: ['Laravel', 'MySQL', 'Bootstrap', 'Decision Tree C4.5'],
            image: '{{ asset('project-media/kpri.png') }}',
            link: '#'
        },
        {
            id: 'jaugja',
            tag: 'AI RECOMMENDATION',
            title: 'JaugjaKita App',
            desc: 'Sistem cerdas rekomendasi wisata di Yogyakarta berbasis Machine Learning.',
            bg: 'Inisiatif untuk mendukung pariwisata lokal melalui platform aplikasi mobile yang cerdas dan interaktif.',
            problem: 'Kurangnya platform yang mengintegrasikan Machine Learning untuk memberikan rekomendasi berbasis lokasi dan preferensi real-time.',
            solution: 'Integrasi API Machine Learning ke dalam aplikasi Flutter untuk filter destinasi berbasis rating dan kategori.',
            tech: ['Flutter', 'Firebase', 'TensorFlow Lite', 'Node.js'],
            image: '{{ asset('project-media/jaugjakita.png') }}',
            link: '#'
        },
        {
            id: 'kauiz',
            tag: 'ED-TECH SAAS',
            title: 'Kauiz Ai Platform',
            desc: 'Automatisasi pembuatan kuis cerdas berbasis AI untuk pengajar modern.',
            bg: 'Pemanfaatan Generative AI untuk membantu efisiensi pembuatan materi evaluasi bagi pendidik.',
            problem: 'Membuat kuis secara manual membutuhkan waktu yang lama, terutama untuk bank soal yang bervariasi.',
            solution: 'Platform Web yang memanfaatkan LLM (Large Language Model) untuk mengekstrak pertanyaan dari file PDF atau teks bebas secara otomatis.',
            tech: ['Next.js', 'Tailwind CSS', 'Gemini AI API', 'Prisma'],
            image: '{{ asset('project-media/kauiz.png') }}',
            link: 'http://kauiz.jauharfauzi.my.id/'
        },
        {
            id: 'livechat',
            tag: 'REAL-TIME NLP',
            title: 'LiveChat Interaction',
            desc: 'Filtering kata kasar real-time berbasis pemrosesan bahasa alami.',
            bg: 'Kebutuhan akan lingkungan komunikasi online yang sehat dan bebas dari konten toksik.',
            problem: 'Sistem filter konvensional seringkali mudah diakali dengan variasi kata kasar atau slang.',
            solution: 'Engine NLP yang mampu mendeteksi dan menyensor kata-kata kasar secara real-time menggunakan algoritma pencocokan pola cerdas.',
            tech: ['Socket.io', 'Node.js', 'NLP.js', 'Redis'],
            image: '{{ asset('project-media/livechat.png') }}',
            link: 'https://livechat.jauharfauzi.my.id/'
        },
        {
            id: 'jokitugas',
            tag: 'WEB SERVICE',
            title: 'JokiTugas Jogja',
            desc: 'Platform penyedia layanan bantuan pengerjaan tugas akademik berbasis web.',
            bg: 'Banyak mahasiswa membutuhkan bantuan teknis atau konsultasi akademik yang terpercaya dan profesional.',
            problem: 'Sulitnya menemukan layanan konsultasi tugas yang transparan dalam harga dan kualitas pengerjaan.',
            solution: 'Membangun platform booking dan order management yang menghubungkan klien dengan tim ahli secara efisien.',
            tech: ['Laravel', 'Alpine.js', 'Tailwind CSS', 'WhatsApp Business API'],
            image: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=2070&auto=format&fit=crop',
            link: 'http://jokitugas-jogja.my.id/'
        }
    ],
    init() {
        document.documentElement.style.setProperty('--primary-rgb', this.currentColor);
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');
        if (id) {
            const project = this.projects.find(p => p.id === id);
            if (project) {
                this.selectedProject = project;
                this.view = 'detail';
            }
        }
    },
    showDetail(project) {
        this.selectedProject = project;
        this.view = 'detail';
        window.history.pushState({}, '', '?id=' + project.id);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    closeDetail() {
        this.view = 'grid';
        this.selectedProject = null;
        window.history.pushState({}, '', window.location.pathname);
    }
}" x-init="init()" :class="darkMode ? 'dark bg-[#050505]' : 'bg-slate-50'"
    class="min-h-screen transition-all duration-500">

    <div class="bg-mesh fixed inset-0 pointer-events-none opacity-50"></div>

    <!-- Navigation -->
    <nav class="sticky top-0 z-50 backdrop-blur-xl border-b border-gray-200 dark:border-white/10 px-6 py-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <template x-if="view === 'grid'">
                <a href="{{ url('/') }}" class="flex items-center gap-4 group">
                    <div
                        class="bg-primary-600 rounded-full w-10 h-10 flex items-center justify-center shadow-lg shadow-primary-500/30 group-hover:scale-110 transition">
                        <span class="font-bold text-white tracking-tighter">JF</span>
                    </div>
                    <span
                        class="font-black dark:text-white group-hover:text-primary-500 transition uppercase tracking-widest text-sm">Homepage</span>
                </a>
            </template>
            <template x-if="view === 'detail'">
                <button @click="closeDetail()" class="flex items-center gap-4 group">
                    <div
                        class="bg-zinc-800 rounded-full w-10 h-10 flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                        </svg>
                    </div>
                    <span
                        class="font-black dark:text-white group-hover:text-primary-500 transition uppercase tracking-widest text-sm">Kembali
                        ke List</span>
                </button>
            </template>

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

    <!-- Project List View -->
    <main x-show="view === 'grid'" x-cloak x-transition.opacity.duration.500 class="max-w-7xl mx-auto px-6 py-16">
        <div class="mb-16 text-center md:text-left">
            <p class="text-primary-500 font-black text-xs uppercase tracking-[0.4em] mb-4">Portfolio / 03</p>
            <h1 class="text-5xl md:text-7xl font-black dark:text-white tracking-tighter mb-6">
                All <span class="text-primary-500">Masterpieces</span>
            </h1>
            <p class="text-gray-600 dark:text-gray-400 text-xl max-w-2xl leading-relaxed font-medium">
                Eksplorasi mendalam tentang solusi teknologi, inovasi AI, dan sistem yang telah saya bangun.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <template x-for="project in projects" :key="project.id">
                <div @click="showDetail(project)"
                    class="group relative h-[500px] rounded-[3rem] overflow-hidden cursor-pointer shadow-2xl transition-all duration-700 hover:scale-[1.02] border border-white/5">

                    <img :src="project.image"
                        class="absolute inset-0 w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110">
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent opacity-90">
                    </div>

                    <div class="absolute inset-0 p-10 flex flex-col justify-end">
                        <div
                            class="transform translate-y-6 group-hover:translate-y-0 transition-transform duration-500">
                            <span
                                class="inline-block px-4 py-1.5 rounded-full bg-primary-600/30 backdrop-blur-md border border-white/10 text-[10px] font-black tracking-widest text-white mb-6 uppercase"
                                x-text="project.tag"></span>
                            <h3 class="text-3xl font-black text-white mb-3 leading-tight" x-text="project.title"></h3>
                            <p class="text-gray-300 text-sm font-medium line-clamp-2 mb-8" x-text="project.desc"></p>

                            <div class="flex items-center gap-4">
                                <span
                                    class="bg-white text-black px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest group-hover:bg-primary-600 group-hover:text-white transition-colors">Lihat
                                    Detail</span>
                                <div class="flex -space-x-2">
                                    <template x-for="t in project.tech.slice(0,3)">
                                        <div class="w-8 h-8 rounded-full bg-zinc-800 border-2 border-black flex items-center justify-center text-[8px] font-bold text-gray-400 uppercase"
                                            x-text="t.charAt(0)"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <!-- Project Detail View (Pindah Halaman Feel) -->
    <main x-show="view === 'detail'" x-cloak x-transition.opacity.duration.500 class="min-h-screen">
        <div class="max-w-7xl mx-auto px-6 py-12">
            <!-- Hero Detail -->
            <div
                class="relative w-full h-[50vh] md:h-[70vh] rounded-[4rem] overflow-hidden mb-16 shadow-3xl shadow-primary-500/10 border border-white/10">
                <img :src="selectedProject?.image" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-[#050505] via-transparent to-transparent"></div>
                <div class="absolute bottom-12 md:bottom-20 left-8 md:left-20 max-w-4xl">
                    <span
                        class="inline-block px-6 py-2 rounded-full bg-primary-600 text-white text-[12px] font-black tracking-widest uppercase mb-6"
                        x-text="selectedProject?.tag"></span>
                    <h1 class="text-5xl md:text-8xl font-black text-white tracking-tighter mb-8"
                        x-text="selectedProject?.title"></h1>
                    <div class="flex flex-wrap gap-4">
                        <template x-for="t in selectedProject?.tech">
                            <span
                                class="px-5 py-2 glass rounded-full text-white text-xs font-bold border border-white/10"
                                x-text="t"></span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-start">
                <div class="lg:col-span-8 space-y-20">
                    <section>
                        <div class="flex items-center gap-6 mb-8">
                            <div class="w-3 h-12 bg-primary-600 rounded-full"></div>
                            <h2 class="text-3xl font-black dark:text-white uppercase tracking-tighter">Latar Belakang
                            </h2>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 text-xl md:text-2xl leading-relaxed font-medium"
                            x-text="selectedProject?.bg"></p>
                    </section>

                    <section>
                        <div class="flex items-center gap-6 mb-8">
                            <div class="w-3 h-12 bg-pink-600 rounded-full"></div>
                            <h2 class="text-3xl font-black dark:text-white uppercase tracking-tighter">Masalah yang Di
                                Hadapi</h2>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 text-xl md:text-2xl leading-relaxed font-medium"
                            x-text="selectedProject?.problem"></p>
                    </section>

                    <section>
                        <div class="flex items-center gap-6 mb-8">
                            <div class="w-3 h-12 bg-emerald-600 rounded-full"></div>
                            <h2 class="text-3xl font-black dark:text-white uppercase tracking-tighter">Penyelesaian
                                Masalah</h2>
                        </div>
                        <p class="text-gray-600 dark:text-gray-400 text-xl md:text-2xl leading-relaxed font-medium"
                            x-text="selectedProject?.solution"></p>
                    </section>
                </div>

                <div class="lg:col-span-4 sticky top-32">
                    <div
                        class="p-10 rounded-[3rem] bg-white dark:bg-zinc-950 border border-gray-200 dark:border-white/10 shadow-2xl">
                        <h4 class="text-xs font-black uppercase tracking-widest text-primary-500 mb-8">Informasi Projek
                        </h4>
                        <div class="space-y-8">
                            <div>
                                <h5 class="text-[10px] font-black uppercase text-gray-400 mb-2">Teknologi</h5>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="t in selectedProject?.tech">
                                        <span class="text-zinc-900 dark:text-white font-bold"
                                            x-text="t + (selectedProject?.tech.indexOf(t) === selectedProject?.tech.length - 1 ? '' : ', ')"></span>
                                    </template>
                                </div>
                            </div>
                            <hr class="border-gray-100 dark:border-white/5">
                            <a :href="selectedProject?.link" target="_blank"
                                class="block w-full py-5 bg-primary-600 hover:bg-primary-700 text-white text-center rounded-2xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-primary-500/20">
                                Kunjungi Live Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="max-w-7xl mx-auto px-6 py-20 border-t border-gray-200 dark:border-white/5 text-center">
        <p class="text-gray-500 font-medium">&copy; 2026 Jauhar Fauzi Ulul Albab. Project Showcase Portfolio.</p>
    </footer>
</body>

</html>