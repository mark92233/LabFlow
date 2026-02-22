<nav class="bg-blue-700 text-white p-4 shadow-md flex justify-between items-center">
    <div class="flex items-center space-x-4">
        <a href="../session_kill.php" class="hover:bg-blue-800 p-2 rounded-full transition" title="Back to Login">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 class="text-xl font-bold">SNHS LIMS</h1>
    </div>
    
    <div class="flex items-center space-x-4">
        <span class="text-sm border-r pr-4 border-blue-400 italic">
            Logged in as: <?php echo $_SESSION['user_role']; ?>
        </span>
        <a href="../session_kill.php" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm font-semibold transition">
            Logout
        </a>
    </div>
</nav>