    <!-- Bootstrap 5.3.3 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php echo $pageScripts ?? ''; ?>
    </main>
</div>
    <script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
    (function() {
        const toggle = document.querySelector('.mobile-sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        if (!toggle || !sidebar) {
            return;
        }

        const closeSidebar = () => {
            sidebar.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        };

        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            const isOpen = sidebar.classList.toggle('open');
            document.body.classList.toggle('sidebar-open', isOpen);
        });

        document.addEventListener('click', function(event) {
            if (!sidebar.classList.contains('open')) {
                return;
            }

            if (sidebar.contains(event.target) || toggle.contains(event.target)) {
                return;
            }

            closeSidebar();
        }, true);

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });
    })();
    </script>
</body>
</html>
