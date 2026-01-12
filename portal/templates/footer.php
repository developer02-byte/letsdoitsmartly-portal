    </div><!-- End wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>

    <?php if (isset($extra_js)): ?>
    <?php echo $extra_js; ?>
    <?php endif; ?>

    <?php
    // Display flash messages
    $flash = getFlash();
    if ($flash):
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo $flash['type']; ?>', '<?php echo addslashes($flash['message']); ?>');
        });
    </script>
    <?php endif; ?>
</body>
</html>
