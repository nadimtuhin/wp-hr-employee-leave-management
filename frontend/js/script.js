jQuery(document).ready(function($) {
    // Set minimum date for all date inputs
    function setMinDate() {
        var today = new Date().toISOString().split('T')[0];
        $('.leave-date-picker').attr('min', today);
    }
    
    // Initialize on page load
    setMinDate();
    
    // Add new date row
    $('#add-date-row').on('click', function() {
        var newRow = $('.date-type-row:first').clone();
        newRow.find('input').val('');
        newRow.find('select').val('');
        $('#leave-dates-container').append(newRow);
        setMinDate(); // Set minimum date for new row
        updateRemoveButtons();
    });
    
    // Remove date row
    $(document).on('click', '.remove-date-row', function() {
        if ($('.date-type-row').length > 1) {
            $(this).closest('.date-type-row').remove();
            updateRemoveButtons();
        }
    });
    
    // Update remove button visibility
    function updateRemoveButtons() {
        if ($('.date-type-row').length <= 1) {
            $('.remove-date-row').hide();
        } else {
            $('.remove-date-row').show();
        }
    }
    
    // Initial update
    updateRemoveButtons();
    
    // Form submission
    $('#employee-leave-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#submit-leave-request');
        var $message = $('#leave-form-message');
        
        // Disable submit button
        $button.prop('disabled', true).text('Submitting...');
        
        // Collect form data
        var formData = {
            action: 'submit_leave_request',
            nonce: wp_employee_leaves_ajax.nonce,
            employee_id: $('#employee_id').val(),
            manager_emails: collectEmails('#manager-emails-container'),
            reliever_emails: collectEmails('#reliever-emails-container'),
            reason: $('#reason').val(),
            leave_dates: [],
            leave_types: []
        };
        
        // Collect dates and types
        var seenDates = {};
        var duplicateDates = [];
        
        $('.date-type-row').each(function() {
            var date = $(this).find('.leave-date-picker').val();
            var type = $(this).find('select').val();
            
            if (date && type) {
                // Check for duplicate dates
                if (seenDates[date]) {
                    duplicateDates.push(date);
                } else {
                    seenDates[date] = true;
                }
                
                formData.leave_dates.push(date);
                formData.leave_types.push(type);
            }
        });
        
        // Check for duplicate dates
        if (duplicateDates.length > 0) {
            showMessage('Duplicate dates found: ' + duplicateDates.join(', ') + '. Please remove duplicate entries.', 'error');
            $button.prop('disabled', false).text('Submit Leave Request');
            return;
        }
        
        // Validate
        if (!formData.employee_id.trim()) {
            showMessage('Please enter your employee ID.', 'error');
            $button.prop('disabled', false).text('Submit Leave Request');
            return;
        }
        
        if (formData.leave_dates.length === 0) {
            showMessage('Please select at least one date and leave type.', 'error');
            $button.prop('disabled', false).text('Submit Leave Request');
            return;
        }
        
        if (!formData.reason.trim()) {
            showMessage('Please provide a reason for your leave.', 'error');
            $button.prop('disabled', false).text('Submit Leave Request');
            return;
        }
        
        // Validate email fields
        var managerEmailsValid = validateAllEmails('#manager-emails-container');
        var relieverEmailsValid = validateAllEmails('#reliever-emails-container');
        
        if (!managerEmailsValid || !relieverEmailsValid) {
            showMessage('Please fix the email validation errors before submitting.', 'error');
            $button.prop('disabled', false).text('Submit Leave Request');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            url: wp_employee_leaves_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success modal
                    showSuccessModal();
                    
                    // Reset form
                    $form[0].reset();
                    // Remove extra date rows
                    $('.date-type-row:not(:first)').remove();
                    updateRemoveButtons();
                    setMinDate(); // Reset minimum date for remaining row
                    
                    // Check for redirect URL
                    var redirectUrl = $form.data('redirect');
                    if (redirectUrl) {
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, 3000);
                    }
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while submitting your request. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Submit Leave Request');
            }
        });
    });
    
    // Show message function
    function showMessage(message, type) {
        var $message = $('#leave-form-message');
        $message.removeClass('success error').addClass(type);
        $message.text(message).show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut();
        }, 5000);
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }
    
    // Email validation
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    
    // Email Field Management
    
    // Add new email field
    $('.add-email-field').on('click', function() {
        var target = $(this).data('target');
        var container = $('#' + target + '-emails-container');
        var firstRow = container.find('.email-field-row:first');
        var newRow = firstRow.clone();
        
        // Clear the input value
        newRow.find('input').val('').removeClass('valid-email invalid-email');
        
        // Show remove button for new rows
        newRow.find('.remove-email-field').show();
        
        // Append to container
        container.append(newRow);
        
        // Focus on the new input
        newRow.find('input').focus();
        
        // Update remove buttons visibility
        updateEmailRemoveButtons(container);
    });
    
    // Remove email field
    $(document).on('click', '.remove-email-field', function() {
        var container = $(this).closest('.email-fields-container');
        $(this).closest('.email-field-row').remove();
        updateEmailRemoveButtons(container);
    });
    
    // Update remove buttons visibility
    function updateEmailRemoveButtons(container) {
        var rows = container.find('.email-field-row');
        if (rows.length <= 1) {
            rows.find('.remove-email-field').hide();
        } else {
            rows.find('.remove-email-field').show();
        }
    }
    
    // Validate individual email field
    function validateEmailInput(input) {
        var email = input.val().trim();
        
        // Remove previous validation classes
        input.removeClass('valid-email invalid-email');
        
        if (email === '') {
            return true; // Empty is valid (fields are optional)
        }
        
        if (validateEmail(email)) {
            input.addClass('valid-email');
            return true;
        } else {
            input.addClass('invalid-email');
            return false;
        }
    }
    
    // Validate email on blur
    $(document).on('blur', '.email-field', function() {
        validateEmailInput($(this));
    });
    
    // Real-time validation and autocomplete on input
    $(document).on('input', '.email-field', function() {
        var $field = $(this);
        
        // Clear previous validation classes
        $field.removeClass('valid-email invalid-email');
        
        // Only validate if field is not empty
        if ($field.val().trim()) {
            // Debounce validation to avoid excessive calls
            clearTimeout($field.data('validationTimeout'));
            $field.data('validationTimeout', setTimeout(function() {
                validateEmailInput($field);
                // Show contact suggestions
                showContactSuggestions($field);
            }, 500));
        } else {
            // Hide suggestions if field is empty
            hideContactSuggestions($field);
        }
    });
    
    // Collect emails from multiple fields
    function collectEmails(containerSelector) {
        var emails = [];
        $(containerSelector + ' .email-field').each(function() {
            var email = $(this).val().trim();
            if (email && validateEmail(email)) {
                emails.push(email);
            }
        });
        return emails.join(', ');
    }
    
    // Validate all email fields in a container
    function validateAllEmails(containerSelector) {
        var allValid = true;
        $(containerSelector + ' .email-field').each(function() {
            var email = $(this).val().trim();
            if (email && !validateEmailInput($(this))) {
                allValid = false;
            }
        });
        return allValid;
    }
    
    // Success Modal Functions - Moved inside jQuery ready block to access $
    window.showSuccessModal = function() {
        $('#success-modal').fadeIn(300);
        
        // Close modal when clicking outside
        $('#success-modal').on('click', function(e) {
            if (e.target === this) {
                window.closeSuccessModal();
            }
        });
        
        // Close modal with Escape key
        $(document).on('keydown.modal', function(e) {
            if (e.keyCode === 27) { // Escape key
                window.closeSuccessModal();
            }
        });
        
        // Close modal when clicking the close button
        $('#success-modal').on('click', '.success-modal-close', function() {
            window.closeSuccessModal();
        });
    };

    window.closeSuccessModal = function() {
        $('#success-modal').fadeOut(300);
        $(document).off('keydown.modal');
    };
    
    
    // Contact Suggestions Auto-Complete
    
    // Show contact suggestions
    function showContactSuggestions($field) {
        var query = $field.val().trim();
        var fieldType = getFieldType($field);
        
        if (query.length < 2) {
            hideContactSuggestions($field);
            return;
        }
        
        // Get suggestions from server
        $.ajax({
            url: wp_employee_leaves_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_contact_suggestions',
                nonce: wp_employee_leaves_ajax.nonce,
                query: query,
                contact_type: fieldType
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displayContactSuggestions($field, response.data);
                } else {
                    hideContactSuggestions($field);
                }
            },
            error: function() {
                hideContactSuggestions($field);
            }
        });
    }
    
    // Display contact suggestions dropdown
    function displayContactSuggestions($field, suggestions) {
        // Remove existing suggestions
        hideContactSuggestions($field);
        
        // Create suggestions dropdown
        var $dropdown = $('<div class="contact-suggestions"></div>');
        
        suggestions.forEach(function(contact) {
            var $suggestion = $('<div class="suggestion-item"></div>');
            
            var displayText = contact.display_name ? 
                contact.display_name + ' (' + contact.email_address + ')' : 
                contact.email_address;
                
            $suggestion.text(displayText);
            $suggestion.data('email', contact.email_address);
            $suggestion.data('name', contact.display_name || '');
            
            // Click handler for suggestion
            $suggestion.on('click', function() {
                $field.val(contact.email_address);
                validateEmailInput($field);
                hideContactSuggestions($field);
                $field.focus();
            });
            
            $dropdown.append($suggestion);
        });
        
        // Position dropdown
        var fieldOffset = $field.offset();
        var fieldHeight = $field.outerHeight();
        
        $dropdown.css({
            position: 'absolute',
            top: fieldOffset.top + fieldHeight,
            left: fieldOffset.left,
            width: $field.outerWidth(),
            zIndex: 1000
        });
        
        // Append to body
        $('body').append($dropdown);
        
        // Store reference for cleanup
        $field.data('suggestions-dropdown', $dropdown);
        
        // Close dropdown when clicking outside
        $(document).on('click.suggestions', function(e) {
            if (!$(e.target).closest('.contact-suggestions, .email-field').length) {
                hideContactSuggestions($field);
            }
        });
    }
    
    // Hide contact suggestions
    function hideContactSuggestions($field) {
        var $dropdown = $field.data('suggestions-dropdown');
        if ($dropdown) {
            $dropdown.remove();
            $field.removeData('suggestions-dropdown');
        }
        $(document).off('click.suggestions');
    }
    
    // Get field type (manager or reliever)
    function getFieldType($field) {
        var container = $field.closest('.email-fields-container');
        if (container.attr('id') === 'manager-emails-container') {
            return 'manager';
        } else if (container.attr('id') === 'reliever-emails-container') {
            return 'reliever';
        }
        return 'unknown';
    }
    
    // Handle keyboard navigation in suggestions
    $(document).on('keydown', '.email-field', function(e) {
        var $field = $(this);
        var $dropdown = $field.data('suggestions-dropdown');
        
        if (!$dropdown) return;
        
        var $suggestions = $dropdown.find('.suggestion-item');
        var $active = $suggestions.filter('.active');
        
        switch(e.keyCode) {
            case 38: // Up arrow
                e.preventDefault();
                if ($active.length) {
                    $active.removeClass('active');
                    var $prev = $active.prev('.suggestion-item');
                    if ($prev.length) {
                        $prev.addClass('active');
                    } else {
                        $suggestions.last().addClass('active');
                    }
                } else {
                    $suggestions.last().addClass('active');
                }
                break;
                
            case 40: // Down arrow
                e.preventDefault();
                if ($active.length) {
                    $active.removeClass('active');
                    var $next = $active.next('.suggestion-item');
                    if ($next.length) {
                        $next.addClass('active');
                    } else {
                        $suggestions.first().addClass('active');
                    }
                } else {
                    $suggestions.first().addClass('active');
                }
                break;
                
            case 13: // Enter
                if ($active.length) {
                    e.preventDefault();
                    $active.click();
                }
                break;
                
            case 27: // Escape
                hideContactSuggestions($field);
                break;
        }
    });
    
    // Highlight active suggestion on hover
    $(document).on('mouseenter', '.suggestion-item', function() {
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
    });
    
    // Hide suggestions when field loses focus (with delay for clicks)
    $(document).on('blur', '.email-field', function() {
        var $field = $(this);
        setTimeout(function() {
            hideContactSuggestions($field);
        }, 200);
    });
});