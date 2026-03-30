    </div><!-- /.container-fluid -->

    <footer class="text-center text-muted py-3 mt-4 border-top">
        <small>Ad Intelligence Dashboard &copy; <?= date('Y') ?> &mdash; Aani Brothers</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script>
    // Navbar clock
    (function() {
        var el = document.getElementById('navClock');
        if (!el) return;
        function tick() {
            el.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        tick();
        setInterval(tick, 30000);
    })();
    </script>
</body>
</html>
