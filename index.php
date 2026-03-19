<?php
session_start();

// --- CONFIGURATION & IMPORTS ---
// Adjust these paths if your folder structure changes
require_once __DIR__ . '/dbRelated/operation.php';

// Safe include for EmailSender (prevents crash if file is missing during dev)
if (file_exists('dbRelated/EmailSender.php')) {
    require_once 'dbRelated/EmailSender.php';
}

// --- INITIALIZATION ---
$showModal = false; 
$step = 1; 

// Steps Definition:
// 1: Identity Check
// 2: Login Password (Existing User)
// 3: Register - Input Email (New User)
// 4: Register - Verify OTP
// 5: Register - Set Password

// --- HANDLE RESET / LOGOUT ---
// Resets the login/registration flow if user clicks "Switch Account" or "Cancel"
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// --- INITIALIZATION ---
$initialState = ['step' => 1, 'showModal' => false, 'data' => []];
try {
    $dataMgr = new DataManager();
    $status = "✅ System Pulse: Online";

    // --- STATE MANAGEMENT (Determine Step based on Session) ---
    if (isset($_SESSION['login_id']) && !isset($_SESSION['user_id'])) {
        $initialState['step'] = 2;
        $initialState['showModal'] = true;
        $initialState['data']['user_name'] = $_SESSION['temp_name'] ?? 'User';
    } elseif (isset($_SESSION['temp_id'])) {
        if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) $initialState['step'] = 5;
        elseif (isset($_SESSION['current_otp'])) $initialState['step'] = 4;
        else $initialState['step'] = 3;
        
        $initialState['showModal'] = true;
        $initialState['data']['user_name'] = $_SESSION['temp_name'] ?? 'User';
    }

    if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
        $_SESSION['toast_message'] = ['text' => "Account created successfully! You may now log in.", 'type' => 'success'];
        $initialState['showModal'] = true;
    }
} catch (Exception $e) {
    $status = "❌ System Pulse: Offline";
}

$initialStateJSON = json_encode($initialState);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU – CSM LabFlow | Modern Lab Management</title>
    <!-- OFFLINE TAILWIND -->
    <link href="HTML_Demo/css/output.css" rel="stylesheet">

    <!-- ONLINE TAILWIND (CDN) - Fallback -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        crimson: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            DEFAULT: '#dc143c',
                        },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <!-- PWA Manifest & Theme Color -->
    <link rel="manifest" href="/LabFlow/manifest.json">
    <meta name="theme-color" content="#dc143c"/>
    <link rel="icon" href="HTML_Demo/img/labflow.jpg">
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Modern Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        ::selection { background: #dc143c; color: white; }

        .bg-crimson-gradient {
            background: linear-gradient(135deg, #ff8c00 0%, #dc143c 100%);
        }
        
        .text-crimson { color: #dc143c; }
        .bg-crimson { background-color: #dc143c; }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(220, 20, 60, 0.1);
        }

        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .hover-lift:hover {
            transform: translateY(-8px);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .float-animation { animation: float 6s ease-in-out infinite; }
        .section-title { position: relative; display: inline-block; padding-bottom: 12px; }
        .section-title::after { content: ''; position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); width: 40px; height: 3px; background: #ff8c00; border-radius: 2px; }

        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
        
        .bg-grid-pattern {
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 32px 32px;
        }
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>
</head>
<body class="bg-[#FFF9F8] text-slate-900 overflow-x-hidden bg-grid-pattern">

    <div class="fixed top-0 left-0 w-full z-50 flex justify-center pt-4 px-4 pointer-events-none">
        <nav class="w-full max-w-7xl bg-white/70 backdrop-blur-xl border border-white/60 shadow-lg shadow-slate-200/20 rounded-[2rem] md:rounded-full px-4 md:px-6 py-3 flex flex-col md:flex-row justify-between items-center transition-all hover:bg-white/90 pointer-events-auto relative">
            <div class="w-full md:w-auto flex justify-between items-center">
                <div class="flex items-center gap-2 group cursor-pointer">
                    <div class="flex -space-x-3 group-hover:scale-105 transition-transform">
                        <img src="HTML_Demo/img/wmsu.png" alt="Logo 1" class="w-10 h-10 md:w-12 md:h-12 rounded-full border-2 border-white shadow-md z-30">
                        <img src="HTML_Demo/img/csm.jpg" alt="Logo 2" class="w-10 h-10 md:w-12 md:h-12 rounded-full border-2 border-white shadow-md z-20">
                        <img src="HTML_Demo/img/labflow.jpg" alt="Logo 3" class="w-10 h-10 md:w-12 md:h-12 rounded-full border-2 border-white shadow-md z-10">
                    </div>
                    <span class="font-extrabold text-lg md:text-2xl tracking-tight bg-clip-text text-transparent bg-crimson-gradient">LabFlow</span>
                </div>
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 text-slate-600 hover:text-crimson transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            
            <div id="nav-menu" class="hidden md:flex flex-col md:flex-row gap-4 md:gap-10 font-semibold text-slate-600 w-full md:w-auto mt-4 md:mt-0 items-center">
                <a href="#about" class="hover:text-crimson transition-colors">About</a>
                <a href="#how-it-works" class="hover:text-crimson transition-colors">Workflow</a>
                <a href="#why-choose" class="hover:text-crimson transition-colors">Benefits</a>
                <a href="#install-pwa" class="hover:text-crimson transition-colors">Install App</a>
                <button onclick="toggleModal(true)" class="bg-crimson text-white px-6 py-2.5 rounded-full font-bold hover:shadow-lg hover:shadow-crimson/30 hover:-translate-y-0.5 transition-all active:scale-95 active:translate-y-0 w-full md:w-auto">
                    Get Started
                </button>
            </div>
        </nav>
    </div>

    <section class="relative min-h-screen flex items-center overflow-hidden pt-24 md:pt-32 pb-20">
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-orange-100 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-96 h-96 bg-crimson/10 rounded-full blur-3xl opacity-50"></div>

        <div class="max-w-7xl mx-auto px-6 grid lg:grid-cols-2 gap-16 items-center relative">
            <div class="animate__animated animate__fadeInUp">
                <h1 class="text-3xl md:text-6xl lg:text-8xl font-extrabold leading-tight mb-6">
                    Smarter. Faster.<br>
                    <span class="bg-clip-text text-transparent bg-crimson-gradient">Accountable.</span>
                </h1>
                <p class="text-xl text-slate-600 mb-10 leading-relaxed max-w-lg">
                    A digital solution designed to simplify, track, and secure laboratory apparatus borrowing for the College of Science and Mathematics.
                </p>
                <div class="flex flex-wrap gap-4">
                    <button onclick="toggleModal(true)" class="bg-crimson-gradient text-white px-6 py-3 md:px-10 md:py-4 rounded-full font-black text-lg shadow-xl shadow-crimson/20 hover:shadow-2xl hover:shadow-crimson/40 hover:-translate-y-1 transition-all">
                        Get Started
                    </button>
                    <a href="#about" class="bg-white border border-slate-200 text-slate-700 px-6 py-3 md:px-10 md:py-4 rounded-full font-bold text-lg hover:border-orange-200 hover:bg-orange-50 hover:-translate-y-1 transition-all shadow-sm">
                        Learn More
                    </a>
                </div>
            </div>

            <div class="relative animate__animated animate__zoomIn">
                <div class="relative z-10">
                    <img src="HTML_Demo/img/testhero.png" alt="Lab Work" class="rounded-[2.5rem] shadow-2xl border-4 md:border-8 border-white">
                    <div class="absolute -bottom-10 -left-10 glass-card p-6 rounded-2xl shadow-xl animate__animated animate__fadeInUp animate__delay-1s">
                        <p class="text-crimson font-black text-3xl">100%</p>
                        <p class="text-slate-500 font-bold text-sm uppercase">QR Accountability</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="py-20 md:py-32 bg-white/50 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div class="space-y-6">
                    <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 leading-tight">
                        Transforming manual processes into <span class="text-crimson">seamless digital workflows.</span>
                    </h2>
                    <p class="text-lg text-slate-600">
                        The WMSU CSM LabFlow System is a structured, QR-powered laboratory borrowing platform that ensures efficiency, transparency, and accountability in every transaction.
                    </p>
                    <div class="p-6 bg-white border-l-4 border-crimson rounded-r-2xl shadow-sm italic text-slate-700">
                        "Promoting responsibility, integrity, and technological advancement in academic laboratory management."
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative h-64 bg-crimson-gradient rounded-[2rem] p-8 text-white flex flex-col justify-end overflow-hidden group shadow-2xl shadow-crimson/20">
                        <svg class="absolute inset-0 w-full h-full opacity-30 text-red-900 mix-blend-multiply group-hover:scale-110 transition-transform duration-700" viewBox="0 0 24 24" fill="currentColor" preserveAspectRatio="xMidYMid slice">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 1v2h2V5H5zm8-1a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V4zm2 1v2h2V5h-2zM3 16a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H4a1 1 0 01-1-1v-4zm2 1v2h2v-2H5zm10-1a1 1 0 011-1h.01a1 1 0 110 2H16a1 1 0 01-1-1zm2 3a1 1 0 011-1h.01a1 1 0 110 2H18a1 1 0 01-1-1zm-4-3a1 1 0 011-1h.01a1 1 0 110 2H14a1 1 0 01-1-1zm2 3a1 1 0 011-1h.01a1 1 0 110 2H16a1 1 0 01-1-1zm-2-5a1 1 0 011-1h.01a1 1 0 110 2H14a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                        <div class="relative z-10">
                            <span class="text-4xl font-black">QR</span>
                            <p class="font-bold opacity-80">Technology Driven</p>
                        </div>
                    </div>
                    <div class="relative h-64 bg-slate-900 rounded-[2rem] p-8 text-white flex flex-col justify-end overflow-hidden group shadow-2xl shadow-slate-900/20">
                        <svg class="absolute inset-0 w-full h-full opacity-30 text-slate-500 group-hover:scale-110 transition-transform duration-700" viewBox="0 0 24 24" fill="currentColor" preserveAspectRatio="xMidYMid slice">
                            <path fill-rule="evenodd" d="M4 6a2 2 0 012-2h2a2 2 0 002-2h4a2 2 0 002 2h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6z M12 18a4 4 0 100-8 4 4 0 000 8z M12 11.5a.5.5 0 01.5.5v2l1.5.9a.5.5 0 01-.5.8l-1.8-1a.5.5 0 01-.2-.4v-2.8a.5.5 0 01.5-.5z" clip-rule="evenodd" />
                        </svg>
                        <div class="relative z-10">
                            <span class="text-4xl font-black">24/7</span>
                            <p class="font-bold opacity-80">Transparency</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<section id="how-it-works" class="py-24 px-6 relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[500px] bg-gradient-to-r from-orange-50/50 via-white/0 to-crimson-50/50 -z-10 rounded-full blur-3xl opacity-60"></div>

        <div class="max-w-7xl mx-auto text-center">
            <div class="mb-16">
                <span class="text-crimson font-bold tracking-wider uppercase text-xs mb-2 block">Workflow</span>
                <h2 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-6">How It <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-crimson">Works</span></h2>
                <p class="text-slate-500 max-w-2xl mx-auto text-lg">A step-by-step guide to the digital borrowing process, ensuring clarity and accountability for every transaction.</p>
            </div>
            
            <!-- Tabs Navigation -->
            <div class="flex justify-center mb-16 overflow-x-auto py-4">
                <div class="bg-slate-100 p-1.5 rounded-full inline-flex relative min-w-max">
                    <div id="tab-indicator" class="absolute h-[calc(100%-12px)] top-[6px] transition-all duration-300 bg-crimson rounded-full shadow-md w-[calc(20%-4px)] left-[6px]"></div>
                    
                    <button onclick="switchTab('account-setup')" id="btn-account-setup" class="tab-btn relative z-10 px-2 md:px-6 py-2 md:py-3 text-[10px] md:text-sm font-bold transition-colors w-28 md:w-48 rounded-full text-white flex items-center justify-center gap-1 md:gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                        Account Setup
                    </button>

                    <button onclick="switchTab('group')" id="btn-group" class="tab-btn relative z-10 px-2 md:px-6 py-2 md:py-3 text-[10px] md:text-sm font-bold transition-colors w-28 md:w-48 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1 md:gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Group Activity
                    </button>
                    
                    <button onclick="switchTab('individual')" id="btn-individual" class="tab-btn relative z-10 px-2 md:px-6 py-2 md:py-3 text-[10px] md:text-sm font-bold transition-colors w-28 md:w-48 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1 md:gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Individual
                    </button>
                    
                    <button onclick="switchTab('no-activity')" id="btn-no-activity" class="tab-btn relative z-10 px-2 md:px-6 py-2 md:py-3 text-[10px] md:text-sm font-bold transition-colors w-28 md:w-48 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1 md:gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        Direct Borrow
                    </button>

                    <button onclick="switchTab('damage')" id="btn-damage" class="tab-btn relative z-10 px-2 md:px-6 py-2 md:py-3 text-[10px] md:text-sm font-bold transition-colors w-28 md:w-48 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-1 md:gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Damage Return
                    </button>
                </div>
            </div>

            <!-- Tab Contents -->
            <div id="tab-contents" class="relative min-h-[400px]">
                
                <!-- Tab 1: Account Setup (Default) -->
                <div id="content-account-setup" class="tab-content transition-opacity duration-500">
                    <div class="relative">
                        <!-- Central Line (Desktop) -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-1.5 bg-gradient-to-r from-orange-200 via-crimson-200 to-orange-200 -translate-y-1/2 rounded-full z-0 opacity-30"></div>

                        <!-- Vertical Line (Mobile) -->
                        <div class="lg:hidden absolute left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-100 via-crimson-100 to-orange-100 -translate-x-1/2 rounded-full z-0"></div>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-8 lg:gap-0 lg:h-[450px]">
                            
                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/><path d="M12 8h6"/><path d="M12 13h6"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Class Creation</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Teacher creates a class for their respective lab advisory.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">                              
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Registration</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Teacher registers students via MISTO Class List (Individual or CSV).</p>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                         <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Student Login</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Student clicks login and inputs their Student ID.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Email Verification</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Student inputs WMSU email and verifies via OTP.</p>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Account Secured</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Student creates a password and setup is done.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Group Activity -->
                <div id="content-group" class="tab-content hidden transition-opacity duration-500">
                    <div class="relative">
                        <!-- Central Line (Desktop) -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-1.5 bg-gradient-to-r from-orange-200 via-crimson-200 to-orange-200 -translate-y-1/2 rounded-full z-0 opacity-30"></div>

                        <!-- Vertical Line (Mobile) -->
                        <div class="lg:hidden absolute left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-100 via-crimson-100 to-orange-100 -translate-x-1/2 rounded-full z-0"></div>

                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-8 lg:gap-0 lg:h-[450px]">
                            
                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                        <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="18" r="1"/><path d="M9 6h6M9 10h6M9 14h2"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800 mb-2">Activity Setup</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Faculty create lab activities and select required apparatus.</p>
                        </div>
                        <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                            <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                            <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                        </div>
                    </div>

                    <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                        <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                            <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                            <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                        </div>
                        <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                            <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">                              
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800 mb-2">Group Accountability</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Leaders assign borrowed items digitally to members.</p>
                        </div>
                    </div>

                    <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                        <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                 <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M12 11h4M12 15h4M8 11h.01M8 15h.01"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800 mb-2">Digital Reservation</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Students pre-order equipment eliminating confusion.</p>
                        </div>
                        <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                            <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                            <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                        </div>
                    </div>

                    <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                        <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                            <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                            <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                        </div>
                        <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                            <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M7 7h.01M17 7h.01M17 17h.01M7 17h.01"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800 mb-2">QR Code Release</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Each approved request generates a unique QR code.</p>
                        </div>
                    </div>

                    <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                        <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <h3 class="font-bold text-slate-800 mb-2">Secure Return</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Technicians scan items on return, logging damages.</p>
                        </div>
                        <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                            <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                            <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Individual Activity -->
                <div id="content-individual" class="tab-content hidden transition-opacity duration-500">
                    <div class="relative">
                        <!-- Central Line (Desktop) -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-1.5 bg-gradient-to-r from-orange-200 via-crimson-200 to-orange-200 -translate-y-1/2 rounded-full z-0 opacity-30"></div>

                        <!-- Vertical Line (Mobile) -->
                        <div class="lg:hidden absolute left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-100 via-crimson-100 to-orange-100 -translate-x-1/2 rounded-full z-0"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-0 lg:h-[450px]">
                            
                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="18" r="1"/><path d="M9 6h6M9 10h6M9 14h2"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Activity Setup</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Faculty create lab activities and select required apparatus.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M12 11h4M12 15h4M8 11h.01M8 15h.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Digital Reservation</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Students pre-order equipment eliminating confusion.</p>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M7 7h.01M17 7h.01M17 17h.01M7 17h.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">QR Code Release</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Each approved request generates a unique QR code.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Secure Return</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Technicians scan items on return, logging damages.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Tab 4: Without Activity -->
                <div id="content-no-activity" class="tab-content hidden transition-opacity duration-500">
                    <div class="relative">
                        <!-- Central Line (Desktop) -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-1.5 bg-gradient-to-r from-orange-200 via-crimson-200 to-orange-200 -translate-y-1/2 rounded-full z-0 opacity-30"></div>

                        <!-- Vertical Line (Mobile) -->
                        <div class="lg:hidden absolute left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-100 via-crimson-100 to-orange-100 -translate-x-1/2 rounded-full z-0"></div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-0 lg:h-[450px]">
                            
                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Shop & Checkout</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Browse inventory, select items, and checkout digitally.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="3" height="3" rx="1"/><rect x="14" y="7" width="3" height="3" rx="1"/><rect x="7" y="14" width="3" height="3" rx="1"/><path d="M14 17h3"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Approval Scan</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Present receipt QR to technician for approval and release.</p>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/><path d="M12 15h.01"/><path d="M16 15h.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Return & Log</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Scan items upon return to log condition and damages.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Tab 5: Damage Return Workflow -->
                <div id="content-damage" class="tab-content hidden transition-opacity duration-500">
                    <div class="relative">
                        <!-- Central Line (Desktop) -->
                        <div class="hidden lg:block absolute top-1/2 left-0 w-full h-1.5 bg-gradient-to-r from-orange-200 via-crimson-200 to-orange-200 -translate-y-1/2 rounded-full z-0 opacity-30"></div>

                        <!-- Vertical Line (Mobile) -->
                        <div class="lg:hidden absolute left-1/2 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-100 via-crimson-100 to-orange-100 -translate-x-1/2 rounded-full z-0"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-0 lg:h-[450px]">
                            
                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Inspect & Log</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Technician flags item as damaged/lost and captures photo evidence.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Settlement Option</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Student chooses to pay the fee or provide a replacement item.</p>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-start lg:h-[50%] lg:self-start group">
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-orange-100/50 border border-orange-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mb-6 lg:mb-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-orange-600 mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Proof & Receipt</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Technician uploads proof of settlement and issues a transaction receipt.</p>
                                </div>
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-start pt-4">
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-orange-400 shadow-md relative z-10"></div>
                                    <div class="w-0.5 h-full bg-gradient-to-b from-orange-200 to-transparent border-l border-dashed border-orange-300/50"></div>
                                </div>
                            </div>

                            <div class="relative flex flex-col items-center lg:justify-end lg:h-[50%] lg:self-end group">
                                <div class="hidden lg:flex flex-col items-center flex-grow w-full justify-end pb-4">
                                    <div class="w-0.5 h-full bg-gradient-to-t from-crimson-200 to-transparent border-l border-dashed border-crimson-300/50"></div>
                                    <div class="w-4 h-4 bg-white rounded-full border-4 border-crimson shadow-md relative z-10"></div>
                                </div>
                                <div class="bg-white p-6 rounded-[2rem] shadow-lg shadow-crimson-100/50 border border-crimson-50 hover:shadow-xl hover:shadow-crimson/10 transition-all duration-300 hover:-translate-y-2 relative z-10 w-full text-center mt-6 lg:mt-0 max-w-xs mx-auto">
                                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-crimson mx-auto mb-4 shadow-inner">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2">Record Cleared</h3>
                                    <p class="text-xs text-slate-500 leading-relaxed">Student liability is cleared and inventory is reconciled.</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section id="why-choose" class="py-24 px-6 relative overflow-hidden">
        <!-- Decorative background elements -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute top-20 left-10 w-64 h-64 bg-orange-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob"></div>
            <div class="absolute top-20 right-10 w-64 h-64 bg-red-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-2000"></div>
            <div class="absolute -bottom-32 left-1/2 w-64 h-64 bg-pink-50 rounded-full mix-blend-multiply filter blur-3xl opacity-70 animate-blob animation-delay-4000"></div>
        </div>

        <div class="max-w-7xl mx-auto relative z-10">
            <div class="text-center mb-20">
                <span class="text-crimson font-bold tracking-wider uppercase text-xs mb-2 block">System Advantages</span>
                <h2 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-6">Why Choose <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-crimson">LabFlow?</span></h2>
                <p class="text-slate-500 max-w-2xl mx-auto text-lg">Built specifically for the academic needs of WMSU, replacing outdated manual logs with a secure, digital ecosystem.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Card 1: Transparent Records (Short Text -> Small) -->
                <div class="group bg-white p-8 rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-blue-500/10 hover:-translate-y-2 transition-all duration-300 relative overflow-hidden w-full">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-50 to-blue-100 rounded-bl-[100%] -mr-8 -mt-8 transition-transform group-hover:scale-110 opacity-50"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-blue-200 mb-6 group-hover:scale-110 transition-transform duration-300 relative z-10">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-xl mb-3">Transparent Records</h3>
                    <p class="text-slate-500 leading-relaxed">Complete digital audit trails for every transaction, ensuring no item goes unaccounted for.</p>
                </div>

                <!-- Card 2: Organized Workflow (Long Text -> Large) -->
                <div class="group bg-white p-8 rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-orange-500/10 hover:-translate-y-2 transition-all duration-300 relative overflow-hidden w-full lg:col-span-2">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-orange-50 to-orange-100 rounded-bl-[100%] -mr-8 -mt-8 transition-transform group-hover:scale-110 opacity-50"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-orange-200 mb-6 group-hover:scale-110 transition-transform duration-300 relative z-10">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-xl mb-3">Organized Workflow</h3>
                    <p class="text-slate-500 leading-relaxed">Streamlined borrowing and returning processes that reduce queue times and administrative bottlenecks.</p>
                </div>

                <!-- Card 3: Group Accountability (Long Text -> Large) -->
                <div class="group bg-white p-8 rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-purple-500/10 hover:-translate-y-2 transition-all duration-300 relative overflow-hidden w-full lg:col-span-2">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-purple-50 to-purple-100 rounded-bl-[100%] -mr-8 -mt-8 transition-transform group-hover:scale-110 opacity-50"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-purple-200 mb-6 group-hover:scale-110 transition-transform duration-300 relative z-10">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-xl mb-3">Group Accountability</h3>
                    <p class="text-slate-500 leading-relaxed">Shared responsibility tracking for team activities, making it easy to manage group projects and liability.</p>
                </div>

                <!-- Card 4: QR Verification (Short Text -> Small) -->
                <div class="group bg-white p-8 rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-crimson/10 hover:-translate-y-2 transition-all duration-300 relative overflow-hidden w-full">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-red-50 to-red-100 rounded-bl-[100%] -mr-8 -mt-8 transition-transform group-hover:scale-110 opacity-50"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-[#dc143c] to-red-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-red-200 mb-6 group-hover:scale-110 transition-transform duration-300 relative z-10">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M7 7h.01M17 7h.01M17 17h.01M7 17h.01"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-xl mb-3">QR Verification</h3>
                    <p class="text-slate-500 leading-relaxed">Instant identification and secure equipment release using unique QR codes for every transaction.</p>
                </div>

                <!-- Card 5 -->
                <div class="group bg-white p-8 rounded-[2rem] border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-emerald-500/10 hover:-translate-y-2 transition-all duration-300 relative overflow-hidden w-full md:col-span-2 lg:col-span-3">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-bl-[100%] -mr-8 -mt-8 transition-transform group-hover:scale-110 opacity-50"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-emerald-200 mb-6 group-hover:scale-110 transition-transform duration-300 relative z-10">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-xl mb-3">Real-Time Updates</h3>
                    <p class="text-slate-500 leading-relaxed">Live inventory status and instant notifications keep students and faculty informed about equipment availability.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="install-pwa" class="py-24 px-6 bg-gradient-to-b from-slate-50 to-white relative overflow-hidden border-t border-slate-100">
        <!-- Decorative Elements -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute top-1/4 -left-20 w-96 h-96 bg-orange-100/50 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-crimson/5 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="order-2 lg:order-1">
                    <span class="text-crimson font-bold tracking-wider uppercase text-xs mb-2 block">Go Mobile</span>
                    <h2 class="text-3xl md:text-5xl font-extrabold text-slate-900 mb-6">Install <span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-500 to-crimson">LabFlow</span> App</h2>
                    <p class="text-slate-500 text-lg mb-8 leading-relaxed">
                        LabFlow works seamlessly in your web browser. For a more integrated experience, you have the <strong>option to install it</strong> as a Progressive Web App (PWA) directly to your device, no app store required.
                    </p>
                    
                    <div class="space-y-6 mb-10">
                        <div class="flex items-start gap-4 p-4 rounded-2xl hover:bg-white hover:shadow-lg hover:shadow-orange-500/5 transition-all duration-300 border border-transparent hover:border-orange-100">
                            <div class="w-12 h-12 rounded-2xl bg-orange-100 flex items-center justify-center text-orange-600 flex-shrink-0 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-lg">Cross-Platform Compatibility</h4>
                                <p class="text-slate-500 text-sm">Install on Android, iOS, Windows, or macOS. One consistent experience everywhere.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 rounded-2xl hover:bg-white hover:shadow-lg hover:shadow-crimson/5 transition-all duration-300 border border-transparent hover:border-crimson/10">
                            <div class="w-12 h-12 rounded-2xl bg-crimson/10 flex items-center justify-center text-crimson flex-shrink-0 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-lg">Lightning Fast Performance</h4>
                                <p class="text-slate-500 text-sm">Optimized caching ensures the system loads instantly, even on slower networks.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 p-4 rounded-2xl hover:bg-white hover:shadow-lg hover:shadow-blue-500/5 transition-all duration-300 border border-transparent hover:border-blue-100">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600 flex-shrink-0 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900 text-lg">No App Store Required</h4>
                                <p class="text-slate-500 text-sm">Bypass downloads and updates. Just add to your home screen and you're ready.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="order-1 lg:order-2 relative">
                    <div class="absolute inset-0 bg-gradient-to-tr from-orange-200 to-crimson/20 rounded-[3rem] transform rotate-3 scale-95 -z-10 blur-sm"></div>
                    <div class="bg-white/80 backdrop-blur-xl rounded-[2.5rem] p-8 shadow-2xl border border-white/50 relative overflow-hidden">
                        <div class="text-center">
                            <h3 class="font-bold text-slate-900 mb-2 text-2xl">Get LabFlow</h3>
                            <p class="text-slate-500 text-sm mb-8">Select your device to begin installation.</p>
                            
                            <div class="grid grid-cols-3 md:grid-cols-5 gap-2 mb-8">
                                <button onclick="selectDevice('windows')" id="dev-windows" class="device-btn p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 hover:bg-orange-50 transition-all flex flex-col items-center gap-2 group">
                                    <svg class="w-8 h-8 text-slate-400 group-hover:text-orange-500 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/></svg>
                                    <span class="text-xs font-bold text-slate-600">Windows</span>
                                </button>
                                <button onclick="selectDevice('macos')" id="dev-macos" class="device-btn p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 hover:bg-orange-50 transition-all flex flex-col items-center gap-2 group">
                                    <svg class="w-8 h-8 text-slate-400 group-hover:text-orange-500 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.21-1.98 1.08-3.11-1.06.05-2.31.71-3.06 1.61-.69.87-1.23 2.1-1.09 3.11 1.19.09 2.4-.64 3.07-1.61z"/></svg>
                                    <span class="text-xs font-bold text-slate-600">macOS</span>
                                </button>
                                <button onclick="selectDevice('linux')" id="dev-linux" class="device-btn p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 hover:bg-orange-50 transition-all flex flex-col items-center gap-2 group">
                                    <svg class="w-8 h-8 text-slate-400 group-hover:text-orange-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 17l6-6-6-6m8 14h8"></path></svg>
                                    <span class="text-xs font-bold text-slate-600">Linux</span>
                                </button>
                                <button onclick="selectDevice('android')" id="dev-android" class="device-btn p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 hover:bg-orange-50 transition-all flex flex-col items-center gap-2 group">
                                    <svg class="w-8 h-8 text-slate-400 group-hover:text-orange-500 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M17.523 15.3414c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993.0001.5511-.4482.9997-.9993.9997m-11.046 0c-.5511 0-.9993-.4486-.9993-.9997s.4482-.9993.9993-.9993c.5511 0 .9993.4482.9993.9993 0 .5511-.4482.9997-.9993.9997m11.4045-6.02l1.9973-3.4592a.416.416 0 00-.1521-.5676.416.416 0 00-.5676.1521l-2.0223 3.503C15.5902 8.4213 13.8533 8.0028 12 8.0028s-3.5902.4185-5.1367.9669L4.841 5.4667a.4161.4161 0 00-.5677-.1521.4157.4157 0 00-.1521.5676l1.9973 3.4592C2.6889 11.1867.3432 14.6589 0 18.761h24c-.3432-4.1021-2.6889-7.5743-6.1185-9.4396"/></svg>
                                    <span class="text-xs font-bold text-slate-600">Android</span>
                                </button>
                                <button onclick="selectDevice('ios')" id="dev-ios" class="device-btn p-4 rounded-2xl border-2 border-slate-100 hover:border-orange-200 hover:bg-orange-50 transition-all flex flex-col items-center gap-2 group">
                                    <svg class="w-8 h-8 text-slate-400 group-hover:text-orange-500 transition-colors" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.21-1.98 1.08-3.11-1.06.05-2.31.71-3.06 1.61-.69.87-1.23 2.1-1.09 3.11 1.19.09 2.4-.64 3.07-1.61z"/></svg>
                                    <span class="text-xs font-bold text-slate-600">iOS</span>
                                </button>
                            </div>
                            <button onclick="startInstallation()" id="install-btn" disabled class="w-full py-4 rounded-xl bg-slate-200 text-slate-400 font-bold text-lg transition-all shadow-none cursor-not-allowed">
                                Select Device
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<footer class="bg-slate-950 text-slate-300 pt-12 md:pt-20 pb-10 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12 mb-16">
            
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <img src="HTML_Demo/img/labflow.jpg" alt="LabFlow" class="w-10 h-10 rounded-xl grayscale hover:grayscale-0 transition-all shadow-lg shadow-crimson/10">
                    <span class="font-extrabold text-2xl tracking-tight text-white">LabFlow</span>
                </div>
                <p class="text-sm leading-relaxed text-slate-400">
                    The official Laboratory Apparatus Borrowing System of WMSU College of Science and Mathematics. Streamlining science through digital accountability.
                </p>
                <div class="flex gap-4">
                    <a href="#" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-crimson transition-colors group">
                        <svg class="w-5 h-5 group-hover:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V14H7v-3h3V8.5c0-3 1.8-4.5 4.5-4.5 1.2 0 2.5.2 2.5.2V7h-1.3c-1.5 0-2 .9-2 1.8V11h3.2l-.5 3h-2.7v7.8c4.56-.93 8-4.96 8-9.8z"/></svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="text-white font-bold mb-6">Quick Links</h4>
                <ul class="space-y-4 text-sm font-medium">
                    <li><a href="#about" class="hover:text-crimson transition-colors">About the System</a></li>
                    <li><a href="#how-it-works" class="hover:text-crimson transition-colors">Borrowing Workflow</a></li>
                    <li><a href="#why-choose" class="hover:text-crimson transition-colors">System Benefits</a></li>
                    <li><a href="#install-pwa" class="hover:text-crimson transition-colors">Install App</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-white font-bold mb-6">Support</h4>
                <ul class="space-y-4 text-sm font-medium">
                    <li><button onclick="toggleRecoveryModal(true)" class="hover:text-crimson transition-colors text-left">Account Recovery</button></li>
                    <li><button onclick="toggleLegalModal(true)" class="hover:text-crimson transition-colors text-left">Legal Information</button></li>
                </ul>
            </div>

            <div>
                <h4 class="text-white font-bold mb-6">Contact Office</h4>
                <div class="w-full h-48 bg-slate-900 rounded-xl mb-6 border border-white/10 overflow-hidden">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1980.3!2d122.059!3d6.913!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x325041dd7a24816f%3A0x51af215fb64cc81a!2sWestern%20Mindanao%20State%20University!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
                <ul class="space-y-4 text-sm">
                    <li class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-crimson mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>CSM Building, WMSU Main Campus,<br>Zamboanga City</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-crimson" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span>csm@wmsu.edu.ph</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-xs text-slate-500 font-medium">
                © 2026 LabFlow System. All Rights Reserved.
            </p>
            <div class="flex items-center gap-2 px-4 py-2 bg-white/5 rounded-full border border-white/5">
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">System by</span>
                <span class="text-xs font-black text-white">CCS Development Team</span>
            </div>
        </div>
    </div>
</footer>

    <div id="ctaModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="toggleModal(false)"></div>

        <div class="relative transform overflow-hidden rounded-2xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-8 border border-slate-100 animate__animated animate__zoomIn">
            
            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                <button type="button" class="rounded-md bg-white text-slate-400 hover:text-slate-500 focus:outline-none" onclick="toggleModal(false)">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div id="login-flow-container">
                <!-- JS will render content here -->
            </div>

            <div class="mt-6 text-center">
                <p class="text-[10px] font-mono p-2 bg-slate-50 rounded text-slate-400 border border-slate-100">
                    <?php echo $status; ?>
                </p>
            </div>
            </div>
        </div>
    </div>

    <!-- Legal Modal (Terms & Privacy) -->
    <div id="legalModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="toggleLegalModal(false)"></div>

        <div class="relative transform overflow-hidden rounded-2xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-8 border border-slate-100 animate__animated animate__zoomIn">
            
            <div class="absolute right-0 top-0 pr-4 pt-4 block">
                <button type="button" class="rounded-md bg-white text-slate-400 hover:text-slate-500 focus:outline-none" onclick="toggleLegalModal(false)">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div>
                <h3 class="text-xl font-bold leading-6 text-slate-900 mb-6 text-center">Legal Information</h3>
                
                <!-- Tabs -->
                <div class="flex justify-center mb-6">
                    <div class="bg-slate-100 p-1.5 rounded-full inline-flex relative w-full sm:w-auto">
                        <div id="legal-tab-indicator" class="absolute h-[calc(100%-12px)] top-[6px] transition-all duration-300 bg-crimson rounded-full shadow-md w-[calc(50%-4px)] left-[6px]"></div>
                        
                        <button onclick="switchLegalTab('terms')" id="btn-legal-terms" class="relative z-10 px-8 py-3 text-sm font-bold transition-colors w-1/2 sm:w-56 whitespace-nowrap rounded-full text-white flex items-center justify-center">
                            Terms of Service
                        </button>
                        
                        <button onclick="switchLegalTab('privacy')" id="btn-legal-privacy" class="relative z-10 px-8 py-3 text-sm font-bold transition-colors w-1/2 sm:w-56 whitespace-nowrap rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center">
                            Privacy Policy
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div id="legal-content-terms" class="legal-tab-content transition-opacity duration-300">
                    <div class="text-sm text-slate-500 space-y-4 max-h-[50vh] overflow-y-auto pr-4 leading-relaxed">
                        <h4 class="text-lg font-bold text-slate-800">1. Terms of Service</h4>
                        <p class="text-xs text-slate-400">Last Updated: February 22, 2026</p>
                        
                        <p>Welcome to LabFlow. These Terms of Service ("Terms") govern your access to and use of the LabFlow platform, including its inventory management and requisition systems. By accessing or using the system, you agree to comply with these terms.</p>

                        <h5 class="font-bold text-slate-700 mt-4">1.1 User Accounts and Roles</h5>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Eligibility:</strong> Access is restricted to authorized students, faculty, and administrative staff of the institution.</li>
                            <li><strong>Role-Based Access:</strong> Users are assigned specific roles (Student, Teacher, or Admin). Any attempt to bypass role-restricted areas or gain unauthorized access to the Admin Dashboard is a violation of these terms.</li>
                            <li><strong>Account Security:</strong> You are responsible for maintaining the confidentiality of your credentials. All actions performed under your account are your responsibility.</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">1.2 Requisition and Equipment Usage</h5>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Borrowing Obligations:</strong> By using the "Borrow Wizard" or generating a "Borrowing Slip," you agree to take full responsibility for the laboratory equipment assigned to you.</li>
                            <li><strong>Asset Care:</strong> Equipment must be used only for sanctioned institutional activities. Damage or loss of items must be reported immediately via the "Settlement" workflow.</li>
                            <li><strong>Handover Protocols:</strong> Users must adhere to the physical "Handover" process and ensure the digital status of the item is updated by an authorized Teacher or Admin upon return.</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">1.3 Prohibited Conduct</h5>
                        <ul class="list-disc pl-5 space-y-2">
                            <li>You may not use the system to upload malicious scripts or attempt to disrupt the MySQL database operations.</li>
                            <li>Unauthorized extraction of the "Masterlist" or institutional inventory data for non-academic purposes is strictly prohibited.</li>
                        </ul>
                    </div>
                </div>

                <div id="legal-content-privacy" class="legal-tab-content hidden transition-opacity duration-300">
                    <div class="text-sm text-slate-500 space-y-4 max-h-[50vh] overflow-y-auto pr-4 leading-relaxed">
                        <h4 class="text-lg font-bold text-slate-800">2. Privacy Policy</h4>
                        <p class="text-xs text-slate-400">Last Updated: February 22, 2026</p>

                        <p>LabFlow is committed to protecting the privacy of its users. This policy outlines how we collect, use, and safeguard the information generated within the platform.</p>

                        <h5 class="font-bold text-slate-700 mt-4">2.1 Information We Collect</h5>
                        <p>To facilitate laboratory management, the system collects the following data:</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Identity Data:</strong> Full name, institutional ID number, and email address (collected via dbRelated/operation.php).</li>
                            <li><strong>Academic Data:</strong> Class enrollments.</li>
                            <li><strong>Transactional Data:</strong> Detailed logs of equipment borrowing, including timestamps of requisition and return.</li>
                            <li><strong>Visual Evidence:</strong> Images uploaded as proof of equipment condition or damage (stored in /uploads/evidence/).</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">2.2 How We Use Your Information</h5>
                        <p>Your data is used strictly for administrative and educational purposes:</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Operational Integrity:</strong> To track institutional assets and ensure accountability.</li>
                            <li><strong>Communication:</strong> Automated notifications sent via PHPMailer for requisition status or laboratory deadlines.</li>
                            <li><strong>Reporting:</strong> Generating institutional inventory and performance reports using PhpSpreadsheet.</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">2.3 Data Storage and Security</h5>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Database Security:</strong> All data is stored in the snhs_inventory MySQL database.</li>
                            <li><strong>File Protection:</strong> Access to sensitive directories like /uploads/manuals/ is restricted to authorized roles.</li>
                            <li><strong>Retention:</strong> Data is retained for the duration of your academic tenure or as required by institutional record-keeping policies.</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">2.4 Data Sharing</h5>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Internal Access:</strong> Student data (including borrowing history) is visible to relevant Teachers and Administrators.</li>
                            <li><strong>Third Parties:</strong> LabFlow does not sell or share user data with external commercial entities. Data is only shared with third-party libraries (like PHPMailer) necessary to perform system functions.</li>
                        </ul>

                        <h5 class="font-bold text-slate-700 mt-4">2.5 User Rights</h5>
                        <p>Users have the right to:</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li>View their profile information and borrowing history.</li>
                            <li>Request a correction of their inventory logs through their Teacher or an Administrator.</li>
                            <li>Be notified of any significant changes to the system's data handling practices.</li>
                        </ul>
                    </div>
                </div>

                <!-- Disclaimer -->
                <div class="mt-6 p-4 bg-orange-50 border border-orange-100 rounded-xl text-xs text-orange-800">
                    <strong class="block mb-1 font-bold">Disclaimer</strong>
                    LabFlow is provided "as is" for institutional management purposes. While every effort is made to ensure data accuracy and system uptime, the developers are not liable for laboratory accidents or physical equipment failure occurring outside the digital management of the software.
                </div>

                <div class="mt-6">
                    <button type="button" class="w-full rounded-lg bg-slate-100 px-3 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-colors" onclick="toggleLegalModal(false)">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Recovery Modal -->
    <div id="recoveryModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="toggleRecoveryModal(false)"></div>

        <div class="relative transform overflow-hidden rounded-2xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-8 border border-slate-100 animate__animated animate__zoomIn">
            
            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                <button type="button" class="rounded-md bg-white text-slate-400 hover:text-slate-500 focus:outline-none" onclick="toggleRecoveryModal(false)">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div id="recovery-flow-container">
                <!-- JS will render recovery steps here -->
            </div>
        </div>
    </div>

    <!-- Installation Modal -->
    <div id="installModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity"></div>

        <div class="relative transform overflow-hidden rounded-3xl bg-white p-8 text-center shadow-2xl transition-all w-full max-w-sm border border-slate-100 animate__animated animate__zoomIn">
            
            <div class="mb-6 relative">
                <div class="w-20 h-20 bg-orange-50 rounded-full flex items-center justify-center mx-auto relative z-10">
                    <img src="HTML_Demo/img/labflow.jpg" class="w-12 h-12 rounded-xl shadow-sm">
                </div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-24 h-24 bg-orange-100/50 rounded-full animate-ping -z-0"></div>
            </div>

            <h3 class="text-xl font-black text-slate-900 mb-2">Installing LabFlow</h3>
            <p class="text-sm text-slate-500 mb-8">Adding to Home Screen...</p>

            <!-- Progress Bar -->
            <div class="w-full bg-slate-100 rounded-full h-4 mb-4 overflow-hidden relative">
                <div id="install-progress" class="bg-gradient-to-r from-orange-500 to-crimson h-4 rounded-full transition-all duration-300 ease-out w-0 relative">
                    <div class="absolute inset-0 bg-white/20 animate-[shimmer_1s_infinite] border-r border-white/30" style="animation: shimmer 1s infinite linear;"></div>
                </div>
            </div>
            <p id="install-percentage" class="text-xs font-bold text-crimson">0%</p>
        </div>
    </div>

    <?php
    $toast_message = null;
    $toast_type = 'success'; // Default type

    if (isset($_SESSION['toast_message']) && $_SESSION['toast_message'] !== null) {
        $toast_message = $_SESSION['toast_message']['text'];
        $toast_type = $_SESSION['toast_message']['type'];
        unset($_SESSION['toast_message']);
    }
    ?>

    <!-- Generic Toast Container -->
    <div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal" role="alert">
        <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl">
            <!-- Icon will be inserted by JS -->
        </div>
        <div id="toast-message" class="text-sm font-bold"></div>
    </div>

    <!-- Chat Widget -->
    <div id="chat-widget" class="fixed bottom-6 right-6 z-[100] flex flex-col items-end gap-4">
        <!-- Chat Window -->
        <div id="chat-window" class="hidden w-80 md:w-96 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200/60 overflow-hidden flex flex-col transition-all duration-300 origin-bottom-right transform scale-95 opacity-0 ring-1 ring-black/5">
            <!-- Header -->
            <div class="bg-crimson-gradient p-4 flex justify-between items-center text-white shadow-md relative overflow-hidden z-10">
                <!-- Decorative circle in header -->
                <div class="absolute -top-4 -right-4 w-16 h-16 bg-white/10 rounded-full blur-xl"></div>
                
                <div class="flex items-center gap-3 relative z-10">
                    <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/20 shadow-inner">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm tracking-wide">LabFlow AI</h4>
                        <p class="text-[15px] text-white/90 flex items-center gap-1.5 font-medium"><span class="w-1.5 h-1.5 bg-green-400 rounded-full shadow-[0_0_5px_rgba(74,222,128,0.8)]"></span> Powered by Gemini 2.5 Flash</p>
                    </div>
                </div>
                <button onclick="toggleChat()" class="text-white/70 hover:text-white hover:bg-white/10 rounded-full p-1 transition-all relative z-10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <!-- Messages Area -->
            <div id="chat-messages" class="h-96 overflow-y-auto p-4 bg-slate-50/50 space-y-4 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
                <!-- Date Separator -->
                <div class="flex justify-center">
                    <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded-full uppercase tracking-wider">Today</span>
                </div>

                <!-- Bot Welcome Message -->
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-gradient-to-br from-orange-100 to-crimson-100 rounded-full flex items-center justify-center flex-shrink-0 text-crimson border border-crimson/10 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <div class="space-y-1 max-w-[85%]">
                        <div class="bg-white p-3.5 rounded-2xl rounded-tl-none shadow-sm border border-slate-100 text-sm text-slate-600 leading-relaxed">
                            Hello! I'm your LabFlow assistant. Ask me anything about borrowing procedures, account setup, or lab rules.
                        </div>
                        <span class="text-[10px] text-slate-400 pl-1">Just now</span>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-3 bg-white border-t border-slate-100 relative z-20">
                <form onsubmit="handleChatSubmit(event)" class="flex gap-2 items-end">
                    <div class="relative flex-1">
                        <input type="text" id="chat-input" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:outline-none focus:border-crimson focus:ring-1 focus:ring-crimson transition-all placeholder:text-slate-400" placeholder="Type your question...">
                    </div>
                    <button type="submit" class="w-11 h-11 bg-crimson-gradient text-white rounded-xl flex items-center justify-center hover:shadow-lg hover:shadow-crimson/20 hover:-translate-y-0.5 transition-all active:scale-95">
                        <svg class="w-5 h-5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.768 0 013.27 20.876L5.999 12zm0 0h7.5"></path></svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- Chat Head Button -->
        <button onclick="toggleChat()" id="chat-head" class="w-14 h-14 bg-crimson-gradient text-white rounded-full shadow-lg shadow-crimson/30 flex items-center justify-center hover:scale-110 transition-transform duration-300 group relative z-50">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
        </button>
    </div>

    <script>
        let deferredPrompt;
        let selectedDevice = null;
        const initialState = <?php echo $initialStateJSON; ?>;
        function switchTab(tabName) {
            const tabs = ['account-setup', 'group', 'individual', 'no-activity', 'damage'];
            const index = tabs.indexOf(tabName);
            const indicator = document.getElementById('tab-indicator');
            
            // Update Indicator Position
            if (index === 0) {
                indicator.style.left = '6px';
            } else if (index === 1) {
                indicator.style.left = 'calc(20% + 2px)';
            } else if (index === 2) {
                indicator.style.left = 'calc(40% - 2px)';
            } else if (index === 3) {
                indicator.style.left = 'calc(60% - 6px)';
            } else if (index === 4) {
                indicator.style.left = 'calc(80% - 10px)';
            }

            // Update Text Colors
            tabs.forEach(t => {
                const btn = document.getElementById(`btn-${t}`);
                if (t === tabName) {
                    btn.classList.remove('text-slate-500', 'hover:text-slate-700');
                    btn.classList.add('text-white');
                } else {
                    btn.classList.remove('text-white');
                    btn.classList.add('text-slate-500', 'hover:text-slate-700');
                }
            });

            // Update Content
            document.querySelectorAll('.tab-content').forEach(content => {
                if(content.id === `content-${tabName}`) {
                    content.classList.remove('hidden');
                    content.classList.add('animate__animated', 'animate__fadeIn');
                } else {
                    content.classList.add('hidden');
                    content.classList.remove('animate__animated', 'animate__fadeIn');
                }
            });
        }

        function toggleModal(show) {
            const modal = document.getElementById('ctaModal');
            if (show) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                // If the modal is being opened and it's currently empty, render the initial step.
                if (!modalContainer.querySelector('form')) {
                    renderStep(initialState.step, initialState.data);
                }
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function toggleMobileMenu() {
            const menu = document.getElementById('nav-menu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
        }

        function switchLegalTab(tabName) {
            const tabs = ['terms', 'privacy'];
            const index = tabs.indexOf(tabName);
            const indicator = document.getElementById('legal-tab-indicator');
            
            // Update Indicator Position
            if (index === 0) {
                indicator.style.left = '6px';
            } else {
                indicator.style.left = 'calc(50% + 2px)';
            }

            // Update Text Colors
            tabs.forEach(t => {
                const btn = document.getElementById(`btn-legal-${t}`);
                if (t === tabName) {
                    btn.classList.remove('text-slate-500', 'hover:text-slate-700');
                    btn.classList.add('text-white');
                } else {
                    btn.classList.remove('text-white');
                    btn.classList.add('text-slate-500', 'hover:text-slate-700');
                }
            });

            // Update Content
            document.querySelectorAll('.legal-tab-content').forEach(content => {
                if(content.id === `legal-content-${tabName}`) {
                    content.classList.remove('hidden');
                    content.classList.add('animate__animated', 'animate__fadeIn');
                } else {
                    content.classList.add('hidden');
                    content.classList.remove('animate__animated', 'animate__fadeIn');
                }
            });
        }

        function toggleLegalModal(show) {
            const modal = document.getElementById('legalModal');
            if (show) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                switchLegalTab('terms'); // Default to terms
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function toggleRecoveryModal(show) {
            const modal = document.getElementById('recoveryModal');
            if (show) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                renderRecoveryStep(1); // Render the first step of recovery when opening
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                resetFlow(); // Also reset the main login flow to step 1 when closing recovery
            }
        }

        function toggleRecoveryModal(show) {
            const modal = document.getElementById('recoveryModal');
            if (show) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                renderRecoveryStep(1); // Render the first step of recovery when opening
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                resetFlow(); // Also reset the main login flow to step 1 when closing recovery
            }
        }

        const recoveryModalContainer = document.getElementById('recovery-flow-container');

        function getRecoveryStepHtml(step, data = {}) {
            const userEmail = data.user_email || '';
            const maskedEmail = maskEmail(userEmail);

            switch(step) {
                case 1: // Find Account
                    return `
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-crimson/10 mb-4">
                            <svg class="h-6 w-6 text-crimson" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                        </div>
                        <div class="text-center mb-6"><h3 class="text-xl font-bold text-slate-900">Account Recovery</h3><p class="text-sm text-slate-500">Enter your ID number to find your account.</p></div>
                        <form data-action="recovery_identity" class="space-y-4">
                            <input type="hidden" name="action_type" value="recovery_identity">
                            <div><label class="block text-sm font-bold text-slate-700">ID Number</label><input type="text" name="id_number" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-crimson focus:ring-crimson sm:text-sm p-2.5 border" placeholder="e.g. 2024-001"></div>
                            <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors">Find Account</button>
                        </form>`;
                case 2: // Send OTP
                    return `
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-crimson/10 mb-4"><svg class="h-6 w-6 text-crimson" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg></div>
                        <div class="text-center mb-6"><h3 class="text-xl font-bold text-slate-900">Confirm Email</h3><p class="text-sm text-slate-500">Is this your registered email? A recovery code will be sent here.</p></div>
                        <form data-action="recovery_send_otp" class="space-y-4">
                            <input type="hidden" name="action_type" value="recovery_send_otp">
                            <div class="text-center p-4 bg-slate-50 rounded-lg border border-slate-200">
                                <p class="text-sm font-bold text-slate-800">${maskedEmail}</p>
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors">Send Recovery Code</button>
                        </form>`;
                case 3: // Verify OTP
                    return `
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-crimson/10 mb-4"><svg class="h-6 w-6 text-crimson" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.159 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg></div>
                        <div class="text-center mb-6"><h3 class="text-xl font-bold text-slate-900">Check Your Email</h3><p class="text-sm text-slate-500">Enter the 6-digit code we sent.</p></div>
                        <form data-action="recovery_verify_otp" class="space-y-4">
                            <input type="hidden" name="action_type" value="recovery_verify_otp">
                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Recovery Code</label><div class="otp-container flex justify-center gap-2 mb-2"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="hidden" name="otp_code" id="otp_code"></div></div>
                            <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white hover:bg-red-700 transition-colors">Verify Code</button>
                        </form>`;
                case 4: // Reset Password
                    return `
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-crimson/10 mb-4"><svg class="h-6 w-6 text-crimson" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg></div>
                        <div class="text-center mb-6"><h3 class="text-xl font-bold text-slate-900">Reset Password</h3><p class="text-sm text-slate-500">Create a new secure password.</p></div>
                        <form data-action="recovery_reset_password" class="space-y-4">
                            <input type="hidden" name="action_type" value="recovery_reset_password">
                            <div><label class="block text-sm font-bold text-slate-700">New Password</label><input type="password" name="password" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-crimson focus:ring-crimson sm:text-sm p-2.5 border"></div>
                            <div><label class="block text-sm font-bold text-slate-700">Confirm Password</label><input type="password" name="confirm_password" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-crimson focus:ring-crimson sm:text-sm p-2.5 border"></div>
                            <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-3 text-sm font-bold text-white hover:bg-green-700 transition-colors">Update Password</button>
                        </form>`;
                default: return 'An error occurred during recovery.';
            }
        }

        function renderRecoveryStep(step, data = {}) {
            if (!recoveryModalContainer) return;
            recoveryModalContainer.innerHTML = getRecoveryStepHtml(step, data);
            const form = recoveryModalContainer.querySelector('form');
            if (form) {
                form.addEventListener('submit', handleRecoverySubmit);
                const firstInput = form.querySelector('input[type="text"], input[type="password"]');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
            if (step === 3) { // OTP step
                setupOtpInputs(recoveryModalContainer.querySelector('.otp-container'));
            }
        }

        async function handleRecoverySubmit(e) {
            e.preventDefault();
            const form = e.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="animate-pulse">Processing...</span>';

            const formData = new FormData(form);
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    if (result.message) showToast(result.message, 'success');
                    
                    if (result.next_step === 1) { // If success leads back to login
                        toggleRecoveryModal(false); // Close recovery modal
                        resetFlow(); // Reset main login flow to show ID input
                        toggleModal(true); // Show main login modal
                    } else {
                        renderRecoveryStep(result.next_step, result.data || {});
                    }
                } else {
                    showToast(result.message || 'An unknown error occurred.', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            } catch (error) {
                showToast('Could not connect to the server.', 'error');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }

        async function startForgotPasswordFlow() {
            // 1. Close the current login modal
            toggleModal(false);

            // 2. Show a temporary loading state in the recovery modal
            const recoveryModal = document.getElementById('recoveryModal');
            const recoveryContainer = document.getElementById('recovery-flow-container');
            recoveryModal.classList.remove('hidden');
            recoveryModal.classList.add('flex');
            recoveryContainer.innerHTML = '<div class="text-center p-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-crimson mx-auto"></div><p class="mt-4 text-sm text-slate-500 font-medium">Finding your account...</p></div>';

            // 3. Fetch the user's email from the backend
            try {
                const formData = new FormData();
                formData.append('action_type', 'get_recovery_email');

                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    // 4. Render the "Confirm Email" step
                    renderRecoveryStep(result.next_step, result.data);
                } else {
                    // Handle error - show step 1 as a fallback if session expired
                    showToast(result.message || 'Could not initiate recovery. Please try again.', 'error');
                    renderRecoveryStep(1);
                }
            } catch (error) {
                showToast('Could not connect to the server.', 'error');
                // Fallback to the original flow
                renderRecoveryStep(1);
            }
        }

        function togglePasswordVisibility(button) {
            const input = button.previousElementSibling;
            const eyeOpen = button.querySelector('.eye-open');
            const eyeClosed = button.querySelector('.eye-closed');

            if (input.type === 'password') {
                input.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast-container');
            if (!toast) return;

            const iconContainer = document.getElementById('toast-icon-container');
            const messageContainer = document.getElementById('toast-message');

            // Reset classes
            toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
            iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';

            messageContainer.textContent = message;

            if (type === 'success') {
                toast.classList.add('bg-emerald-600');
                iconContainer.classList.add('bg-emerald-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`;
            } else { // error
                toast.classList.add('bg-red-600');
                iconContainer.classList.add('bg-red-100');
                iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`;
            }

            toast.classList.remove('hidden');
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
            toast.style.transition = 'all 0.5s ease';

            setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
        }

        // --- DYNAMIC MODAL LOGIC ---
        const modalContainer = document.getElementById('login-flow-container');

        function getStepHtml(step, data = {}) {
            const userName = data.user_name ? data.user_name.replace(/'/g, "\\'") : 'User';
            switch(step) {
                case 1: return `
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50"><svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg></div>
                    <div class="mt-3 text-center sm:mt-5"><h3 class="text-xl font-display font-bold leading-6 text-slate-900">E-LIMS Gatekeeper</h3><p class="mt-2 text-sm text-slate-500">Please verify your identity.</p></div>
                    <form data-action="verify_identity" class="mt-6 space-y-4">
                        <input type="hidden" name="action_type" value="verify_identity">
                        <div><label for="id_number" class="block text-sm font-bold text-slate-700">ID Number</label><input type="text" name="id_number" id="id_number" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400" placeholder="e.g. 2024-001"></div>
                        <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Verify Identity</button>
                    </form>`;
                case 2: return `
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50"><svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg></div>
                    <div class="mt-3 text-center sm:mt-5"><h3 class="text-xl font-display font-bold leading-6 text-slate-900">Welcome Back</h3><p class="mt-2 text-sm text-slate-500">Login as <span class="font-bold text-slate-800">${userName}</span></p></div>
                    <form data-action="verify_password" class="mt-6 space-y-4">
                        <input type="hidden" name="action_type" value="verify_password">
                        <div>
                            <div class="flex justify-between items-center"><label for="password" class="block text-sm font-bold text-slate-700">Password</label>                        <button type="button" onclick="startForgotPasswordFlow()" class="font-semibold text-crimson hover:text-red-700">Forgot Password?</button></div>
                            <div class="relative mt-1">
                                <input type="password" name="password" id="password" required autofocus class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400 pr-10" placeholder="Enter password">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5 eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg class="h-5 w-5 eye-closed hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 .95-3.11 3.543-5.45 6.83-6.21M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12c-1.274 4.057-5.064 7-9.542 7a10.05 10.05 0 01-1.875-.225M3 3l18 18" /></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Access Dashboard</button>
                        <div class="text-center pt-2"><a href="#" onclick="resetFlow(); return false;" class="text-xs text-slate-400 hover:text-crimson transition-colors">Not you? Switch account</a></div>
                    </form>`;
                case 3:
                    const userEmail = data.user_email || '';
                    const maskedEmail = maskEmail(userEmail);
                    return `
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50"><svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg></div>
                    <div class="mt-3 text-center sm:mt-5"><h3 class="text-xl font-display font-bold leading-6 text-slate-900">Account Setup</h3><p class="mt-2 text-sm text-slate-500">Hello <span class="font-bold text-slate-800">${userName}</span>. Please confirm your email.</p></div>
                    <form data-action="reg_send_otp" class="mt-6 space-y-4">
                        <input type="hidden" name="action_type" value="reg_send_otp">
                        <div class="text-center p-4 bg-slate-50 rounded-lg border border-slate-200">
                            <p class="text-sm font-bold text-slate-800">${maskedEmail}</p>
                            <p class="text-xs text-slate-400 mt-1">A verification code will be sent to this address.</p>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Send Verification Code</button>
                        <div class="text-center pt-2"><a href="#" onclick="resetFlow(); return false;" class="text-xs text-slate-400 hover:text-crimson transition-colors">Cancel</a></div>
                    </form>`;
                case 4: return `
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50"><svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.159 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg></div>
                    <div class="mt-3 text-center sm:mt-5"><h3 class="text-xl font-display font-bold leading-6 text-slate-900">Verify Email</h3><p class="mt-2 text-sm text-slate-500">Enter the 6-digit code sent to your email.</p></div>
                    <form data-action="reg_verify_otp" class="mt-6 space-y-4">
                        <input type="hidden" name="action_type" value="reg_verify_otp">
                        <div><label for="otp_code" class="block text-sm font-bold text-slate-700 mb-2">Verification Code</label><div class="otp-container flex justify-center gap-2 mb-2"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*"><input type="hidden" name="otp_code" id="otp_code"></div></div>
                        <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Verify OTP</button>
                        <div class="text-center pt-2"><a href="#" onclick="resetFlow(); return false;" class="text-xs text-slate-400 hover:text-crimson transition-colors">Cancel</a></div>
                    </form>`;
                case 5: return `
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50"><svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg></div>
                    <div class="mt-3 text-center sm:mt-5"><h3 class="text-xl font-display font-bold leading-6 text-slate-900">Secure Your Account</h3><p class="mt-2 text-sm text-slate-500">Create a password to finish setup.</p></div>
                    <form data-action="reg_finalize" class="mt-6 space-y-4">
                        <input type="hidden" name="action_type" value="reg_finalize">
                        <div>
                            <label for="password" class="block text-sm font-bold text-slate-700">New Password</label>
                            <div class="relative mt-1">
                                <input type="password" name="password" id="password" required class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border pr-10">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5 eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg class="h-5 w-5 eye-closed hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 .95-3.11 3.543-5.45 6.83-6.21M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12c-1.274 4.057-5.064 7-9.542 7a10.05 10.05 0 01-1.875-.225M3 3l18 18" /></svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-bold text-slate-700">Confirm Password</label>
                            <div class="relative mt-1">
                                <input type="password" name="confirm_password" id="confirm_password" required class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border pr-10">
                                <button type="button" onclick="togglePasswordVisibility(this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                                    <svg class="h-5 w-5 eye-open" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    <svg class="h-5 w-5 eye-closed hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7 .95-3.11 3.543-5.45 6.83-6.21M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12c-1.274 4.057-5.064 7-9.542 7a10.05 10.05 0 01-1.875-.225M3 3l18 18" /></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-green-700 transition-colors">Finish Registration</button>
                    </form>`;
                default: return 'An error occurred.';
            }
        }

        function renderStep(step, data = {}) {
            if (!modalContainer) return;
            modalContainer.innerHTML = getStepHtml(step, data);
            const form = modalContainer.querySelector('form');
            if (form) {
                // Directly attach the event listener to the newly created form
                // to ensure it's always active for the current step.
                form.addEventListener('submit', handleFormSubmit);

                const firstInput = form.querySelector('input[type="text"], input[type="password"], input[type="email"]');
                if (firstInput) {
                    setTimeout(() => firstInput.focus(), 100);
                }
            }
            if (step === 4) {
                setupOtpInputs(modalContainer.querySelector('.otp-container'));
            }
        }

        async function handleFormSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const action = form.dataset.action;
            if (!action) return;

            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="animate-pulse">Processing...</span>';

            const formData = new FormData(form);
            
            try {
                const response = await fetch('auth_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success') {
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    } else {
                        if (result.message) {
                            showToast(result.message, 'success');
                        }
                        renderStep(result.next_step, result.data || {});
                    }
                } else {
                    showToast(result.message || 'An unknown error occurred.', 'error');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }

            } catch (error) {
                showToast('Could not connect to the server.', 'error');
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }

        async function resetFlow() {
            try {
                await fetch('auth_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action_type=reset'
                });
            } catch (e) {
                console.error("Failed to reset session on server, but resetting client-side anyway.");
            }
            renderStep(1);
        }

        function maskEmail(email) {
            if (!email) return '';
            const [name, domain] = email.split('@');
            if (!domain) return email; // Not a valid email format
            const maskedName = name.length > 2 ? name.substring(0, 2) + '*'.repeat(name.length - 2) : name;
            return maskedName + '@' + domain;
        }

        // Simple Intersection Observer for scroll animations
        const observerOptions = { threshold: 0.1 };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.hover-lift').forEach(el => observer.observe(el));

        function setupOtpInputs(container) {
            if (!container) return;
            const inputs = container.querySelectorAll('input[type="text"]');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            
            const updateHidden = () => {
                let val = '';
                inputs.forEach(i => val += i.value);
                hiddenInput.value = val;
            };

            inputs.forEach((input, index) => {
                    input.oninput = (e) => {
                        input.value = input.value.replace(/[^0-9]/g, '');
                        if (input.value.length > 0 && index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                        updateHidden();
                    };

                    input.onkeydown = (e) => {
                        if (e.key === 'Backspace' && !input.value && index > 0) {
                            inputs[index - 1].focus();
                        }
                    };
                    
                    input.onpaste = (e) => {
                        e.preventDefault();
                        const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, inputs.length);
                        data.split('').forEach((char, i) => {
                            if (inputs[i]) inputs[i].value = char;
                        });
                        updateHidden();
                        if (data.length > 0) {
                            const focusIndex = Math.min(data.length, inputs.length - 1);
                            inputs[focusIndex].focus();
                        }
                    };
            });
        }

        function selectDevice(device) {
            selectedDevice = device;
            
            // Reset styles
            document.querySelectorAll('.device-btn').forEach(btn => {
                btn.classList.remove('border-orange-500', 'bg-orange-50');
                btn.classList.add('border-slate-100');
                btn.querySelector('svg').classList.remove('text-orange-500');
                btn.querySelector('svg').classList.add('text-slate-400');
            });

            // Highlight selected
            const btn = document.getElementById(`dev-${device}`);
            btn.classList.remove('border-slate-100');
            btn.classList.add('border-orange-500', 'bg-orange-50');
            btn.querySelector('svg').classList.remove('text-slate-400');
            btn.querySelector('svg').classList.add('text-orange-500');

            // Enable Install Button
            const installBtn = document.getElementById('install-btn');
            installBtn.disabled = false;
            installBtn.classList.remove('bg-slate-200', 'text-slate-400', 'cursor-not-allowed', 'shadow-none');
            installBtn.classList.add('bg-crimson-gradient', 'text-white', 'hover:shadow-xl', 'hover:shadow-crimson/30', 'hover:-translate-y-1');
            installBtn.textContent = 'Install Now';
        }

        // --- REAL PWA INSTALLATION LOGIC ---
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the browser's default install prompt
            e.preventDefault();
            // Stash the event so it can be triggered later.
            deferredPrompt = e;
            // Log that the event was fired, useful for debugging
            console.log(`'beforeinstallprompt' event was fired.`);
            // You could optionally enable the install button here if it's not already visible
        });

        function startInstallation() {
            if (!deferredPrompt) {
                showToast('Installation is not available on this browser or has already been installed.', 'error');
                return;
            }
            // Show the browser's install prompt
            deferredPrompt.prompt();
            // Wait for the user to respond
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                    showToast('LabFlow installed successfully!');
                }
                deferredPrompt = null; // We can only use it once.
            });
        }

        function toggleChat() {
            const chatWindow = document.getElementById('chat-window');
            
            if (chatWindow.classList.contains('hidden')) {
                chatWindow.classList.remove('hidden');
                // Small delay to allow display:block to apply before opacity transition
                setTimeout(() => {
                    chatWindow.classList.remove('scale-95', 'opacity-0');
                    chatWindow.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                chatWindow.classList.remove('scale-100', 'opacity-100');
                chatWindow.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    chatWindow.classList.add('hidden');
                }, 300);
            }
        }

        async function handleChatSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input');
            const message = input.value.trim();
            if (!message) return;

            // Add User Message
            addMessage(message, 'user');
            input.value = '';

            // Show typing indicator
            showTypingIndicator();
            
            // Call API
            try {
                const response = await fetch('HTML_Demo/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: message })
                });
                
                const data = await response.json();
                removeTypingIndicator();
                
                if (data.reply) {
                    addMessage(data.reply, 'bot');
                } else {
                    addMessage("Sorry, I encountered an error: " + (data.error || "Unknown error"), 'bot');
                }
            } catch (error) {
                removeTypingIndicator();
                addMessage("Connection error. Please ensure chat_api.php is accessible.", 'bot');
            }
        }

        function addMessage(text, sender) {
            const container = document.getElementById('chat-messages');
            const div = document.createElement('div');
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            if (sender === 'user') {
                div.className = 'flex items-end justify-end gap-2 animate__animated animate__fadeInUp animate__faster';
                div.innerHTML = `
                    <div class="space-y-1 max-w-[85%] flex flex-col items-end">
                        <div class="bg-crimson-gradient text-white p-3.5 rounded-2xl rounded-tr-none shadow-md text-sm leading-relaxed">
                            ${text}
                        </div>
                        <span class="text-[10px] text-slate-400 pr-1">${time}</span>
                    </div>
                `;
                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
            } else {
                div.className = 'flex items-start gap-3 animate__animated animate__fadeInUp animate__faster';
                div.innerHTML = `
                    <div class="w-8 h-8 bg-gradient-to-br from-orange-100 to-crimson-100 rounded-full flex items-center justify-center flex-shrink-0 text-crimson border border-crimson/10 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    <div class="space-y-1 max-w-[85%]">
                        <div class="bg-white p-3.5 rounded-2xl rounded-tl-none shadow-sm border border-slate-100 text-sm text-slate-600 leading-relaxed message-content">${text}</div>
                        <span class="text-[10px] text-slate-400 pl-1">${time}</span>
                    </div>
                `;
                
                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
            }
        }

        function showTypingIndicator() {
            const container = document.getElementById('chat-messages');
            const div = document.createElement('div');
            div.id = 'typing-indicator';
            div.className = 'flex items-start gap-3 animate__animated animate__fadeIn';
            div.innerHTML = `
                <div class="w-8 h-8 bg-gradient-to-br from-orange-100 to-crimson-100 rounded-full flex items-center justify-center flex-shrink-0 text-crimson border border-crimson/10 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div class="bg-white p-4 rounded-2xl rounded-tl-none shadow-sm border border-slate-100 text-sm text-slate-600">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                </div>
            `;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        function removeTypingIndicator() {
            const el = document.getElementById('typing-indicator');
            if (el) el.remove();
        }

        // --- CHATBOT LOGIC ---
        // --- SERVICE WORKER REGISTRATION ---
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/LabFlow/sw.js').then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }


        // Open modal if PHP state dictates
        <?php if ($showModal): ?>
            toggleModal(true);
        <?php endif; ?>

        <?php if ($toast_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($toast_message); ?>', '<?php echo $toast_type; ?>');
        });
        <?php endif; ?>
    </script>
</body>
</html>