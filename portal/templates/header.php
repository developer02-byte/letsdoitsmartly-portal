<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>

    <!-- Theme Initialization - Critical blocking script to prevent flicker -->
    <script>
        // IIFE to apply theme immediately before any CSS loads
        (function() {
            const savedTheme = localStorage.getItem('theme-preference');
            const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

            // Apply no-transition class to prevent color transitions during page load
            const html = document.documentElement;
            html.classList.add('no-transition');

            // Apply theme immediately and synchronously
            if (theme === 'dark') {
                html.setAttribute('data-theme', 'dark');
            } else {
                html.removeAttribute('data-theme');
            }

            // Mark that initial theme has been applied
            html.setAttribute('data-theme-initialized', 'true');

            // Re-enable transitions after paint
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    // Use requestAnimationFrame to ensure DOM is painted first
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            html.classList.remove('no-transition');
                        });
                    });
                });
            } else {
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        html.classList.remove('no-transition');
                    });
                });
            }
        })();
    </script>

    <!-- Critical Inline Styles - Apply base colors immediately to prevent white flash -->
    <style>
        /* Prevent all transitions during initial page load */
        .no-transition,
        .no-transition *,
        .no-transition *::before,
        .no-transition *::after {
            transition: none !important;
            animation: none !important;
        }

        /* Light mode defaults - applied immediately */
        html {
            background-color: #f0f9ff;
        }
        html[data-theme="dark"] {
            background-color: #0a1628;
        }
        body {
            background-color: #f0f9ff;
            color: #0c4a6e;
            margin: 0;
            padding: 0;
        }
        html[data-theme="dark"] body {
            background-color: #0a1628;
            color: #f0f9ff;
        }

        /* Prevent sidebar flicker */
        .sidebar {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
        }
        html[data-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #1e3a8a 0%, #0c1f3a 100%);
        }

        /* Prevent card flicker */
        .card {
            background-color: #ffffff;
            color: #0c4a6e;
        }
        html[data-theme="dark"] .card {
            background-color: #0f2744;
            color: #f0f9ff;
        }

        /* Prevent table flicker */
        html[data-theme="dark"] .table {
            background-color: #0f2744;
            color: #f0f9ff;
        }
    </style>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=20260126c" rel="stylesheet">

    <?php if (isset($extra_css)): ?>
    <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="wrapper">