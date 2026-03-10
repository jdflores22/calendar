import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './js/ui-components.js';

console.log('TESDA Calendar System - Enhanced UI Components Loaded! 🎉');

// Initialize enhanced UI features
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize smooth scrolling
    initializeSmoothScrolling();
    
    // Initialize enhanced form interactions
    initializeFormEnhancements();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize theme management
    initializeThemeManager();
});

// Tooltip initialization
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(event) {
    const element = event.target;
    const text = element.dataset.tooltip;
    const position = element.dataset.tooltipPosition || 'top';
    
    const tooltip = document.createElement('div');
    tooltip.className = `absolute z-50 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg pointer-events-none transition-opacity duration-200 opacity-0`;
    tooltip.textContent = text;
    tooltip.id = 'tooltip';
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();
    
    let top, left;
    
    switch (position) {
        case 'bottom':
            top = rect.bottom + 8;
            left = rect.left + (rect.width - tooltipRect.width) / 2;
            break;
        case 'left':
            top = rect.top + (rect.height - tooltipRect.height) / 2;
            left = rect.left - tooltipRect.width - 8;
            break;
        case 'right':
            top = rect.top + (rect.height - tooltipRect.height) / 2;
            left = rect.right + 8;
            break;
        default: // top
            top = rect.top - tooltipRect.height - 8;
            left = rect.left + (rect.width - tooltipRect.width) / 2;
    }
    
    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
    
    // Animate in
    requestAnimationFrame(() => {
        tooltip.classList.remove('opacity-0');
        tooltip.classList.add('opacity-100');
    });
}

function hideTooltip() {
    const tooltip = document.getElementById('tooltip');
    if (tooltip) {
        tooltip.classList.remove('opacity-100');
        tooltip.classList.add('opacity-0');
        setTimeout(() => tooltip.remove(), 200);
    }
}

// Smooth scrolling for anchor links
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Enhanced form interactions
function initializeFormEnhancements() {
    // Auto-resize textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    // Enhanced file inputs
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const label = this.nextElementSibling;
            if (label && this.files.length > 0) {
                const fileName = this.files.length === 1 
                    ? this.files[0].name 
                    : `${this.files.length} files selected`;
                label.textContent = fileName;
            }
        });
    });
    
    // Form validation feedback
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('form-input-error');
                    isValid = false;
                } else {
                    field.classList.remove('form-input-error');
                    field.classList.add('form-input-success');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                window.toastManager.error('Please fill in all required fields');
            }
        });
    });
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"], input[placeholder*="search" i]');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to close modals/dropdowns
        if (e.key === 'Escape') {
            // Close any open dropdowns
            document.querySelectorAll('.dropdown-menu:not(.hidden)').forEach(menu => {
                menu.classList.add('hidden');
            });
            
            // Close any open modals
            if (window.modalManager && window.modalManager.activeModal) {
                window.modalManager.close();
            }
        }
        
        // Ctrl/Cmd + Enter to submit forms
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.form) {
                activeElement.form.submit();
            }
        }
    });
}

// Theme management
function initializeThemeManager() {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Apply saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.classList.toggle('dark', savedTheme === 'dark');
    }
}

function toggleTheme() {
    const isDark = document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    // Update theme toggle icon
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (themeToggle) {
        const icon = themeToggle.querySelector('svg');
        if (icon) {
            icon.innerHTML = isDark 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />'
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />';
        }
    }
}

// Utility functions
window.utils = {
    // Format currency
    formatCurrency: (amount, currency = 'PHP') => {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },
    
    // Format date
    formatDate: (date, options = {}) => {
        const defaultOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        return new Intl.DateTimeFormat('en-PH', { ...defaultOptions, ...options }).format(new Date(date));
    },
    
    // Debounce function
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Copy to clipboard
    copyToClipboard: async (text) => {
        try {
            await navigator.clipboard.writeText(text);
            window.toastManager.success('Copied to clipboard');
        } catch (err) {
            window.toastManager.error('Failed to copy to clipboard');
        }
    },
    
    // Generate random ID
    generateId: () => {
        return Math.random().toString(36).substr(2, 9);
    },
    
    // Validate email
    isValidEmail: (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
};

// Global event handlers
window.addEventListener('online', () => {
    window.toastManager.success('Connection restored');
});

window.addEventListener('offline', () => {
    window.toastManager.warning('Connection lost');
});

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', () => {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log(`Page loaded in ${Math.round(perfData.loadEventEnd - perfData.fetchStart)}ms`);
        }, 0);
    });
}
