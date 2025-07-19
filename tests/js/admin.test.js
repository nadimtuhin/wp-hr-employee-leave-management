/**
 * Admin JavaScript tests for WP Employee Leaves Plugin
 */

// Mock the admin script functionality
const mockAdminScript = `
// Mock admin script functions for testing
function createLeavePage(pageTitle) {
    return new Promise((resolve, reject) => {
        if (!pageTitle || pageTitle.trim() === '') {
            reject(new Error('Page title is required'));
            return;
        }
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'create_leave_page',
                page_title: pageTitle,
                nonce: wp_employee_leaves_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error(error));
            }
        });
    });
}

function approveLeaveRequest(requestId, comment) {
    return new Promise((resolve, reject) => {
        if (!requestId) {
            reject(new Error('Request ID is required'));
            return;
        }
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'approve_leave_request',
                request_id: requestId,
                comment: comment || '',
                nonce: wp_employee_leaves_admin.approve_nonce
            },
            success: function(response) {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error(error));
            }
        });
    });
}

function rejectLeaveRequest(requestId, comment) {
    return new Promise((resolve, reject) => {
        if (!requestId) {
            reject(new Error('Request ID is required'));
            return;
        }
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'reject_leave_request',
                request_id: requestId,
                comment: comment || '',
                nonce: wp_employee_leaves_admin.reject_nonce
            },
            success: function(response) {
                if (response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                reject(new Error(error));
            }
        });
    });
}

function validatePageTitle(title) {
    if (!title || title.trim() === '') {
        return { valid: false, error: 'Page title is required' };
    }
    
    if (title.length > 200) {
        return { valid: false, error: 'Page title is too long' };
    }
    
    // Check for invalid characters
    const invalidChars = /[<>]/;
    if (invalidChars.test(title)) {
        return { valid: false, error: 'Page title contains invalid characters' };
    }
    
    return { valid: true };
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = \`notice notice-\${type}\`;
    notification.innerHTML = \`<p>\${escapeHtml(message)}</p>\`;
    
    const container = document.querySelector('.wrap h1') || document.body;
    container.parentNode.insertBefore(notification, container.nextSibling);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

function updateRequestRow(requestId, status) {
    const row = document.querySelector(\`tr[data-request-id="\${requestId}"]\`);
    if (row) {
        const statusCell = row.querySelector('.status');
        if (statusCell) {
            statusCell.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusCell.className = \`status status-\${status}\`;
        }
        
        // Hide action buttons for non-pending requests
        if (status !== 'pending') {
            const actionButtons = row.querySelectorAll('.approve-btn, .reject-btn');
            actionButtons.forEach(btn => btn.style.display = 'none');
        }
    }
}

// Export functions for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        createLeavePage,
        approveLeaveRequest,
        rejectLeaveRequest,
        validatePageTitle,
        escapeHtml,
        showNotification,
        updateRequestRow
    };
}
`;

// Execute the mock script
eval(mockAdminScript);

describe('Admin JavaScript Functions', () => {
    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = '';
        
        // Reset jQuery mocks
        jest.clearAllMocks();
        
        // Setup basic admin structure
        document.body.innerHTML = `
            <div class="wrap">
                <h1>WP Employee Leaves</h1>
                <table class="requests-table">
                    <tr data-request-id="1">
                        <td class="status">Pending</td>
                        <td>
                            <button class="approve-btn">Approve</button>
                            <button class="reject-btn">Reject</button>
                        </td>
                    </tr>
                    <tr data-request-id="2">
                        <td class="status">Approved</td>
                        <td>
                            <button class="approve-btn" style="display:none;">Approve</button>
                            <button class="reject-btn" style="display:none;">Reject</button>
                        </td>
                    </tr>
                </table>
            </div>
        `;
    });
    
    describe('Page Creation', () => {
        test('createLeavePage should resolve on successful creation', async () => {
            const mockResponse = {
                success: true,
                data: {
                    message: 'Page created successfully',
                    page_id: 123,
                    edit_url: '/wp-admin/post.php?post=123&action=edit',
                    view_url: '/leave-request/'
                }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            const result = await createLeavePage('Test Leave Page');
            
            expect(result).toEqual(mockResponse);
            expect($.ajax).toHaveBeenCalledWith({
                url: wp_employee_leaves_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_leave_page',
                    page_title: 'Test Leave Page',
                    nonce: wp_employee_leaves_admin.nonce
                },
                success: expect.any(Function),
                error: expect.any(Function)
            });
        });
        
        test('createLeavePage should reject on empty title', async () => {
            await expect(createLeavePage('')).rejects.toThrow('Page title is required');
            await expect(createLeavePage('   ')).rejects.toThrow('Page title is required');
            await expect(createLeavePage(null)).rejects.toThrow('Page title is required');
        });
        
        test('createLeavePage should reject on server error', async () => {
            const mockResponse = {
                success: false,
                data: 'Server error message'
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            await expect(createLeavePage('Test Page')).rejects.toThrow('Server error message');
        });
        
        test('createLeavePage should reject on AJAX error', async () => {
            $.ajax.mockImplementation(({ error }) => {
                error({}, 'error', 'Network error');
            });
            
            await expect(createLeavePage('Test Page')).rejects.toThrow('Network error');
        });
    });
    
    describe('Leave Request Actions', () => {
        test('approveLeaveRequest should resolve on successful approval', async () => {
            const mockResponse = {
                success: true,
                data: { message: 'Request approved successfully' }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            const result = await approveLeaveRequest(123, 'Approved by manager');
            
            expect(result).toEqual(mockResponse);
            expect($.ajax).toHaveBeenCalledWith({
                url: wp_employee_leaves_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'approve_leave_request',
                    request_id: 123,
                    comment: 'Approved by manager',
                    nonce: wp_employee_leaves_admin.approve_nonce
                },
                success: expect.any(Function),
                error: expect.any(Function)
            });
        });
        
        test('approveLeaveRequest should handle empty comment', async () => {
            const mockResponse = {
                success: true,
                data: { message: 'Request approved successfully' }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            await approveLeaveRequest(123);
            
            expect($.ajax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        comment: ''
                    })
                })
            );
        });
        
        test('approveLeaveRequest should reject on missing request ID', async () => {
            await expect(approveLeaveRequest()).rejects.toThrow('Request ID is required');
            await expect(approveLeaveRequest(null)).rejects.toThrow('Request ID is required');
            await expect(approveLeaveRequest(0)).rejects.toThrow('Request ID is required');
        });
        
        test('rejectLeaveRequest should resolve on successful rejection', async () => {
            const mockResponse = {
                success: true,
                data: { message: 'Request rejected successfully' }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            const result = await rejectLeaveRequest(123, 'Rejected due to staffing');
            
            expect(result).toEqual(mockResponse);
            expect($.ajax).toHaveBeenCalledWith({
                url: wp_employee_leaves_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'reject_leave_request',
                    request_id: 123,
                    comment: 'Rejected due to staffing',
                    nonce: wp_employee_leaves_admin.reject_nonce
                },
                success: expect.any(Function),
                error: expect.any(Function)
            });
        });
        
        test('rejectLeaveRequest should reject on missing request ID', async () => {
            await expect(rejectLeaveRequest()).rejects.toThrow('Request ID is required');
        });
    });
    
    describe('Validation Functions', () => {
        test('validatePageTitle should return valid for good titles', () => {
            const validTitles = [
                'Leave Request Page',
                'Employee Leave Form',
                'My Leave Requests',
                'Test Page 123'
            ];
            
            validTitles.forEach(title => {
                const result = validatePageTitle(title);
                expect(result.valid).toBe(true);
            });
        });
        
        test('validatePageTitle should return invalid for empty titles', () => {
            const invalidTitles = ['', '   ', null, undefined];
            
            invalidTitles.forEach(title => {
                const result = validatePageTitle(title);
                expect(result.valid).toBe(false);
                expect(result.error).toBe('Page title is required');
            });
        });
        
        test('validatePageTitle should return invalid for too long titles', () => {
            const longTitle = 'A'.repeat(201);
            const result = validatePageTitle(longTitle);
            
            expect(result.valid).toBe(false);
            expect(result.error).toBe('Page title is too long');
        });
        
        test('validatePageTitle should return invalid for titles with invalid characters', () => {
            const invalidTitles = [
                'Page <script>',
                'Page > Title',
                'Title < Test'
            ];
            
            invalidTitles.forEach(title => {
                const result = validatePageTitle(title);
                expect(result.valid).toBe(false);
                expect(result.error).toBe('Page title contains invalid characters');
            });
        });
    });
    
    describe('Security Functions', () => {
        test('escapeHtml should escape HTML characters', () => {
            const testCases = [
                { input: '<script>alert("xss")</script>', expected: '&lt;script&gt;alert("xss")&lt;/script&gt;' },
                { input: 'Test & Company', expected: 'Test &amp; Company' },
                { input: 'Quote "test"', expected: 'Quote "test"' },
                { input: "Single 'quote'", expected: "Single 'quote'" }
            ];
            
            testCases.forEach(({ input, expected }) => {
                const result = escapeHtml(input);
                expect(result).toBe(expected);
            });
        });
        
        test('escapeHtml should handle non-string inputs', () => {
            expect(escapeHtml(123)).toBe(123);
            expect(escapeHtml(null)).toBe(null);
            expect(escapeHtml(undefined)).toBe(undefined);
            expect(escapeHtml({})).toEqual({});
        });
    });
    
    describe('UI Functions', () => {
        test('showNotification should create notification element', () => {
            showNotification('Test message', 'success');
            
            const notification = document.querySelector('.notice.notice-success');
            expect(notification).not.toBeNull();
            expect(notification.innerHTML).toContain('Test message');
        });
        
        test('showNotification should default to success type', () => {
            showNotification('Test message');
            
            const notification = document.querySelector('.notice.notice-success');
            expect(notification).not.toBeNull();
        });
        
        test('showNotification should handle different types', () => {
            showNotification('Error message', 'error');
            
            const notification = document.querySelector('.notice.notice-error');
            expect(notification).not.toBeNull();
            expect(notification.innerHTML).toContain('Error message');
        });
        
        test('showNotification should escape HTML in message', () => {
            showNotification('<script>alert("xss")</script>Test', 'success');
            
            const notification = document.querySelector('.notice.notice-success');
            expect(notification.innerHTML).toContain('Test');
            expect(notification.innerHTML).not.toContain('<script>');
        });
        
        test('updateRequestRow should update status cell', () => {
            updateRequestRow('1', 'approved');
            
            const statusCell = document.querySelector('tr[data-request-id="1"] .status');
            expect(statusCell.textContent).toBe('Approved');
            expect(statusCell.className).toBe('status status-approved');
        });
        
        test('updateRequestRow should hide action buttons for non-pending status', () => {
            updateRequestRow('1', 'approved');
            
            const approveBtn = document.querySelector('tr[data-request-id="1"] .approve-btn');
            const rejectBtn = document.querySelector('tr[data-request-id="1"] .reject-btn');
            
            expect(approveBtn.style.display).toBe('none');
            expect(rejectBtn.style.display).toBe('none');
        });
        
        test('updateRequestRow should handle non-existent row gracefully', () => {
            expect(() => updateRequestRow('999', 'approved')).not.toThrow();
        });
    });
    
    describe('Integration Tests', () => {
        test('complete approval workflow', async () => {
            const mockResponse = {
                success: true,
                data: { message: 'Request approved successfully' }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            // Approve request
            const result = await approveLeaveRequest(1, 'Approved');
            expect(result.success).toBe(true);
            
            // Update UI
            updateRequestRow('1', 'approved');
            
            const statusCell = document.querySelector('tr[data-request-id="1"] .status');
            expect(statusCell.textContent).toBe('Approved');
            
            const approveBtn = document.querySelector('tr[data-request-id="1"] .approve-btn');
            expect(approveBtn.style.display).toBe('none');
        });
        
        test('complete rejection workflow', async () => {
            const mockResponse = {
                success: true,
                data: { message: 'Request rejected successfully' }
            };
            
            $.ajax.mockImplementation(({ success }) => {
                success(mockResponse);
            });
            
            // Reject request
            const result = await rejectLeaveRequest(1, 'Rejected');
            expect(result.success).toBe(true);
            
            // Update UI
            updateRequestRow('1', 'rejected');
            
            const statusCell = document.querySelector('tr[data-request-id="1"] .status');
            expect(statusCell.textContent).toBe('Rejected');
            
            const rejectBtn = document.querySelector('tr[data-request-id="1"] .reject-btn');
            expect(rejectBtn.style.display).toBe('none');
        });
        
        test('error handling in complete workflow', async () => {
            $.ajax.mockImplementation(({ error }) => {
                error({}, 'error', 'Network error');
            });
            
            // Should handle network error gracefully
            await expect(approveLeaveRequest(1, 'Approved')).rejects.toThrow('Network error');
            
            // UI should remain unchanged
            const statusCell = document.querySelector('tr[data-request-id="1"] .status');
            expect(statusCell.textContent).toBe('Pending');
        });
    });
});