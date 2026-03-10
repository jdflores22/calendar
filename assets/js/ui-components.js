/**
 * TESDA Calendar System - UI Components
 * Enhanced Tailwind UI components with JavaScript interactions
 */

// Global Mobile Sidebar Function - Available immediately
window.toggleMobileSidebar = function() {
    const overlay = document.getElementById('mobile-sidebar-overlay');
    const panel = document.getElementById('mobile-sidebar-panel');
    
    if (!overlay || !panel) {
        console.warn('Mobile sidebar elements not found');
        return;
    }
    
    if (overlay.classList.contains('hidden')) {
        // Show sidebar
        overlay.classList.remove('hidden');
        setTimeout(() => {
            panel.classList.remove('-translate-x-full');
        }, 10);
        document.body.classList.add('overflow-hidden');
    } else {
        // Hide sidebar
        panel.classList.add('-translate-x-full');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
        document.body.classList.remove('overflow-hidden');
    }
};

// Polyfill for Element.closest() for older browsers
if (!Element.prototype.closest) {
    Element.prototype.closest = function(selector) {
        let element = this;
        while (element && element.nodeType === Node.ELEMENT_NODE) {
            if (element.matches(selector)) {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    };
}

// Polyfill for Element.matches() for older browsers
if (!Element.prototype.matches) {
    Element.prototype.matches = Element.prototype.msMatchesSelector || 
                                Element.prototype.webkitMatchesSelector;
}

// Global error handler for UI components
window.addEventListener('error', function(e) {
    if (e.filename && e.filename.includes('ui-components')) {
        console.warn('UI Component Error caught and handled:', e.message);
        // Prevent the error from breaking the entire page
        e.preventDefault();
        return true;
    }
});

// Global unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(e) {
    console.warn('Unhandled promise rejection in UI components:', e.reason);
    e.preventDefault();
});

// Toast Notification System
class ToastManager {
    constructor() {
        this.container = this.createContainer();
        this.toasts = new Map();
    }

    createContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
        return container;
    }

    show(message, type = 'info', duration = 5000) {
        const id = Date.now().toString();
        const toast = this.createToast(id, message, type);
        
        this.container.appendChild(toast);
        this.toasts.set(id, toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        });

        // Auto dismiss
        if (duration > 0) {
            setTimeout(() => this.dismiss(id), duration);
        }

        return id;
    }

    createToast(id, message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} transform translate-x-full opacity-0 transition-all duration-300 ease-in-out`;
        toast.dataset.toastId = id;

        const iconMap = {
            success: `<svg class="h-5 w-5 text-success-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>`,
            error: `<svg class="h-5 w-5 text-danger-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>`,
            warning: `<svg class="h-5 w-5 text-warning-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>`,
            info: `<svg class="h-5 w-5 text-tesda-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>`
        };

        toast.innerHTML = `
            <div class="flex items-start p-4">
                <div class="flex-shrink-0">
                    ${iconMap[type] || iconMap.info}
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-900">${message}</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <button class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition-colors duration-200" onclick="window.toastManager.dismiss('${id}')">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        `;

        return toast;
    }

    dismiss(id) {
        const toast = this.toasts.get(id);
        if (!toast) return;

        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-full', 'opacity-0');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            this.toasts.delete(id);
        }, 300);
    }

    success(message, duration) {
        return this.show(message, 'success', duration);
    }

    error(message, duration) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
}

// Modal System
class ModalManager {
    constructor() {
        this.activeModal = null;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeModal) {
                this.close();
            }
        });
    }

    open(modalId, options = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        this.activeModal = modal;
        
        // Show modal
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Animate in
        requestAnimationFrame(() => {
            const overlay = modal.querySelector('.modal-overlay');
            const container = modal.querySelector('.modal-container');
            
            if (overlay) {
                overlay.classList.remove('opacity-0');
                overlay.classList.add('opacity-100');
            }
            
            if (container) {
                container.classList.remove('scale-95', 'opacity-0');
                container.classList.add('scale-100', 'opacity-100');
            }
        });

        // Focus management
        const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }

        // Callback
        if (options.onOpen) {
            options.onOpen(modal);
        }
    }

    close(callback) {
        if (!this.activeModal) return;

        const modal = this.activeModal;
        const overlay = modal.querySelector('.modal-overlay');
        const container = modal.querySelector('.modal-container');

        // Animate out
        if (overlay) {
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');
        }
        
        if (container) {
            container.classList.remove('scale-100', 'opacity-100');
            container.classList.add('scale-95', 'opacity-0');
        }

        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            this.activeModal = null;
            
            if (callback) callback();
        }, 200);
    }
}

// Dropdown System
class DropdownManager {
    constructor() {
        this.activeDropdown = null;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            // Ensure e.target is valid before using it
            if (this.activeDropdown && e.target && e.target.nodeType === Node.ELEMENT_NODE && !this.activeDropdown.contains(e.target)) {
                this.close();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeDropdown) {
                this.close();
            }
        });
    }

    toggle(dropdownId) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;

        if (this.activeDropdown === dropdown) {
            this.close();
        } else {
            this.open(dropdown);
        }
    }

    open(dropdown) {
        this.close(); // Close any active dropdown
        
        this.activeDropdown = dropdown;
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (menu) {
            menu.classList.remove('hidden', 'scale-95', 'opacity-0');
            menu.classList.add('scale-100', 'opacity-100');
        }
    }

    close() {
        if (!this.activeDropdown) return;

        const menu = this.activeDropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.classList.remove('scale-100', 'opacity-100');
            menu.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                menu.classList.add('hidden');
            }, 100);
        }

        this.activeDropdown = null;
    }
}

// Tab System
class TabManager {
    static init() {
        document.querySelectorAll('[data-tab-target]').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(tab);
            });
        });
    }

    static switchTab(activeTab) {
        // Safety check
        if (!activeTab || !activeTab.dataset) return;
        
        const targetId = activeTab.dataset.tabTarget;
        if (!targetId) return;
        
        const tabGroup = activeTab.closest('[data-tab-group]');
        
        if (!tabGroup) return;

        // Update tab states
        tabGroup.querySelectorAll('[data-tab-target]').forEach(tab => {
            tab.classList.remove('tabs-tab-active');
            tab.classList.add('tabs-tab');
        });
        
        activeTab.classList.remove('tabs-tab');
        activeTab.classList.add('tabs-tab-active');

        // Update panel states
        tabGroup.querySelectorAll('[data-tab-panel]').forEach(panel => {
            panel.classList.remove('tabs-panel-active');
            panel.classList.add('tabs-panel');
        });

        const targetPanel = document.getElementById(targetId);
        if (targetPanel) {
            targetPanel.classList.remove('tabs-panel');
            targetPanel.classList.add('tabs-panel-active');
        }
    }
}

// Accordion System
class AccordionManager {
    static init() {
        document.querySelectorAll('[data-accordion-toggle]').forEach(toggle => {
            toggle.addEventListener('click', () => {
                this.toggle(toggle);
            });
        });
    }

    static toggle(toggle) {
        const targetId = toggle.dataset.accordionToggle;
        const content = document.getElementById(targetId);
        const icon = toggle.querySelector('.accordion-icon');
        
        if (!content) return;

        const isOpen = !content.classList.contains('hidden');
        
        if (isOpen) {
            // Close
            content.classList.add('hidden');
            if (icon) {
                icon.classList.remove('accordion-icon-open');
            }
        } else {
            // Open
            content.classList.remove('hidden');
            if (icon) {
                icon.classList.add('accordion-icon-open');
            }
        }
    }
}

// Loading States
class LoadingManager {
    static show(element, text = 'Loading...') {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (!element) return;

        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="flex flex-col items-center">
                <div class="spinner-primary spinner-lg mb-4"></div>
                <p class="text-sm text-gray-600">${text}</p>
            </div>
        `;
        
        element.style.position = 'relative';
        element.appendChild(overlay);
    }

    static hide(element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (!element) return;

        const overlay = element.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
}

// Form Validation
class FormValidator {
    constructor(form, rules = {}) {
        this.form = typeof form === 'string' ? document.getElementById(form) : form;
        this.rules = rules;
        this.errors = {};
        
        if (this.form) {
            this.setupEventListeners();
        }
    }

    setupEventListeners() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });

        // Real-time validation
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('blur', () => {
                    this.validateField(fieldName);
                });
                
                field.addEventListener('input', () => {
                    this.clearFieldError(fieldName);
                });
            }
        });
    }

    validate() {
        this.errors = {};
        let isValid = true;

        Object.keys(this.rules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const rules = this.rules[fieldName];
        
        if (!field || !rules) return true;

        const value = field.value.trim();
        
        // Required validation
        if (rules.required && !value) {
            this.setFieldError(fieldName, rules.required.message || 'This field is required');
            return false;
        }

        // Min length validation
        if (rules.minLength && value.length < rules.minLength.value) {
            this.setFieldError(fieldName, rules.minLength.message || `Minimum ${rules.minLength.value} characters required`);
            return false;
        }

        // Email validation
        if (rules.email && value && !this.isValidEmail(value)) {
            this.setFieldError(fieldName, rules.email.message || 'Please enter a valid email address');
            return false;
        }

        // Custom validation
        if (rules.custom && !rules.custom.validate(value)) {
            this.setFieldError(fieldName, rules.custom.message || 'Invalid value');
            return false;
        }

        this.clearFieldError(fieldName);
        return true;
    }

    setFieldError(fieldName, message) {
        this.errors[fieldName] = message;
        
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const errorElement = this.form.querySelector(`[data-error="${fieldName}"]`);
        
        if (field) {
            field.classList.add('form-input-error');
            field.classList.remove('form-input-success');
        }
        
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
        }
    }

    clearFieldError(fieldName) {
        delete this.errors[fieldName];
        
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const errorElement = this.form.querySelector(`[data-error="${fieldName}"]`);
        
        if (field) {
            field.classList.remove('form-input-error');
        }
        
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
        }
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
}

// Tooltip System
class TooltipManager {
    constructor() {
        this.tooltips = new Map();
        this.init();
    }

    init() {
        document.addEventListener('mouseenter', (e) => {
            // Ensure e.target is an Element and has closest method
            if (e.target && e.target.nodeType === Node.ELEMENT_NODE && typeof e.target.closest === 'function') {
                const element = e.target.closest('[data-tooltip]');
                if (element) {
                    this.show(element);
                }
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            // Ensure e.target is an Element and has closest method
            if (e.target && e.target.nodeType === Node.ELEMENT_NODE && typeof e.target.closest === 'function') {
                const element = e.target.closest('[data-tooltip]');
                if (element) {
                    this.hide(element);
                }
            }
        }, true);
    }

    show(element) {
        const text = element.dataset.tooltip;
        const position = element.dataset.tooltipPosition || 'top';
        
        if (!text) return;

        const tooltip = this.create(text, position);
        this.position(tooltip, element, position);
        
        document.body.appendChild(tooltip);
        this.tooltips.set(element, tooltip);

        // Animate in
        requestAnimationFrame(() => {
            tooltip.classList.remove('opacity-0', 'scale-95');
            tooltip.classList.add('opacity-100', 'scale-100');
        });
    }

    hide(element) {
        const tooltip = this.tooltips.get(element);
        if (!tooltip) return;

        tooltip.classList.remove('opacity-100', 'scale-100');
        tooltip.classList.add('opacity-0', 'scale-95');

        setTimeout(() => {
            if (tooltip.parentNode) {
                tooltip.parentNode.removeChild(tooltip);
            }
            this.tooltips.delete(element);
        }, 150);
    }

    create(text, position) {
        const tooltip = document.createElement('div');
        tooltip.className = `tooltip tooltip-${position} opacity-0 scale-95 transition-all duration-150`;
        tooltip.innerHTML = `
            <div class="tooltip-content">${text}</div>
            <div class="tooltip-arrow"></div>
        `;
        return tooltip;
    }

    position(tooltip, element, position) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top, left;
        
        switch (position) {
            case 'top':
                top = rect.top - tooltipRect.height - 8;
                left = rect.left + (rect.width - tooltipRect.width) / 2;
                break;
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
        }
        
        tooltip.style.position = 'fixed';
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
        tooltip.style.zIndex = '9999';
    }
}

// Enhanced Data Table with Search and Sort
class DataTable {
    constructor(tableId, options = {}) {
        this.table = document.getElementById(tableId);
        this.options = {
            searchable: true,
            sortable: true,
            pagination: true,
            pageSize: 10,
            ...options
        };
        
        this.data = [];
        this.filteredData = [];
        this.currentPage = 1;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        
        if (this.table) {
            this.init();
        }
    }

    init() {
        this.extractData();
        this.setupSearch();
        this.setupSort();
        this.setupPagination();
    }

    extractData() {
        const rows = this.table.querySelectorAll('tbody tr');
        this.data = Array.from(rows).map(row => {
            const cells = row.querySelectorAll('td');
            return {
                element: row,
                data: Array.from(cells).map(cell => cell.textContent.trim())
            };
        });
        this.filteredData = [...this.data];
    }

    setupSearch() {
        if (!this.options.searchable) return;
        
        // Safety check for table and closest method
        if (!this.table || typeof this.table.closest !== 'function') return;
        
        const searchInput = this.table.closest('.datatable')?.querySelector('.datatable-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                // Safety check for event target
                if (e && e.target && typeof e.target.value !== 'undefined') {
                    this.search(e.target.value);
                }
            });
        }
    }

    search(query) {
        if (!query.trim()) {
            this.filteredData = [...this.data];
        } else {
            this.filteredData = this.data.filter(row => 
                row.data.some(cell => 
                    cell.toLowerCase().includes(query.toLowerCase())
                )
            );
        }
        this.currentPage = 1;
        this.render();
    }

    setupSort() {
        if (!this.options.sortable) return;
        
        const headers = this.table.querySelectorAll('.table-header-cell-sortable');
        headers.forEach((header, index) => {
            header.addEventListener('click', () => {
                this.sort(index);
            });
        });
    }

    sort(columnIndex) {
        if (this.sortColumn === columnIndex) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = columnIndex;
            this.sortDirection = 'asc';
        }

        this.filteredData.sort((a, b) => {
            const aValue = a.data[columnIndex];
            const bValue = b.data[columnIndex];
            
            const comparison = aValue.localeCompare(bValue, undefined, { numeric: true });
            return this.sortDirection === 'asc' ? comparison : -comparison;
        });

        this.render();
    }

    render() {
        const tbody = this.table.querySelector('tbody');
        tbody.innerHTML = '';

        const startIndex = (this.currentPage - 1) * this.options.pageSize;
        const endIndex = startIndex + this.options.pageSize;
        const pageData = this.filteredData.slice(startIndex, endIndex);

        pageData.forEach(row => {
            tbody.appendChild(row.element);
        });

        this.updatePagination();
    }

    setupPagination() {
        if (!this.options.pagination) return;
        // Pagination setup would go here
    }

    updatePagination() {
        // Update pagination controls
        const totalPages = Math.ceil(this.filteredData.length / this.options.pageSize);
        // Implementation would update pagination UI
    }
}

// Theme Manager
class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('tesda-theme') || 'light';
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.setupToggle();
    }

    setupToggle() {
        const toggles = document.querySelectorAll('[data-theme-toggle]');
        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                this.toggle();
            });
        });
    }

    toggle() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(this.currentTheme);
        localStorage.setItem('tesda-theme', this.currentTheme);
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update toggle buttons
        const toggles = document.querySelectorAll('[data-theme-toggle]');
        toggles.forEach(toggle => {
            const icon = toggle.querySelector('.theme-icon');
            if (icon) {
                icon.innerHTML = theme === 'light' 
                    ? '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>'
                    : '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>';
            }
        });
    }
}

// Keyboard Navigation Manager
class KeyboardNavigation {
    constructor() {
        this.focusableElements = [
            'button',
            '[href]',
            'input',
            'select',
            'textarea',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');
        
        this.init();
    }

    init() {
        document.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });
    }

    handleKeydown(e) {
        // Handle escape key for modals and dropdowns
        if (e.key === 'Escape') {
            this.handleEscape();
        }
        
        // Handle arrow keys for navigation
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            this.handleArrowKeys(e);
        }
        
        // Handle enter and space for activation
        if (e.key === 'Enter' || e.key === ' ') {
            this.handleActivation(e);
        }
    }

    handleEscape() {
        // Close active modal
        if (window.modalManager && window.modalManager.activeModal) {
            window.modalManager.close();
        }
        
        // Close active dropdown
        if (window.dropdownManager && window.dropdownManager.activeDropdown) {
            window.dropdownManager.close();
        }
    }

    handleArrowKeys(e) {
        const activeElement = document.activeElement;
        
        // Ensure activeElement is an Element and has closest method
        if (!activeElement || activeElement.nodeType !== Node.ELEMENT_NODE || typeof activeElement.closest !== 'function') {
            return;
        }
        
        // Handle dropdown navigation
        if (activeElement.closest('.dropdown-menu')) {
            e.preventDefault();
            this.navigateDropdown(e.key, activeElement);
        }
        
        // Handle tab navigation
        if (activeElement.closest('.tabs-list')) {
            e.preventDefault();
            this.navigateTabs(e.key, activeElement);
        }
    }

    navigateDropdown(key, activeElement) {
        // Safety check
        if (!activeElement || typeof activeElement.closest !== 'function') return;
        
        const dropdown = activeElement.closest('.dropdown-menu');
        if (!dropdown) return;
        
        const items = dropdown.querySelectorAll('.dropdown-item');
        const currentIndex = Array.from(items).indexOf(activeElement);
        
        let nextIndex;
        if (key === 'ArrowDown') {
            nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        } else if (key === 'ArrowUp') {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }
        
        if (nextIndex !== undefined && items[nextIndex]) {
            items[nextIndex].focus();
        }
    }

    navigateTabs(key, activeElement) {
        // Safety check
        if (!activeElement || typeof activeElement.closest !== 'function') return;
        
        const tabsList = activeElement.closest('.tabs-list');
        if (!tabsList) return;
        
        const tabs = tabsList.querySelectorAll('[data-tab-target]');
        const currentIndex = Array.from(tabs).indexOf(activeElement);
        
        let nextIndex;
        if (key === 'ArrowRight') {
            nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
        } else if (key === 'ArrowLeft') {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
        }
        
        if (nextIndex !== undefined) {
            tabs[nextIndex].focus();
            tabs[nextIndex].click();
        }
    }

    handleActivation(e) {
        const activeElement = document.activeElement;
        
        // Handle custom button activation
        if (activeElement.hasAttribute('data-action')) {
            e.preventDefault();
            activeElement.click();
        }
    }
}

// Mobile Sidebar Manager - Enhanced global function for all pages
class MobileSidebarManager {
    constructor() {
        this.overlay = null;
        this.panel = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        // Find sidebar elements
        this.overlay = document.getElementById('mobile-sidebar-overlay');
        this.panel = document.getElementById('mobile-sidebar-panel');
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Enhance the existing global function
        const originalToggle = window.toggleMobileSidebar;
        window.toggleMobileSidebar = () => {
            this.toggle();
        };
    }

    setupEventListeners() {
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });

        // Close when clicking outside (if overlay exists)
        if (this.overlay) {
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.close();
                }
            });
        }
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        if (!this.overlay || !this.panel) {
            console.warn('Mobile sidebar elements not found');
            return;
        }

        this.isOpen = true;
        
        // Show overlay
        this.overlay.classList.remove('hidden');
        
        // Animate panel in
        setTimeout(() => {
            this.panel.classList.remove('-translate-x-full');
        }, 10);

        // Prevent body scroll
        document.body.classList.add('overflow-hidden');
    }

    close() {
        if (!this.overlay || !this.panel) {
            return;
        }

        this.isOpen = false;
        
        // Animate panel out
        this.panel.classList.add('-translate-x-full');
        
        // Hide overlay after animation
        setTimeout(() => {
            this.overlay.classList.add('hidden');
        }, 300);

        // Restore body scroll
        document.body.classList.remove('overflow-hidden');
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    try {
        console.log('TESDA Calendar System - Enhanced UI Components Loaded! 🎉');
        
        // Initialize all UI components
        window.toastManager = new ToastManager();
        window.modalManager = new ModalManager();
        window.dropdownManager = new DropdownManager();
        window.tooltipManager = new TooltipManager();
        window.themeManager = new ThemeManager();
        window.keyboardNavigation = new KeyboardNavigation();
        window.mobileSidebarManager = new MobileSidebarManager();
    
    // Initialize component systems
    TabManager.init();
    AccordionManager.init();
    
    // Initialize data tables
    document.querySelectorAll('[data-datatable]').forEach(table => {
        new DataTable(table.id, {
            searchable: table.dataset.searchable !== 'false',
            sortable: table.dataset.sortable !== 'false',
            pagination: table.dataset.pagination !== 'false',
            pageSize: parseInt(table.dataset.pageSize) || 10
        });
    });
    
    // Initialize form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        const rules = JSON.parse(form.dataset.validate || '{}');
        new FormValidator(form, rules);
    });
    
    // Initialize loading states for AJAX requests
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function(...args) {
            LoadingManager.show(document.body, 'Loading...');
            return originalFetch.apply(this, args)
                .finally(() => {
                    LoadingManager.hide(document.body);
                });
        };
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.datatable-search-input, input[type="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to close modals/dropdowns
        if (e.key === 'Escape') {
            if (window.modalManager?.activeModal) {
                window.modalManager.close();
            }
            if (window.dropdownManager?.activeDropdown) {
                window.dropdownManager.close();
            }
        }
    });
    
    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert:not(.alert-dismissible)').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Initialize smooth scrolling for anchor links
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
    
    // Add ripple effect to buttons
    document.querySelectorAll('.btn, button').forEach(button => {
        button.addEventListener('click', function(e) {
            try {
                // Ensure we have valid event and element
                if (!e || !e.clientX || !e.clientY || !this.getBoundingClientRect) return;
                
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    if (ripple.parentNode) {
                        ripple.remove();
                    }
                }, 600);
            } catch (error) {
                console.warn('Ripple effect error:', error);
            }
        });
    });
    
    console.log('TESDA Calendar UI Components initialized successfully');
    
    } catch (error) {
        console.error('Error initializing TESDA Calendar UI Components:', error);
        // Fallback: ensure basic functionality still works
        if (typeof window.TesdaCalendar === 'undefined') {
            window.TesdaCalendar = {
                showNotification: function(message, type = 'info') {
                    console.log(`${type.toUpperCase()}: ${message}`);
                },
                showLoading: function() { console.log('Loading...'); },
                hideLoading: function() { console.log('Loading complete'); }
            };
        }
    }
});

// Add ripple effect styles
const rippleStyles = document.createElement('style');
rippleStyles.textContent = `
    .btn, button {
        position: relative;
        overflow: hidden;
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyles);

// Export for module usage
export {
    ToastManager,
    ModalManager,
    DropdownManager,
    TabManager,
    AccordionManager,
    LoadingManager,
    FormValidator
};