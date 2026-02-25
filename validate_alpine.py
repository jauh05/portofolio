
import json

content = """{ 
        lang: 'id',
        darkMode: localStorage.getItem('theme') ? localStorage.getItem('theme') === 'dark' : true,
        currentColor: localStorage.getItem('color') || '59, 130, 246',
        t: {
            id: {
                nav: { about: 'Tentang', projects: 'Projek', skills: 'Keahlian', contact: 'Kontak', talk: 'Bicara' },
                hero: {
                    title: 'JAUHAR FAUZI',
                    subtitle: 'ULUL ALBAB',
                    tagline: 'Digital Solutions Engineer & Tech Problem Solver',
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
                    livechat: { tag: 'Real-time NLP', title: 'LiveChat Interaction', desc: 'Filtering kata kasar real-time berbasis pemrosesan bahasa alami.' }
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
                }
            },
            en: {
                nav: { about: 'About', projects: 'Projects', skills: 'Skills', contact: 'Contact', talk: 'Let\'s Talk' },
                hero: {
                    title: 'JAUHAR FAUZI',
                    subtitle: 'ULUL ALBAB',
                    tagline: 'Digital Solutions Engineer & Tech Problem Solver',
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
                    livechat: { tag: 'Real-time NLP', title: 'LiveChat Interaction', desc: 'Real-time coarse word filtering based on natural language processing.' }
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
        }
    }"""

def check_brackets(text):
    stack = []
    for i, char in enumerate(text):
        if char == '{':
            stack.append(('{', i))
        elif char == '}':
            if not stack:
                return f"Unmatched }} at position {i}"
            stack.pop()
        elif char == '[':
            stack.append(('[', i))
        elif char == ']':
            if not stack or stack[-1][0] != '[':
                return f"Unmatched ] at position {i}"
            stack.pop()
    if stack:
        return f"Unclosed {stack[-1][0]} from position {stack[-1][1]}"
    return "OK"

print(check_brackets(content))
