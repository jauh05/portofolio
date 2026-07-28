<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Featured Projects — Jauhar Fauzi Ulul Albab</title>
    <meta name="description" content="Proyek pilihan Jauhar Fauzi Ulul Albab — AI, web, dan digital products.">
    <link rel="icon" type="image/png" href="{{ asset('favicon_jf.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #fff; --ink: #102e5c; --text: #42526b; --muted: #667085;
            --blue: #2e90fa; --blue-dark: #1570ef; --soft: #eaf3ff;
            --surface: #f5f9ff; --line: #d9e8fb; --max: 1240px;
            --ease: cubic-bezier(.22,1,.36,1);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: Inter, system-ui, sans-serif; color: var(--text); background: var(--bg); line-height: 1.6; overflow-x: hidden; }
        h1, h2, h3, h4 { font-family: Sora, sans-serif; color: var(--ink); line-height: 1.12; letter-spacing: -.035em; }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }

        .wrap { max-width: var(--max); margin: auto; padding: 0 32px; }
        .eyebrow { display: inline-flex; gap: 9px; align-items: center; color: var(--blue); font: 600 12px 'JetBrains Mono', monospace; letter-spacing: .13em; text-transform: uppercase; margin-bottom: 18px; }
        .eyebrow:before { content: ''; width: 22px; height: 1px; background: var(--blue); }
        .btn { display: inline-flex; align-items: center; gap: 9px; border-radius: 100px; padding: 13px 24px; font-weight: 700; font-size: 14px; border: 1px solid transparent; transition: .25s var(--ease); text-align: center; justify-content: center; cursor: pointer; }
        .btn-primary { color: #fff; background: var(--blue); box-shadow: 0 10px 24px -12px var(--blue-dark); }
        .btn-primary:hover { background: var(--blue-dark); transform: translateY(-2px); }
        .btn-secondary { color: var(--blue-dark); border-color: var(--line); background: #fff; }
        .btn-secondary:hover { background: var(--surface); border-color: var(--blue); }
        .tag { display: inline-block; padding: 5px 13px; border-radius: 999px; background: var(--soft); color: var(--blue-dark); font: 600 11px 'JetBrains Mono', monospace; letter-spacing: .02em; }

        /* Nav */
        .sub-nav { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,.92); backdrop-filter: blur(15px); border-bottom: 1px solid var(--line); padding: 14px 0; }
        .sub-nav .wrap { display: flex; align-items: center; justify-content: space-between; }
        .logo-link { display: flex; align-items: center; gap: 12px; }
        .logo { width: 43px; height: 43px; border-radius: 14px; background: linear-gradient(145deg, var(--blue), var(--blue-dark)); display: grid; place-items: center; color: #fff; font: 800 16px Sora; box-shadow: 0 10px 20px -10px var(--blue-dark); }
        .logo-text { font: 700 13px Sora; color: var(--ink); letter-spacing: .02em; }
        .back-btn { display: flex; align-items: center; gap: 10px; font: 700 13px Sora; color: var(--ink); cursor: pointer; background: none; border: none; }
        .back-btn:hover { color: var(--blue); }

        /* Page Header */
        .page-header { padding: 80px 0 50px; background: var(--surface); border-bottom: 1px solid var(--line); }
        .page-header h1 { font-size: clamp(2.8rem, 5vw, 4.2rem); font-weight: 800; margin-bottom: 14px; }
        .page-header .lead { font-size: 17px; color: var(--muted); max-width: 660px; line-height: 1.75; }

        /* Project Grid */
        .projects-section { padding: 70px 0 100px; }
        .project-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 28px; }
        .project-card { background: #fff; border: 1px solid var(--line); border-radius: 22px; overflow: hidden; box-shadow: 0 20px 50px -36px rgba(15,46,92,.3); transition: .35s var(--ease); cursor: pointer; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 28px 60px -30px rgba(15,46,92,.4); border-color: #b8d8fb; }
        .project-media { width: 100%; height: 240px; overflow: hidden; position: relative; }
        .project-media img { width: 100%; height: 100%; object-fit: cover; transition: .6s var(--ease); }
        .project-card:hover .project-media img { transform: scale(1.06); }
        .project-media .tag-overlay { position: absolute; top: 16px; left: 16px; }
        .project-body { padding: 24px 26px 28px; }
        .project-body h3 { font-size: 22px; margin-bottom: 8px; }
        .project-body p { font-size: 14px; color: var(--muted); line-height: 1.65; margin-bottom: 16px; }
        .project-tech { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; }
        .project-link-row { display: flex; align-items: center; justify-content: space-between; padding-top: 16px; border-top: 1px solid var(--line); }
        .project-action { font: 700 13px Inter; color: var(--blue-dark); transition: .2s; }
        .project-action:hover { color: var(--blue); }

        /* Detail View */
        .detail-view { display: none; }
        .detail-view.active { display: block; }
        .detail-hero { width: 100%; height: 50vh; border-radius: 28px; overflow: hidden; position: relative; margin-bottom: 60px; }
        .detail-hero img { width: 100%; height: 100%; object-fit: cover; }
        .detail-hero-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(16,46,92,.9) 0, transparent 60%); display: flex; flex-direction: column; justify-content: flex-end; padding: 50px; }
        .detail-hero-overlay h1 { font-size: clamp(2.4rem, 5vw, 4rem); color: #fff; margin-bottom: 16px; }
        .detail-hero-overlay .tag { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(6px); }
        .detail-content { display: grid; grid-template-columns: 2fr 1fr; gap: 50px; align-items: start; margin-bottom: 80px; }
        .detail-section { margin-bottom: 40px; }
        .detail-section-label { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .detail-section-label .bar { width: 4px; height: 36px; border-radius: 4px; }
        .detail-section-label h2 { font-size: 22px; }
        .detail-section p { font-size: 17px; line-height: 1.8; color: var(--text); }
        .detail-sidebar { position: sticky; top: 100px; padding: 32px; background: #fff; border: 1px solid var(--line); border-radius: 22px; box-shadow: 0 20px 50px -36px rgba(15,46,92,.3); }
        .detail-sidebar h4 { font: 700 11px 'JetBrains Mono', monospace; color: var(--blue); letter-spacing: .1em; text-transform: uppercase; margin-bottom: 20px; }
        .detail-sidebar .tech-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }

        @media (max-width: 768px) {
            .project-grid { grid-template-columns: 1fr; }
            .detail-content { grid-template-columns: 1fr; }
            .detail-sidebar { position: static; }
            .detail-hero { height: 35vh; border-radius: 18px; }
            .detail-hero-overlay { padding: 24px; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="sub-nav">
        <div class="wrap">
            <div id="navContent">
                <a class="logo-link" href="{{ url('/') }}">
                    <div class="logo">JF</div>
                    <span class="logo-text">← Homepage</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Grid View -->
    <div id="gridView">
        <header class="page-header">
            <div class="wrap">
                <span class="eyebrow">PORTFOLIO / PROJECTS</span>
                <h1>All <span style="color: var(--blue)">Masterpieces</span></h1>
                <p class="lead">Eksplorasi mendalam tentang solusi teknologi, inovasi AI, dan sistem yang telah saya bangun.</p>
            </div>
        </header>

        <section class="projects-section">
            <div class="wrap">
                <div class="project-grid" id="projectGrid"></div>
            </div>
        </section>
    </div>

    <!-- Detail View -->
    <div id="detailView" class="detail-view">
        <section style="padding: 40px 0 100px;">
            <div class="wrap">
                <div class="detail-hero">
                    <img id="detailImg" src="" alt="">
                    <div class="detail-hero-overlay">
                        <div style="margin-bottom: 14px;">
                            <span class="tag" id="detailTag"></span>
                        </div>
                        <h1 id="detailTitle"></h1>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;" id="detailTechHero"></div>
                    </div>
                </div>
                <div class="detail-content">
                    <div>
                        <div class="detail-section">
                            <div class="detail-section-label">
                                <div class="bar" style="background: var(--blue)"></div>
                                <h2>Latar Belakang</h2>
                            </div>
                            <p id="detailBg"></p>
                        </div>
                        <div class="detail-section">
                            <div class="detail-section-label">
                                <div class="bar" style="background: #e84393"></div>
                                <h2>Masalah yang Dihadapi</h2>
                            </div>
                            <p id="detailProblem"></p>
                        </div>
                        <div class="detail-section">
                            <div class="detail-section-label">
                                <div class="bar" style="background: #00b894"></div>
                                <h2>Penyelesaian Masalah</h2>
                            </div>
                            <p id="detailSolution"></p>
                        </div>
                    </div>
                    <div class="detail-sidebar">
                        <h4>Informasi Projek</h4>
                        <div class="tech-list" id="detailTechSidebar"></div>
                        <a class="btn btn-primary" id="detailLink" href="#" target="_blank" style="width: 100%">Kunjungi Live Web</a>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer style="text-align: center; padding: 50px 0; color: var(--muted); font-size: 13px; border-top: 1px solid var(--line);">
        &copy; 2026 Jauhar Fauzi Ulul Albab. Project Showcase Portfolio.
    </footer>

    <script>
        const projects = [
            {
                id: 'journal',
                tag: 'JAIC PUBLICATION',
                title: 'Recommendation System Research',
                desc: 'Publikasi ilmiah resmi pada Journal of Applied Informatics and Computing (JAIC).',
                bg: 'Penelitian ini didorong oleh kebutuhan untuk meningkatkan sistem rekomendasi pariwisata yang lebih akurat dan personal bagi wisatawan di Yogyakarta.',
                problem: 'Wisatawan seringkali kesulitan menemukan destinasi yang sesuai dengan preferensi mereka di tengah banyaknya pilihan objek wisata.',
                solution: 'Mengimplementasikan Content-Based Filtering menggunakan algoritma TF-IDF dan Cosine Similarity untuk akurasi data yang lebih baik.',
                tech: ['Python', 'Scikit-Learn', 'Pandas', 'Flask'],
                image: '{{ asset("project-media/journal.png") }}',
                link: 'https://jurnal.polibatam.ac.id/index.php/JAIC/article/view/11751/3420'
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
                image: '{{ asset("project-media/kpri.png") }}',
                link: 'http://kpribaktimulia.or.id/'
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
                image: '{{ asset("project-media/jaugjakita.png") }}',
                link: 'http://jaugjakita.jauharfauzi.my.id'
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
                image: '{{ asset("project-media/kauiz.png") }}',
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
                image: '{{ asset("project-media/livechat.png") }}',
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
        ];

        const grid = document.getElementById('projectGrid');
        const gridView = document.getElementById('gridView');
        const detailView = document.getElementById('detailView');
        const navContent = document.getElementById('navContent');

        // Check URL for project ID on load
        function init() {
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            if (id) {
                const project = projects.find(p => p.id === id);
                if (project) { showDetail(project); return; }
            }
            renderGrid();
        }

        function renderGrid() {
            grid.innerHTML = '';
            projects.forEach(project => {
                const card = document.createElement('div');
                card.className = 'project-card';
                card.onclick = () => showDetail(project);
                card.innerHTML = `
                    <div class="project-media">
                        <img src="${project.image}" alt="${project.title}" loading="lazy">
                        <div class="tag-overlay"><span class="tag" style="background:rgba(255,255,255,.9);backdrop-filter:blur(6px)">${project.tag}</span></div>
                    </div>
                    <div class="project-body">
                        <h3>${project.title}</h3>
                        <p>${project.desc}</p>
                        <div class="project-tech">
                            ${project.tech.map(t => `<span class="tag">${t}</span>`).join('')}
                        </div>
                        <div class="project-link-row">
                            <span class="project-action">Lihat Detail →</span>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function showDetail(project) {
            gridView.style.display = 'none';
            detailView.classList.add('active');
            window.history.pushState({}, '', '?id=' + project.id);
            window.scrollTo({ top: 0, behavior: 'smooth' });

            document.getElementById('detailImg').src = project.image;
            document.getElementById('detailTag').textContent = project.tag;
            document.getElementById('detailTitle').textContent = project.title;
            document.getElementById('detailBg').textContent = project.bg;
            document.getElementById('detailProblem').textContent = project.problem;
            document.getElementById('detailSolution').textContent = project.solution;
            document.getElementById('detailLink').href = project.link;

            const heroTech = document.getElementById('detailTechHero');
            heroTech.innerHTML = project.tech.map(t => `<span class="tag" style="background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.2)">${t}</span>`).join('');

            const sidebarTech = document.getElementById('detailTechSidebar');
            sidebarTech.innerHTML = project.tech.map(t => `<span class="tag">${t}</span>`).join('');

            navContent.innerHTML = `
                <button class="back-btn" onclick="closeDetail()">
                    <div style="width:40px;height:40px;border-radius:12px;background:var(--ink);display:grid;place-items:center">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </div>
                    <span>← Kembali ke List</span>
                </button>
            `;
        }

        function closeDetail() {
            detailView.classList.remove('active');
            gridView.style.display = '';
            window.history.pushState({}, '', window.location.pathname);
            navContent.innerHTML = `
                <a class="logo-link" href="{{ url('/') }}">
                    <div class="logo">JF</div>
                    <span class="logo-text">← Homepage</span>
                </a>
            `;
        }

        window.addEventListener('popstate', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            if (id) {
                const project = projects.find(p => p.id === id);
                if (project) showDetail(project);
            } else {
                closeDetail();
            }
        });

        init();
    </script>

</body>
</html>