/**
 * Unified Email Management Portal
 * Main JavaScript
 */

// Toast notification
function showToast(type, message) {
    const container = document.querySelector('.toast-container') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);

    // Close button handler
    toast.querySelector('.btn-close').addEventListener('click', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Confirm dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX helper
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (data) {
        if (method === 'GET') {
            url += '?' + new URLSearchParams(data).toString();
        } else {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.body = new URLSearchParams(data).toString();
        }
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

// Form validation
function validateForm(form) {
    let isValid = true;

    // Clear previous errors
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });

    // Check required fields
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });

    // Check email fields
    form.querySelectorAll('input[type="email"]').forEach(field => {
        if (field.value && !isValidEmail(field.value)) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });

    return isValid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Password validation - matches server-side rules
function validatePassword(password, email = '') {
    const checks = {
        length: password.length >= 8,
        maxLength: password.length <= 100,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>\-_=+\[\]\\\/`~;'£€¥]/.test(password),
        noEmail: true
    };

    // Check if password contains username
    if (email) {
        const username = email.split('@')[0];
        if (username.length >= 3) {
            checks.noEmail = !password.toLowerCase().includes(username.toLowerCase());
        }
    }

    // Complexity: 3 of 4 required
    const complexityCount = [checks.uppercase, checks.lowercase, checks.number, checks.special]
        .filter(Boolean).length;
    checks.complexity = complexityCount >= 3;

    const errors = [];
    if (!checks.length) errors.push('At least 8 characters');
    if (!checks.maxLength) errors.push('Maximum 100 characters');
    if (!checks.complexity) errors.push('Use 3 of: uppercase, lowercase, number, special character');
    if (!checks.noEmail) errors.push('Cannot contain your username');

    return {
        valid: errors.length === 0,
        errors: errors,
        checks: checks
    };
}

// Update password requirements UI
function updatePasswordRequirementsUI(container, checks) {
    if (!container) return;

    const rules = {
        'length': checks.length,
        'complexity': checks.complexity,
        'no-email': checks.noEmail
    };

    Object.keys(rules).forEach(rule => {
        const element = container.querySelector(`[data-rule="${rule}"]`);
        if (element) {
            const icon = element.querySelector('i');
            element.classList.remove('valid', 'invalid');
            if (icon) {
                icon.classList.remove('bi-circle', 'bi-check-circle-fill', 'bi-x-circle-fill');
            }
            if (rules[rule]) {
                element.classList.add('valid');
                if (icon) icon.classList.add('bi-check-circle-fill');
            } else if (document.activeElement?.type === 'password') {
                element.classList.add('invalid');
                if (icon) icon.classList.add('bi-x-circle-fill');
            } else {
                if (icon) icon.classList.add('bi-circle');
            }
        }
    });
}

// Initialize password validation on a form
function initPasswordValidation(passwordInput, emailInput = null, requirementsContainer = null) {
    if (!passwordInput) return;

    const validate = () => {
        const password = passwordInput.value;
        const email = emailInput ? emailInput.value : '';
        const result = validatePassword(password, email);

        if (requirementsContainer) {
            updatePasswordRequirementsUI(requirementsContainer, result.checks);
        }

        return result;
    };

    passwordInput.addEventListener('input', debounce(validate, 100));
    passwordInput.addEventListener('focus', validate);

    if (emailInput) {
        emailInput.addEventListener('input', debounce(validate, 100));
    }

    return validate;
}

// Legacy function for backward compatibility
function checkPasswordStrength(password) {
    const result = validatePassword(password);
    return [result.checks.length, result.checks.lowercase, result.checks.uppercase,
            result.checks.number, result.checks.special].filter(Boolean).length;
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Copied to clipboard!');
    }).catch(() => {
        showToast('error', 'Failed to copy');
    });
}

// Loading state
function setLoading(element, loading = true) {
    if (loading) {
        element.dataset.originalText = element.innerHTML;
        element.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';
        element.disabled = true;
    } else {
        element.innerHTML = element.dataset.originalText;
        element.disabled = false;
    }
}

// Format file size
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Mobile Sidebar Toggle
    initMobileSidebar();
});

// Mobile Sidebar Functions
function initMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    // Toggle sidebar
    toggle.addEventListener('click', function() {
        toggleSidebar();
    });

    // Close on overlay click
    overlay.addEventListener('click', function() {
        closeSidebar();
    });

    // Close on nav link click (mobile)
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', debounce(function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    }, 100));
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (sidebar.classList.contains('show')) {
        closeSidebar();
    } else {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        toggle.innerHTML = '<i class="bi bi-x-lg"></i>';
        document.body.style.overflow = 'hidden';
    }
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    if (toggle) toggle.innerHTML = '<i class="bi bi-list"></i>';
    document.body.style.overflow = '';
}


// ===== Theme Management =====
const Theme = {
    LIGHT: 'light',
    DARK: 'dark',
    STORAGE_KEY: 'theme-preference'
};

// Initialize theme on page load
function initTheme() {
    const html = document.documentElement;

    // Check if theme was already initialized by blocking script in header
    const alreadyInitialized = html.getAttribute('data-theme-initialized') === 'true';

    if (!alreadyInitialized) {
        // Fallback: apply theme if blocking script didn't run
        const savedTheme = localStorage.getItem(Theme.STORAGE_KEY);
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = savedTheme || (systemPrefersDark ? Theme.DARK : Theme.LIGHT);

        html.classList.add('no-transition');
        setTheme(theme, false);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                html.classList.remove('no-transition');
            });
        });
    }

    // Update theme toggle button to match current theme
    updateThemeToggleButton();

    // Setup theme toggle button
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem(Theme.STORAGE_KEY)) {
            setTheme(e.matches ? Theme.DARK : Theme.LIGHT);
        }
    });
}

// Set theme
function setTheme(theme, savePreference = true) {
    const html = document.documentElement;

    // Apply theme changes immediately and synchronously to prevent flicker
    if (theme === Theme.DARK) {
        html.setAttribute('data-theme', 'dark');
    } else {
        html.removeAttribute('data-theme');
    }

    // Update theme toggle button
    updateThemeToggleButton();

    if (savePreference) {
        localStorage.setItem(Theme.STORAGE_KEY, theme);
    }
}

// Update theme toggle button UI to match current theme
function updateThemeToggleButton() {
    const currentTheme = getCurrentTheme();
    const themeIcon = document.getElementById('themeIcon');
    const themeToggle = document.getElementById('themeToggle');

    if (currentTheme === Theme.DARK) {
        if (themeIcon) {
            themeIcon.className = 'bi bi-sun-fill';
        }
        if (themeToggle) {
            const span = themeToggle.querySelector('span');
            if (span) {
                span.textContent = 'Light';
            }
        }
    } else {
        if (themeIcon) {
            themeIcon.className = 'bi bi-moon-fill';
        }
        if (themeToggle) {
            const span = themeToggle.querySelector('span');
            if (span) {
                span.textContent = 'Dark';
            }
        }
    }
}

// Toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? Theme.LIGHT : Theme.DARK;
    setTheme(newTheme);
}

// Get current theme
function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? Theme.DARK : Theme.LIGHT;
}

// Initialize theme as soon as possible
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}

// ===== Form Conditional Logic =====

// CMS Content Management conditional fields
document.addEventListener('DOMContentLoaded', function() {
    const cmsYes = document.getElementById('cms_yes');
    const cmsNo = document.getElementById('cms_no');
    const contentTypesSection = document.getElementById('cms_content_types_section');
    const cmsSolutionSection = document.getElementById('cms_solution_section');

    if (cmsYes && cmsNo && contentTypesSection && cmsSolutionSection) {
        function toggleCMSFields() {
            if (cmsYes.checked) {
                contentTypesSection.style.display = 'block';
                cmsSolutionSection.style.display = 'block';
            } else {
                contentTypesSection.style.display = 'none';
                cmsSolutionSection.style.display = 'none';
            }
        }

        // Add event listeners
        cmsYes.addEventListener('change', toggleCMSFields);
        cmsNo.addEventListener('change', toggleCMSFields);

        // Initialize on page load
        toggleCMSFields();
    }

    // Domain owned conditional field
    const domYes = document.getElementById('dom_yes');
    const domNo = document.getElementById('dom_no');
    const domainNameSection = document.getElementById('domain_name_section');

    if (domYes && domNo && domainNameSection) {
        function toggleDomainNameField() {
            if (domYes.checked) {
                domainNameSection.style.display = 'block';
            } else {
                domainNameSection.style.display = 'none';
            }
        }

        domYes.addEventListener('change', toggleDomainNameField);
        domNo.addEventListener('change', toggleDomainNameField);
        toggleDomainNameField();
    }

    // Hosting account exists conditional field
    const hostYes = document.getElementById('host_yes');
    const hostNo = document.getElementById('host_no');
    const hostingProviderSection = document.getElementById('hosting_provider_section');

    if (hostYes && hostNo && hostingProviderSection) {
        function toggleHostingProviderField() {
            if (hostYes.checked) {
                hostingProviderSection.style.display = 'block';
            } else {
                hostingProviderSection.style.display = 'none';
            }
        }

        hostYes.addEventListener('change', toggleHostingProviderField);
        hostNo.addEventListener('change', toggleHostingProviderField);
        toggleHostingProviderField();
    }

    // Webmail preference Other conditional field
    const webmailRc = document.getElementById('webmail_rc');
    const webmailOther = document.getElementById('webmail_other');
    const webmailOtherField = document.getElementById('webmail_other_field');

    if (webmailRc && webmailOther && webmailOtherField) {
        function toggleWebmailOtherField() {
            if (webmailOther.checked) {
                webmailOtherField.style.display = 'block';
            } else {
                webmailOtherField.style.display = 'none';
            }
        }

        webmailRc.addEventListener('change', toggleWebmailOtherField);
        webmailOther.addEventListener('change', toggleWebmailOtherField);
        toggleWebmailOtherField();
    }
});
