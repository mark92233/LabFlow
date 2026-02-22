</main> </div> </div> <script>
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

        revealElements.forEach(el => observer.observe(el));
    });

    /**
     * Tooltip / Active Link State persistence
     * Ensures the sidebar reflects the current location accurately.
     */
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('aside nav a');
    navLinks.forEach(link => {
        if (link.getAttribute('href') !== '#' && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('bg-white/10', 'text-white', 'border-white/10');
            link.classList.remove('text-slate-400');
        }
    });
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

</body>
</html>