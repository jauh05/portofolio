<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sertifikasi — Jauhar Fauzi Ulul Albab</title>
    <meta name="description" content="Sertifikasi dan pencapaian profesional Jauhar Fauzi Ulul Albab.">
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

        /* Nav */
        .sub-nav { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,.92); backdrop-filter: blur(15px); border-bottom: 1px solid var(--line); padding: 14px 0; }
        .sub-nav .wrap { display: flex; align-items: center; justify-content: space-between; }
        .logo-link { display: flex; align-items: center; gap: 12px; }
        .logo { width: 43px; height: 43px; border-radius: 14px; background: linear-gradient(145deg, var(--blue), var(--blue-dark)); display: grid; place-items: center; color: #fff; font: 800 16px Sora; box-shadow: 0 10px 20px -10px var(--blue-dark); }
        .logo-text { font: 700 13px Sora; color: var(--ink); letter-spacing: .02em; }

        /* Page Header */
        .page-header { padding: 80px 0 50px; background: var(--surface); border-bottom: 1px solid var(--line); }
        .page-header h1 { font-size: clamp(2.8rem, 5vw, 4.2rem); font-weight: 800; margin-bottom: 14px; }
        .page-header .lead { font-size: 17px; color: var(--muted); max-width: 660px; line-height: 1.75; }

        /* Cert Grid */
        .cert-section { padding: 70px 0 100px; }
        .cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; }
        .cert-card { background: #fff; border: 1px solid var(--line); border-radius: 22px; overflow: hidden; box-shadow: 0 20px 50px -36px rgba(15,46,92,.3); transition: .35s var(--ease); display: flex; flex-direction: column; }
        .cert-card:hover { transform: translateY(-5px); box-shadow: 0 28px 60px -30px rgba(15,46,92,.4); border-color: #b8d8fb; }
        .cert-preview { width: 100%; aspect-ratio: 4/3; background: #f0f4f8; position: relative; overflow: hidden; }
        .cert-preview iframe { width: 100%; height: 100%; border: none; pointer-events: none; }
        .cert-preview-overlay { position: absolute; inset: 0; background: transparent; cursor: pointer; }
        .cert-body { padding: 24px 26px 28px; flex: 1; display: flex; flex-direction: column; }
        .cert-predicate { font: 700 11px 'JetBrains Mono', monospace; color: var(--blue); letter-spacing: .1em; text-transform: uppercase; margin-bottom: 10px; }
        .cert-card h3 { font-size: 20px; margin-bottom: 6px; line-height: 1.3; }
        .cert-org { font-size: 13px; color: var(--muted); font-weight: 600; margin-bottom: 14px; }
        .cert-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 16px; border-top: 1px solid var(--line); margin-top: auto; }
        .cert-date { font: 500 12px 'JetBrains Mono', monospace; color: var(--muted); }
        .cert-action { font: 700 12px Inter; color: var(--blue-dark); transition: .2s; }
        .cert-action:hover { color: var(--blue); }

        /* Modal */
        .modal-backdrop { position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,.75); backdrop-filter: blur(8px); display: none; place-items: center; padding: 24px; }
        .modal-backdrop.active { display: grid; }
        .modal-content { background: #fff; border-radius: 28px; max-width: 1100px; width: 100%; max-height: 90vh; overflow: hidden; display: grid; grid-template-columns: 1.6fr 1fr; box-shadow: 0 40px 100px -40px rgba(0,0,0,.6); }
        .modal-pdf { background: #1a1a2e; min-height: 500px; }
        .modal-pdf iframe { width: 100%; height: 100%; border: none; }
        .modal-info { padding: 40px; display: flex; flex-direction: column; justify-content: center; gap: 28px; }
        .modal-info h2 { font-size: 28px; }
        .modal-info .detail-block { padding: 18px; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; }
        .modal-info .detail-label { font: 600 10px 'JetBrains Mono', monospace; color: var(--muted); text-transform: uppercase; letter-spacing: .12em; margin-bottom: 6px; }
        .modal-info .detail-value { font: 600 14px Inter; color: var(--ink); }
        .modal-close { position: absolute; top: 20px; right: 20px; width: 42px; height: 42px; border-radius: 50%; background: var(--surface); border: 1px solid var(--line); font-size: 20px; cursor: pointer; display: grid; place-items: center; color: var(--ink); transition: .2s; }
        .modal-close:hover { background: var(--soft); border-color: var(--blue); }
        .btn { display: inline-flex; align-items: center; gap: 9px; border-radius: 100px; padding: 13px 24px; font-weight: 700; font-size: 14px; border: 1px solid transparent; transition: .25s var(--ease); text-align: center; justify-content: center; }
        .btn-primary { color: #fff; background: var(--blue); box-shadow: 0 10px 24px -12px var(--blue-dark); }
        .btn-primary:hover { background: var(--blue-dark); transform: translateY(-2px); }

        @media (max-width: 768px) {
            .cert-grid { grid-template-columns: 1fr; }
            .modal-content { grid-template-columns: 1fr; }
            .modal-pdf { min-height: 300px; }
            .modal-info { padding: 24px; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="sub-nav">
        <div class="wrap">
            <a class="logo-link" href="{{ url('/') }}">
                <div class="logo">JF</div>
                <span class="logo-text">← Kembali</span>
            </a>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <div class="wrap">
            <span class="eyebrow">RECOGNITIONS & AWARDS</span>
            <h1>Official <span style="color: var(--blue)">Certifications</span></h1>
            <p class="lead">Pencapaian dan sertifikasi profesional dalam bidang teknologi dan akademik.</p>
        </div>
    </header>

    <!-- Certificates Grid -->
    <section class="cert-section">
        <div class="wrap">
            <div class="cert-grid" id="certGrid"></div>
        </div>
    </section>

    <!-- Modal -->
    <div class="modal-backdrop" id="certModal">
        <div class="modal-content" style="position:relative">
            <button class="modal-close" onclick="closeModal()">✕</button>
            <div class="modal-pdf">
                <iframe id="modalPdf" src=""></iframe>
            </div>
            <div class="modal-info">
                <div>
                    <div class="cert-predicate" id="modalPredicate"></div>
                    <h2 id="modalTitle"></h2>
                    <p style="color: var(--blue); font-weight: 700; margin-top: 6px;" id="modalOrg"></p>
                </div>
                <div class="detail-block">
                    <div class="detail-label">Role / Achievement</div>
                    <div class="detail-value" id="modalRole"></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="detail-block">
                        <div class="detail-label">Date</div>
                        <div class="detail-value" id="modalDate"></div>
                    </div>
                    <div class="detail-block">
                        <div class="detail-label">Ref ID</div>
                        <div class="detail-value" id="modalNumber" style="font-size: 11px;"></div>
                    </div>
                </div>
                <a class="btn btn-primary" id="modalDownload" download>Download PDF</a>
            </div>
        </div>
    </div>

    <footer style="text-align: center; padding: 50px 0; color: var(--muted); font-size: 13px; border-top: 1px solid var(--line);">
        &copy; 2026 Jauhar Fauzi Ulul Albab. All rights reserved.
    </footer>

    <script>
        const certs = [
            {
                title: 'ASISTEN PRAKTIKUM',
                organization: 'AMIKOM CREATIVE ECONOMY PARK',
                role: 'Asisten Praktikum Mata Kuliah Struktur Data',
                date: '25-02-2026',
                number: 'NO. 0486',
                predicate: 'BAIK',
                file: 'Sertifikat Asisten JAUHAR FAUZI ULUL ALBAB-Struktur Data.pdf'
            },
            {
                title: 'FISCREATION',
                organization: 'UNIVERSITAS NEGERI YOGYAKARTA',
                role: 'Peserta FISCREATION 2023: Futuristic Exploration Workshop',
                date: '18 November 2023',
                number: 'NO:014/Pan-FISCREATION/MEDINFO/BEM FISHIPOL/XI/2023',
                predicate: 'Peserta',
                file: 'JAUHAR FAUZI ULUL ALBAB.pdf'
            },
            {
                title: 'Karya Tulis Islami',
                organization: 'KANTOR KEMENTERIAN AGAMA KOTA YOGYAKARTA',
                role: 'Juara III Lomba Karya Tulis Islami Putra - MTQ XXXI',
                date: '29 September 2025',
                number: 'Nomor: 2670/Kk.12.05/6/BA.00/09/2025',
                predicate: 'JUARA III',
                file: 'Jauhar Fauzi Ulul Albab (1).pdf'
            },
            {
                title: 'Waroeng Steak & Shake',
                organization: 'PT. WAROENG STEAK INDONESIA',
                role: 'Asisten Programmer – Internship Program',
                date: 'September 2024',
                number: 'NO: WS/INTERN/2024/001',
                predicate: 'ASSISTANT PROGRAMMER',
                file: 'ws.pdf'
            }
        ];

        const grid = document.getElementById('certGrid');
        const assetBase = '{{ asset("serti") }}';

        certs.forEach((cert, i) => {
            const card = document.createElement('div');
            card.className = 'cert-card';
            card.innerHTML = `
                <div class="cert-preview" onclick="openModal(${i})">
                    <iframe src="${assetBase}/${encodeURIComponent(cert.file)}#toolbar=0&navpanes=0&scrollbar=0&view=FitH" loading="lazy"></iframe>
                    <div class="cert-preview-overlay"></div>
                </div>
                <div class="cert-body">
                    <div class="cert-predicate">${cert.predicate}</div>
                    <h3>${cert.title}</h3>
                    <div class="cert-org">${cert.organization}</div>
                    <div class="cert-footer">
                        <span class="cert-date">${cert.date}</span>
                        <span class="cert-action" onclick="openModal(${i})" style="cursor:pointer">Lihat Detail →</span>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });

        function openModal(index) {
            const cert = certs[index];
            document.getElementById('modalPdf').src = `${assetBase}/${encodeURIComponent(cert.file)}#toolbar=0&navpanes=0&scrollbar=0&view=FitH`;
            document.getElementById('modalPredicate').textContent = cert.predicate;
            document.getElementById('modalTitle').textContent = cert.title;
            document.getElementById('modalOrg').textContent = cert.organization;
            document.getElementById('modalRole').textContent = cert.role;
            document.getElementById('modalDate').textContent = cert.date;
            document.getElementById('modalNumber').textContent = cert.number;
            document.getElementById('modalDownload').href = `${assetBase}/${encodeURIComponent(cert.file)}`;
            document.getElementById('certModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('certModal').classList.remove('active');
            document.getElementById('modalPdf').src = '';
            document.body.style.overflow = '';
        }

        document.getElementById('certModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>

</body>
</html>