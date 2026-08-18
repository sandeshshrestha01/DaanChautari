<?php
/**
 * Daan Chautari — Admin Panel Footer
 * Closes the admin layout and adds sidebar toggle JS.
 */
?>
    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<script>
(function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('adminSidebar');
    if (!toggleBtn || !sidebar) return;

    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function(e) {
        if (sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
})();
</script>
</body>
</html>
