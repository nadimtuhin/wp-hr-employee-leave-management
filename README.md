# WP Employee Leaves Plugin

A comprehensive WordPress plugin for managing employee leave requests with a modern admin interface.

## Features

### Frontend Features
- **Leave Request Form** via shortcode `[employee_leave_form]`
- **Multi-type Leave Support** - Annual, Casual, Sick, Emergency (Probation)
- **Gapped Date Selection** - Non-consecutive leave dates
- **Real-time Balance Validation** - Check available leave before submission
- **Manager/Reliever Emails** - Optional email fields for notifications
- **Responsive Design** - Mobile-friendly form interface
- **Success Modal** - Beautiful modal confirmation after successful submission
- **Duplicate Date Validation** - Prevents duplicate dates in the same request
- **Email Validation** - Real-time validation with visual feedback for comma-separated emails

### Admin Features
- **Modern Dashboard** - Statistics overview with visual indicators
- **Leave Request Management** - Approve/reject with one-click
- **Year-based Filtering** - Filter requests by year
- **Settings Panel** - 4 comprehensive tabs:
  - General (HR Email configuration)
  - Leave Types (Manage allocations)
  - Email Templates (5 customizable templates)
  - Page Management (Create/manage pages)

### Email System
- **5 Email Templates**:
  - Leave Request Submitted (to HR)
  - Leave Request Approved (to employee)
  - Leave Request Rejected (to employee)
  - Leave Notification Manager (to managers)
  - Leave Notification Reliever (to relievers)
- **Template Variables**: `{{employee_name}}`, `{{leave_dates}}`, `{{leave_types}}`, etc.
- **HTML Email Support** with proper formatting

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to "Employee Leaves" in the admin menu
4. Configure settings and create a page with the leave form

## Usage

### Creating a Leave Request Page

1. Go to **Employee Leaves > Settings > Page Management**
2. Enter a page title (e.g., "Employee Leave Request")
3. Click "Create Page"
4. The page will be created with the shortcode automatically embedded

### Manual Shortcode Usage

Add `[employee_leave_form]` to any page or post.

**Shortcode Attributes:**
- `title="Custom Title"` - Custom form title
- `show_balance="false"` - Hide balance information
- `redirect_url="/thank-you"` - Custom redirect after submission

**Example:**
```
[employee_leave_form title="Request Time Off" show_balance="false" redirect_url="/success"]
```

### Managing Leave Requests

1. Go to **Employee Leaves > Leave Requests**
2. Filter by year using the dropdown
3. Click "Approve" or "Reject" for pending requests
4. View employee details, leave dates, and reasons

### Configuring Email Templates

1. Go to **Employee Leaves > Settings > Email Templates**
2. Customize subject and body for each template type
3. Use template variables for dynamic content
4. Enable/disable individual templates

## Database Tables

The plugin creates 7 database tables:

- `wp_employee_leaves_types` - Leave type definitions
- `wp_employee_leaves_requests` - Main leave requests
- `wp_employee_leaves_dates` - Individual leave dates with types
- `wp_employee_leaves_balances` - Yearly leave balances per employee
- `wp_employee_leaves_logs` - Activity logs with year tracking
- `wp_employee_leaves_notifications` - Email notification logs
- `wp_employee_leaves_email_templates` - Email template storage

## Leave Balance System

- **Automatic Initialization** - Balances created when first accessed
- **Year-based Tracking** - Separate balances per year
- **Real-time Updates** - Balances updated when requests approved
- **Balance Validation** - Prevents over-allocation during submission

## Technical Details

### File Structure
```
wp-employee-leaves/
├── wp-employee-leaves.php (Main plugin file)
├── admin/
│   ├── css/admin.css (Admin styling)
│   └── js/admin.js (Admin JavaScript)
├── frontend/
│   ├── css/style.css (Frontend styling)
│   └── js/script.js (Frontend JavaScript)
├── DEVELOPMENT_PLAN.md (Development roadmap)
├── test-curl.sh (API testing script)
└── README.md (This file)
```

### AJAX Endpoints
- `wp_ajax_create_leave_page` - Create new page with shortcode
- `wp_ajax_add_shortcode_to_page` - Add shortcode to existing page
- `wp_ajax_approve_leave_request` - Approve leave request
- `wp_ajax_reject_leave_request` - Reject leave request
- `wp_ajax_submit_leave_request` - Submit new leave request (with proper nonce validation)

### Hooks and Filters
- `employee_leave_form` - Shortcode hook
- Standard WordPress activation/deactivation hooks

## Troubleshooting

### Page Creation Issues
1. Check browser console for JavaScript errors
2. Verify user has 'manage_options' capability
3. Ensure plugin is properly activated
4. Check WordPress error logs

### Email Issues
1. Verify HR email is configured in settings
2. Check email template settings
3. Ensure WordPress mail function is working
4. Review email notification logs

### Form Submission Issues
1. Verify user is logged in
2. Check leave balance availability
3. Ensure JavaScript is enabled
4. Review form validation errors

## Support

For issues and feature requests, please check the WordPress admin error logs and browser console for detailed error messages.

## Testing

A test script is included for API testing:

```bash
# Make the script executable
chmod +x test-curl.sh

# Run tests
./test-curl.sh
```

The test script includes:
- AJAX endpoint connectivity tests
- Form validation tests
- Authentication tests
- Error handling tests

**Note:** Update the COOKIES variable in the script with current WordPress session cookies before running.

## Version History

- **1.0.0** - Initial release with full functionality
  - Leave request form with multi-type support
  - Admin dashboard with approval workflow
  - Email template system
  - Page creation tools
  - Modern responsive design

- **1.1.0** - Enhanced user experience and validation
  - Added success modal confirmation
  - Improved duplicate date validation
  - Enhanced email validation with real-time feedback
  - Better error handling and AJAX response management
  - Added comprehensive test suite