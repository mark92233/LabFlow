
<!-- AlpineJS for dropdowns and other interactive components -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
     /**
     * Reveal Animation Logic
     * Uses IntersectionObserver to trigger animations when elements 
     * enter the viewport, creating a sense of performance.
     */
    document.addEventListener('DOMContentLoaded', () => {
        const revealElements = document.querySelectorAll('.animate-reveal');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, { threshold: 0.1 });

        // Add transition classes to sidebar after initial render to prevent FOUC
        // Use a timeout to ensure this runs after the browser's first paint cycle,
        // guaranteeing no animation on initial load.
        setTimeout(() => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.add('transition-all', 'duration-300');
        }, 50);

        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.addEventListener('mouseenter', () => {
                // Only expand on hover if the user's preference is 'collapsed'
                if (localStorage.getItem('sidebarState') === 'collapsed') {
                    document.body.classList.remove('sidebar-collapsed');
                }
            });

            sidebar.addEventListener('mouseleave', () => {
                // Only collapse on mouseleave if the user's preference is 'collapsed'
                if (localStorage.getItem('sidebarState') === 'collapsed') {
                    document.body.classList.add('sidebar-collapsed');
                }
            });
        }

        revealElements.forEach(el => observer.observe(el));

        // Use a small timeout to ensure this runs after Alpine.js has initialized all components.
        // This is a robust way to prevent race conditions where the cart-updated event
        // might fire before the header's Alpine component is ready to listen for it.
        setTimeout(() => {
            const urlParams = new URLSearchParams(window.location.search);
            let cart = [];

            // 1. Check if we just came from a successful submission.
            if (urlParams.get('success') === 'requisition_submitted') {
                // If so, clear the storage completely.
                localStorage.removeItem('labflow_cart');
                // Clean up the URL to prevent re-clearing on refresh.
                const newUrl = window.location.pathname + window.location.hash;
                history.replaceState({}, document.title, newUrl);
                // The cart is now definitively empty.
            } else {
                // Otherwise, load the cart from storage as usual.
                cart = JSON.parse(localStorage.getItem('labflow_cart') || '[]');
            }

            // 2. Dispatch the final, correct state to the header.
            window.dispatchEvent(new CustomEvent('cart-updated', {
                detail: { cart: cart }
            }));
        }, 10); // A minimal delay is sufficient to break the race condition.
    });

    /**
     * Sidebar Toggle Logic
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            // Is the current preference collapsed?
            const isCollapsed = localStorage.getItem('sidebarState') === 'collapsed';

            if (isCollapsed) {
                // If preference is collapsed, clicking should expand it permanently
                localStorage.setItem('sidebarState', 'expanded');
                document.body.classList.remove('sidebar-collapsed');
            } else {
                // If preference is expanded, clicking should collapse it
                localStorage.setItem('sidebarState', 'collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
        }
    }

    function openLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
</script>

<style>
    /* Helper styles for the Reveal Animation 
       Ensures elements slide up smoothly rather than popping in.
    */
    .animate-reveal {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
    }
    
    .animate-reveal.active {
        opacity: 1;
        transform: translateY(0);
    }

    /* Custom Scrollbar for Monochrome Blue Theme */
    ::-webkit-scrollbar {
        width: 6px;
    }
    ::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    ::-webkit-scrollbar-thumb {
        background: #0f172a;
        border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #3b82f6;
    }

</style>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeLogoutModal()"></div>

    <div class="relative transform overflow-hidden rounded-2xl bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-8 border border-slate-100 animate-reveal active">
        
        <div class="text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-6">
                <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
            </div>
            <h3 class="text-2xl font-bold text-slate-900 mb-2">Confirm Sign Out</h3>
            <p class="text-slate-500 mb-8 text-sm">Are you sure you want to end your current session?</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <button type="button" onclick="closeLogoutModal()" class="w-full rounded-lg bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                Cancel
            </button>
            <a href="/LabFlow/logout.php" class="w-full text-center rounded-lg bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-red-500/20 hover:bg-red-700 transition-colors">
                Confirm Logout
            </a>
        </div>
    </div>
</div>

</body>
</html>