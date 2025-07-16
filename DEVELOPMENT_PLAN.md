# WP Employee Leaves Plugin - Development Plan

## Core Features Overview

### Leave Request Form Components
- Multiple Line Manager email fields (optional)
- Leave Reliever email fields (optional) 
- **Multiple Leave Types** - Select different leave types for different dates in one request
- **Date-Type Assignment** - Assign specific leave types to specific dates
- **Gapped Dates Support** - Allow non-consecutive leave dates
- **Balance Validation** - Check available leave balance before submission
- Short description field for leave reason
- Automated email notifications to HR, entered manager emails, reliever emails, and employee

### Leave Types
1. Annual Leave
2. Casual Leave
3. Sick Leave
4. Emergency Leave (Probation)

## Technical Implementation Plan

### Phase 1: Database & Foundation (High Priority)
1. **Database Schema Enhancement** - Create tables for leave types, notifications, dates, balances, and logs with year tracking
2. **Leave Types Setup** - Implement Annual, Casual, Sick, Emergency (Probation) leave types
3. **Leave Balance System** - Track yearly leave balances per employee per leave type with year column
4. **Multi-Type Leave Form** - Frontend form allowing multiple leave type selections
5. **Date-Type Assignment System** - Interface to assign leave types to specific dates
6. **Leave Logs System** - Comprehensive logging with year tracking for all activities

### Phase 2: Core Functionality (Medium Priority)
7. **Approval Workflow** - HR approval process that updates leave balances
8. **Email Notification System** - Send emails to HR + manually entered emails
9. **HR Year-Filtered Dashboard** - View logs, counts, and balances filtered by year
10. **Leave Statistics System** - Generate counts and summaries per employee per year

### Phase 3: Admin Interface (Medium Priority)
11. **Admin Settings Panel** - Configure leave types, HR email, and yearly balance allocation
12. **Leave Request Dashboard** - Admin interface to approve/reject and view requests

### Phase 4: User Experience (Low Priority)
13. **Employee Dashboard** - Frontend view showing remaining balances and request status

## Database Structure

### wp_employee_leaves_types
- id (primary key)
- name (varchar)
- yearly_allocation (decimal)
- active (boolean)
- created_at, updated_at

### wp_employee_leaves_requests
- id (primary key)
- employee_id (user_id)
- manager_emails (text - JSON array)
- reliever_emails (text - JSON array)
- reason (text)
- status (pending/approved/rejected)
- approved_by (user_id)
- approved_at (datetime)
- created_at, updated_at

### wp_employee_leaves_dates
- id (primary key)
- request_id (foreign key)
- leave_date (date)
- leave_type_id (foreign key)
- created_at

### wp_employee_leaves_balances
- id (primary key)
- employee_id (user_id)
- leave_type_id (foreign key)
- year (int)
- allocated (decimal)
- used (decimal)
- remaining (decimal)
- created_at, updated_at

### wp_employee_leaves_logs
- id (primary key)
- employee_id (user_id)
- request_id (foreign key)
- action (varchar) - submitted/approved/rejected/modified
- details (text)
- year (int)
- performed_by (user_id)
- created_at

### wp_employee_leaves_notifications
- id (primary key)
- request_id (foreign key)
- email_type (varchar) - hr/manager/reliever/employee
- email_address (varchar)
- sent_at (datetime)
- status (sent/failed)

## HR Year-Filtered Dashboard Features

### Year Selector
- Dropdown to filter by specific year (2023, 2024, 2025, etc.)

### Employee Leave Logs
- All leave activities for selected year
- Filterable by employee, leave type, status

### Leave Counts
- Total days taken per leave type per employee per year
- Department-wise summaries

### Balance Overview
- Current balances vs. allocated vs. used per year
- Visual charts and graphs

### Statistics
- Most used leave types
- Peak leave periods
- Department comparisons

## Example Workflows

### Employee Leave Request
1. Employee selects multiple dates
2. Assigns leave types to specific dates
3. Enters manager/reliever emails
4. Submits with reason
5. System validates against balance
6. Emails sent to all stakeholders

### HR Approval Process
1. HR reviews request in dashboard
2. Approves/rejects with comments
3. System updates leave balances automatically
4. Confirmation emails sent
5. Activity logged with year tracking

### Year-End Balance Management
1. HR sets new yearly allocations
2. System carries forward unused balances (if configured)
3. Resets counters for new year
4. Generates annual reports

## Example HR Dashboard Views

### 2024 Leave Summary for John Doe
- Annual Leave: 18/21 used, 3 remaining
- Sick Leave: 5/14 used, 9 remaining  
- Casual Leave: 8/10 used, 2 remaining
- Emergency Leave: 2/5 used, 3 remaining

### Department Summary for 2024
- Engineering: 85% annual leave utilization
- Sales: 92% annual leave utilization
- HR: 78% annual leave utilization

## Multi-Type Leave Examples

### Single Request with Mixed Types
- 2024-01-15: Annual Leave
- 2024-01-16: Sick Leave  
- 2024-01-18: Annual Leave
- 2024-01-19: Emergency Leave (Probation)

### Gapped Dates Examples
- Single day: "2024-01-15"
- Consecutive: "2024-01-15, 2024-01-16, 2024-01-17"
- Gapped: "2024-01-15, 2024-01-17, 2024-01-19" (skip weekends/specific days)