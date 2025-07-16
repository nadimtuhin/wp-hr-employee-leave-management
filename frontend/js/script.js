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
            manager_emails: $('#manager_emails').val(),
            reliever_emails: $('#reliever_emails').val(),
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
        var emailValidation = true;
        $('#manager_emails, #reliever_emails').each(function() {
            if ($(this).val().trim() && !validateEmailField($(this))) {
                emailValidation = false;
            }
        });
        
        if (!emailValidation) {
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
        $message.html(message).show();
        
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
    
    // Validate comma-separated emails
    function validateEmailField($field) {
        var emails = $field.val().split(',');
        var invalidEmails = [];
        var validEmails = [];
        
        for (var i = 0; i < emails.length; i++) {
            var email = emails[i].trim();
            if (email) {
                if (validateEmail(email)) {
                    validEmails.push(email);
                } else {
                    invalidEmails.push(email);
                }
            }
        }
        
        // Remove validation classes first
        $field.removeClass('valid-emails invalid-emails');
        
        if (invalidEmails.length > 0) {
            $field.addClass('invalid-emails');
            showMessage('Invalid email addresses in ' + $field.prev('label').text() + ': ' + invalidEmails.join(', '), 'error');
            return false;
        } else if (validEmails.length > 0) {
            $field.addClass('valid-emails');
            // Clean up the field with properly formatted emails
            $field.val(validEmails.join(', '));
        }
        
        return true;
    }
    
    // Validate email fields on blur
    $('#manager_emails, #reliever_emails').on('blur', function() {
        validateEmailField($(this));
    });
    
    // Real-time validation on input
    $('#manager_emails, #reliever_emails').on('input', function() {
        var $field = $(this);
        // Clear previous validation classes
        $field.removeClass('valid-emails invalid-emails');
        
        // Only validate if field is not empty
        if ($field.val().trim()) {
            // Debounce validation to avoid excessive calls
            clearTimeout($field.data('validationTimeout'));
            $field.data('validationTimeout', setTimeout(function() {
                validateEmailField($field);
            }, 1000));
        }
    });
});

// Success Modal Functions
function showSuccessModal() {
    $('#success-modal').fadeIn(300);
    
    // Close modal when clicking outside
    $('#success-modal').on('click', function(e) {
        if (e.target === this) {
            closeSuccessModal();
        }
    });
    
    // Close modal with Escape key
    $(document).on('keydown.modal', function(e) {
        if (e.keyCode === 27) { // Escape key
            closeSuccessModal();
        }
    });
}

function closeSuccessModal() {
    $('#success-modal').fadeOut(300);
    $(document).off('keydown.modal');
}