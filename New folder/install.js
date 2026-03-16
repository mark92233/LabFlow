document.addEventListener('DOMContentLoaded', () => {
    const osItems = document.querySelectorAll('.os-item');
    const selectDeviceBtn = document.getElementById('select-device-btn');

    // 1. Function to detect User's OS automatically
    const detectOS = () => {
        const platform = window.navigator.platform.toLowerCase();
        const ua = window.navigator.userAgent.toLowerCase();

        if (ua.includes("android")) return "Android";
        if (ua.includes("iphone") || ua.includes("ipad")) return "iOS";
        if (platform.includes("win")) return "Windows";
        if (platform.includes("mac")) return "macOS";
        if (platform.includes("linux")) return "Linux";
        return null;
    };

    // 2. Auto-select the user's device on load
    const userOS = detectOS();
    if (userOS) {
        const autoTarget = document.querySelector(`[data-os="${userOS}"]`);
        if (autoTarget) {
            autoTarget.classList.add('selected');
            selectDeviceBtn.textContent = `Get for ${userOS}`;
            selectDeviceBtn.classList.add('enabled');
        }
    }

    // 3. Handle Manual Selection
    osItems.forEach(item => {
        item.addEventListener('click', () => {
            // Visual feedback: remove selection from others
            osItems.forEach(i => i.classList.remove('selected'));
            
            // Add selection to clicked item
            item.classList.add('selected');
            
            // Update Button State
            const selectedOS = item.getAttribute('data-os');
            selectDeviceBtn.textContent = `Get for ${selectedOS}`;
            
            // Trigger a little "pop" animation via JS
            selectDeviceBtn.style.transform = "scale(1.05)";
            setTimeout(() => selectDeviceBtn.style.transform = "scale(1)", 150);
            
            selectDeviceBtn.classList.add('enabled');
        });
    });

    // 4. Handle Install Button Click
    selectDeviceBtn.addEventListener('click', () => {
        if (!selectDeviceBtn.classList.contains('enabled')) {
            alert("Please select a device first!");
            return;
        }

        const finalOS = selectDeviceBtn.textContent.replace('Get for ', '');
        
        // This is where you'd trigger the PWA install prompt
        console.log(`Initiating PWA installation for ${finalOS}...`);
        
        // Mock success message
        selectDeviceBtn.textContent = "Processing...";
        setTimeout(() => {
            alert(`Instructions for ${finalOS}: \n1. Open your browser menu. \n2. Tap 'Add to Home Screen'.`);
            selectDeviceBtn.textContent = `Get for ${finalOS}`;
        }, 800);
    });
});