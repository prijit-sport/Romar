// Nav Slide Bar Toggle - Mobile Responsive
(function() {
    'use strict';

    // Elements
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.mobile-sidebar-toggle');
    const body = document.body;
    const layout = document.querySelector('.layout');

    if (!sidebar) return;

    function toggleSidebar() {
        sidebar.classList.toggle('open');
        body.classList.toggle('sidebar-open');
        
        if (sidebar.classList.contains('open')) {
            // Add backdrop
            let backdrop = document.querySelector('.sidebar-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'sidebar-backdrop';
                backdrop.addEventListener('click', closeSidebar);
                document.body.appendChild(backdrop);
            }
            backdrop.classList.add('show');
        }
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        body.classList.remove('sidebar-open');
        const backdrop = document.querySelector('.sidebar-backdrop');
        if (backdrop) backdrop.classList.remove('show');
    }

    // Event listeners
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    // Close on escape, overlay click (handled above), window resize
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) closeSidebar();
    });

    // Touch swipe support (mobile)
    let startX = 0;
    sidebar.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
    }, { passive: true });

    sidebar.addEventListener('touchend', (e) => {
        const endX = e.changedTouches[0].clientX;
        const diffX = startX - endX;
        if (Math.abs(diffX) > 50 && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    }, { passive: true });

})();

