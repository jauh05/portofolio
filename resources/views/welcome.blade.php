<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jauhar Ulul Fauzi - Portfolio</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon_jf.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@latest/devicon.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
            /* Ensures navbar doesn't cover content */
        }

        .bg-dark-mesh {
            background-color: #050505;
            position: relative;
            z-index: 0;
            overflow-x: hidden;
        }

        .bg-dark-mesh::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(16, 185, 129, 0.15) 0%, transparent 50%);
            animation: mesh-rotate 20s linear infinite;
            z-index: -1;
            pointer-events: none;
        }

        @keyframes mesh-rotate {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        .project-card-glow {
            position: absolute;
            inset: -1px;
            background: linear-gradient(45deg, transparent, rgba(59, 130, 246, 0.3), transparent);
            z-index: -1;
            border-radius: 1.5rem;
            opacity: 0;
            transition: opacity 0.5s;
        }

        .group:hover .project-card-glow {
            opacity: 1;
        }

        .glass-card {
            background: rgba(24, 24, 27, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .float {
            animation: float 6s ease-in-out infinite;
        }

        .fold-effect {
            clip-path: polygon(0 0, 0% 100%, 100% 100%);
            background: linear-gradient(135deg, #f3f4f6 45%, #d1d5db 50%, #9ca3af 100%);
        }
    </style>
</head>

<body class="bg-dark-mesh text-white antialiased">

    <!-- Dynamic Island Navbar -->
    <nav class="fixed top-6 left-1/2 -translate-x-1/2 z-50 transition-all duration-300 w-fit max-w-[95vw] px-4 md:px-0"
        x-data="{ expanded: false }">
        <div class="bg-zinc-900/90 backdrop-blur-2xl border border-white/10 rounded-full shadow-2xl transition-all duration-500 ease-[cubic-bezier(0.4,0,0.2,1)] overflow-hidden"
            :class="expanded ? 'px-8 py-6 rounded-[2.5rem]' : 'p-2'">
            <div class="flex items-center" :class="expanded ? 'justify-between' : 'gap-3'">
                <!-- Logo / Home Icon -->
                <div class="bg-blue-600 rounded-full w-10 h-10 flex items-center justify-center flex-shrink-0 cursor-pointer hover:scale-105 transition shadow-[0_0_15px_rgba(37,99,235,0.4)]"
                    @click="expanded = !expanded">
                    <span class="font-bold text-white tracking-tighter">JF</span>
                </div>

                <!-- Expanded Menu (Desktop) -->
                <div class="hidden md:flex items-center gap-1" :class="expanded ? 'hidden' : 'flex'">
                    <a href="#about"
                        class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-full transition">About</a>
                    <a href="#projects"
                        class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-full transition">Projects</a>
                    <a href="#skills"
                        class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/5 rounded-full transition">Skills</a>
                    <a href="#contact"
                        class="px-5 py-2 text-sm bg-white text-black font-bold rounded-full hover:bg-blue-50 transition ml-2">Contact</a>
                </div>

                <!-- Mobile Menu Toggle Text (When collapsed on mobile) -->
                <div class="md:hidden text-xs font-bold text-gray-400 pr-3 uppercase tracking-widest pl-1"
                    @click="expanded = !expanded" x-show="!expanded">
                    Menu
                </div>
            </div>

            <!-- Expanded Content (Mobile & Desktop Full View) -->
            <div x-show="expanded" x-collapse style="display: none;" class="mt-6 md:hidden">
                <div class="flex flex-col gap-4 text-center min-w-[200px]">
                    <a href="#about" @click="expanded = false"
                        class="text-gray-300 hover:text-white text-lg font-medium">About</a>
                    <a href="#projects" @click="expanded = false"
                        class="text-gray-300 hover:text-white text-lg font-medium">Projects</a>
                    <a href="#skills" @click="expanded = false"
                        class="text-gray-300 hover:text-white text-lg font-medium">Skills</a>
                    <a href="#contact" @click="expanded = false"
                        class="bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold">Contact Me</a>
                </div>
            </div>
        </div>
    </nav>

    <header id="about"
        class="max-w-6xl mx-auto px-6 pt-24 md:pt-40 pb-16 md:pb-24 text-center md:text-left flex flex-col-reverse md:flex-row items-center gap-12">
        <div class="flex-1">
            <h1 class="text-4xl md:text-6xl font-bold leading-tight mb-4">
                JAUHAR FAUZI <br><span class="text-blue-500">ULUL ALBAB</span>
            </h1>
            <p class="text-xl text-gray-200 font-semibold mb-6">
                Software Engineer & Fullstack Developer
            </p>
            <p class="text-gray-400 text-lg mb-8 max-w-lg leading-relaxed">
                Pemuda yang antusias dengan perkembangan teknologi 💻 dan sangat mencintai coding & algoritma 🧠✨.
                Senang belajar hal baru, membangun solusi, dan mengeksplorasi teknologi untuk masa depan yang lebih baik
                🚀.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                <a href="#projects"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-full font-semibold transition-all transform hover:scale-105 text-center">
                    View My Projects
                </a>
                <a href="#contact"
                    class="bg-zinc-800 hover:bg-zinc-700 text-white px-8 py-4 rounded-full font-semibold transition-all border border-white/10 text-center">
                    Contact Me
                </a>
            </div>
        </div>

        <div class="flex-1 relative">
            <div class="w-full aspect-square rounded-[3rem] overflow-hidden relative group">
                <!-- Placeholder for profile image, using a tech-abstract one for now -->
                <img src="{{ asset('foto.png') }}" alt="Profile"
                    class="w-full h-full object-cover transition duration-500 hover:scale-105">
            </div>
            <div
                class="absolute -bottom-6 -left-6 bg-blue-600 p-6 rounded-2xl shadow-xl transform rotate-3 hover:rotate-0 transition duration-300">
                <p class="text-3xl font-bold">4+</p>
                <p class="text-xs uppercase font-semibold tracking-wider">Years Experience</p>
            </div>
        </div>
    </header>

    <section class="max-w-6xl mx-auto px-6 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div
                class="bg-blue-600/10 border border-blue-500/20 p-8 rounded-3xl text-center hover:bg-blue-600/20 transition duration-300">
                <h3 class="text-3xl font-bold mb-1 text-blue-400">8+</h3>
                <p class="text-sm text-gray-400">Projects Completed</p>
            </div>
            <div
                class="bg-zinc-900 border border-white/5 p-8 rounded-3xl text-center hover:border-blue-500/30 transition duration-300">
                <h3 class="text-3xl font-bold mb-1">10+</h3>
                <p class="text-sm text-gray-400">Tech Stack</p>
            </div>
            <div
                class="bg-zinc-900 border border-white/5 p-8 rounded-3xl text-center hover:border-blue-500/30 transition duration-300">
                <h3 class="text-3xl font-bold mb-1">4+</h3>
                <p class="text-sm text-gray-400">Years Exp</p>
            </div>
            <div
                class="bg-zinc-900 border border-white/5 p-8 rounded-3xl text-center hover:border-blue-500/30 transition duration-300">
                <h3 class="text-3xl font-bold mb-1">100%</h3>
                <p class="text-sm text-gray-400">Commitment</p>
            </div>
        </div>
    </section>

    <!-- Technical Skills Section (Moved Up) -->
    <section id="skills" class="max-w-6xl mx-auto px-6 py-8 md:py-20">
        <div
            class="bg-zinc-900/40 border border-white/10 rounded-[2rem] md:rounded-[4rem] p-6 md:p-12 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-blue-600/20 blur-[100px]"></div>

            <div class="flex flex-col md:flex-row justify-between items-end mb-8 md:mb-12">
                <div>
                    <h2 class="text-3xl md:text-4xl font-bold mb-4">Technical <span class="text-blue-500">Skills</span>
                    </h2>
                    <p class="text-gray-400 text-sm md:text-base">Expertise in modern web technologies and AI.</p>
                </div>
                <div class="text-blue-500 font-mono text-sm hidden md:block">02 / SKILLS</div>
            </div>

            <!-- Mobile Swipe Indicator -->
            <div class="md:hidden flex items-center gap-2 mb-4 text-gray-500 text-xs animate-pulse">
                <span>Swipe to explore</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 8l4 4m0 0l-4 4m4-4H3" />
                </svg>
            </div>

            <div
                class="flex flex-nowrap overflow-x-auto snap-x snap-mandatory gap-4 md:grid md:grid-cols-2 md:gap-6 no-scrollbar pb-6 md:pb-0">
                <!-- Backend -->
                <div
                    class="p-6 md:p-8 bg-zinc-900/40 rounded-3xl border border-white/5 min-w-[85vw] md:min-w-0 snap-center">
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <span class="text-2xl">⚙️</span>
                        <h3 class="text-lg md:text-xl font-bold">Backend</h3>
                    </div>
                    <div class="flex flex-wrap gap-3 md:gap-4">
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-laravel-original text-3xl md:text-4xl text-red-500"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">Laravel</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-codeigniter-plain text-3xl md:text-4xl text-orange-500"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">CodeIgniter</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-mysql-original text-3xl md:text-4xl text-blue-400"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">MySQL</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <!-- Generic API Icon -->
                            <svg class="w-8 h-8 md:w-9 md:h-9 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-[10px] md:text-xs text-gray-400">REST API</span>
                        </div>
                    </div>
                </div>

                <!-- Frontend -->
                <div
                    class="p-6 md:p-8 bg-zinc-900/40 rounded-3xl border border-white/5 min-w-[85vw] md:min-w-0 snap-center">
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <span class="text-2xl">🎨</span>
                        <h3 class="text-lg md:text-xl font-bold">Frontend</h3>
                    </div>
                    <div class="flex flex-wrap gap-3 md:gap-4">
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-tailwindcss-original text-3xl md:text-4xl text-cyan-400"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">Tailwind</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-bootstrap-plain text-3xl md:text-4xl text-purple-500"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">Bootstrap</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-css3-plain text-3xl md:text-4xl text-blue-500"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">CSS3</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <!-- Blade approximate icon (HTML5 or generic code) -->
                            <svg class="w-8 h-8 md:w-9 md:h-9 text-red-500" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            <span class="text-[10px] md:text-xs text-gray-400">Blade</span>
                        </div>
                    </div>
                </div>

                <!-- Languages -->
                <div
                    class="p-6 md:p-8 bg-zinc-900/40 rounded-3xl border border-white/5 min-w-[85vw] md:min-w-0 snap-center">
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <span class="text-2xl">💻</span>
                        <h3 class="text-lg md:text-xl font-bold">Languages</h3>
                    </div>
                    <div class="flex flex-wrap gap-3 md:gap-4">
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-php-plain text-3xl md:text-4xl text-indigo-400"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">PHP</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-python-plain text-3xl md:text-4xl text-yellow-500"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">Python</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-javascript-plain text-3xl md:text-4xl text-yellow-400"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">JavaScript</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <i class="devicon-cplusplus-plain text-3xl md:text-4xl text-blue-600"></i>
                            <span class="text-[10px] md:text-xs text-gray-400">C++</span>
                        </div>
                    </div>
                </div>

                <!-- AI -->
                <div
                    class="p-6 md:p-8 bg-zinc-900/40 rounded-3xl border border-white/5 min-w-[85vw] md:min-w-0 snap-center">
                    <div class="flex items-center gap-3 mb-4 md:mb-6">
                        <span class="text-2xl">🤖</span>
                        <h3 class="text-lg md:text-xl font-bold">AI & Tech</h3>
                    </div>
                    <div class="flex flex-wrap gap-3 md:gap-4">
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <span class="text-2xl md:text-3xl">🧩</span>
                            <span class="text-[10px] md:text-xs text-gray-400 text-center">Prompt Eng.</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <span class="text-2xl md:text-3xl">🚀</span>
                            <span class="text-[10px] md:text-xs text-gray-400 text-center">Few-shot</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <span class="text-2xl md:text-3xl">🧠</span>
                            <span class="text-[10px] md:text-xs text-gray-400 text-center">NLP</span>
                        </div>
                        <div
                            class="flex flex-col items-center gap-2 bg-zinc-800/50 p-3 md:p-4 rounded-xl min-w-[80px] md:min-w-[100px] hover:bg-zinc-800 transition">
                            <span class="text-2xl md:text-3xl">🛸</span>
                            <span class="text-[10px] md:text-xs text-gray-400 text-center">Antigravity</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Projects Section -->
    <section id="projects" class="max-w-6xl mx-auto px-6 py-16 md:py-24 relative">
        <!-- Background Glow -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-600/10 blur-[120px] rounded-full -z-10"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-indigo-600/10 blur-[120px] rounded-full -z-10"></div>

        <div class="flex flex-col md:flex-row justify-between items-end mb-12 lg:mb-16">
            <div class="max-w-2xl">
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[10px] font-black tracking-widest uppercase mb-4">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                    Work & Research
                </div>
                <h2 class="text-4xl md:text-6xl font-bold mb-6 tracking-tight">Featured <span
                        class="text-blue-500">Projects</span></h2>
                <p class="text-gray-400 text-lg md:text-xl leading-relaxed">Kumpulan solusi digital dan publikasi ilmiah
                    yang berfokus pada inovasi teknologi.</p>
            </div>
            <div class="text-blue-500 font-mono text-sm hidden md:block border-b border-blue-500/30 pb-2">03 / PORTFOLIO
            </div>
        </div>
        </div>

        <!-- Mobile Swipe Indicator (Enhanced & Animated) -->
        <div class="md:hidden flex flex-col items-center justify-center mb-10 mt-4 px-4">
            <div class="flex items-center gap-4 text-white/80 text-sm font-bold tracking-widest uppercase mb-3">
                <div class="flex gap-1.5 order-2">
                    <div class="w-10 h-1.5 bg-blue-600 rounded-full"></div>
                    <div class="w-2 h-1.5 bg-zinc-800 rounded-full"></div>
                    <div class="w-2 h-1.5 bg-zinc-800 rounded-full"></div>
                </div>
                <span class="order-1">Geser Proyek</span>
            </div>
            <div class="relative w-full h-8 flex justify-center items-center pointer-events-none">
                <div class="absolute animate-[swipe_1.5s_ease-in-out_infinite] flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-full bg-blue-600/20 flex items-center justify-center border border-blue-500/30">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <style>
            @keyframes swipe {
                0% {
                    transform: translateX(-40px);
                    opacity: 0;
                }

                50% {
                    opacity: 1;
                }

                100% {
                    transform: translateX(40px);
                    opacity: 0;
                }
            }
        </style>

        <div
            class="flex flex-nowrap overflow-x-auto snap-x snap-mandatory gap-6 md:grid md:grid-cols-2 lg:grid-cols-2 lg:gap-12 no-scrollbar pb-16 md:pb-0">

            <!-- Project 0: Journal Publication (Redesigned PDF Style) -->
            <div
                class="group relative bg-zinc-950 border border-white/5 rounded-[2.5rem] overflow-hidden transition-all duration-500 hover:border-blue-500/40 hover:shadow-[0_0_60px_-15px_rgba(59,130,246,0.25)] min-w-[90vw] md:min-w-0 snap-center lg:col-span-2">
                <div class="project-card-glow"></div>
                <div class="flex flex-col lg:flex-row min-h-[480px]">
                    <!-- PDF Visual Side -->
                    <div
                        class="lg:w-1/2 p-8 lg:p-12 bg-white/5 flex items-center justify-center relative overflow-hidden group-hover:bg-white/10 transition-colors">
                        <!-- Abstract Background Shapes -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-600/10 blur-[80px] -z-0"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-indigo-600/10 blur-[80px] -z-0"></div>

                        <!-- PDF Paper Simulation -->
                        <div
                            class="relative w-full max-w-[280px] aspect-[1/1.414] bg-white rounded-sm shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform -rotate-3 group-hover:rotate-0 transition-transform duration-500 p-6 flex flex-col">
                            <!-- Paper Corner Fold -->
                            <div
                                class="absolute top-0 right-0 w-12 h-12 bg-gray-100 shadow-[-5px_5px_10px_rgba(0,0,0,0.1)] border-b border-l border-gray-200 fold-effect">
                            </div>

                            <!-- Header Text -->
                            <div class="w-1/2 h-4 bg-gray-200 mb-6 rounded-full"></div>
                            <div class="w-full h-2 bg-blue-100 mb-2 rounded-full"></div>
                            <div class="w-4/5 h-2 bg-blue-100 mb-10 rounded-full"></div>

                            <!-- Journal Title Concept -->
                            <div class="space-y-3 mb-10">
                                <div class="w-full h-3 bg-gray-800 rounded-full"></div>
                                <div class="w-full h-3 bg-gray-800 rounded-full"></div>
                                <div class="w-2/3 h-3 bg-gray-800 rounded-full"></div>
                            </div>

                            <!-- Abstract Lines -->
                            <div class="space-y-2 mt-auto">
                                <div class="w-full h-1 bg-gray-200 rounded-full"></div>
                                <div class="w-full h-1 bg-gray-200 rounded-full"></div>
                                <div class="w-full h-1 bg-gray-200 rounded-full"></div>
                                <div class="w-4/5 h-1 bg-gray-200 rounded-full"></div>
                            </div>

                            <!-- Footer of Paper -->
                            <div class="mt-6 flex justify-between items-end">
                                <div class="w-16 h-16 bg-blue-50 rounded-sm"></div>
                                <div class="bg-blue-600 text-[8px] text-white font-bold px-2 py-1 rounded">RESEARCH
                                </div>
                            </div>
                        </div>

                        <!-- Floating PDF Icon -->
                        <div
                            class="absolute bottom-10 right-10 bg-red-600 w-12 h-12 rounded-xl flex items-center justify-center shadow-2xl transform rotate-12 group-hover:rotate-0 transition-transform">
                            <span class="text-white font-black text-sm">PDF</span>
                        </div>
                    </div>

                    <!-- Content Side -->
                    <div class="lg:w-1/2 p-8 lg:p-14 flex flex-col justify-center relative z-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div
                                class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase border border-blue-500/20">
                                Scientific Paper
                            </div>
                            <div class="text-white/40 text-[10px] font-bold tracking-widest uppercase">
                                JAIC JOURNAL • 2024
                            </div>
                        </div>
                        <h3
                            class="text-2xl lg:text-4xl font-bold mb-6 text-white group-hover:text-blue-400 transition-colors leading-[1.1] tracking-tight">
                            Yogyakarta Tourism Recommendation System Using TF-IDF & Cosine Similarity
                        </h3>
                        <p class="text-gray-400 mb-10 leading-relaxed text-base lg:text-lg">
                            Penelitian akademik yang mengoptimalkan sistem rekomendasi wisata melalui pemrosesan bahasa
                            alami (**NLP**) dan normalisasi kata untuk akurasi pencarian yang lebih baik.
                        </p>
                        <div class="flex flex-wrap gap-3 mb-10">
                            <span
                                class="px-4 py-1.5 bg-zinc-900 text-gray-400 border border-white/5 rounded-full text-xs font-semibold">TF-IDF</span>
                            <span
                                class="px-4 py-1.5 bg-zinc-900 text-gray-400 border border-white/5 rounded-full text-xs font-semibold">Cosine
                                Similarity</span>
                            <span
                                class="px-4 py-1.5 bg-zinc-900 text-gray-400 border border-white/5 rounded-full text-xs font-semibold">Word
                                Normalization</span>
                        </div>
                        <div>
                            <a href="https://jurnal.polibatam.ac.id/index.php/JAIC/article/view/11751/3420"
                                target="_blank"
                                class="inline-flex items-center gap-4 px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-black transition-all transform hover:scale-105 active:scale-95 shadow-[0_10px_30px_-10px_rgba(37,99,235,0.5)]">
                                Read Journal PDF
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project 1 -->
            <div
                class="group relative bg-zinc-900 border border-white/5 rounded-[2rem] overflow-hidden hover:border-green-500/30 transition-all duration-500 min-w-[85vw] md:min-w-0 snap-center hover:shadow-[0_20px_40px_-15px_rgba(16,185,129,0.1)]">
                <div class="h-48 md:h-64 overflow-hidden relative">
                    <img src="{{ asset('projects/kpri.png') }}" alt="KPRI Bakti Mulia"
                        class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 to-transparent opacity-60"></div>
                </div>
                <div class="p-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                        <span class="text-green-500 text-[10px] font-black tracking-widest uppercase">FINTECH •
                            Koperasi</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-white group-hover:text-green-500 transition">KPRI Bakti
                        Mulia</h3>
                    <p class="text-gray-400 mb-8 leading-relaxed text-sm">
                        Digitalisasi sistem simpan pinjam & analisis kredit menggunakan **C4.5 Algorithm** untuk Kemenag
                        Kota Yogyakarta.
                    </p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">PHP Native</span>
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">Decision
                            Support</span>
                    </div>
                    <a href="http://kpribaktimulia.or.id/" target="_blank"
                        class="inline-flex items-center text-sm font-black text-white hover:text-green-400 transition-all group/link">
                        Live Preview <span class="ml-2 transform group-hover/link:translate-x-2 transition">→</span>
                    </a>
                </div>
            </div>

            <!-- Project 2 -->
            <div
                class="group relative bg-zinc-900 border border-white/5 rounded-[2rem] overflow-hidden hover:border-indigo-500/30 transition-all duration-500 min-w-[85vw] md:min-w-0 snap-center hover:shadow-[0_20px_40px_-15px_rgba(99,102,241,0.1)]">
                <div class="h-48 md:h-64 overflow-hidden relative">
                    <img src="{{ asset('projects/jaugjakita.png') }}" alt="JaugjaKita"
                        class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 to-transparent opacity-60"></div>
                </div>
                <div class="p-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1.5 h-1.5 rounded-full bg-indigo-500"></div>
                        <span class="text-indigo-500 text-[10px] font-black tracking-widest uppercase">AI •
                            RecSys</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-white group-hover:text-indigo-500 transition">JaugjaKita App
                    </h3>
                    <p class="text-gray-400 mb-8 leading-relaxed text-sm">
                        Portal rekomendasi wisata cerdas berbasis **TF-IDF & Cosine Similarity** untuk destinasi
                        Yogyakarta.
                    </p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">Python</span>
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">Streamlit</span>
                    </div>
                    <a href="http://jaugjakita.jauharfauzi.my.id"
                        class="inline-flex items-center text-sm font-black text-white hover:text-indigo-400 transition-all group/link">
                        Open Project <span class="ml-2 transform group-hover/link:translate-x-2 transition">→</span>
                    </a>
                </div>
            </div>

            <!-- Project 3 -->
            <div
                class="group relative bg-zinc-900 border border-white/5 rounded-[2rem] overflow-hidden hover:border-emerald-500/30 transition-all duration-500 min-w-[85vw] md:min-w-0 snap-center hover:shadow-[0_20px_40px_-15px_rgba(16,185,129,0.1)]">
                <div class="h-48 md:h-64 overflow-hidden relative">
                    <img src="{{ asset('projects/kauiz.png') }}" alt="Kauiz"
                        class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 to-transparent opacity-60"></div>
                </div>
                <div class="p-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                        <span class="text-emerald-500 text-[10px] font-black tracking-widest uppercase">EDTECH •
                            AI</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-white group-hover:text-emerald-500 transition">Kauiz Ai</h3>
                    <p class="text-gray-400 mb-8 leading-relaxed text-sm">
                        Platform pembuatan kuis otomatis berbasis AI untuk efisiensi evaluasi pembelajaran bagi
                        pengajar.
                    </p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">Laravel</span>
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">OpenAI API</span>
                    </div>
                    <a href="http://kauiz.jauharfauzi.my.id/"
                        class="inline-flex items-center text-sm font-black text-white hover:text-emerald-400 transition-all group/link">
                        View Details <span class="ml-2 transform group-hover/link:translate-x-2 transition">→</span>
                    </a>
                </div>
            </div>

            <!-- Project 4 -->
            <div
                class="group relative bg-zinc-900 border border-white/5 rounded-[2rem] overflow-hidden hover:border-purple-500/30 transition-all duration-500 min-w-[85vw] md:min-w-0 snap-center hover:shadow-[0_20px_40px_-15px_rgba(168,85,247,0.1)]">
                <div class="h-48 md:h-64 overflow-hidden relative">
                    <img src="{{ asset('projects/livechat.png') }}" alt="LiveChat Event"
                        class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 to-transparent opacity-60"></div>
                </div>
                <div class="p-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1.5 h-1.5 rounded-full bg-purple-500"></div>
                        <span class="text-purple-500 text-[10px] font-black tracking-widest uppercase">REALTIME •
                            NLP</span>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-white group-hover:text-purple-500 transition">LiveChat Event
                    </h3>
                    <p class="text-gray-400 mb-8 leading-relaxed text-sm">
                        Aplikasi chat interaktif event dengan filtering kata kasar berbasis NLP untuk komunitas positif.
                    </p>
                    <div class="flex flex-wrap gap-2 mb-10">
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">Socket.io</span>
                        <span class="px-3 py-1 bg-zinc-800 text-gray-500 rounded-lg text-xs font-bold">NLP.js</span>
                    </div>
                    <a href="https://livechat.jauharfauzi.my.id/"
                        class="inline-flex items-center text-sm font-black text-white hover:text-purple-400 transition-all group/link">
                        Open Project <span class="ml-2 transform group-hover/link:translate-x-2 transition">→</span>
                    </a>
                </div>
            </div>
        </div>
        </div>
    </section>

    <!-- Education Section -->
    <section class="max-w-6xl mx-auto px-6 py-12">
        <h2 class="text-3xl font-bold mb-8 text-center md:text-left">Education <span class="text-blue-500">&
                Training</span></h2>
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Amikom -->
            <div
                class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl hover:border-blue-500/50 transition duration-300 flex items-start gap-4">
                <div
                    class="w-16 h-16 bg-white rounded-xl flex items-center justify-center p-2 flex-shrink-0 overflow-hidden">
                    <img src="{{ asset('logo_amikom.png') }}" alt="Amikom Logo" class="w-full h-full object-contain">
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-1">Universitas Amikom Yogyakarta</h3>
                    <p class="text-blue-400 font-medium mb-1">Program Studi Sistem Informasi</p>
                    <p class="text-gray-400 text-sm mb-1">Angkatan Tahun 2023 • IPK: 3.91</p>
                    <p class="text-gray-500 text-xs">Agustus 2023 - Sekarang</p>
                </div>
            </div>

            <!-- Trainit -->
            <div
                class="bg-zinc-900/50 border border-white/5 p-8 rounded-3xl hover:border-purple-500/50 transition duration-300 flex items-start gap-4">
                <div
                    class="w-12 h-12 bg-purple-600/20 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">
                    💻
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white mb-1">Pelatihan – TrainIT Jogja</h3>
                    <p class="text-purple-400 font-medium mb-1">Fullstack Developer</p>
                    <p class="text-gray-400 text-sm mb-1">Materi: Laravel & Machine Learning (K-Means)</p>
                    <p class="text-gray-500 text-xs">Febuari 2025 - Sekarang</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Experience Section -->
    <section class="max-w-6xl mx-auto px-6 py-12">
        <h2 class="text-3xl font-bold mb-8 text-center md:text-left">Professional <span
                class="text-blue-500">Experience</span></h2>
        <div class="space-y-6">
            <!-- Asdos -->
            <div class="bg-zinc-900/30 border border-white/5 p-8 rounded-3xl hover:bg-zinc-900/50 transition">
                <div class="flex flex-col md:flex-row justify-between md:items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold text-white">Asisten Dosen – Struktur Data</h3>
                        <p class="text-blue-400 text-lg">Informatika Universitas Amikom Yogyakarta</p>
                    </div>
                    <span class="text-gray-500 text-sm mt-2 md:mt-0 bg-zinc-800 px-3 py-1 rounded-full">September 2024 –
                        Sekarang</span>
                </div>
                <p class="text-gray-400 leading-relaxed">
                    Membantu dosen dalam pengajaran dan asistensi praktikum pemrograman struktural. Membimbing mahasiswa
                    dalam memahami konsep struktur data.
                </p>
            </div>

            <!-- Founder Jauki -->
            <div class="bg-zinc-900/30 border border-white/5 p-8 rounded-3xl hover:bg-zinc-900/50 transition">
                <div class="flex flex-col md:flex-row justify-between md:items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold text-white">Founder – Jauki Academy</h3>
                        <p class="text-purple-400 text-lg">Software House & Education</p>
                    </div>
                    <span class="text-gray-500 text-sm mt-2 md:mt-0 bg-zinc-800 px-3 py-1 rounded-full">November 2022 –
                        Sekarang</span>
                </div>
                <p class="text-gray-400 leading-relaxed mb-4">
                    Mengembangkan layanan jasa pembuatan website, prompting AI, dan portofolio digital. Memimpin tim
                    kecil dan berperan langsung dalam desain, implementasi, dan strategi pemasaran.
                </p>
            </div>

            <!-- Waroeng Steak -->
            <div class="bg-zinc-900/30 border border-white/5 p-8 rounded-3xl hover:bg-zinc-900/50 transition">
                <div class="flex flex-col md:flex-row justify-between md:items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold text-white">Magang – Waroeng Steak Indonesia</h3>
                        <p class="text-yellow-500 text-lg">Asisten Programmer - Divisi Marketing</p>
                    </div>
                    <span class="text-gray-500 text-sm mt-2 md:mt-0 bg-zinc-800 px-3 py-1 rounded-full">Agustus 2024 –
                        September 2024</span>
                </div>
                <ul class="list-disc list-inside text-gray-400 space-y-2">
                    <li>Berkontribusi dalam pengembangan sistem loyalitas pelanggan berbasis website.</li>
                    <li>Mendalami framework Laravel dan PHP lanjutan.</li>
                    <li>Terlibat langsung dalam pemecahan masalah teknis dan presentasi solusi kepada tim.</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Additional Info / Soft Skills -->
    <section class="max-w-6xl mx-auto px-6 py-12 mb-12">
        <h2 class="text-3xl font-bold mb-8 text-center md:text-left">Informasi <span
                class="text-blue-500">Tambahan</span></h2>

        <!-- Mobile Swipe Indicator -->
        <div class="md:hidden flex items-center gap-2 mb-4 text-gray-500 text-xs animate-pulse">
            <span>Swipe for more</span>
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
        </div>

        <div
            class="flex flex-nowrap overflow-x-auto snap-x snap-mandatory gap-6 md:grid md:grid-cols-3 md:gap-6 no-scrollbar pb-6 md:pb-0">
            <div
                class="bg-zinc-900/50 p-6 rounded-2xl border border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center">
                <div class="text-4xl mb-4">🤝</div>
                <h3 class="text-xl font-bold text-white mb-2">Kerja Tim yang Solid</h3>
                <p class="text-gray-400 text-sm">Terbiasa bekerja sama dalam tim lintas bidang dan menjaga komunikasi
                    yang efektif.</p>
            </div>
            <div
                class="bg-zinc-900/50 p-6 rounded-2xl border border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center">
                <div class="text-4xl mb-4">⏱️</div>
                <h3 class="text-xl font-bold text-white mb-2">Disiplin & Tepat Waktu</h3>
                <p class="text-gray-400 text-sm">Mampu menyelesaikan tugas sesuai target dengan manajemen waktu yang
                    baik.</p>
            </div>
            <div
                class="bg-zinc-900/50 p-6 rounded-2xl border border-white/5 text-center hover:-translate-y-1 transition min-w-[75vw] md:min-w-0 snap-center">
                <div class="text-4xl mb-4">📢</div>
                <h3 class="text-xl font-bold text-white mb-2">Komunikatif & Percaya Diri</h3>
                <p class="text-gray-400 text-sm">Mampu menyampaikan ide dan presentasi dengan jelas, baik dalam diskusi
                    maupun forum formal.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="max-w-6xl mx-auto px-6 py-20 border-t border-white/5">
        <div class="grid md:grid-cols-2 gap-12 items-center mb-16">
            <div>
                <h2 class="text-3xl md:text-4xl font-bold mb-6">Let's build something <br> <span
                        class="text-blue-500">extraordinary</span> together.</h2>
                <p class="text-xl text-gray-400 italic mb-8 border-l-4 border-blue-500 pl-4 py-1">
                    "Terus belajar, beradaptasi, dan tumbuh bersama teknologi."
                </p>
                <div class="flex flex-col gap-4">
                    <a href="mailto:jauharfua05@gmail.com"
                        class="bg-white text-black px-6 py-3 rounded-full font-bold hover:bg-gray-200 transition flex items-center gap-3 w-fit">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        jauharfua05@gmail.com
                    </a>
                    <a href="https://instagram.com/jauhar.fauzi_"
                        class="bg-zinc-800 text-white px-6 py-3 rounded-full font-bold hover:bg-zinc-700 transition border border-white/10 flex items-center gap-3 w-fit">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg>
                        @jauhar.fauzi_
                    </a>
                    <a href="https://wa.me/6289529104230"
                        class="bg-green-600 text-white px-6 py-3 rounded-full font-bold hover:bg-green-500 transition border border-white/10 flex items-center gap-3 w-fit">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                        </svg>
                        +62 895-2910-4230
                    </a>
                </div>
            </div>
            <div class="flex justify-center md:justify-end">
                <div class="relative w-full max-w-sm p-8 bg-zinc-900 rounded-3xl border border-white/5 text-center">
                    <p class="text-gray-400 mb-2">Location</p>
                    <h3 class="text-2xl font-bold mb-8">Yogyakarta, Indonesia 🇮🇩</h3>
                    <p class="text-gray-400 mb-2">Open For</p>
                    <h3 class="text-xl font-bold text-white">Freelance & Collaboration</h3>
                </div>
            </div>
        </div>
    </footer>

    <!-- Marquee -->
    <div class="border-y border-white/10 py-8 overflow-hidden bg-black">
        <div
            class="flex gap-8 whitespace-nowrap animate-[marquee_20s_linear_infinite] uppercase font-black text-4xl opacity-50">
            <span>Code + Innovate + Solve • </span>
            <span>Build + Scale + Deploy • </span>
            <span>Code + Innovate + Solve • </span>
            <span class="text-blue-600">Build + Scale + Deploy • </span>
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