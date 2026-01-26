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

    // Handle Primary Purpose and Secondary Purpose interaction
    initPrimarySecondaryPurpose();

    // Initialize Multi-Select Dropdowns
    initMultiSelectDropdowns();
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

// Primary and Secondary Purpose Interaction
function initPrimarySecondaryPurpose() {
    const primaryRadios = document.querySelectorAll('input[name="primary_purpose"]');

    if (primaryRadios.length === 0) return;

    // Function to update secondary checkboxes based on selected primary radio in same row
    function updateSecondaryCheckboxes() {
        const secondaryCheckboxes = document.querySelectorAll('input[name="secondary_purpose[]"]');

        // First, enable all secondary checkboxes and remove disabled styling
        secondaryCheckboxes.forEach(checkbox => {
            checkbox.disabled = false;

            // Remove disabled class from parent cell
            const parentCell = checkbox.closest('td');
            if (parentCell) parentCell.classList.remove('disabled-option');
        });

        // Now, for each primary radio that is checked, disable its corresponding secondary checkbox
        primaryRadios.forEach(radio => {
            if (radio.checked) {
                // Find the secondary checkbox in the same row
                const row = radio.closest('tr');
                if (row) {
                    const secondaryCheckbox = row.querySelector('input[name="secondary_purpose[]"]');
                    if (secondaryCheckbox) {
                        // Disable the secondary checkbox in this row
                        secondaryCheckbox.disabled = true;
                        secondaryCheckbox.checked = false;

                        // Add disabled class to parent cell for visual feedback
                        const parentCell = secondaryCheckbox.closest('td');
                        if (parentCell) parentCell.classList.add('disabled-option');
                    }
                }
            }
        });
    }

    // Add event listeners to all primary radio buttons
    primaryRadios.forEach(radio => {
        radio.addEventListener('change', updateSecondaryCheckboxes);
    });

    // Run on page load to handle any pre-selected values
    updateSecondaryCheckboxes();
}

// Multi-Select Dropdown Functionality
function initMultiSelectDropdowns() {
    const dropdowns = document.querySelectorAll('.multi-select-dropdown');

    dropdowns.forEach(dropdown => {
        const selected = dropdown.querySelector('.dropdown-selected');
        const optionsContainer = dropdown.querySelector('.dropdown-options');
        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');

        if (!selected || !optionsContainer) return;

        // Toggle dropdown on click
        selected.addEventListener('click', function(e) {
            e.stopPropagation();

            // Close other dropdowns
            document.querySelectorAll('.multi-select-dropdown .dropdown-options').forEach(opt => {
                if (opt !== optionsContainer) {
                    opt.classList.remove('show');
                }
            });
            document.querySelectorAll('.multi-select-dropdown .dropdown-selected').forEach(sel => {
                if (sel !== selected) {
                    sel.classList.remove('active');
                }
            });

            // Toggle current dropdown
            optionsContainer.classList.toggle('show');
            selected.classList.toggle('active');
        });

        // Update selected display when checkboxes change
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function(e) {
                e.stopPropagation();
                updateSelectedDisplay(dropdown);
            });
        });

        // Prevent dropdown from closing when clicking inside options
        optionsContainer.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Initialize display
        updateSelectedDisplay(dropdown);
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.multi-select-dropdown .dropdown-options').forEach(opt => {
            opt.classList.remove('show');
        });
        document.querySelectorAll('.multi-select-dropdown .dropdown-selected').forEach(sel => {
            sel.classList.remove('active');
        });
    });
}

function updateSelectedDisplay(dropdown) {
    const selected = dropdown.querySelector('.dropdown-selected');
    const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:checked');

    // Remove existing content
    const existingItems = selected.querySelector('.selected-items');
    const existingPlaceholder = selected.querySelector('.placeholder');

    if (existingItems) existingItems.remove();
    if (existingPlaceholder) existingPlaceholder.remove();

    // Get the chevron icon
    const chevron = selected.querySelector('i');

    if (checkboxes.length > 0) {
        // Create container for selected items
        const itemsContainer = document.createElement('div');
        itemsContainer.className = 'selected-items';

        checkboxes.forEach(checkbox => {
            const tag = document.createElement('span');
            tag.className = 'selected-tag';
            tag.textContent = checkbox.nextElementSibling.textContent;
            itemsContainer.appendChild(tag);
        });

        // Insert before chevron
        selected.insertBefore(itemsContainer, chevron);
    } else {
        // Show placeholder
        const placeholder = document.createElement('span');
        placeholder.className = 'placeholder';
        placeholder.textContent = 'Select age ranges';
        selected.insertBefore(placeholder, chevron);
    }
}
