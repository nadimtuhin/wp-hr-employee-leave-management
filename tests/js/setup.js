/**
 * Jest setup file for WP Employee Leaves Plugin JavaScript tests
 */

import '@testing-library/jest-dom';

// Mock WordPress globals
global.wp = {
    i18n: {
        __: jest.fn((text) => text),
        _e: jest.fn((text) => text),
        sprintf: jest.fn((format, ...args) => {
            return format.replace(/%s/g, () => args.shift());
        })
    }
};

// Mock jQuery
global.$ = global.jQuery = jest.fn(() => ({
    ready: jest.fn(),
    on: jest.fn(),
    off: jest.fn(),
    click: jest.fn(),
    val: jest.fn(),
    text: jest.fn(),
    html: jest.fn(),
    attr: jest.fn(),
    prop: jest.fn(),
    addClass: jest.fn(),
    removeClass: jest.fn(),
    hasClass: jest.fn(),
    show: jest.fn(),
    hide: jest.fn(),
    fadeIn: jest.fn(),
    fadeOut: jest.fn(),
    append: jest.fn(),
    prepend: jest.fn(),
    remove: jest.fn(),
    find: jest.fn(() => ({
        val: jest.fn(),
        text: jest.fn(),
        html: jest.fn(),
        length: 0
    })),
    each: jest.fn(),
    ajax: jest.fn(),
    serialize: jest.fn(() => 'test=data'),
    serializeArray: jest.fn(() => []),
    trigger: jest.fn(),
    focus: jest.fn(),
    blur: jest.fn(),
    submit: jest.fn(),
    preventDefault: jest.fn()
}));

// Mock WordPress AJAX
global.ajaxurl = '/wp-admin/admin-ajax.php';

// Mock localized script objects
global.wp_employee_leaves_ajax = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'test-nonce',
    strings: {
        submit_success: 'Leave request submitted successfully!',
        submit_error: 'Error submitting leave request.',
        validation_error: 'Please fill in all required fields.',
        email_invalid: 'Please enter valid email addresses.',
        date_required: 'Please select at least one leave date.',
        employee_id_required: 'Employee ID is required.',
        reason_required: 'Reason is required.'
    }
};

global.wp_employee_leaves_admin = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'admin-test-nonce',
    approve_nonce: 'approve-test-nonce',
    reject_nonce: 'reject-test-nonce',
    strings: {
        page_created: 'Page created successfully!',
        page_creation_error: 'Error creating page.',
        approve_success: 'Leave request approved successfully!',
        approve_error: 'Error approving leave request.',
        reject_success: 'Leave request rejected successfully!',
        reject_error: 'Error rejecting leave request.',
        confirm_approve: 'Are you sure you want to approve this request?',
        confirm_reject: 'Are you sure you want to reject this request?'
    }
};

// Mock console methods
global.console = {
    log: jest.fn(),
    error: jest.fn(),
    warn: jest.fn(),
    info: jest.fn()
};

// Mock localStorage
const localStorageMock = (() => {
    let store = {};
    
    return {
        getItem: (key) => store[key] || null,
        setItem: (key, value) => {
            store[key] = value.toString();
        },
        removeItem: (key) => {
            delete store[key];
        },
        clear: () => {
            store = {};
        }
    };
})();

Object.defineProperty(window, 'localStorage', {
    value: localStorageMock
});

// Mock window methods
global.alert = jest.fn();
global.confirm = jest.fn(() => true);
global.prompt = jest.fn();

// Mock DOM methods
Object.defineProperty(window, 'location', {
    value: {
        href: 'http://localhost',
        assign: jest.fn(),
        reload: jest.fn()
    },
    writable: true
});

// Setup DOM
document.body.innerHTML = '';

// Mock setTimeout and setInterval
global.setTimeout = jest.fn((fn) => fn());
global.setInterval = jest.fn((fn) => fn());
global.clearTimeout = jest.fn();
global.clearInterval = jest.fn();