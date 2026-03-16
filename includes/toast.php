<!-- Toast Container -->
<div id="toast-container" class="fixed bottom-10 right-10 z-[200] hidden items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl" role="alert">
    <div id="toast-icon-container" class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl"></div>
    <div id="toast-message" class="text-sm font-bold"></div>
</div>

<script>
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast-container');
        if (!toast) return;
        const iconContainer = document.getElementById('toast-icon-container');
        const messageContainer = document.getElementById('toast-message');
        toast.className = 'fixed bottom-10 right-10 z-[200] flex items-center w-full max-w-xs p-4 space-x-4 text-white rounded-2xl shadow-2xl animate-reveal';
        iconContainer.className = 'inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-xl';
        messageContainer.textContent = message;
        if (type === 'success') { toast.classList.add('bg-emerald-600'); iconContainer.classList.add('bg-emerald-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>`; } else { toast.classList.add('bg-red-600'); iconContainer.classList.add('bg-red-100'); iconContainer.innerHTML = `<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>`; }
        toast.classList.remove('hidden'); toast.style.opacity = '1'; toast.style.transform = 'translateY(0)';
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(20px)'; setTimeout(() => { toast.classList.add('hidden'); }, 500); }, 4000);
    }

    <?php
    if (isset($_SESSION['toast_message'])) {
        $toast = $_SESSION['toast_message'];
        unset($_SESSION['toast_message']);
        echo "document.addEventListener('DOMContentLoaded', () => showToast('" . addslashes($toast['text']) . "', '" . $toast['type'] . "'));";
    }
    ?>
</script>