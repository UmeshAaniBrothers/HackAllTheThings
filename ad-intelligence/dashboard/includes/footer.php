    </div><!-- /.container-fluid -->

    <footer class="text-center text-muted py-3 mt-4 border-top">
        <small>Ad Intelligence Dashboard &copy; <?= date('Y') ?></small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <?php if (($currentPage ?? '') === 'geo'): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
