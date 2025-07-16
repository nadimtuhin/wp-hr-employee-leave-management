#!/bin/bash

# Test curl script for WP Employee Leaves plugin
# This script tests the AJAX endpoints for the plugin

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
BASE_URL="http://wptest.local"
AJAX_URL="$BASE_URL/wp-admin/admin-ajax.php"
FORM_URL="$BASE_URL/leave-request/"

# WordPress session cookies (update these with current valid cookies)
COOKIES="wordpress_b2398a4c0dc538785b6d3243923c34cf=admin%7C1752826739%7CaNfUuDvtITdiPnJLZokimwG1jAvmQah5Zyfdxkvb6wT%7C8abd45c5c465a5b386579818ad8300830c6b047a2b8f902dd0a36cdef31c4e44; wordpress_logged_in_b2398a4c0dc538785b6d3243923c34cf=admin%7C1752826739%7CaNfUuDvtITdiPnJLZokimwG1jAvmQah5Zyfdxkvb6wT%7Cf3ce66f91c21162a2c75fee1506426f133e8db09fcfb0d79b13eafc3056ca6ef"

echo -e "${YELLOW}WP Employee Leaves Plugin - Test Script${NC}"
echo "======================================"

# Function to get fresh nonce
get_nonce() {
    echo -e "${YELLOW}Getting fresh nonce...${NC}"
    NONCE=$(curl -s "$FORM_URL" \
        -b "$COOKIES" \
        | grep -o 'wp_employee_leaves_ajax.*nonce":"[^"]*"' \
        | head -1 \
        | sed 's/.*nonce":"//' \
        | sed 's/".*//')
    
    if [ -z "$NONCE" ]; then
        echo -e "${RED}Error: Could not get nonce${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}Nonce: $NONCE${NC}"
}

# Function to test AJAX endpoint
test_ajax_endpoint() {
    local action=$1
    local data=$2
    local expected_success=$3
    
    echo -e "${YELLOW}Testing $action endpoint...${NC}"
    
    response=$(curl -s "$AJAX_URL" \
        -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
        -b "$COOKIES" \
        --data-raw "$data" \
        --insecure)
    
    echo "Response: $response"
    
    if [[ $response == *"\"success\":true"* ]] && [[ $expected_success == "true" ]]; then
        echo -e "${GREEN}✓ Test passed${NC}"
    elif [[ $response == *"\"success\":false"* ]] && [[ $expected_success == "false" ]]; then
        echo -e "${GREEN}✓ Test passed (expected failure)${NC}"
    else
        echo -e "${RED}✗ Test failed${NC}"
    fi
    
    echo ""
}

# Get fresh nonce
get_nonce

# Test 1: Test basic connectivity (this should fail since we removed the test endpoint)
echo -e "${YELLOW}Test 1: Basic AJAX connectivity${NC}"
test_ajax_endpoint "test_employee_leaves" "action=test_employee_leaves" "false"

# Test 2: Submit leave request with valid data
echo -e "${YELLOW}Test 2: Submit valid leave request${NC}"
test_ajax_endpoint "submit_leave_request" \
    "action=submit_leave_request&nonce=$NONCE&employee_id=EMP001&manager_emails=manager@company.com&reliever_emails=reliever@company.com&reason=Medical+appointment&leave_dates[]=2025-07-17&leave_types[]=1" \
    "true"

# Test 3: Submit leave request without required fields
echo -e "${YELLOW}Test 3: Submit leave request without employee ID${NC}"
test_ajax_endpoint "submit_leave_request" \
    "action=submit_leave_request&nonce=$NONCE&employee_id=&manager_emails=manager@company.com&reliever_emails=reliever@company.com&reason=Medical+appointment&leave_dates[]=2025-07-17&leave_types[]=1" \
    "false"

# Test 4: Submit leave request without nonce
echo -e "${YELLOW}Test 4: Submit leave request without nonce${NC}"
test_ajax_endpoint "submit_leave_request" \
    "action=submit_leave_request&employee_id=EMP001&manager_emails=manager@company.com&reliever_emails=reliever@company.com&reason=Medical+appointment&leave_dates[]=2025-07-17&leave_types[]=1" \
    "false"

# Test 5: Submit leave request with invalid nonce
echo -e "${YELLOW}Test 5: Submit leave request with invalid nonce${NC}"
test_ajax_endpoint "submit_leave_request" \
    "action=submit_leave_request&nonce=invalid_nonce&employee_id=EMP001&manager_emails=manager@company.com&reliever_emails=reliever@company.com&reason=Medical+appointment&leave_dates[]=2025-07-17&leave_types[]=1" \
    "false"

# Test 6: Submit leave request with invalid email format
echo -e "${YELLOW}Test 6: Submit leave request with invalid email format${NC}"
test_ajax_endpoint "submit_leave_request" \
    "action=submit_leave_request&nonce=$NONCE&employee_id=EMP001&manager_emails=invalid-email&reliever_emails=reliever@company.com&reason=Medical+appointment&leave_dates[]=2025-07-17&leave_types[]=1" \
    "true"

# Test 7: Test my leave requests page creation
echo -e "${YELLOW}Test 7: Create My Leave Requests page${NC}"
test_ajax_endpoint "create_my_requests_page" \
    "action=create_my_requests_page&nonce=$NONCE&page_title=My+Leave+Requests" \
    "true"

echo -e "${GREEN}All tests completed!${NC}"
echo ""
echo -e "${YELLOW}Usage Instructions:${NC}"
echo "1. Update the COOKIES variable with current valid WordPress login cookies"
echo "2. Update BASE_URL if your WordPress site is on a different domain"
echo "3. Make sure the leave request page exists at /leave-request/"
echo "4. Run: chmod +x test-curl.sh && ./test-curl.sh"
echo ""
echo -e "${YELLOW}Note:${NC} This script requires curl and a valid WordPress session"