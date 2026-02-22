<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNHS | Laboratory Resource Planning</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff', 100: '#dbeafe', 600: '#2563eb', 900: '#1e3a8a',
                        }
                    },
                    backgroundImage: {
                        'grid-slate': 'linear-gradient(to right, #e2e8f0 1px, transparent 1px), linear-gradient(to bottom, #e2e8f0 1px, transparent 1px)',
                    }
                }
            }
        }
    </script>

    <style>
        /* Rich Textures */
        .bg-noise {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.05'/%3E%3C/svg%3E");
        }
        
        .glass-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }

        /* Bento Card Hover Effect */
        .bento-card {
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .bento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        /* Gradient Text */
        .text-glow {
            background: linear-gradient(135deg, #0f172a 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="antialiased text-slate-600 bg-white font-sans selection:bg-brand-600 selection:text-white">

    <div class="bg-slate-900 text-white text-[11px] font-bold uppercase tracking-widest py-2 text-center relative z-50">
        <span class="opacity-70">System Status:</span> <span class="text-emerald-400 ml-2">● All Systems Operational</span>
    </div>

    <nav class="sticky top-0 z-40 glass-nav w-full">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3 cursor-pointer group" onclick="window.scrollTo(0,0)">
                <div class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center shadow-xl group-hover:bg-brand-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                </div>
                <div>
                    <h1 class="font-display font-bold text-xl text-slate-900 leading-none">SNHS</h1>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Laboratory Portal</p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-8 bg-slate-50/50 px-6 py-2 rounded-full border border-slate-200/50">
                <a href="#features" class="text-sm font-semibold text-slate-600 hover:text-brand-600 transition-colors">Platform</a>
                <a href="#roles" class="text-sm font-semibold text-slate-600 hover:text-brand-600 transition-colors">For Teachers</a>
                <a href="#roles" class="text-sm font-semibold text-slate-600 hover:text-brand-600 transition-colors">For Students</a>
            </div>

            <div class="flex items-center gap-4">
                <a href="pages/login.php" class="text-sm font-bold text-slate-900 hover:text-brand-600">Log In</a>
                <a href="pages/login.php" class="hidden sm:inline-flex bg-slate-900 text-white px-5 py-2.5 rounded-lg text-sm font-bold hover:bg-brand-600 transition-all shadow-lg shadow-slate-900/20">
                    Student Portal &rarr;
                </a>
            </div>
        </div>
    </nav>

    <section class="relative pt-24 pb-32 overflow-hidden">
        <div class="absolute inset-0 bg-grid-slate bg-[size:40px_40px] [mask-image:linear-gradient(to_bottom,white,transparent)]"></div>
        <div class="absolute inset-0 bg-noise opacity-40 mix-blend-overlay"></div>
        
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[500px] bg-brand-100/50 rounded-full blur-3xl opacity-60 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-6 relative z-10 text-center">
            
            <div class="inline-flex items-center gap-2 bg-white border border-slate-200 rounded-full px-4 py-1.5 shadow-sm mb-8 animate-fade-in-up">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wide">Academic Year 2024-2025 Ready</span>
            </div>

            <h1 class="font-display font-extrabold text-5xl md:text-7xl text-slate-900 tracking-tight leading-[1.1] mb-8 max-w-4xl mx-auto">
                Manage your Lab <br>
                <span class="text-glow">Like a Modern Tech Company.</span>
            </h1>

            <p class="text-lg md:text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed mb-10">
                Replace paper logbooks with QR code scanning. Track inventory in real-time. Automate breakage reports. The all-in-one OS for the SNHS Science Department.
            </p>

            <div class="flex flex-col sm:flex-row justify-center gap-4 mb-20">
                <a href="pages/login.php" class="bg-brand-600 text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-brand-900 transition-all shadow-xl shadow-brand-600/20 hover:shadow-2xl hover:-translate-y-1">
                    Launch Application
                </a>
                <a href="#features" class="bg-white text-slate-700 border border-slate-200 px-8 py-4 rounded-xl font-bold text-lg hover:border-slate-400 transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    See How It Works
                </a>
            </div>

            <div class="relative max-w-5xl mx-auto">
                <div class="absolute -inset-1 bg-gradient-to-b from-brand-600 to-transparent opacity-20 blur-2xl rounded-[2rem]"></div>
                <div class="relative bg-slate-900 rounded-[1.5rem] p-2 shadow-2xl border border-slate-800">
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80" alt="Dashboard" class="rounded-2xl opacity-90 border border-white/10 w-full h-auto">
                    
                    <div class="absolute top-12 -left-8 md:-left-12 bg-white p-4 rounded-xl shadow-xl border border-slate-100 animate-bounce-slow hidden md:block">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase">Transaction</p>
                                <p class="text-sm font-bold text-slate-900">Items Returned</p>
                            </div>
                        </div>
                    </div>

                    <div class="absolute bottom-12 -right-8 md:-right-12 bg-white p-4 rounded-xl shadow-xl border border-slate-100 hidden md:block">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase">Scanned</p>
                                <p class="text-sm font-bold text-slate-900">Student ID Verified</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-900 py-12 border-y border-slate-800">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center md:text-left">
            <div>
                <p class="text-4xl font-display font-bold text-white mb-1">1,400+</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Active Inventory</p>
            </div>
            <div>
                <p class="text-4xl font-display font-bold text-white mb-1">Zero</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Paper Forms</p>
            </div>
            <div>
                <p class="text-4xl font-display font-bold text-white mb-1">15s</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Avg. Checkout Time</p>
            </div>
            <div>
                <p class="text-4xl font-display font-bold text-white mb-1">100%</p>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Audit Accuracy</p>
            </div>
        </div>
    </section>

    <section id="features" class="py-32 bg-slate-50 relative">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="font-display font-bold text-3xl text-slate-900 mb-4">Complete Laboratory OS</h2>
                <p class="text-slate-500">Everything needed to run a high-volume science department.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 grid-rows-2 gap-6 h-auto md:h-[600px]">
                
                <div class="md:col-span-2 md:row-span-2 bg-white rounded-3xl p-8 bento-card relative overflow-hidden group">
                    <div class="relative z-10 h-full flex flex-col">
                        <div class="mb-auto">
                            <span class="bg-blue-100 text-blue-700 text-xs font-bold px-3 py-1 rounded-full uppercase">Flagship Feature</span>
                            <h3 class="font-display font-bold text-3xl text-slate-900 mt-4 mb-2">The Handover Terminal</h3>
                            <p class="text-slate-500 max-w-md">A POS-style interface for Teachers. Process borrowing requests, check queues, and report damages with a single click. No typing required.</p>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-slate-700"><span class="w-2 h-2 rounded-full bg-green-500"></span> Live Queue</div>
                            <div class="flex items-center gap-2 text-sm font-bold text-slate-700"><span class="w-2 h-2 rounded-full bg-blue-500"></span> QR Scanner</div>
                        </div>
                    </div>
                    <img src="https://images.unsplash.com/photo-1581093588401-fbb073d7816c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                         class="absolute right-0 bottom-0 w-1/2 h-3/4 object-cover rounded-tl-3xl border-t-4 border-l-4 border-slate-50 group-hover:scale-105 transition-transform duration-500">
                </div>

                <div class="bg-white rounded-3xl p-8 bento-card relative group overflow-hidden">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl text-slate-900 mb-2">Smart Inventory</h3>
                    <p class="text-sm text-slate-500">Stock levels auto-update. Low stock alerts trigger automatically.</p>
                </div>

                <div class="bg-white rounded-3xl p-8 bento-card relative group overflow-hidden">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center mb-4 group-hover:bg-rose-600 group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl text-slate-900 mb-2">Damage Logs</h3>
                    <p class="text-sm text-slate-500">Upload photos of broken items. Fine students directly through the portal.</p>
                </div>

            </div>
        </div>
    </section>

    <section id="roles" class="py-24 bg-white" x-data="{ role: 'teachers' }">
        <div class="max-w-4xl mx-auto px-6">
            <div class="flex flex-col items-center mb-16">
                <h2 class="font-display font-bold text-3xl text-slate-900 mb-8">Tailored Workflows</h2>
                
                <div class="bg-slate-100 p-1.5 rounded-full inline-flex relative">
                    <div class="absolute h-[calc(100%-12px)] top-[6px] transition-all duration-300 bg-white rounded-full shadow-sm w-[calc(50%-6px)]"
                         :class="role === 'teachers' ? 'left-[6px]' : 'left-[calc(50%)]'"></div>
                    
                    <button @click="role = 'teachers'" class="relative z-10 px-8 py-2 text-sm font-bold transition-colors w-40 rounded-full"
                            :class="role === 'teachers' ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'">Teachers</button>
                    
                    <button @click="role = 'students'" class="relative z-10 px-8 py-2 text-sm font-bold transition-colors w-40 rounded-full"
                            :class="role === 'students' ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'">Students</button>
                </div>
            </div>

            <div class="bg-slate-50 rounded-[2.5rem] p-8 md:p-12 border border-slate-200 shadow-sm relative overflow-hidden transition-all duration-300" x-show="role === 'teachers'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="font-display font-bold text-2xl text-slate-900 mb-4">Total Lab Oversight</h3>
                        <p class="text-slate-600 mb-6 leading-relaxed">Stop chasing paper slips. See exactly which student has which beaker in real-time. Manage activities and class sections from one dashboard.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> Bulk Approve Requests
                            </li>
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> Export Broken Item Reports
                            </li>
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> Class History Logs
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <img src="https://cdn-icons-png.flaticon.com/512/3429/3429149.png" class="w-32 mx-auto mb-6 opacity-90 drop-shadow-xl" alt="Teacher">
                        <div class="bg-white p-4 rounded-xl shadow-lg border border-slate-100 text-center">
                            <p class="text-xs font-bold text-slate-400 uppercase">Productivity</p>
                            <p class="text-2xl font-black text-brand-600">+40%</p>
                            <p class="text-xs text-slate-500">More time teaching</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 rounded-[2.5rem] p-8 md:p-12 border border-slate-200 shadow-sm relative overflow-hidden transition-all duration-300" x-show="role === 'students'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" style="display:none">
                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="font-display font-bold text-2xl text-slate-900 mb-4">Your Pocket Lab Assistant</h3>
                        <p class="text-slate-600 mb-6 leading-relaxed">No more "I forgot the manual." Access experiment PDFs on your phone while you work. Check your borrowing history and pay fines transparently.</p>
                        <ul class="space-y-3">
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> Digital Receipts
                            </li>
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> View Lab Manuals on Mobile
                            </li>
                            <li class="flex items-center gap-3 text-sm font-bold text-slate-700">
                                <span class="text-brand-600">✓</span> Track Borrowing
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <img src="https://cdn-icons-png.flaticon.com/512/2995/2995459.png" class="w-32 mx-auto mb-6 opacity-90 drop-shadow-xl" alt="Student">
                        <div class="bg-white p-4 rounded-xl shadow-lg border border-slate-100 text-center">
                            <p class="text-xs font-bold text-slate-400 uppercase">Accessibility</p>
                            <p class="text-2xl font-black text-brand-600">100%</p>
                            <p class="text-xs text-slate-500">Digital Access</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <footer class="bg-slate-900 text-slate-400 py-20 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-12 gap-12">
            
            <div class="md:col-span-4 space-y-4">
                <div class="flex items-center gap-2 text-white">
                    <div class="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center font-bold">S</div>
                    <span class="font-display font-bold text-2xl">SNHS</span>
                </div>
                <p class="text-sm leading-relaxed max-w-xs">
                    Surigao del Norte National High School<br>
                    Department of Science & Technology.<br>
                    Empowering students through digital innovation.
                </p>
                <div class="flex gap-4 pt-4">
                    <div class="w-8 h-8 bg-slate-800 rounded-full hover:bg-white transition-colors cursor-pointer"></div>
                    <div class="w-8 h-8 bg-slate-800 rounded-full hover:bg-white transition-colors cursor-pointer"></div>
                </div>
            </div>

            <div class="md:col-span-2">
                <h4 class="text-white font-bold mb-6">Platform</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="#" class="hover:text-white transition-colors">Student Login</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Teacher Login</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Admin Console</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">System Status</a></li>
                </ul>
            </div>

            <div class="md:col-span-2">
                <h4 class="text-white font-bold mb-6">Resources</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="#" class="hover:text-white transition-colors">Lab Rules</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Safety Manual</a></li>
                    <li><a href="#" class="hover:text-white transition-colors">Report Incident</a></li>
                </ul>
            </div>

            <div class="md:col-span-4 bg-slate-800 rounded-2xl p-6">
                <h4 class="text-white font-bold mb-2">Need Help?</h4>
                <p class="text-xs mb-4">Contact the Laboratory Custodian directly.</p>
                <a href="mailto:support@snhs.edu" class="flex items-center justify-between bg-slate-700 hover:bg-slate-600 transition-colors p-3 rounded-xl text-white text-sm font-bold">
                    <span>Contact Support</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 mt-20 pt-8 border-t border-slate-800 flex flex-col md:flex-row justify-between items-center text-xs font-bold uppercase tracking-widest">
            <p>&copy; 2024 SNHS Science Dept.</p>
            <p class="mt-4 md:mt-0">Built with PHP & Tailwind</p>
        </div>
    </footer>

</body>
</html>