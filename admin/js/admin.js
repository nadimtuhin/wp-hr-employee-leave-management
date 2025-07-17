jQuery(document).ready(function($) {
    // Page creation functionality
    $('#create-leave-page').on('click', function() {
        var $button = $(this);
        var pageTitle = $('#leave-page-title').val().trim();
        
        if (!pageTitle) {
            alert('Please enter a page title');
            return;
        }
        
        $button.prop('disabled', true).text('Creating...');
        
        // Debug logging
        console.log('Creating page with title:', pageTitle);
        console.log('AJAX URL:', ajaxurl);
        console.log('Nonce:', wp_employee_leaves_admin.nonce);
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url || ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'create_leave_page',
                page_title: pageTitle,
                nonce: wp_employee_leaves_admin.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response && response.success) {
                    $('#page-creation-result').html('<div class="notice notice-success"><p>' + response.data.message + ' <a href="' + response.data.edit_url + '" target="_blank">Edit Page</a> | <a href="' + response.data.view_url + '" target="_blank">View Page</a></p></div>');
                    $('#leave-page-select').append('<option value="' + response.data.page_id + '">' + pageTitle + '</option>');
                    $('#leave-page-title').val(''); // Clear the input
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error occurred';
                    $('#page-creation-result').html('<div class="notice notice-error"><p>Error: ' + errorMsg + '</p></div>');
                }
                $button.text('Create Page');
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                console.log('Response Text:', xhr.responseText);
                $('#page-creation-result').html('<div class="notice notice-error"><p>An error occurred while creating the page. Details: ' + error + '</p></div>');
                $button.text('Create Page');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Add shortcode to existing page
    $('#add-shortcode-to-page').on('click', function() {
        var $button = $(this);
        var pageId = $('#leave-page-select').val();
        
        if (!pageId) {
            alert('Please select a page');
            return;
        }
        
        $button.prop('disabled', true).text('Adding...');
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url || ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'add_shortcode_to_page',
                page_id: pageId,
                nonce: wp_employee_leaves_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#page-creation-result').html('<div class="notice notice-success"><p>' + response.data.message + ' <a href="' + response.data.edit_url + '" target="_blank">Edit Page</a> | <a href="' + response.data.view_url + '" target="_blank">View Page</a></p></div>');
                    $('#leave-page-select').val(''); // Clear the selection
                } else {
                    $('#page-creation-result').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
                $button.text('Add Shortcode');
            },
            error: function() {
                $('#page-creation-result').html('<div class="notice notice-error"><p>An error occurred while adding the shortcode.</p></div>');
                $button.text('Add Shortcode');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Enhanced approval/rejection with better UX
    $(document).on('click', '.approve-request', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $row = $button.closest('tr');
        var requestId = $button.data('request-id');
        var $statusCell = $row.find('.status-pending, .status-approved, .status-rejected');
        var $actionCell = $button.closest('td');
        
        if (!requestId) {
            showNotice('Invalid request ID.', 'error');
            return;
        }
        
        if (confirm('Are you sure you want to approve this leave request?')) {
            // Visual feedback
            $button.prop('disabled', true).text('Approving...');
            $row.addClass('loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'approve_leave_request',
                    request_id: requestId,
                    nonce: wp_employee_leaves_admin.approve_nonce
                },
                success: function(response) {
                    console.log('Approve response:', response);
                    
                    if (response && response.success) {
                        // Update status cell with new styling
                        $statusCell
                            .removeClass('status-pending status-rejected')
                            .addClass('status-approved')
                            .html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>Approved');
                        
                        // Replace action buttons with status icon
                        $actionCell.html('<span class="dashicons dashicons-yes-alt" style="color: #27ae60; font-size: 20px;" title="Approved"></span>');
                        
                        // Add success animation
                        $row.removeClass('loading').addClass('success-highlight');
                        setTimeout(function() {
                            $row.removeClass('success-highlight');
                        }, 2000);
                        
                        showNotice(response.data.message || 'Leave request approved successfully!', 'success');
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error occurred';
                        showNotice('Error: ' + errorMsg, 'error');
                        resetButton($button, 'Approve');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr.responseText, status, error);
                    showNotice('Network error occurred while processing the request.', 'error');
                    resetButton($button, 'Approve');
                },
                complete: function() {
                    $row.removeClass('loading');
                }
            });
        }
    });
    
    $(document).on('click', '.reject-request', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $row = $button.closest('tr');
        var requestId = $button.data('request-id');
        var $statusCell = $row.find('.status-pending, .status-approved, .status-rejected');
        var $actionCell = $button.closest('td');
        
        if (!requestId) {
            showNotice('Invalid request ID.', 'error');
            return;
        }
        
        if (confirm('Are you sure you want to reject this leave request?')) {
            // Visual feedback
            $button.prop('disabled', true).text('Rejecting...');
            $row.addClass('loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'reject_leave_request',
                    request_id: requestId,
                    nonce: wp_employee_leaves_admin.reject_nonce
                },
                success: function(response) {
                    console.log('Reject response:', response);
                    
                    if (response && response.success) {
                        // Update status cell with new styling
                        $statusCell
                            .removeClass('status-pending status-approved')
                            .addClass('status-rejected')
                            .html('<span class="dashicons dashicons-no" style="margin-right: 5px;"></span>Rejected');
                        
                        // Replace action buttons with status icon
                        $actionCell.html('<span class="dashicons dashicons-dismiss" style="color: #e74c3c; font-size: 20px;" title="Rejected"></span>');
                        
                        // Add warning animation
                        $row.removeClass('loading').addClass('warning-highlight');
                        setTimeout(function() {
                            $row.removeClass('warning-highlight');
                        }, 2000);
                        
                        showNotice(response.data.message || 'Leave request rejected successfully!', 'success');
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Unknown error occurred';
                        showNotice('Error: ' + errorMsg, 'error');
                        resetButton($button, 'Reject');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr.responseText, status, error);
                    showNotice('Network error occurred while processing the request.', 'error');
                    resetButton($button, 'Reject');
                },
                complete: function() {
                    $row.removeClass('loading');
                }
            });
        }
    });
    
    // Helper function to reset button state
    function resetButton($button, originalText) {
        $button.prop('disabled', false).text(originalText);
    }
    
    // Enhanced notification function
    function showNotice(message, type) {
        // Remove existing notices first
        $('.notice').remove();
        
        var noticeClass = 'notice-' + type;
        var icon = type === 'success' ? 'yes-alt' : 'warning';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="margin: 15px 0; padding: 15px; border-left: 4px solid; border-radius: 5px; animation: slideDown 0.3s ease;">' +
            '<p style="margin: 0; display: flex; align-items: center; gap: 8px;">' +
            '<span class="dashicons dashicons-' + icon + '" style="font-size: 16px;"></span>' +
            message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 4 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
        
        // Make dismissible
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Copy shortcode functionality
    $('#copy-shortcode').on('click', function() {
        var shortcode = '[employee_leave_form]';
        
        // Create temporary input to copy text
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(shortcode).select();
        document.execCommand('copy');
        tempInput.remove();
        
        $(this).text('Copied!').prop('disabled', true);
        setTimeout(function() {
            $('#copy-shortcode').text('Copy Shortcode').prop('disabled', false);
        }, 2000);
    });
    
    // Copy my requests shortcode functionality
    $('#copy-my-requests-shortcode').on('click', function() {
        var shortcode = '[my_leave_requests]';
        
        // Create temporary input to copy text
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(shortcode).select();
        document.execCommand('copy');
        tempInput.remove();
        
        $(this).text('Copied!').prop('disabled', true);
        setTimeout(function() {
            $('#copy-my-requests-shortcode').text('Copy Shortcode').prop('disabled', false);
        }, 2000);
    });
    
    // Create My Leave Requests page functionality
    $('#create-my-requests-page').on('click', function() {
        var $button = $(this);
        var pageTitle = $('#my-requests-page-title').val().trim();
        
        if (!pageTitle) {
            alert('Please enter a page title');
            return;
        }
        
        $button.prop('disabled', true).text('Creating...');
        
        // Debug logging
        console.log('Creating my requests page with title:', pageTitle);
        console.log('AJAX URL:', ajaxurl);
        console.log('Nonce:', wp_employee_leaves_admin.nonce);
        
        $.ajax({
            url: wp_employee_leaves_admin.ajax_url || ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'create_my_requests_page',
                page_title: pageTitle,
                nonce: wp_employee_leaves_admin.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response && response.success) {
                    $('#page-creation-result').html('<div class="notice notice-success"><p>' + response.data.message + ' <a href="' + response.data.edit_url + '" target="_blank">Edit Page</a> | <a href="' + response.data.view_url + '" target="_blank">View Page</a></p></div>');
                    $('#leave-page-select').append('<option value="' + response.data.page_id + '">' + pageTitle + '</option>');
                    $('#my-requests-page-title').val(''); // Clear the input
                } else {
                    var errorMsg = response && response.data ? response.data : 'Unknown error occurred';
                    $('#page-creation-result').html('<div class="notice notice-error"><p>Error: ' + errorMsg + '</p></div>');
                }
                $button.text('Create Page');
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                console.log('Response Text:', xhr.responseText);
                $('#page-creation-result').html('<div class="notice notice-error"><p>An error occurred while creating the page. Details: ' + error + '</p></div>');
                $button.text('Create Page');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Form validation for settings
    $('form').on('submit', function() {
        var $form = $(this);
        var valid = true;
        
        // Check required fields
        $form.find('input[required], select[required], textarea[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                valid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!valid) {
            showNotice('Please fill in all required fields.', 'error');
            return false;
        }
        
        // Show loading state
        $form.find('input[type="submit"]').prop('disabled', true).val('Saving...');
        
        return true;
    });
    
    // Remove error class on input
    $('input, select, textarea').on('focus', function() {
        $(this).removeClass('error');
    });
    
    // Auto-save draft for email templates
    var autoSaveTimeout;
    $('textarea[name*="email_templates"]').on('input', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Auto-save functionality can be added here
            console.log('Auto-saving email template...');
        }, 3000);
    });
    
    // Add visual feedback animations
    $('<style type="text/css">' +
        '@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }' +
        '.success-highlight { background-color: rgba(39, 174, 96, 0.1) !important; transition: background-color 0.3s ease; }' +
        '.warning-highlight { background-color: rgba(231, 76, 60, 0.1) !important; transition: background-color 0.3s ease; }' +
        '.loading td { opacity: 0.7; }' +
    '</style>').appendTo('head');
    
    // Per-page selector functionality
    window.changePerPage = function(perPage) {
        var url = new URL(window.location);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('paged'); // Reset to first page when changing per_page
        
        // Preserve the year parameter if it exists
        var yearParam = url.searchParams.get('year');
        if (yearParam) {
            url.searchParams.set('year', yearParam);
        }
        
        // Show loading feedback
        var selectElement = document.getElementById('per-page-select');
        if (selectElement) {
            selectElement.disabled = true;
            selectElement.style.opacity = '0.6';
        }
        
        window.location.href = url.toString();
    };
});

// Add error styling
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .error {
                border-color: #e74c3c !important;
                box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.2) !important;
            }
        `)
        .appendTo('head');
});