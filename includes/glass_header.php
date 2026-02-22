<header class="sticky top-0 z-40 px-8 py-6 flex justify-between items-center bg-white/60 backdrop-blur-md">
    <div class="flex items-center gap-4">
        <button onclick="history.back()" class="p-3 bg-white shadow-sm border border-slate-200 rounded-2xl text-slate-600 hover:bg-slate-900 hover:text-white transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div>
            <h2 class="text-xl font-bold text-slate-900"><?= $page_title ?? 'Portal' ?></h2>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">SNHS Laboratory</p>
        </div>
    </div>

    <div class="flex items-center gap-6">
        <div class="relative cursor-pointer text-slate-400 hover:text-blue-500 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <span class="absolute top-0 right-0 w-2 h-2 bg-blue-500 rounded-full border-2 border-white"></span>
        </div>

        <div class="flex items-center gap-3 pl-6 border-l border-slate-200">
            <div class="text-right hidden sm:block">
                <p class="text-xs font-bold text-slate-900"><?= $_SESSION['user_name'] ?></p>
                <p class="text-[10px] text-slate-400 uppercase"><?= $_SESSION['user_role'] ?></p>
            </div>
            <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['user_name'] ?>&background=0f172a&color=fff" 
                 class="w-10 h-10 rounded-2xl border-2 border-blue-500 shadow-md shadow-blue-500/10" alt="">
        </div>
    </div>
</header>