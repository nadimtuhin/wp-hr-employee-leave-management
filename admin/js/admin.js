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
    $('.approve-request').on('click', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var requestId = $button.data('request-id');
        
        if (confirm('Are you sure you want to approve this leave request?')) {
            $button.prop('disabled', true).text('Approving...');
            $row.addClass('loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'approve_leave_request',
                    request_id: requestId,
                    nonce: wp_employee_leaves_admin.approve_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.find('.status-pending').removeClass('status-pending').addClass('status-approved').text('Approved');
                        $button.parent().html('<span class="dashicons dashicons-yes" style="color: #27ae60;"></span>');
                        showNotice('Leave request approved successfully!', 'success');
                    } else {
                        showNotice('Error: ' + response.data, 'error');
                        $button.prop('disabled', false).text('Approve');
                    }
                },
                error: function() {
                    showNotice('An error occurred while processing the request.', 'error');
                    $button.prop('disabled', false).text('Approve');
                },
                complete: function() {
                    $row.removeClass('loading');
                }
            });
        }
    });
    
    $('.reject-request').on('click', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var requestId = $button.data('request-id');
        
        if (confirm('Are you sure you want to reject this leave request?')) {
            $button.prop('disabled', true).text('Rejecting...');
            $row.addClass('loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'reject_leave_request',
                    request_id: requestId,
                    nonce: wp_employee_leaves_admin.reject_nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.find('.status-pending').removeClass('status-pending').addClass('status-rejected').text('Rejected');
                        $button.parent().html('<span class="dashicons dashicons-no" style="color: #e74c3c;"></span>');
                        showNotice('Leave request rejected successfully!', 'success');
                    } else {
                        showNotice('Error: ' + response.data, 'error');
                        $button.prop('disabled', false).text('Reject');
                    }
                },
                error: function() {
                    showNotice('An error occurred while processing the request.', 'error');
                    $button.prop('disabled', false).text('Reject');
                },
                complete: function() {
                    $row.removeClass('loading');
                }
            });
        }
    });
    
    // Show notification function
    function showNotice(message, type) {
        var noticeClass = 'notice-' + type;
        var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);
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