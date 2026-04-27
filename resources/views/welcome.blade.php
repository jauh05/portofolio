<!DOCTYPE html>
<html :lang="lang">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jauhar Ulul Fauzi - Portfolio</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon_jf.png') }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css">

    <!-- Vite & React -->
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Tailwind Config -->
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
                    },
                    animation: {
                        'spin-slow': 'spin 8s linear infinite',
                    }
                }
            }
        }
    </script>

    <style>
        /* Premium Easing Utility */
        :root {
            --primary-rgb: 59, 130, 246;
            --ease-premium: cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Smooth Theme Transitions */
        html,
        body {
            transition: background-color 0.6s var(--ease-premium), color 0.6s var(--ease-premium);
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
            overflow-x: hidden;
            width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            width: 100%;
        }

        /* Dynamic Premium Gradient Background */
        .bg-mesh-theme {
            position: relative;
            z-index: 0;
            overflow-x: hidden;
            background: radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.12), transparent 40%),
                        radial-gradient(circle at bottom left, rgba(var(--primary-rgb), 0.08), transparent 40%);
        }

        .dark .bg-mesh-theme {
            background: radial-gradient(circle at top right, rgba(var(--primary-rgb), 0.15), transparent 40%),
                        radial-gradient(circle at bottom left, rgba(var(--primary-rgb), 0.1), transparent 40%);
        }

        .bg-mesh-theme::before {
            content: '';
            position: fixed;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(var(--primary-rgb), 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(var(--primary-rgb), 0.05) 0%, transparent 50%);
            z-index: -15;
            pointer-events: none;
            animation: pulse-gradient 20s ease-in-out infinite alternate;
        }

        @keyframes pulse-gradient {
            0% { opacity: 0.4; transform: scale(1) translate(0, 0); }
            100% { opacity: 0.8; transform: scale(1.05) translate(2%, 2%); }
        }

        .dark .bg-mesh-theme::before {
            background: 
                radial-gradient(circle at 20% 30%, rgba(var(--primary-rgb), 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(var(--primary-rgb), 0.08) 0%, transparent 50%);
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(var(--primary-rgb), 0.1);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            transition: all 0.6s var(--ease-premium);
        }

        .dark .glass-card {
            background: rgba(15, 15, 20, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        /* Animation & Transition Optimization */
        [data-aos] {
            pointer-events: none;
            will-change: transform, opacity;
        }

        [data-aos].aos-animate {
            pointer-events: auto;
        }

        /* Smooth UI Interactions */
        button, a, .group, .card-hover {
            transition: all 0.5s var(--ease-premium);
        }

        .hero-photo-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(2deg); }
            50% { transform: translateY(-20px) rotate(-1deg); }
        }

        @media (max-width: 768px) {
            @keyframes float {
                0%, 100% { transform: translateY(0) rotate(0deg); }
                50% { transform: translateY(-10px) rotate(1deg); }
            }
        }
    </style>
</head>

<body x-data="{ 
        lang: 'id',
        darkMode: localStorage.getItem('theme') ? localStorage.getItem('theme') === 'dark' : true,
        currentColor: localStorage.getItem('color') || '59, 130, 246',
        selectedJournal: false,
        t: {
            id: {
                nav: { about: 'Tentang', projects: 'Projek', skills: 'Keahlian', contact: 'Kontak', talk: 'Bicara', serti: 'Sertifikasi' },
                hero: {
                    title: 'ULUL ALBAB',
                    subtitle_first: 'JAUHAR',
                    subtitle_second: 'FAUZI',
                    tagline_line1: 'Digital Solutions Engineer',
                    tagline_rotating: ['Tech Problem Solver', 'Full Stack Developer', 'System Architect', 'AI Enthusiast'],
                    desc: 'Seorang profesional teknologi yang berfokus pada pengembangan solusi digital yang inovatif, efisien, dan berdampak. Memiliki ketertarikan besar pada pemanfaatan teknologi modern, AI, serta rekayasa perangkat lunak untuk menyelesaikan masalah nyata.',
                    cta_projects: 'Lihat Projek Saya',
                    cta_contact: 'Hubungi Saya',
                    years_exp: '4+ Tahun Pengalaman'
                },
                stats: { completed: 'Projek Selesai', stack: 'Tech Stack', exp: 'Tahun Exp', commit: 'Komitmen' },
                experience: {
                    title: 'Professional',
                    subtitle: 'Experience',
                    asdos: {
                        role: 'Asisten Dosen – Struktur Data',
                        company: 'Universitas Amikom Yogyakarta',
                        date: 'September 2024 – Sekarang',
                        points: [
                            'Mendukung proses pembelajaran mata kuliah Struktur Data dan pemrograman struktural.',
                            'Membimbing mahasiswa dalam memahami konsep fundamental seperti array, linked list, stack, queue, tree, dan algoritma dasar.',
                            'Memberikan asistensi praktikum serta membantu penyusunan dan evaluasi tugas pemrograman.',
                            'Berperan sebagai fasilitator diskusi teknis untuk meningkatkan pemahaman konseptual dan problem-solving mahasiswa.'
                        ]
                    },
                    jauki: {
                        role: 'Tim Leader – Jauki Academy',
                        company: 'Software House & Education',
                        date: 'November 2022 – Sekarang',
                        points: [
                            'Memimpin tim dalam pengembangan layanan pembuatan website, digital portfolio, dan AI prompting solutions.',
                            'Terlibat langsung dalam perancangan arsitektur sistem, pengembangan backend & frontend, serta deployment.',
                            'Menyusun strategi pemasaran digital dan branding untuk meningkatkan visibilitas serta akuisisi klien.',
                            'Mengelola proyek end-to-end mulai dari requirement gathering, development, hingga maintenance.',
                            'Berhasil membangun sistem kerja tim yang agile dan berorientasi pada hasil.'
                        ]
                    },
                    magang: {
                        role: 'Magang – Asisten Programmer',
                        company: 'Waroeng Steak Indonesia',
                        date: 'Agustus 2024 – September 2024',
                        points: [
                            'Berkontribusi dalam pengembangan sistem loyalitas pelanggan berbasis website.',
                            'Mengimplementasikan fitur berbasis Laravel dan PHP lanjutan sesuai kebutuhan bisnis.',
                            'Terlibat dalam troubleshooting teknis serta mempresentasikan solusi kepada tim marketing.',
                            'Mengembangkan sistem loyalty yang dirancang untuk mendukung pertumbuhan bisnis.'
                        ]
                    },
                    freedom: {
                        role: 'Team Leader & System Innovator',
                        company: 'Freedomspace – Live Chat System',
                        date: 'Mei 2025',
                        points: [
                            'Menginisiasi sistem live chat berbasis web dengan 10.000+ pengguna aktif.',
                            'Solusi interaktif untuk mengisi jeda pergantian musisi dalam konser.',
                            'Diimplementasikan pada Perantara Fest and Lane of Koplo.',
                            'Memimpin operasional tim secara real-time di lokasi acara.',
                            'Bertanggung jawab atas stabilitas sistem selama kondisi high traffic.'
                        ]
                    }
                },
                projects: {
                    tag: 'Work & Research',
                    title: 'Featured',
                    subtitle: 'Projects',
                    desc: 'Kumpulan solusi digital dan publikasi ilmiah yang berfokus pada inovasi teknologi.',
                    swipe: 'Geser Layar',
                    visit: 'KUNJUNGI WEB',
                    open: 'BUKA PROJEK',
                    explore: 'EKSPLOR APLIKASI',
                    demo: 'LIVE DEMO',
                    journal: {
                        tag: 'JAIC PUBLICATION',
                        title: 'Recommendation System Research',
                        desc: 'Publikasi ilmiah resmi pada Journal of Applied Informatics and Computing (JAIC). Membahas optimasi TF-IDF & Cosine Similarity untuk pariwisata.',
                        badge: 'SINTA 3',
                        accredited: 'Terakreditasi',
                        official: 'Official National Indexing',
                        cta: 'View Official PDF'
                    },
                    kpri: { tag: 'Fintech Admin', title: 'KPRI Bakti Mulia', desc: 'Digitalization of savings & loans and credit analysis using C4.5 Algorithm.' },
                    jaugja: { tag: 'AI Recommendation', title: 'JaugjaKita App', desc: 'Sistem cerdas rekomendasi wisata di Yogyakarta berbasis Machine Learning.' },
                    kauiz: { tag: 'Ed-Tech SaaS', title: 'Kauiz Ai Platform', desc: 'Automatisasi pembuatan kuis cerdas berbasis AI untuk pengajar modern.' },
                    livechat: { tag: 'Real-time NLP', title: 'LiveChat Interaction', desc: 'Filtering kata kasar real-time berbasis pemrosesan bahasa alami.' },
                    jokitugas: { tag: 'Academic Service', title: 'JokiTugas Jogja', desc: 'Platform layanan bantuan akademik terintegrasi untuk mahasiswa.' }
                },
                skills: { 
                    title: 'Technical', subtitle: 'Skills', desc: 'Keahlian dalam teknologi web modern dan AI.', 
                    swipe: 'Geser untuk eksplorasi', categories: { backend: 'Backend', frontend: 'Frontend', mobile: 'Mobile', tools: 'Tools' }
                },
                education: {
                    title: 'Education', subtitle: '& Training',
                    amikom: { name: 'Universitas Amikom Yogyakarta', major: 'Sistem Informasi', info: 'Angkatan 2023 • IPK: 3.91', date: 'Agustus 2023 - Sekarang' },
                    trainit: { name: 'TrainIT Jogja', major: 'Fullstack Developer', info: 'Laravel & Machine Learning (K-Means)', date: 'Febuari 2025 - Sekarang' }
                },
                soft: {
                    title: 'Informasi', subtitle: 'Tambahan', swipe: 'Geser untuk lainnya',
                    team: { title: 'Kerja Tim Solid', desc: 'Terbiasa bekerja dalam tim lintas bidang dan komunikasi efektif.' },
                    time: { title: 'Disiplin Waktu', desc: 'Menyelesaikan tugas sesuai target dengan manajemen waktu baik.' },
                    comm: { title: 'Komunikatif', desc: 'Menyampaikan ide dan presentasi dengan jelas dan percaya diri.' }
                },
                footer: {
                    tag: 'Get In Touch',
                    title: 'Ayo bangun',
                    subtitle: 'sesuatu yang luar biasa',
                    quote: 'Terus belajar, beradaptasi, dan tumbuh bersama teknologi.',
                    email_label: 'Email Saya',
                    status_label: 'Status Saat Ini',
                    available: 'Tersedia untuk Pekerjaan',
                    loc_label: 'Lokasi Utama',
                    location: 'Yogyakarta, Indonesia',
                    remote: 'Remote / On-site capable',
                    open_label: 'Terbuka Untuk',
                    freelance: 'Freelance',
                    collab: 'Kolaborasi',
                    cta: 'Mulai Percakapan'
                },
                certs: {
                    tag: 'Recognitions',
                    title: 'Featured',
                    subtitle: 'Certifications',
                    desc: 'Penghargaan dan sertifikasi profesional dalam bidang teknologi dan akademik.',
                    cta: 'Lihat Semua Sertifikat'
                }
            },
            en: {
                nav: { about: 'About', projects: 'Projects', skills: 'Skills', contact: 'Contact', talk: 'Let\'s Talk', serti: 'Certifications' },
                hero: {
                    title: 'ULUL ALBAB',
                    subtitle_first: 'JAUHAR',
                    subtitle_second: 'FAUZI',
                    tagline_line1: 'Digital Solutions Engineer',
                    tagline_rotating: ['Tech Problem Solver', 'Full Stack Developer', 'System Architect', 'AI Enthusiast'],
                    desc: 'A technology professional focused on developing innovative, efficient, and impactful digital solutions. Passionate about modern technology, AI, and software engineering to solve real-world problems.',
                    cta_projects: 'View My Projects',
                    cta_contact: 'Contact Me',
                    years_exp: '4+ Years Experience'
                },
                stats: { completed: 'Projects Completed', stack: 'Tech Stack', exp: 'Years Exp', commit: 'Commitment' },
                experience: {
                    title: 'Professional',
                    subtitle: 'Experience',
                    asdos: {
                        role: 'Teaching Assistant – Data Structures',
                        company: 'Amikom University Yogyakarta',
                        date: 'September 2024 – Present',
                        points: [
                            'Supported the learning process for Data Structures and structural programming courses.',
                            'Guided students in understanding fundamental concepts like arrays, linked lists, stacks, queues, trees, and basic algorithms.',
                            'Provided lab assistance and helped in drafting and evaluating programming assignments.',
                            'Acted as a technical discussion facilitator to improve students\' conceptual understanding and problem-solving skills.'
                        ]
                    },
                    jauki: {
                        role: 'Team Leader – Jauki Academy',
                        company: 'Software House & Education',
                        date: 'November 2022 – Present',
                        points: [
                            'Led the team in developing website creation services, digital portfolios, and AI prompting solutions.',
                            'Directly involved in system architecture design, backend & frontend development, and deployment.',
                            'Developed digital marketing and branding strategies to increase visibility and client acquisition.',
                            'Managed projects end-to-end from requirement gathering, development, to maintenance.',
                            'Successfully built an agile and result-oriented team work system.'
                        ]
                    },
                    magang: {
                        role: 'Intern – Assistant Programmer',
                        company: 'Waroeng Steak Indonesia',
                        date: 'August 2024 – September 2024',
                        points: [
                            'Contributed to the development of a website-based customer loyalty system.',
                            'Implemented Laravel and advanced PHP features according to business needs.',
                            'Involved in technical troubleshooting and presented solutions to the marketing team.',
                            'Developed a loyalty system designed to support business growth.'
                        ]
                    },
                    freedom: {
                        role: 'Team Leader & System Innovator',
                        company: 'Freedomspace – Live Chat System',
                        date: 'May 2025',
                        points: [
                            'Initiated and developed a web-based live chat system with 10,000+ active users.',
                            'Interactive solution to fill the musician changeover gap in concerts.',
                            'Implemented at Perantara Fest and Lane of Koplo events.',
                            'Led the team operationally in real-time at the event location.',
                            'Fully responsible for system stability during high traffic conditions.'
                        ]
                    }
                },
                projects: {
                    tag: 'Work & Research',
                    title: 'Featured',
                    subtitle: 'Projects',
                    desc: 'A collection of digital solutions and scientific publications focused on technological innovation.',
                    swipe: 'Swipe Screen',
                    visit: 'VISIT WEBSITE',
                    open: 'OPEN PROJECT',
                    explore: 'EXPLORE APP',
                    demo: 'LIVE DEMO',
                    journal: {
                        tag: 'JAIC PUBLICATION',
                        title: 'Recommendation System Research',
                        desc: 'Official scientific publication in the Journal of Applied Informatics and Computing (JAIC). Discusses TF-IDF & Cosine Similarity optimization for tourism.',
                        badge: 'SINTA 3',
                        accredited: 'Accredited',
                        official: 'Official National Indexing',
                        cta: 'View Official PDF'
                    },
                    kpri: { tag: 'Fintech Admin', title: 'KPRI Bakti Mulia', desc: 'Digitalization of savings & loans and credit analysis using C4.5 Algorithm.' },
                    jaugja: { tag: 'AI Recommendation', title: 'JaugjaKita App', desc: 'Intelligent tourism recommendation system in Yogyakarta based on Machine Learning.' },
                    kauiz: { tag: 'Ed-Tech SaaS', title: 'Kauiz Ai Platform', desc: 'Automation of intelligent AI-based quiz creation for modern educators.' },
                    livechat: { tag: 'Real-time NLP', title: 'LiveChat Interaction', desc: 'Real-time coarse word filtering based on natural language processing.' },
                    jokitugas: { tag: 'Academic Service', title: 'JokiTugas Jogja', desc: 'Integrated academic assistance service platform for students.' }
                },
                skills: {
                    title: 'Technical', subtitle: 'Skills', desc: 'Expertise in modern web technologies and AI.',
                    swipe: 'Swipe to explore', categories: { backend: 'Backend', frontend: 'Frontend', mobile: 'Mobile', tools: 'Tools' }
                },
                education: {
                    title: 'Education', subtitle: '& Training',
                    amikom: { name: 'Amikom University Yogyakarta', major: 'Information Systems', info: 'Class of 2023 • GPA: 3.91', date: 'August 2023 - Present' },
                    trainit: { name: 'TrainIT Jogja', major: 'Fullstack Developer', info: 'Laravel & Machine Learning (K-Means)', date: 'February 2025 - Present' }
                },
                soft: {
                    title: 'Additional', subtitle: 'Information', swipe: 'Swipe for more',
                    team: { title: 'Solid Teamwork', desc: 'Used to working in cross-functional teams and effective communication.' },
                    time: { title: 'Time Discipline', desc: 'Completing tasks according to targets with good time management.' },
                    comm: { title: 'Communicative', desc: 'Conveying ideas and presentations clearly and confidently.' }
                },
                footer: {
                    tag: 'Get In Touch',
                    title: 'Let\'s build',
                    subtitle: 'something extraordinary',
                    quote: 'Keep learning, adapting, and growing with technology.',
                    email_label: 'Email Me',
                    status_label: 'Current Status',
                    available: 'Available for Work',
                    loc_label: 'Base Location',
                    location: 'Yogyakarta, Indonesia',
                    remote: 'Remote / On-site capable',
                    open_label: 'Open For',
                    freelance: 'Freelance',
                    collab: 'Collaboration',
                    cta: 'Start a Conversation'
                },
                certs: {
                    tag: 'Recognitions',
                    title: 'Featured',
                    subtitle: 'Certifications',
                    desc: 'Professional awards and certifications in technology and academics.',
                    cta: 'View All Certificates'
                }
            }
        },
        initTheme() {
            if (this.darkMode) document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
            document.documentElement.style.setProperty('--primary-rgb', this.currentColor);
        },
        toggleTheme() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            this.initTheme();
        },
        setThemeColor(rgb) {
            this.currentColor = rgb;
            localStorage.setItem('color', rgb);
            this.initTheme();
        window.dispatchEvent(new CustomEvent('color-change', { detail: { color: rgb } }));
        }
    }" x-init="initTheme()" :class="darkMode ? 'text-white' : 'text-zinc-900'"
    class="bg-mesh-theme antialiased transition-colors duration-300">

    <!-- Global Background Color Layer -->
    <div class="fixed inset-0 z-[-20] transition-colors duration-500"
        :class="darkMode ? 'bg-[#08080a]' : 'bg-white'"></div>

    <!-- Dynamic Navbar Bubble Menu -->
    <div data-bubble-menu data-logo="JF" :data-items="JSON.stringify([
            { label: t[lang].nav.about, href: '#about', rotation: -8, hoverStyles: { bgColor: 'rgba(var(--primary-rgb), 1)', textColor: '#fff' } },
            { label: t[lang].nav.projects, href: '#projects', rotation: 8, hoverStyles: { bgColor: '#f59e0b', textColor: '#fff' } },
            { label: t[lang].nav.skills, href: '#skills', rotation: -8, hoverStyles: { bgColor: '#10b981', textColor: '#fff' } },
            { label: t[lang].nav.serti, href: '#certifications', rotation: 8, hoverStyles: { bgColor: '#8b5cf6', textColor: '#fff' } },
            { label: t[lang].nav.contact, href: '#contact', rotation: -8, hoverStyles: { bgColor: '#ef4444', textColor: '#fff' } }
        ])"></div>

    <header id="about" data-aos="fade-up" data-aos-duration="1200"
        class="max-w-6xl mx-auto px-6 pt-24 md:pt-32 pb-12 md:pb-16 flex flex-col md:flex-row items-center gap-12 md:gap-8">
        <div class="flex-1 text-center md:text-left order-2 md:order-1">
            <h1
                class="text-5xl md:text-7xl font-black leading-[0.85] mb-6 tracking-tighter uppercase flex flex-col items-center md:items-start translate-x-[-2px]">
                <span
                    class="text-zinc-400 dark:text-zinc-600 block text-2xl md:text-3xl font-light tracking-[0.2em] mb-1 md:-mb-1 leading-none"
                    x-text="t[lang].hero.title" data-split-text></span>
                <div class="flex flex-row flex-wrap md:flex-col justify-center md:justify-start gap-x-2 md:-space-y-1 leading-none mt-1">
                    <span class="text-zinc-900 dark:text-white block overflow-hidden leading-[0.9] md:leading-[0.85]"
                        x-text="t[lang].hero.subtitle_first" data-split-text></span>
                    <span class="text-zinc-900 dark:text-white block overflow-hidden leading-[0.9] md:leading-[0.85]"
                        x-text="t[lang].hero.subtitle_second" data-split-text></span>
                </div>
            </h1>

            <div class="flex flex-col mb-6 items-center md:items-start">
                <p class="text-xl md:text-3xl text-zinc-600 dark:text-zinc-400 font-bold tracking-tight mb-1"
                    x-text="t[lang].hero.tagline_line1" data-split-text></p>

                <div class="flex items-center gap-2 text-xl md:text-3xl justify-center md:justify-start">
                    <span class="text-primary-500 font-black">&</span>
                    <div data-rotating-text :data-rotating-texts="JSON.stringify(t[lang].hero.tagline_rotating)"
                        data-rotating-main-class="px-3 bg-primary-500/10 text-primary-500 dark:text-primary-400 py-0.5 rounded-lg border border-primary-500/20 font-black tracking-tight">
                    </div>
                </div>
            </div>

            <p class="text-gray-600 dark:text-gray-400 text-base md:text-lg mb-6 max-w-2xl leading-snug font-medium text-center md:text-justify"
                x-text="t[lang].hero.desc" data-split-text data-split-type="words">
            </p>
            <div class="flex flex-col gap-3">
                <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start items-center">
                    <a href="#projects"
                        class="bg-primary-600 hover:bg-primary-700 text-white px-10 py-5 rounded-full font-black text-xs uppercase tracking-widest transition-all transform hover:scale-105 text-center min-w-[220px] shadow-[0_20px_40px_-10px_rgba(var(--primary-rgb),0.4)]"
                        x-text="t[lang].hero.cta_projects">
                    </a>
                    <a href="#contact"
                        class="bg-zinc-100 dark:bg-zinc-800/50 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-900 dark:text-white px-10 py-5 rounded-full font-black text-xs uppercase tracking-widest transition-all border border-gray-200 dark:border-white/10 text-center min-w-[220px]"
                        x-text="t[lang].hero.cta_contact">
                    </a>
                </div>
            </div>
        </div>

        <div class="flex-1 relative flex items-center justify-center min-h-[350px] md:min-h-[500px] order-1 md:order-2 w-full">
            <!-- Main Photo Container -->
            <div
                class="relative z-10 w-[280px] sm:w-[350px] md:w-full max-w-[450px] aspect-[0.85] rounded-[3rem] overflow-hidden shadow-2xl border-4 border-white/10 bg-zinc-800 hero-photo-float transition-all duration-1000">
                <img src="/foto.png" alt="Jauhar Fauzi" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-60"></div>
            </div>

            <!-- 3D Interactive Lanyard (Fixed for mobile) -->
            <div
                class="absolute -top-32 -right-8 md:-right-24 w-[300px] md:w-[400px] h-[700px] md:h-[900px] z-30 pointer-events-none drop-shadow-[0_20px_50px_rgba(0,0,0,0.5)] opacity-40 md:opacity-100">
                <div data-lanyard class="w-full h-full pointer-events-auto"></div>
            </div>
        </div>
    </header>

    <section class="max-w-6xl mx-auto px-6 py-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div data-aos="fade-up" data-aos-delay="100"
                class="bg-primary-600/10 border border-primary-500/20 p-8 rounded-3xl text-center hover:bg-primary-600/20 transition duration-300">
                <h3 class="text-3xl font-black mb-1 text-primary-600 dark:text-primary-400">8+</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 font-bold" x-text="t[lang].stats.completed"></p>
            </div>
            <div data-aos="fade-up" data-aos-delay="200"
                class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 p-8 rounded-3xl text-center hover:border-primary-500/30 transition duration-300 shadow-sm dark:shadow-none">
                <h3 class="text-3xl font-black mb-1 text-zinc-900 dark:text-white">10+</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 font-bold" x-text="t[lang].stats.stack"></p>
            </div>
            <div data-aos="fade-up" data-aos-delay="300"
                class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 p-8 rounded-3xl text-center hover:border-primary-500/30 transition duration-300 shadow-sm dark:shadow-none">
                <h3 class="text-3xl font-black mb-1 text-zinc-900 dark:text-white">4+</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 font-bold" x-text="t[lang].stats.exp"></p>
            </div>
            <div data-aos="fade-up" data-aos-delay="400"
                class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 p-8 rounded-3xl text-center hover:border-primary-500/30 transition duration-300 shadow-sm dark:shadow-none">
                <h3 class="text-3xl font-black mb-1 text-zinc-900 dark:text-white">100%</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 font-bold" x-text="t[lang].stats.commit"></p>
            </div>
        </div>
    </section>

    <!-- Professional Experience (Moved Up & Enhanced) -->
    <section class="max-w-6xl mx-auto px-6 py-8" data-aos="fade-up" data-aos-delay="200">
        <div class="flex flex-col md:flex-row justify-between items-end mb-8">
            <div>
                <h2 class="text-3xl md:text-4xl font-black mb-4 tracking-tight">
                    <span class="text-primary-500" x-text="t[lang].experience.title"></span>
                    <span class="text-zinc-900 dark:text-white" x-text="t[lang].experience.subtitle"></span>
                </h2>
                <div class="w-20 h-1.5 bg-primary-600 rounded-full"></div>
            </div>
            <div
                class="text-primary-500 font-mono text-sm hidden md:block border-b border-primary-500/30 pb-2 uppercase tracking-widest">
                Career Timeline
            </div>
        </div>

        <div class="relative space-y-4" x-data="{ activeExp: null }">
            <!-- Asdos -->
            <div data-aos="fade-up" data-aos-delay="100"
                class="group border border-gray-100 dark:border-white/5 rounded-3xl bg-white dark:bg-zinc-900/40 transition-all duration-300 overflow-hidden">
                <button @click="activeExp = (activeExp === 'asdos' ? null : 'asdos')"
                    class="w-full flex items-center justify-between p-5 md:p-6 text-left">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-2xl bg-primary-500/10 flex items-center justify-center text-2xl">
                            🎓</div>
                        <div>
                            <h3 class="text-xl md:text-2xl font-black text-zinc-900 dark:text-white"
                                x-text="t[lang].experience.asdos.role"></h3>
                            <p class="text-primary-600 dark:text-primary-400 font-bold"
                                x-text="t[lang].experience.asdos.company"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="hidden md:block text-xs font-black uppercase tracking-widest text-gray-400"
                            x-text="t[lang].experience.asdos.date"></span>
                        <svg class="w-6 h-6 text-primary-500 transition-transform duration-300"
                            :class="activeExp === 'asdos' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                <div x-show="activeExp === 'asdos'" x-collapse>
                    <div class="px-6 md:px-8 pb-8 pt-2">
                        <ul class="space-y-2 bg-primary-500/5 p-6 rounded-2xl border border-primary-500/10">
                            <template x-for="point in t[lang].experience.asdos.points">
                                <li
                                    class="flex items-start gap-3 text-gray-600 dark:text-gray-400 text-base leading-snug">
                                    <span class="w-2 h-2 rounded-full bg-primary-500 mt-2 flex-shrink-0"></span>
                                    <span x-text="point"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Jauki -->
            <div data-aos="fade-up" data-aos-delay="200"
                class="group border border-gray-100 dark:border-white/5 rounded-3xl bg-white dark:bg-zinc-900/40 transition-all duration-300 overflow-hidden">
                <button @click="activeExp = (activeExp === 'jauki' ? null : 'jauki')"
                    class="w-full flex items-center justify-between p-5 md:p-6 text-left">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-2xl bg-purple-500/10 flex items-center justify-center text-2xl">🚀
                        </div>
                        <div>
                            <h3 class="text-xl md:text-2xl font-black text-zinc-900 dark:text-white"
                                x-text="t[lang].experience.jauki.role"></h3>
                            <p class="text-purple-600 dark:text-purple-400 font-bold"
                                x-text="t[lang].experience.jauki.company"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="hidden md:block text-xs font-black uppercase tracking-widest text-gray-400"
                            x-text="t[lang].experience.jauki.date"></span>
                        <svg class="w-6 h-6 text-purple-500 transition-transform duration-300"
                            :class="activeExp === 'jauki' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                <div x-show="activeExp === 'jauki'" x-collapse>
                    <div class="px-6 md:px-8 pb-8 pt-2">
                        <ul class="space-y-2 bg-purple-500/5 p-6 rounded-2xl border border-purple-500/10">
                            <template x-for="point in t[lang].experience.jauki.points">
                                <li
                                    class="flex items-start gap-3 text-gray-600 dark:text-gray-400 text-base leading-snug">
                                    <span class="w-2 h-2 rounded-full bg-purple-500 mt-2 flex-shrink-0"></span>
                                    <span x-text="point"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Magang -->
            <div data-aos="fade-up" data-aos-delay="300"
                class="group border border-gray-100 dark:border-white/5 rounded-3xl bg-white dark:bg-zinc-900/40 transition-all duration-300 overflow-hidden">
                <button @click="activeExp = (activeExp === 'magang' ? null : 'magang')"
                    class="w-full flex items-center justify-between p-5 md:p-6 text-left">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-2xl">🍔
                        </div>
                        <div>
                            <h3 class="text-xl md:text-2xl font-black text-zinc-900 dark:text-white"
                                x-text="t[lang].experience.magang.role"></h3>
                            <p class="text-yellow-600 dark:text-yellow-500 font-bold"
                                x-text="t[lang].experience.magang.company"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="hidden md:block text-xs font-black uppercase tracking-widest text-gray-400"
                            x-text="t[lang].experience.magang.date"></span>
                        <svg class="w-6 h-6 text-yellow-500 transition-transform duration-300"
                            :class="activeExp === 'magang' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                <div x-show="activeExp === 'magang'" x-collapse>
                    <div class="px-6 md:px-8 pb-8 pt-2">
                        <ul class="space-y-2 bg-yellow-500/5 p-6 rounded-2xl border border-yellow-500/10">
                            <template x-for="point in t[lang].experience.magang.points">
                                <li
                                    class="flex items-start gap-3 text-gray-600 dark:text-gray-400 text-base leading-snug">
                                    <span class="w-2 h-2 rounded-full bg-yellow-500 mt-2 flex-shrink-0"></span>
                                    <span x-text="point"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Freedom -->
            <div data-aos="fade-up" data-aos-delay="400"
                class="group border border-gray-100 dark:border-white/5 rounded-3xl bg-white dark:bg-zinc-900/40 transition-all duration-300 overflow-hidden">
                <button @click="activeExp = (activeExp === 'freedom' ? null : 'freedom')"
                    class="w-full flex items-center justify-between p-5 md:p-6 text-left">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-2xl">🎸
                        </div>
                        <div>
                            <h3 class="text-xl md:text-2xl font-black text-zinc-900 dark:text-white"
                                x-text="t[lang].experience.freedom.role"></h3>
                            <p class="text-indigo-600 dark:text-indigo-400 font-bold"
                                x-text="t[lang].experience.freedom.company"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="hidden md:block text-xs font-black uppercase tracking-widest text-gray-400"
                            x-text="t[lang].experience.freedom.date"></span>
                        <svg class="w-6 h-6 text-indigo-500 transition-transform duration-300"
                            :class="activeExp === 'freedom' ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>
                <div x-show="activeExp === 'freedom'" x-collapse>
                    <div class="px-6 md:px-8 pb-8 pt-2">
                        <ul class="space-y-2 bg-indigo-500/5 p-6 rounded-2xl border border-indigo-500/10">
                            <template x-for="point in t[lang].experience.freedom.points">
                                <li
                                    class="flex items-start gap-3 text-gray-600 dark:text-gray-400 text-base leading-snug">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500 mt-2 flex-shrink-0"></span>
                                    <span x-text="point"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- Certificates Highlights -->
    <section id="certifications" class="max-w-7xl mx-auto px-6 py-16 relative overflow-hidden" data-aos="fade-up"
        data-aos-delay="100">
        <!-- Premium Background Elements -->
        <div
            class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 w-[600px] h-[600px] bg-primary-600/10 rounded-full blur-[140px] pointer-events-none opacity-50">
        </div>
        <div
            class="absolute bottom-0 left-0 translate-y-1/2 -translate-x-1/2 w-[500px] h-[500px] bg-purple-600/5 rounded-full blur-[120px] pointer-events-none opacity-30">
        </div>

        <div class="relative w-full" x-data="{
            canScrollLeft: false,
            canScrollRight: true,
            checkScroll() {
                const slider = this.$refs.featuredSlider;
                if (!slider) return;
                this.canScrollLeft = slider.scrollLeft > 0;
                this.canScrollRight = Math.ceil(slider.scrollLeft) < (slider.scrollWidth - slider.clientWidth - 5);
            },
            slideLeft() { 
                this.$refs.featuredSlider.scrollBy({ left: -window.innerWidth * 0.5, behavior: 'smooth' }); 
                setTimeout(() => this.checkScroll(), 500);
            },
            slideRight() { 
                this.$refs.featuredSlider.scrollBy({ left: window.innerWidth * 0.5, behavior: 'smooth' }); 
                setTimeout(() => this.checkScroll(), 500);
            }
        }"
            x-init="setTimeout(() => checkScroll(), 200); setTimeout(() => checkScroll(), 1000); window.addEventListener('resize', () => checkScroll()); $refs.featuredSlider.addEventListener('scroll', () => checkScroll())">

            <div class="mb-10 relative z-10 flex flex-col md:flex-row md:items-end justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-500/10 border border-primary-500/20 text-primary-400 text-[10px] font-black tracking-widest uppercase mb-6"
                        x-text="t[lang].certs.tag">
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4 uppercase">
                        <span
                            class="text-zinc-400 dark:text-zinc-700 text-2xl md:text-3xl font-light tracking-[0.1em]">Recognitions
                            & Awards</span>
                        <a href="{{ url('/certificates') }}"
                            class="text-primary-500 font-black text-sm hover:underline flex items-center gap-2">
                            Lihat Semua Sertifikat
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    </div>
                    <h2 class="text-4xl md:text-6xl font-black dark:text-white tracking-tighter mb-4 leading-tight">
                        <span class="text-primary-500">Official Certifications</span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 text-lg max-w-2xl leading-snug font-medium">
                        Pencapaian dan sertifikasi profesional dalam bidang teknologi dan akademik.
                    </p>
                </div>

                <!-- Navigation Arrows -->
                <div class="flex gap-4 mt-8 md:mt-0 justify-end md:justify-center relative z-[60] pointer-events-auto">
                    <button type="button" @click="slideLeft" :disabled="!canScrollLeft"
                        class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur border border-gray-200 dark:border-white/10 p-4 rounded-full shadow-lg transition-all duration-300 pointer-events-auto cursor-pointer"
                        :class="!canScrollLeft ? 'opacity-30 cursor-not-allowed text-gray-400' : 'hover:scale-110 active:scale-95 text-primary-500 shadow-primary-500/20'">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button type="button" @click="slideRight" :disabled="!canScrollRight"
                        class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur border border-gray-200 dark:border-white/10 p-3.5 rounded-full shadow-lg transition-all duration-300 pointer-events-auto cursor-pointer"
                        :class="!canScrollRight ? 'opacity-30 cursor-not-allowed text-gray-400' : 'hover:scale-110 active:scale-95 text-primary-500 shadow-primary-500/20'">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div x-ref="featuredSlider" @scroll="checkScroll()"
                class="flex flex-row flex-nowrap overflow-x-auto overflow-y-hidden snap-x snap-mandatory no-scrollbar gap-6 mb-10 relative z-10 items-stretch pb-10 pt-2 h-full">
                <!-- Cert Card 1: Asisten Praktikum -->
                <div
                    class="w-[280px] sm:w-[350px] md:w-[300px] lg:w-[350px] shrink-0 snap-center md:snap-start h-full flex flex-col relative z-10">
                    <div
                        class="w-full flex-1 group relative rounded-[3rem] p-[2px] transition-all duration-700 hover:-translate-y-4 shadow-2xl hover:shadow-[0_40px_60px_-15px_rgba(59,130,246,0.3)] flex flex-col">
                        <!-- Glowing Gradient Border -->
                        <div
                            class="absolute inset-0 rounded-[3rem] bg-gradient-to-br from-primary-600 via-primary-400 to-transparent opacity-30 group-hover:opacity-100 transition-opacity duration-700">
                        </div>
                        <!-- Inner Container -->
                        <div
                            class="relative bg-white/90 dark:bg-zinc-900/80 backdrop-blur-3xl rounded-[2.5rem] p-5 h-full border border-white/20 dark:border-white/5 flex flex-col z-10 overflow-hidden">
                            <!-- Tech Accents -->
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-primary-500/10 blur-[50px] rounded-full pointer-events-none transition-transform duration-700 group-hover:scale-150">
                            </div>

                            <div
                                class="relative aspect-[4/5] rounded-2xl overflow-hidden bg-gray-100/50 dark:bg-zinc-950/50 mb-5 border border-gray-200/50 dark:border-white/5">
                                <!-- Premium PDF Preview with Scaling -->
                                <iframe
                                    src="{{ asset('serti/Sertifikat Asisten JAUHAR FAUZI ULUL ALBAB-Struktur Data.pdf') }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full absolute top-0 left-0 pointer-events-none opacity-90 group-hover:opacity-100 transition-all duration-700"
                                    frameborder="0"></iframe>
                            </div>
                            <div class="px-2 pb-2 md:px-4 md:pb-4 flex flex-col flex-1 h-full">
                                <span
                                    class="text-[9px] font-black tracking-[0.2em] text-primary-500 uppercase mb-2 block truncate">ASISTEN
                                    PRAKTIKUM</span>
                                <h4
                                    class="text-xl font-black dark:text-white mb-2 leading-tight group-hover:text-primary-600 transition-colors">
                                    Data Structures</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-4 line-clamp-2">Amikom
                                    Creative Economy Park</p>
                                <div
                                    class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/5 mt-auto">
                                    <span class="text-[10px] font-mono text-gray-400">25-02-2026</span>
                                    <svg class="w-5 h-5 text-primary-500 transform group-hover:translate-x-1 transition hidden md:block"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cert Card 2: Juara III Nasional -->
                <div
                    class="w-[85vw] sm:w-[400px] md:w-[300px] lg:w-[350px] shrink-0 snap-center md:snap-start h-full flex flex-col pb-2 relative z-10">
                    <div
                        class="w-full flex-1 group relative rounded-[2.5rem] p-[2px] transition-all duration-700 hover:-translate-y-4 shadow-2xl hover:shadow-[0_40px_60px_-15px_rgba(236,72,153,0.3)] flex flex-col">
                        <!-- Glowing Gradient Border -->
                        <div
                            class="absolute inset-0 rounded-[2.5rem] bg-gradient-to-br from-pink-600 via-pink-400 to-transparent opacity-30 group-hover:opacity-100 transition-opacity duration-700">
                        </div>
                        <!-- Inner Container -->
                        <div
                            class="relative bg-white/90 dark:bg-zinc-900/80 backdrop-blur-3xl rounded-[calc(2.5rem-2px)] p-5 h-full border border-white/20 dark:border-white/5 flex flex-col z-10 overflow-hidden">
                            <!-- Tech Accents -->
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-pink-500/10 blur-[50px] rounded-full pointer-events-none transition-transform duration-700 group-hover:scale-150">
                            </div>

                            <div
                                class="relative aspect-[4/5] rounded-2xl overflow-hidden bg-gray-100/50 dark:bg-zinc-950/50 mb-5 border border-gray-200/50 dark:border-white/5">
                                <iframe
                                    src="{{ asset('serti/JAUHAR FAUZI ULUL ALBAB.pdf') }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full absolute top-0 left-0 pointer-events-none opacity-90 group-hover:opacity-100 transition-all duration-700"
                                    frameborder="0"></iframe>
                            </div>
                            <div class="px-2 pb-2 md:px-4 md:pb-4 flex flex-col flex-1 h-full">
                                <div class="inline-flex items-center gap-2 mb-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-pink-500"></span>
                                    <span class="text-[9px] font-black tracking-[0.2em] text-pink-500 uppercase">JUARA
                                        III NASIONAL</span>
                                </div>
                                <h4
                                    class="text-xl font-black dark:text-white mb-2 leading-tight group-hover:text-pink-600 transition-colors">
                                    Karya Tulis Islami</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-4 line-clamp-2">
                                    Universitas Gadjah Mada</p>
                                <div
                                    class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/5 mt-auto">
                                    <span class="text-[10px] font-mono text-gray-400">29-09-2025</span>
                                    <svg class="w-5 h-5 text-pink-500 transform group-hover:translate-x-1 transition hidden md:block"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cert Card 3: Waroeng Steak -->
                <div
                    class="w-[280px] sm:w-[350px] md:w-[300px] lg:w-[350px] shrink-0 snap-center md:snap-start h-full flex flex-col relative z-10">
                    <div
                        class="w-full flex-1 group relative rounded-[2.5rem] p-[2px] transition-all duration-700 hover:-translate-y-4 shadow-2xl hover:shadow-[0_40px_60px_-15px_rgba(234,179,8,0.3)] flex flex-col">
                        <!-- Glowing Gradient Border -->
                        <div
                            class="absolute inset-0 rounded-[2.5rem] bg-gradient-to-br from-yellow-600 via-yellow-400 to-transparent opacity-30 group-hover:opacity-100 transition-opacity duration-700">
                        </div>
                        <!-- Inner Container -->
                        <div
                            class="relative bg-white/90 dark:bg-zinc-900/80 backdrop-blur-3xl rounded-[calc(2.5rem-2px)] p-5 h-full border border-white/20 dark:border-white/5 flex flex-col z-10 overflow-hidden">
                            <!-- Tech Accents -->
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-yellow-500/10 blur-[50px] rounded-full pointer-events-none transition-transform duration-700 group-hover:scale-150">
                            </div>

                            <div
                                class="relative aspect-[4/5] rounded-2xl overflow-hidden bg-gray-100/50 dark:bg-zinc-950/50 mb-5 border border-gray-200/50 dark:border-white/5">
                                <iframe src="{{ asset('serti/ws.pdf') }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full absolute top-0 left-0 pointer-events-none opacity-90 group-hover:opacity-100 transition-all duration-700"
                                    frameborder="0"></iframe>
                            </div>
                            <div class="px-2 pb-2 md:px-4 md:pb-4 flex flex-col flex-1 h-full">
                                <span
                                    class="text-[9px] font-black tracking-[0.2em] text-yellow-600 dark:text-yellow-500 uppercase mb-2 block truncate">PROFESSIONAL
                                    WORKSHOP</span>
                                <h4
                                    class="text-xl font-black dark:text-white mb-2 leading-tight group-hover:text-yellow-600 transition-colors">
                                    Waroeng Steak</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-4 line-clamp-2">
                                    Entrepreneurship & Digitalization</p>
                                <div
                                    class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/5 mt-auto">
                                    <span class="text-[10px] font-mono text-gray-400">September 2024</span>
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-500 transform group-hover:translate-x-1 transition hidden md:block"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cert Card 4: Futuristic Tech -->
                <div
                    class="w-[280px] sm:w-[350px] md:w-[300px] lg:w-[350px] shrink-0 snap-center md:snap-start h-full flex flex-col relative z-10">
                    <div
                        class="w-full flex-1 group relative rounded-[2.5rem] p-[2px] transition-all duration-700 hover:-translate-y-4 shadow-2xl hover:shadow-[0_40px_60px_-15px_rgba(59,130,246,0.3)] flex flex-col">
                        <!-- Glowing Gradient Border -->
                        <div
                            class="absolute inset-0 rounded-[2.5rem] bg-gradient-to-br from-primary-600 via-primary-400 to-transparent opacity-30 group-hover:opacity-100 transition-opacity duration-700">
                        </div>
                        <!-- Inner Container -->
                        <div
                            class="relative bg-white/90 dark:bg-zinc-900/80 backdrop-blur-3xl rounded-[calc(2.5rem-2px)] p-5 h-full border border-white/20 dark:border-white/5 flex flex-col z-10 overflow-hidden">
                            <!-- Tech Accents -->
                            <div
                                class="absolute top-0 right-0 w-32 h-32 bg-primary-500/10 blur-[50px] rounded-full pointer-events-none transition-transform duration-700 group-hover:scale-150">
                            </div>

                            <div
                                class="relative aspect-[4/5] rounded-2xl overflow-hidden bg-gray-100/50 dark:bg-zinc-950/50 mb-5 border border-gray-200/50 dark:border-white/5">
                                <iframe
                                    src="{{ asset('serti/Jauhar Fauzi Ulul Albab (1).pdf') }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full absolute top-0 left-0 pointer-events-none opacity-90 group-hover:opacity-100 transition-all duration-700"
                                    frameborder="0"></iframe>
                            </div>
                            <div class="px-2 pb-2 md:px-4 md:pb-4 flex flex-col flex-1 h-full">
                                <span
                                    class="text-[9px] font-black tracking-[0.2em] text-primary-500 uppercase mb-2 block truncate">FUTURISTIC
                                    WORKSHOP</span>
                                <h4
                                    class="text-xl font-black dark:text-white mb-2 leading-tight group-hover:text-primary-600 transition-colors">
                                    Tech Innovations</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-4 line-clamp-2">
                                    Yogyakarta Tech Hub</p>
                                <div
                                    class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-white/5 mt-auto">
                                    <span class="text-[10px] font-mono text-gray-400">18-11-2023</span>
                                    <svg class="w-5 h-5 text-primary-500 transform group-hover:translate-x-1 transition hidden md:block"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Projects Section -->
    <section id="projects" data-aos="fade-up" data-aos-delay="100"
        class="max-w-6xl mx-auto px-6 py-10 md:py-16 relative" x-data="{
        canScrollLeft: false,
        canScrollRight: true,
        selectedJournal: false,
        checkScroll() {
            const el = this.$refs.projectSlider;
            if(!el) return;
            this.canScrollLeft = el.scrollLeft > 10;
            this.canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 15);
        },
        slideLeft() {
            const el = this.$refs.projectSlider;
            const step = window.innerWidth < 768 ? el.clientWidth * 0.8 : 480;
            el.scrollBy({ left: -step, behavior: 'smooth' });
            setTimeout(() => this.checkScroll(), 500);
        },
        slideRight() {
            const el = this.$refs.projectSlider;
            const step = window.innerWidth < 768 ? el.clientWidth * 0.8 : 480;
            el.scrollBy({ left: step, behavior: 'smooth' });
            setTimeout(() => this.checkScroll(), 500);
        }
    }" x-init="
        setTimeout(() => checkScroll(), 800);
        $refs.projectSlider.addEventListener('scroll', () => checkScroll());
        window.addEventListener('resize', () => checkScroll());
    ">
        <!-- Background Glow -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-primary-600/10 blur-[120px] rounded-full -z-10"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-indigo-600/10 blur-[120px] rounded-full -z-10"></div>

        <div class="flex flex-col md:flex-row justify-between items-end mb-8">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-500/10 border border-primary-500/20 text-primary-400 text-[10px] font-black tracking-widest uppercase mb-4"
                    x-text="t[lang].projects.tag">
                </div>
                <h2 class="text-4xl md:text-5xl font-black mb-6 tracking-tight">
                    <span class="text-zinc-900 dark:text-white" x-text="t[lang].projects.title" data-split-text></span>
                    <span class="text-primary-500" x-text="t[lang].projects.subtitle" data-split-text></span>
                </h2>
                <p class="text-gray-600 dark:text-gray-400 text-base md:text-lg leading-snug font-medium mb-8"
                    x-text="t[lang].projects.desc"></p>
            </div>
        </div>


        <style>
            @keyframes shimmer {
                0% {
                    transform: translateX(-100%);
                }

                100% {
                    transform: translateX(100%);
                }
            }
        </style>

        <!-- Dedicated Journal Section (Top Priority) -->
        <div class="mb-12">
            <!-- Project 0: Journal Publication (Real PDF View) -->
            <div
                class="group relative bg-zinc-950 border border-white/10 rounded-[3rem] overflow-hidden transition-all duration-700 hover:border-primary-500/50 w-full shadow-[0_40px_100px_-20px_rgba(0,0,0,0.8)]">
                <div class="project-card-glow"></div>
                <div class="flex flex-col lg:flex-row lg:items-stretch">
                    <!-- Real PDF Canvas Section -->
                    <div
                        class="lg:w-1/2 p-6 lg:p-10 relative bg-zinc-900/40 overflow-hidden flex items-center justify-center">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-primary-600/10 via-transparent to-indigo-600/10">
                        </div>

                        <!-- High Quality Paper Shadow Effect -->
                        <div class="absolute w-[80%] h-[90%] bg-black/40 blur-[40px] rotate-2 -z-0"></div>

                        <!-- Realistic Paper Container -->
                        <div @click="selectedJournal = true"
                            class="relative z-10 w-full max-w-[320px] lg:max-w-[400px] h-[400px] lg:h-[500px] bg-white rounded-sm shadow-2xl overflow-hidden transform group-hover:rotate-0 transition-all duration-700 hover:scale-[1.03] cursor-pointer group/journal">
                            <!-- PDF Iframe -->
                            <iframe src="https://jurnal.polibatam.ac.id/index.php/JAIC/article/view/11751/3420"
                                class="w-full h-full border-none bg-white pointer-events-none" title="JAIC Journal PDF"
                                scrolling="no">
                            </iframe>

                            <!-- Shimmering Glass Overlay -->
                            <div
                                class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-1000 pointer-events-none">
                            </div>

                            <!-- Click to Expand Overlay -->
                            <div
                                class="absolute inset-0 bg-primary-600/20 backdrop-blur-sm opacity-0 group-hover/journal:opacity-100 transition-all duration-500 flex items-center justify-center">
                                <div
                                    class="bg-white/10 backdrop-blur-xl border border-white/30 p-4 rounded-full scale-50 group-hover/journal:scale-100 transition-transform duration-500">
                                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Badge Overlay -->
                        <div class="absolute bottom-8 right-8 hidden lg:block">
                            <div
                                class="bg-red-600 text-white font-black text-xs px-6 py-3 rounded-2xl shadow-2xl transform rotate-3 flex items-center gap-3 border border-red-400/30">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z" />
                                    <path d="M3 8a2 2 0 012-2v10h8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                                </svg>
                                AUTHENTIC PAPER
                            </div>
                        </div>
                    </div>

                    <!-- Content Side -->
                    <div
                        class="lg:w-1/2 p-8 lg:p-10 flex flex-col justify-center bg-white/95 dark:bg-zinc-950/90 backdrop-blur-3xl border-l border-gray-200 dark:border-white/5 relative">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary-600/10 blur-[60px]"></div>

                        <div class="mb-6">
                            <span
                                class="inline-block px-4 py-1.5 bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20 rounded-full text-[10px] font-black tracking-[0.3em] uppercase mb-6"
                                x-text="t[lang].projects.journal.tag">
                            </span>
                            <h3 class="text-3xl lg:text-5xl font-black mb-6 text-zinc-900 dark:text-white leading-tight tracking-tighter group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors"
                                x-text="t[lang].projects.journal.title">
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug text-base lg:text-lg font-medium"
                                x-text="t[lang].projects.journal.desc">
                            </p>
                        </div>

                        <div class="mb-8">
                            <div
                                class="p-6 bg-gradient-to-r from-gray-100 to-white dark:from-zinc-900 dark:to-zinc-900/50 rounded-2xl border border-primary-500/30 group/stat hover:shadow-lg transition-all relative overflow-hidden">
                                <div class="absolute right-0 top-1/2 -translate-y-1/2 p-3 opacity-10">
                                    <svg class="w-24 h-24 text-primary-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z" />
                                    </svg>
                                </div>
                                <div class="text-primary-600 dark:text-primary-500 font-black text-[12px] mb-2 uppercase tracking-[0.2em] group-hover:translate-x-1 transition-transform"
                                    x-text="t[lang].projects.journal.accredited"></div>
                                <div class="text-zinc-900 dark:text-white font-black text-4xl flex items-center gap-3">
                                    <span x-text="t[lang].projects.journal.badge"></span>
                                    <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 font-medium"
                                    x-text="t[lang].projects.journal.official"></p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <a href="{{ url('/projects?id=journal') }}"
                                class="px-8 py-5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-3xl font-bold text-xs uppercase tracking-widest hover:bg-primary-600 dark:hover:bg-primary-600 hover:text-white dark:hover:text-white transition-all shadow-xl">Lihat
                                Detail</a>
                            <a href="https://jurnal.polibatam.ac.id/index.php/JAIC/article/view/11751/3420"
                                target="_blank"
                                class="flex-1 flex items-center justify-center gap-4 px-10 py-5 bg-primary-600 hover:bg-blue-700 text-white rounded-3xl font-black text-xs uppercase tracking-widest transition-all transform hover:scale-[1.03] active:scale-95 shadow-[0_25px_50px_-15px_rgba(37,99,235,0.4)]">
                                <span x-text="t[lang].projects.journal.cta"></span>
                                <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Slider Navigation Buttons (Specifically for and below the slider content) -->
        <div class="flex flex-wrap items-center gap-6 mb-8 mt-4 relative z-50 pointer-events-auto">
            <div class="flex gap-4">
                <button type="button" @click="slideLeft" :disabled="!canScrollLeft"
                    class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur border border-gray-200 dark:border-white/10 p-4 rounded-full shadow-lg transition-all duration-300 pointer-events-auto cursor-pointer"
                    :class="!canScrollLeft ? 'opacity-30 cursor-not-allowed text-gray-400' : 'hover:scale-110 active:scale-95 text-primary-500 shadow-primary-500/20'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button type="button" @click="slideRight" :disabled="!canScrollRight"
                    class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur border border-gray-200 dark:border-white/10 p-4 rounded-full shadow-lg transition-all duration-300 pointer-events-auto cursor-pointer"
                    :class="!canScrollRight ? 'opacity-30 cursor-not-allowed text-gray-400' : 'hover:scale-110 active:scale-95 text-primary-500 shadow-primary-500/20'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <a href="{{ url('/projects') }}"
                class="group flex items-center gap-3 bg-primary-600/10 hover:bg-primary-600 px-6 py-4 rounded-2xl transition-all duration-300 border border-primary-500/20 hover:border-primary-500">
                <span
                    class="text-xs font-black text-primary-600 group-hover:text-white uppercase tracking-widest transition-colors">Lihat
                    Project Selengkapnya</span>
                <svg class="w-5 h-5 text-primary-500 group-hover:text-white group-hover:translate-x-1 transition-all"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </a>
        </div>

        <div x-ref="projectSlider" @scroll="checkScroll()"
            class="flex flex-nowrap overflow-x-auto snap-x snap-mandatory gap-6 no-scrollbar pb-24 h-full">

            <!-- Project Cards (Generic Update for Light Mode) -->
            <!-- Project Cards -->
            <div
                class="group relative bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-green-500/40 w-[280px] sm:w-[400px] md:w-[450px] shrink-0 snap-center shadow-lg dark:shadow-2xl hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.2)] flex flex-col">
                <div class="h-64 overflow-hidden relative">
                    <img src="{{ asset('project-media/kpri.png') }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-white dark:from-zinc-900 via-white/40 dark:via-zinc-900/40 to-transparent">
                    </div>
                    <div class="absolute top-6 right-6">
                        <div
                            class="bg-green-500 text-white text-[10px] font-black px-4 py-2 rounded-full shadow-lg uppercase tracking-widest border border-green-400/20 backdrop-blur-md">
                            Fintech Admin
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 relative">
                    <div
                        class="absolute -top-12 left-8 md:left-10 w-16 h-16 bg-white dark:bg-zinc-900 rounded-2xl flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-xl group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl">💰</span>
                    </div>
                    <h3 class="text-3xl font-black text-zinc-900 dark:text-white mb-4 mt-2 group-hover:text-green-500 transition-colors"
                        x-text="t[lang].projects.kpri.title">
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug font-medium"
                        x-text="t[lang].projects.kpri.desc"></p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">PHP
                            Native</span>
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Decision
                            Support System</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ url('/projects?id=kpri') }}"
                            class="px-5 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-green-600 dark:hover:bg-green-600 hover:text-white dark:hover:text-white transition-all">Lihat
                            Detail</a>
                        <a href="http://kpribaktimulia.or.id/" target="_blank"
                            class="inline-flex items-center gap-3 text-zinc-900 dark:text-white font-black text-xs uppercase tracking-widest hover:text-green-500 dark:hover:text-green-400 transition-all border-b-2 border-gray-200 dark:border-white/10 hover:border-green-500 pb-1">
                            <span x-text="t[lang].projects.visit"></span> <span
                                class="ml-2 transform group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Project 2 (Modern Card) -->
            <div
                class="group relative bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-primary-500/40 w-[280px] sm:w-[400px] md:w-[450px] shrink-0 snap-center shadow-lg dark:shadow-2xl hover:shadow-[0_20px_60px_-15px_rgba(var(--primary-rgb),0.2)] flex flex-col">
                <div class="h-64 overflow-hidden relative">
                    <img src="{{ asset('project-media/jaugjakita.png') }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-white dark:from-zinc-900 via-white/40 dark:via-zinc-900/40 to-transparent">
                    </div>
                    <div class="absolute top-6 right-6">
                        <div
                            class="bg-indigo-600 text-white text-[10px] font-black px-4 py-2 rounded-full shadow-lg uppercase tracking-widest border border-indigo-400/20 backdrop-blur-md">
                            AI Recommendation
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 relative">
                    <div
                        class="absolute -top-12 left-8 md:left-10 w-16 h-16 bg-white dark:bg-zinc-900 rounded-2xl flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-xl group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl">🤖</span>
                    </div>
                    <h3 class="text-3xl font-black text-zinc-900 dark:text-white mb-4 mt-2 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors"
                        x-text="t[lang].projects.jaugja.title">
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug font-medium"
                        x-text="t[lang].projects.jaugja.desc"></p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Python</span>
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Streamlit</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ url('/projects?id=jaugja') }}"
                            class="px-5 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-indigo-600 dark:hover:bg-indigo-600 hover:text-white dark:hover:text-white transition-all">Lihat
                            Detail</a>
                        <a href="http://jaugjakita.jauharfauzi.my.id" target="_blank"
                            class="inline-flex items-center gap-3 text-zinc-900 dark:text-white font-black text-xs uppercase tracking-widest hover:text-indigo-500 dark:hover:text-indigo-400 transition-all border-b-2 border-gray-200 dark:border-white/10 hover:border-indigo-500 pb-1">
                            <span x-text="t[lang].projects.open"></span> <span
                                class="ml-2 transform group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Project 3 (Modern Layout) -->
            <div
                class="group relative bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-emerald-500/40 w-[85vw] md:w-[450px] shrink-0 snap-center shadow-lg dark:shadow-2xl hover:shadow-[0_20px_60px_-15px_rgba(16,185,129,0.2)] flex flex-col">
                <div class="h-64 overflow-hidden relative">
                    <img src="{{ asset('project-media/kauiz.png') }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-white dark:from-zinc-900 via-white/40 dark:via-zinc-900/40 to-transparent">
                    </div>
                    <div class="absolute top-6 right-6">
                        <div
                            class="bg-emerald-600 text-white text-[10px] font-black px-4 py-2 rounded-full shadow-lg uppercase tracking-widest border border-emerald-400/20 backdrop-blur-md">
                            Ed-Tech SaaS
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 relative">
                    <div
                        class="absolute -top-12 left-8 md:left-10 w-16 h-16 bg-white dark:bg-zinc-900 rounded-2xl flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-xl group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl">🎓</span>
                    </div>
                    <h3 class="text-3xl font-black text-zinc-900 dark:text-white mb-4 mt-2 group-hover:text-emerald-500 dark:group-hover:text-emerald-400 transition-colors"
                        x-text="t[lang].projects.kauiz.title">
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug font-medium"
                        x-text="t[lang].projects.kauiz.desc"></p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Laravel</span>
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">OpenAI
                            API</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ url('/projects?id=kauiz') }}"
                            class="px-5 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-emerald-600 dark:hover:bg-emerald-600 hover:text-white dark:hover:text-white transition-all">Lihat
                            Detail</a>
                        <a href="http://kauiz.jauharfauzi.my.id/" target="_blank"
                            class="inline-flex items-center gap-3 text-zinc-900 dark:text-white font-black text-xs uppercase tracking-widest hover:text-emerald-500 dark:hover:text-emerald-400 transition-all border-b-2 border-gray-200 dark:border-white/10 hover:border-emerald-500 pb-1">
                            <span x-text="t[lang].projects.explore"></span> <span
                                class="ml-2 transform group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Project 4 (Modern Layout) -->
            <div
                class="group relative bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-purple-500/40 w-[85vw] md:w-[450px] shrink-0 snap-center shadow-lg dark:shadow-2xl hover:shadow-[0_20px_60px_-15px_rgba(168,85,247,0.2)] flex flex-col">
                <div class="h-64 overflow-hidden relative">
                    <img src="{{ asset('project-media/livechat.png') }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-white dark:from-zinc-900 via-white/40 dark:via-zinc-900/40 to-transparent">
                    </div>
                    <div class="absolute top-6 right-6">
                        <div
                            class="bg-purple-600 text-white text-[10px] font-black px-4 py-2 rounded-full shadow-lg uppercase tracking-widest border border-purple-400/20 backdrop-blur-md">
                            Real-time NLP
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 relative">
                    <div
                        class="absolute -top-12 left-8 md:left-10 w-16 h-16 bg-white dark:bg-zinc-900 rounded-2xl flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-xl group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl">💬</span>
                    </div>
                    <h3 class="text-3xl font-black text-zinc-900 dark:text-white mb-4 mt-2 group-hover:text-purple-500 dark:group-hover:text-purple-400 transition-colors"
                        x-text="t[lang].projects.livechat.title">
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug font-medium"
                        x-text="t[lang].projects.livechat.desc"></p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Socket.io</span>
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">NLP.js</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ url('/projects?id=livechat') }}"
                            class="px-5 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-purple-600 dark:hover:bg-purple-600 hover:text-white dark:hover:text-white transition-all">Lihat
                            Detail</a>
                        <a href="https://livechat.jauharfauzi.my.id/" target="_blank"
                            class="inline-flex items-center gap-3 text-zinc-900 dark:text-white font-black text-xs uppercase tracking-widest hover:text-purple-500 dark:hover:text-purple-400 transition-all border-b-2 border-gray-200 dark:border-white/10 hover:border-purple-500 pb-1">
                            <span x-text="t[lang].projects.demo"></span> <span
                                class="ml-2 transform group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Project 5 (New: JokiTugas Jogja) -->
            <div
                class="group relative bg-white dark:bg-zinc-900 border border-gray-200 dark:border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-blue-500/40 w-[85vw] md:w-[450px] shrink-0 snap-center shadow-lg dark:shadow-2xl hover:shadow-[0_20px_60px_-15px_rgba(59,130,246,0.2)] flex flex-col">
                <div class="h-64 overflow-hidden relative">
                    <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=2070&auto=format&fit=crop"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-white dark:from-zinc-900 via-white/40 dark:via-zinc-900/40 to-transparent">
                    </div>
                    <div class="absolute top-6 right-6">
                        <div
                            class="bg-blue-600 text-white text-[10px] font-black px-4 py-2 rounded-full shadow-lg uppercase tracking-widest border border-blue-400/20 backdrop-blur-md">
                            WEB SERVICE
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 relative">
                    <div
                        class="absolute -top-12 left-8 md:left-10 w-16 h-16 bg-white dark:bg-zinc-900 rounded-2xl flex items-center justify-center border border-gray-100 dark:border-white/10 shadow-xl group-hover:scale-110 transition-transform duration-300">
                        <span class="text-3xl">📚</span>
                    </div>
                    <h3 class="text-3xl font-black text-zinc-900 dark:text-white mb-4 mt-2 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors"
                        x-text="t[lang].projects.jokitugas.title">
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 leading-snug font-medium"
                        x-text="t[lang].projects.jokitugas.desc"></p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Laravel</span>
                        <span
                            class="px-4 py-2 bg-gray-50 dark:bg-zinc-950 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-white/5 rounded-xl text-xs font-bold hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Tailwind</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="{{ url('/projects?id=jokitugas') }}"
                            class="px-5 py-2.5 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-blue-600 dark:hover:bg-blue-600 hover:text-white dark:hover:text-white transition-all">Lihat
                            Detail</a>
                        <a href="http://jokitugas-jogja.my.id/" target="_blank"
                            class="inline-flex items-center gap-3 text-zinc-900 dark:text-white font-black text-xs uppercase tracking-widest hover:text-blue-500 dark:hover:text-blue-400 transition-all border-b-2 border-gray-200 dark:border-white/10 hover:border-blue-500 pb-1">
                            <span x-text="t[lang].projects.visit"></span> <span
                                class="ml-2 transform group-hover:translate-x-1 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Skills Section (Moved Below Projects) -->
    <section id="skills" data-aos="fade-up" data-aos-delay="100" class="max-w-6xl mx-auto px-6 py-6 md:py-12">
        <div
            class="bg-white/50 dark:bg-zinc-900/40 border border-gray-200 dark:border-white/10 rounded-[2rem] md:rounded-[4rem] p-6 md:p-12 overflow-hidden relative shadow-xl dark:shadow-none backdrop-blur-sm">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-600/20 blur-[100px]"></div>

            <div class="flex flex-col md:flex-row justify-between items-end mb-6">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black mb-2 tracking-tight">
                        <span class="text-zinc-900 dark:text-white" x-text="t[lang].skills.title"
                            data-split-text></span>
                        <span class="text-primary-600 dark:text-primary-500" x-text="t[lang].skills.subtitle"
                            data-split-text></span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 text-sm md:text-base font-medium"
                        x-text="t[lang].skills.desc"></p>
                </div>
                <div
                    class="text-primary-500 font-mono text-sm hidden md:block border-b border-primary-500/30 pb-2 uppercase tracking-widest">
                    Expertise 02
                </div>
            </div>

            <div data-logo-loop data-speed="80" data-logo-height="40" data-gap="60" data-fade-out="true" :data-logos="JSON.stringify([
                    { icon: 'SiLaravel', title: 'Laravel' },
                    { icon: 'SiCodeigniter', title: 'CodeIgniter' },
                    { icon: 'SiMysql', title: 'MySQL' },
                    { icon: 'SiTailwindcss', title: 'Tailwind' },
                    { icon: 'SiReact', title: 'React' },
                    { icon: 'SiGit', title: 'Git' },
                    { icon: 'SiPython', title: 'Python' },
                    { icon: 'SiGooglecolab', title: 'Colab' },
                    { icon: 'SiClaude', title: 'Claude' },
                    { icon: 'SiAndroidstudio', title: 'Android' }
                ])" class="w-full"></div>
        </div>
    </section>


    <section class="max-w-6xl mx-auto px-6 py-8" data-aos="fade-up" data-aos-delay="100">
        <h2 class="text-3xl md:text-4xl font-black mb-6 text-center md:text-left tracking-tight">
            <span class="text-zinc-900 dark:text-white" x-text="t[lang].education.title"></span>
            <span class="text-primary-500" x-text="t[lang].education.subtitle"></span>
        </h2>
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Amikom -->
            <div
                class="bg-white dark:bg-zinc-900/50 border border-gray-200 dark:border-white/5 p-8 rounded-[2.5rem] hover:border-primary-500/50 transition duration-300 flex items-start gap-6 hover:shadow-xl dark:hover:shadow-none group">
                <div
                    class="w-20 h-20 bg-gray-50 dark:bg-white rounded-2xl flex items-center justify-center p-3 flex-shrink-0 overflow-hidden shadow-sm group-hover:scale-110 transition-transform">
                    <img src="{{ asset('logo_amikom.png') }}" alt="Amikom Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <h3 class="text-xl font-black text-zinc-900 dark:text-white mb-1"
                        x-text="t[lang].education.amikom.name"></h3>
                    <p class="text-primary-600 dark:text-primary-400 font-bold mb-1"
                        x-text="t[lang].education.amikom.major"></p>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-1 font-medium"
                        x-text="t[lang].education.amikom.info"></p>
                    <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest"
                        x-text="t[lang].education.amikom.date"></p>
                </div>
            </div>

            <!-- Trainit -->
            <div
                class="bg-white dark:bg-zinc-900/50 border border-gray-200 dark:border-white/5 p-8 rounded-[2.5rem] hover:border-purple-500/50 transition duration-300 flex items-start gap-6 hover:shadow-xl dark:hover:shadow-none group">
                <div
                    class="w-20 h-20 bg-purple-50 dark:bg-purple-600/20 rounded-2xl flex items-center justify-center text-3xl flex-shrink-0 group-hover:scale-110 transition-transform">
                    💻
                </div>
                <div>
                    <h3 class="text-xl font-black text-zinc-900 dark:text-white mb-1"
                        x-text="t[lang].education.trainit.name"></h3>
                    <p class="text-purple-600 dark:text-purple-400 font-bold mb-1"
                        x-text="t[lang].education.trainit.major"></p>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-1 font-medium"
                        x-text="t[lang].education.trainit.info"></p>
                    <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest"
                        x-text="t[lang].education.trainit.date"></p>
                </div>
            </div>
        </div>
    </section>



    <section class="max-w-6xl mx-auto px-6 py-8 mb-8" data-aos="fade-up" data-aos-delay="100">
        <h2 class="text-3xl md:text-4xl font-black mb-6 text-center md:text-left tracking-tight">
            <span class="text-zinc-900 dark:text-white" x-text="t[lang].soft.title"></span>
            <span class="text-primary-500" x-text="t[lang].soft.subtitle"></span>
        </h2>

        <!-- Mobile Swipe Indicator -->
        <div class="md:hidden flex items-center gap-2 mb-4 text-gray-500 text-xs animate-pulse">
            <span x-text="t[lang].soft.swipe"></span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
        </div>

        <div
            class="flex flex-nowrap overflow-x-auto snap-x snap-mandatory gap-6 md:grid md:grid-cols-3 md:gap-6 no-scrollbar pb-6 md:pb-0">
            <div
                class="bg-white dark:bg-zinc-900/50 p-6 rounded-2xl border border-gray-200 dark:border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center shadow-lg dark:shadow-none">
                <div class="text-4xl mb-4">🤝</div>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2" x-text="t[lang].soft.team.title"></h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm" x-text="t[lang].soft.team.desc"></p>
            </div>
            <div
                class="bg-white dark:bg-zinc-900/50 p-6 rounded-2xl border border-gray-200 dark:border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center shadow-lg dark:shadow-none">
                <div class="text-4xl mb-4">⏱️</div>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2" x-text="t[lang].soft.time.title"></h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm" x-text="t[lang].soft.time.desc"></p>
            </div>
            <div
                class="bg-white dark:bg-zinc-900/50 p-6 rounded-2xl border border-gray-200 dark:border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center shadow-lg dark:shadow-none">
                <div class="text-4xl mb-4">📢</div>
                <h3 class="text-xl font-bold text-zinc-900 dark:text-white mb-2" x-text="t[lang].soft.comm.title"></h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm" x-text="t[lang].soft.comm.desc"></p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" data-aos="fade-up" data-aos-delay="100"
        class="max-w-6xl mx-auto px-6 py-16 relative overflow-hidden">
        <!-- Glow Effects -->
        <div
            class="absolute top-1/2 left-0 -translate-y-1/2 w-96 h-96 bg-primary-600/10 blur-[100px] rounded-full pointer-events-none">
        </div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-600/10 blur-[100px] rounded-full pointer-events-none">
        </div>

        <div class="grid lg:grid-cols-2 gap-12 items-center relative z-10">
            <!-- Left: Call to Action & Contact -->
            <div>
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-500/10 border border-primary-500/20 text-primary-400 text-xs font-bold tracking-widest uppercase mb-6">
                    <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                    <span x-text="t[lang].footer.tag"></span>
                </div>

                <h2 class="text-5xl md:text-7xl font-black mb-6 leading-tight tracking-tight">
                    <span class="text-zinc-900 dark:text-white" x-text="t[lang].footer.title"></span> <br>
                    <span
                        class="block text-primary-600 dark:text-primary-400 drop-shadow-[0_0_25px_rgba(var(--primary-rgb),0.3)]"
                        x-text="t[lang].footer.subtitle" data-split-text></span>
                </h2>

                <blockquote
                    class="text-xl text-gray-600 dark:text-gray-400 font-light italic mb-10 border-l-4 border-primary-500 pl-6 py-2"
                    x-text="t[lang].footer.quote">
                </blockquote>

                <div class="flex flex-col gap-4 max-w-md">
                    <!-- Email -->
                    <a href="mailto:jauharfua05@gmail.com"
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50 hover:bg-gray-50 dark:hover:bg-zinc-800 border border-gray-200 dark:border-white/5 hover:border-primary-500/30 rounded-2xl transition-all duration-300 shadow-md dark:shadow-none">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-red-500/10 text-red-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <!-- Gmail Icon -->
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-bold"
                                    x-text="t[lang].footer.email_label"></div>
                                <div class="text-zinc-900 dark:text-white font-medium">jauharfua05@gmail.com</div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>

                    <!-- Instagram -->
                    <a href="https://instagram.com/jauhar.fauzi_"
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50 hover:bg-gray-50 dark:hover:bg-zinc-800 border border-gray-200 dark:border-white/5 hover:border-pink-500/30 rounded-2xl transition-all duration-300 shadow-md dark:shadow-none">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <!-- IG Icon -->
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-bold">Instagram</div>
                                <div class="text-zinc-900 dark:text-white font-medium">@jauhar.fauzi_</div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>

                    <!-- WhatsApp -->
                    <a href="https://wa.me/6289529104230"
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50 hover:bg-gray-50 dark:hover:bg-zinc-800 border border-gray-200 dark:border-white/5 hover:border-green-500/30 rounded-2xl transition-all duration-300 shadow-md dark:shadow-none">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-green-500/10 text-green-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <!-- WA Icon -->
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-bold">Whats App</div>
                                <div class="text-zinc-900 dark:text-white font-medium">+62 895-2910-4230</div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>

                    <!-- TikTok -->
                    <a href="https://tiktok.com/@jauhar.fauzi" target="_blank"
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50 hover:bg-gray-50 dark:hover:bg-zinc-800 border border-gray-200 dark:border-white/5 hover:border-zinc-500/30 rounded-2xl transition-all duration-300 shadow-md dark:shadow-none">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-zinc-900/10 dark:bg-white/10 text-zinc-900 dark:text-white flex items-center justify-center group-hover:scale-110 transition-transform">
                                <!-- TikTok Icon -->
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.17-2.89-.6-4.13-1.47-.13-.09-.26-.18-.38-.28.01 1.05.01 2.1.01 3.15 0 2.64-.17 5.28-1.57 7.56-1.55 2.51-4.22 4.15-7.14 4.39-2.92.24-5.91-.83-7.85-3.04-1.92-2.18-2.61-5.26-1.87-8.08.74-2.83 3.01-4.99 5.86-5.49 1.1-.19 2.22-.16 3.32.1v4.04c-.6-.2-1.24-.26-1.88-.17-1.16.16-2.22.86-2.73 1.9-.51 1.04-.49 2.32.1 3.31.59.99 1.65 1.57 2.79 1.57 2.11-.01 2.11-3.1 2.11-4.66.01-4.61.01-9.22.01-13.84z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-bold">TikTok</div>
                                <div class="text-zinc-900 dark:text-white font-medium">@jauhar.fauzi</div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>

                    <!-- LinkedIn -->
                    <a href="https://www.linkedin.com/in/jauhar-fauzi-ulul-albab-979245291/" target="_blank"
                        class="group flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50 hover:bg-gray-50 dark:hover:bg-zinc-800 border border-gray-200 dark:border-white/5 hover:border-blue-500/30 rounded-2xl transition-all duration-300 shadow-md dark:shadow-none">
                        <div class="flex items-center gap-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-blue-600/10 text-blue-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <!-- LinkedIn Icon -->
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-bold">LinkedIn</div>
                                <div class="text-zinc-900 dark:text-white font-medium">Jauhar Ulul Fauzi</div>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-600 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Right: Status Card -->
            <div class="relative">
                <!-- Decorative Elements -->
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-500/20 rounded-full blur-2xl animate-pulse">
                </div>

                <div
                    class="bg-white/80 dark:bg-zinc-900/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 p-8 rounded-[3rem] shadow-2xl relative overflow-hidden group hover:border-blue-500/30 transition-all duration-500">
                    <!-- Grid Pattern Overlay -->
                    <div
                        class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:30px_30px] opacity-20">
                    </div>

                    <div class="relative z-10 flex flex-col gap-8">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-white/5 pb-6">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mb-1"
                                    x-text="t[lang].footer.status_label">
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-3 w-3">
                                        <span
                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                    </span>
                                    <span class="text-green-400 font-bold" x-text="t[lang].footer.available"></span>
                                </div>
                            </div>
                            <div
                                class="w-12 h-12 rounded-full bg-zinc-800 border border-white/5 flex items-center justify-center text-xl">
                                👋
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="space-y-4">
                            <div
                                class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-zinc-800/30 rounded-2xl border border-gray-200 dark:border-white/5">
                                <div class="p-3 bg-blue-500/10 text-blue-400 rounded-xl">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wide"
                                        x-text="t[lang].footer.loc_label">
                                    </div>
                                    <div class="text-zinc-900 dark:text-white text-lg font-bold"
                                        x-text="t[lang].footer.location">
                                    </div>
                                    <div class="text-gray-500 text-xs mt-1" x-text="t[lang].footer.remote"></div>
                                </div>
                            </div>

                            <!-- Open For -->
                            <div
                                class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-zinc-800/30 rounded-2xl border border-gray-200 dark:border-white/5">
                                <div class="p-3 bg-purple-500/10 text-purple-400 rounded-xl">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wide"
                                        x-text="t[lang].footer.open_label"></div>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span
                                            class="px-3 py-1 rounded-lg bg-blue-500/20 text-blue-300 text-xs font-bold border border-blue-500/20"
                                            x-text="t[lang].footer.freelance"></span>
                                        <span
                                            class="px-3 py-1 rounded-lg bg-purple-500/20 text-purple-300 text-xs font-bold border border-purple-500/20"
                                            x-text="t[lang].footer.collab"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Action -->
                        <a href="https://wa.me/6289529104230" target="_blank"
                            class="w-full py-4 bg-zinc-900 dark:bg-white text-white dark:text-black rounded-xl font-bold text-center hover:bg-zinc-800 dark:hover:bg-gray-200 transition shadow-[0_0_20px_rgba(0,0,0,0.15)] dark:shadow-[0_0_20px_rgba(255,255,255,0.15)] flex justify-center items-center gap-2">
                            <span x-text="t[lang].footer.cta"></span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Marquee -->
    <div class="border-y border-gray-200 dark:border-white/10 py-8 overflow-hidden bg-white dark:bg-black">
        <div
            class="flex gap-8 whitespace-nowrap animate-[marquee_20s_linear_infinite] uppercase font-black text-4xl opacity-50 text-zinc-900 dark:text-white">
            <span>Code + Innovate + Solve • </span>
            <span>Build + Scale + Deploy • </span>
            <span>Code + Innovate + Solve • </span>
            <span class="text-primary-600">Build + Scale + Deploy • </span>
        </div>
    </div>

    <div class="text-center py-6 text-gray-600 text-sm">
        &copy; 2024 Jauhar Ulul Fauzi. All rights reserved.
    </div>

    <style>
        @keyframes marquee {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }
    </style>

    <!-- Theme Settings & Floating Action -->
    <div x-data="{ openSettings: false }" class="fixed bottom-6 right-6 z-50">
        <!-- Panel -->
        <div x-show="openSettings" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95" @click.outside="openSettings = false"
            :class="darkMode ? 'bg-zinc-900 border-white/10' : 'bg-white border-gray-200'"
            class="absolute bottom-16 right-0 mb-2 p-4 rounded-2xl border shadow-2xl w-64 backdrop-blur-xl">

            <!-- Mode Toggle -->
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100 dark:border-white/5">
                <span class="text-xs font-bold uppercase tracking-wider opacity-70"
                    :class="darkMode ? 'text-gray-300' : 'text-gray-600'">Theme Mode</span>
                <button @click="toggleTheme()"
                    class="relative w-12 h-6 rounded-full bg-gray-200 dark:bg-zinc-700 transition-colors">
                    <div class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white shadow-sm transform transition-transform duration-300 flex items-center justify-center"
                        :class="darkMode ? 'translate-x-6' : 'translate-x-0'">
                        <span x-show="!darkMode">☀️</span>
                        <span x-show="darkMode" style="display:none">🌙</span>
                    </div>
                </button>
            </div>

            <!-- Color Picker -->
            <div class="space-y-3">
                <span class="text-xs font-bold uppercase tracking-wider opacity-70"
                    :class="darkMode ? 'text-gray-300' : 'text-gray-600'">Accent Color</span>
                <div class="grid grid-cols-5 gap-2">
                    <!-- Blue -->
                    <button @click="setThemeColor('59, 130, 246')"
                        class="w-8 h-8 rounded-full bg-blue-500 hover:scale-110 transition ring-2 ring-offset-2 ring-offset-transparent outline-none"
                        :class="currentColor === '59, 130, 246' ? 'ring-blue-500' : 'ring-transparent'"></button>
                    <!-- Purple -->
                    <button @click="setThemeColor('139, 92, 246')"
                        class="w-8 h-8 rounded-full bg-purple-500 hover:scale-110 transition ring-2 ring-offset-2 ring-offset-transparent outline-none"
                        :class="currentColor === '139, 92, 246' ? 'ring-purple-500' : 'ring-transparent'"></button>
                    <!-- Emerald -->
                    <button @click="setThemeColor('16, 185, 129')"
                        class="w-8 h-8 rounded-full bg-emerald-500 hover:scale-110 transition ring-2 ring-offset-2 ring-offset-transparent outline-none"
                        :class="currentColor === '16, 185, 129' ? 'ring-emerald-500' : 'ring-transparent'"></button>
                    <!-- Rose -->
                    <button @click="setThemeColor('244, 63, 94')"
                        class="w-8 h-8 rounded-full bg-rose-500 hover:scale-110 transition ring-2 ring-offset-2 ring-offset-transparent outline-none"
                        :class="currentColor === '244, 63, 94' ? 'ring-rose-500' : 'ring-transparent'"></button>
                    <!-- Amber -->
                    <button @click="setThemeColor('245, 158, 11')"
                        class="w-8 h-8 rounded-full bg-amber-500 hover:scale-110 transition ring-2 ring-offset-2 ring-offset-transparent outline-none"
                        :class="currentColor === '245, 158, 11' ? 'ring-amber-500' : 'ring-transparent'"></button>
                </div>
            </div>
        </div>

        <!-- Toggle Button -->
        <button @click="openSettings = !openSettings"
            class="w-12 h-12 rounded-full shadow-2xl flex items-center justify-center text-white transition-transform hover:scale-110 hover:rotate-90 bg-primary-600">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </button>
    </div>

    <!-- Journal Modal -->
    <div x-show="selectedJournal" style="display: none;"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 md:p-10"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">

        <!-- Backdrop -->
        <div @click="selectedJournal = false" class="absolute inset-0 bg-black/95 backdrop-blur-xl"></div>

        <!-- Content -->
        <div class="relative w-full max-w-6xl h-full bg-white rounded-[2rem] overflow-hidden shadow-2xl flex flex-col">
            <div class="p-6 bg-zinc-900 text-white flex justify-between items-center shrink-0">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-primary-600 rounded-xl">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-black text-xs md:text-sm uppercase tracking-widest">Journal Publication</h3>
                        <p class="text-white/60 text-[10px] md:text-xs">Recommendation System Research</p>
                    </div>
                </div>
                <button @click="selectedJournal = false"
                    class="p-3 bg-white/10 hover:bg-white/20 rounded-full transition duration-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex-grow bg-white relative">
                <iframe src="https://jurnal.polibatam.ac.id/index.php/JAIC/article/view/11751/3420"
                    class="w-full h-full border-none" title="Journal PDF"></iframe>
            </div>
        </div>
    </div>

    <!-- AOS Script -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({
                once: true,
                offset: 100,
                duration: 1200,
                easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                delay: 200,
            });
        });
    </script>
</body>

</html>