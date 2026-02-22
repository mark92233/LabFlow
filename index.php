<?php
session_start();

// --- CONFIGURATION & IMPORTS ---
// Adjust these paths if your folder structure changes
require_once 'dbRelated/operation.php';

// Safe include for EmailSender (prevents crash if file is missing during dev)
if (file_exists('dbRelated/EmailSender.php')) {
    require_once 'dbRelated/EmailSender.php';
}

// --- INITIALIZATION ---
$status = ""; 
$error = ""; 
$success_msg = "";
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

// --- CHECK SYSTEM STATUS ---
try {
    $dataMgr = new DataManager();
    $status = "✅ System Pulse: Online";
} catch (Exception $e) {
    $status = "❌ System Pulse: Offline";
}

// --- STATE MANAGEMENT (Determine Step based on Session) ---
if (isset($_SESSION['login_id']) && !isset($_SESSION['user_id'])) {
    // Existing user found, waiting for password
    $step = 2;
} elseif (isset($_SESSION['temp_id'])) {
    // New user registration flow
    if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
        $step = 5; // Password Setup
    } elseif (isset($_SESSION['confirmed_email']) && isset($_SESSION['current_otp'])) {
        $step = 4; // OTP Check
    } else {
        $step = 3; // Email Input
    }
}

// Open modal automatically if we are in the middle of a flow
if ($step > 1) {
    $showModal = true;
}

// --- POST REQUEST HANDLER ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $showModal = true; // Always keep modal open on POST interactions

    // ACTION: VERIFY IDENTITY (Step 1)
if (isset($_POST['action_type']) && $_POST['action_type'] === 'verify_identity') {
        $id_num = trim($_POST['id_number']);
        // DELETED: $name = trim($_POST['full_name']); 

        // CHANGED: Only pass the ID to the function
        $record = $dataMgr->verifyIdentity($id_num);

        if ($record) {
            $existingUser = $dataMgr->checkExistingAccount($record['MasterID']);
            
            if ($existingUser) {
                // EXISTING USER -> GO TO LOGIN
                $_SESSION['login_id'] = $record['MasterID'];
                $_SESSION['temp_name'] = $record['Full_Name']; // We still get the name from DB for the UI
                $step = 2;
            } else {
                // NEW USER -> START REGISTRATION
                $_SESSION['temp_id'] = $record['MasterID']; 
                $_SESSION['temp_name'] = $record['Full_Name'];
                $_SESSION['temp_role'] = $record['Role'];
                $step = 3;
            }
        } else {
            $error = "ID Number not found in school records.";
            $step = 1;
        }
    }

    // ACTION: VERIFY PASSWORD (Step 2 - Login)
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'verify_password') {
        if (!isset($_SESSION['login_id'])) { header("Location: index.php"); exit(); }

        $pass = $_POST['password'];
        $user = $dataMgr->checkExistingAccount($_SESSION['login_id']);

        if ($user && password_verify($pass, $user['Password_Hash'])) {
            $_SESSION['user_id'] = $user['UserID']; 
            $_SESSION['user_role'] = $user['Role']; 
            $_SESSION['user_name'] = $user['Full_Name']; 
            unset($_SESSION['login_id']);
            unset($_SESSION['temp_name']);
            header("Location: dashboard/router.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
            $step = 2;
        }
    }

    // ACTION: SUBMIT EMAIL & SEND OTP (Step 3 -> 4)
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'reg_send_otp') {
        $email = trim($_POST['confirmed_email']);
        
        // Generate OTP
        $otp = rand(100000, 999999);
        
        // Send Email logic
        $sent = false;
        if (class_exists('EmailSender')) {
            $mailer = new EmailSender();
            // Assuming sendOTP returns true on success
            $sent = $mailer->sendOTP($email, $otp);
        } else {
            // Fallback for testing if EmailSender class missing
            // For production, you might want to hide the OTP from the error message
            $error = "Email system missing. Contact Admin. (Dev Note: OTP is $otp)";
            $sent = true; // Force true for dev/testing so you can proceed
        }

        if ($sent) {
            $_SESSION['confirmed_email'] = $email;
            $_SESSION['current_otp'] = $otp;
            $step = 4;
            $success_msg = "Verification code sent to $email";
        } else {
            $error = "Failed to send email. Please check the address.";
            $step = 3;
        }
    }

    // ACTION: VERIFY OTP (Step 4 -> 5)
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'reg_verify_otp') {
        $input_otp = $_POST['otp_code'];
        if ($input_otp == $_SESSION['current_otp']) {
            $_SESSION['otp_verified'] = true;
            $step = 5;
        } else {
            $error = "Invalid code. Please check your email.";
            $step = 4;
        }
    }

    // ACTION: FINALIZE REGISTRATION (Step 5 -> Success)
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'reg_finalize') {
        $pass = $_POST['password'];
        $confirm = $_POST['confirm_password'];

        if ($pass === $confirm) {
            $success = $dataMgr->finalizeRegistration(
                $_SESSION['temp_id'], 
                $_SESSION['confirmed_email'], 
                $pass, 
                $_SESSION['temp_role']
            );

            if ($success) {
                session_unset();
                session_destroy();
                // Redirect to self with success flag
                header("Location: index.php?registered=true");
                exit();
            } else {
                $error = "Database error. Account might already exist.";
                $step = 5;
            }
        } else {
            $error = "Passwords do not match.";
            $step = 5;
        }
    }
}

// Check for registration success flag to show success message
if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    $success_msg = "Account created successfully! You may now log in.";
    $showModal = true;
    $step = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMSU – CSM LabFlow | Modern Lab Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap" rel="stylesheet">
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
            <h2 class="text-3xl font-bold text-crimson section-title mb-16">How It Works</h2>
            
            <!-- Tabs Navigation -->
            <div class="flex justify-center mb-16 overflow-x-auto py-4">
                <div class="bg-slate-100 p-1.5 rounded-full inline-flex relative min-w-max">
                    <div id="tab-indicator" class="absolute h-[calc(100%-12px)] top-[6px] transition-all duration-300 bg-crimson rounded-full shadow-md w-[calc(33.33%-4px)] left-[6px]"></div>
                    
                    <button onclick="switchTab('group')" id="btn-group" class="tab-btn relative z-10 px-4 md:px-6 py-3 text-xs md:text-sm font-bold transition-colors w-36 md:w-64 rounded-full text-white flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Group Activity
                    </button>
                    
                    <button onclick="switchTab('individual')" id="btn-individual" class="tab-btn relative z-10 px-4 md:px-6 py-3 text-xs md:text-sm font-bold transition-colors w-36 md:w-64 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Individual
                    </button>
                    
                    <button onclick="switchTab('no-activity')" id="btn-no-activity" class="tab-btn relative z-10 px-4 md:px-6 py-3 text-xs md:text-sm font-bold transition-colors w-36 md:w-64 rounded-full text-slate-500 hover:text-slate-700 flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 md:w-5 md:h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        Direct Borrow
                    </button>
                </div>
            </div>

            <!-- Tab Contents -->
            <div id="tab-contents" class="relative min-h-[400px]">
                
                <!-- Tab 1: Group Activity -->
                <div id="content-group" class="tab-content transition-opacity duration-500">
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

                <!-- Tab 2: Individual Activity -->
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

                <!-- Tab 3: Without Activity -->
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
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-orange-50">
                    <?php if ($step === 1): ?>
                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    <?php elseif ($step === 2 || $step === 5): ?>
                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    <?php elseif ($step === 3): ?>
                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    <?php elseif ($step === 4): ?>
                        <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.159 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                    <?php endif; ?>
                </div>

                <div class="mt-3 text-center sm:mt-5">
                    <?php if ($step === 1): ?>
                        <h3 class="text-xl font-display font-bold leading-6 text-slate-900">E-LIMS Gatekeeper</h3>
                        <p class="mt-2 text-sm text-slate-500">Please verify your identity.</p>
                    
                    <?php elseif ($step === 2): ?>
                        <h3 class="text-xl font-display font-bold leading-6 text-slate-900">Welcome Back</h3>
                        <p class="mt-2 text-sm text-slate-500">Login as <span class="font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['temp_name'] ?? 'User'); ?></span></p>

                    <?php elseif ($step === 3): ?>
                        <h3 class="text-xl font-display font-bold leading-6 text-slate-900">Account Setup</h3>
                        <p class="mt-2 text-sm text-slate-500">Hello <span class="font-bold text-slate-800"><?php echo htmlspecialchars($_SESSION['temp_name'] ?? 'User'); ?></span>. Please enter your email.</p>

                    <?php elseif ($step === 4): ?>
                        <h3 class="text-xl font-display font-bold leading-6 text-slate-900">Verify Email</h3>
                        <p class="mt-2 text-sm text-slate-500">Enter the 6-digit code sent to your email.</p>

                    <?php elseif ($step === 5): ?>
                        <h3 class="text-xl font-display font-bold leading-6 text-slate-900">Secure Your Account</h3>
                        <p class="mt-2 text-sm text-slate-500">Create a password to finish setup.</p>
                    <?php endif; ?>
                </div>

            <?php if(!empty($error)): ?>
                <div class="mt-4 rounded-lg bg-red-50 p-4 border border-red-100">
                    <div class="flex">
                        <div class="flex-shrink-0"><svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg></div>
                        <div class="ml-3"><h3 class="text-sm font-medium text-red-800">Error</h3><div class="mt-1 text-sm text-red-700"><?php echo $error; ?></div></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success_msg)): ?>
                <div class="mt-4 rounded-lg bg-green-50 p-4 border border-green-100">
                    <div class="flex">
                        <div class="flex-shrink-0"><svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg></div>
                        <div class="ml-3"><h3 class="text-sm font-medium text-green-800">Success</h3><div class="mt-1 text-sm text-green-700"><?php echo $success_msg; ?></div></div>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="mt-6 space-y-4">
                
                <?php if ($step === 1): ?>
                    <input type="hidden" name="action_type" value="verify_identity">
                    <div>
                        <label for="id_number" class="block text-sm font-bold text-slate-700">ID Number</label>
                        <input type="text" name="id_number" id="id_number" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400" placeholder="e.g. 2024-001">
                    </div>
                    
                    <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Verify Identity</button>

                <?php elseif ($step === 2): ?>
                    <input type="hidden" name="action_type" value="verify_password">
                    <div>
                        <div class="flex justify-between items-center">
                            <label for="password" class="block text-sm font-bold text-slate-700">Password</label>
                            <button type="button" onclick="toggleModal(false); toggleRecoveryModal(true)" class="text-xs font-semibold text-crimson hover:text-red-700 transition-colors">Forgot Password?</button>
                        </div>
                        <input type="password" name="password" id="password" required autofocus class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400" placeholder="Enter password">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Access Dashboard</button>
                    <div class="text-center pt-2"><a href="index.php?action=reset" class="text-xs text-slate-400 hover:text-crimson transition-colors">Not you? Switch account</a></div>

                <?php elseif ($step === 3): ?>
                    <input type="hidden" name="action_type" value="reg_send_otp">
                    <div>
                        <label for="confirmed_email" class="block text-sm font-bold text-slate-700">Email Address</label>
                        <input type="email" name="confirmed_email" id="confirmed_email" required autofocus class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400" placeholder="you@school.edu.ph">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Send Verification Code</button>
                    <div class="text-center pt-2"><a href="index.php?action=reset" class="text-xs text-slate-400 hover:text-crimson transition-colors">Cancel</a></div>

                <?php elseif ($step === 4): ?>
                    <input type="hidden" name="action_type" value="reg_verify_otp">
                    <div>
                        <label for="otp_code" class="block text-sm font-bold text-slate-700 mb-2">Verification Code</label>
                        <div class="otp-container flex justify-center gap-2 mb-2">
                            <!-- Visual inputs for OTP -->
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <input type="text" maxlength="1" class="w-10 h-12 text-center text-xl font-bold border border-slate-300 rounded-lg focus:border-crimson focus:ring-1 focus:ring-crimson outline-none transition-all" inputmode="numeric" pattern="[0-9]*">
                            <!-- Actual hidden input for submission -->
                            <input type="hidden" name="otp_code" id="otp_code">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-crimson px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-700 transition-colors">Verify OTP</button>
                    <div class="text-center pt-2"><a href="index.php?action=reset" class="text-xs text-slate-400 hover:text-crimson transition-colors">Cancel</a></div>

                <?php elseif ($step === 5): ?>
                    <input type="hidden" name="action_type" value="reg_finalize">
                    <div>
                        <label for="password" class="block text-sm font-bold text-slate-700">New Password</label>
                        <input type="password" name="password" id="password" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-bold text-slate-700">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-orange-600 focus:ring-orange-600 sm:text-sm p-2.5 border placeholder:text-slate-400">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-green-600 px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-green-700 transition-colors">Finish Registration</button>
                <?php endif; ?>

            </form>

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
                <!-- Dynamic Content -->
                <div class="text-center py-6">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-orange-100 mb-6 animate__animated animate__bounceIn">
                        <svg class="h-10 w-10 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-900 mb-2">Coming Soon</h3>
                    <p class="text-slate-500 mb-8 text-sm">Account recovery will be implemented in a future update.</p>
                    <button type="button" onclick="toggleRecoveryModal(false)" class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:bg-slate-800 transition-colors shadow-lg shadow-slate-200">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-6 right-6 z-[110] hidden items-center gap-3 bg-slate-900 text-white px-5 py-4 rounded-xl shadow-2xl border border-slate-800 animate__animated animate__fadeInUp">
        <div class="w-8 h-8 bg-green-500/20 rounded-full flex items-center justify-center text-green-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div>
            <h4 class="font-bold text-sm">Success</h4>
            <p class="text-xs text-slate-400" id="toast-message">Action completed successfully.</p>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = ['group', 'individual', 'no-activity'];
            const index = tabs.indexOf(tabName);
            const indicator = document.getElementById('tab-indicator');
            
            // Update Indicator Position
            if (index === 0) {
                indicator.style.left = '6px';
            } else if (index === 1) {
                indicator.style.left = 'calc(33.33% + 2px)';
            } else if (index === 2) {
                indicator.style.left = 'calc(66.66% - 2px)';
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
                // Setup OTP inputs if we are on step 4
                <?php if ($step === 4): ?>
                setTimeout(setupOtpInputs, 100);
                <?php endif; ?>
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
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
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

        function setupOtpInputs() {
            const containers = document.querySelectorAll('.otp-container');
            containers.forEach(container => {
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
            });
        }

        // Open modal if PHP state dictates
        <?php if ($showModal): ?>
            toggleModal(true);
        <?php endif; ?>
    </script>
</body>
</html>