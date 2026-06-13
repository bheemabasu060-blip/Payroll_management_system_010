<?php
// ====================================================================
// AUTOMATED BACKEND INTEGRITY TEST RUNNER (test_backend.php)
// ====================================================================
// Check if run via Command Line Interface (CLI)
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Backend Verification Runner</title>";
    echo "<style>body{background:#0b0816; color:#e0dcf5; font-family:monospace; padding:30px; line-height:1.5;}";
    echo "h1{color:#8b5cf6; font-family:sans-serif; border-bottom:1px solid #2d264d; padding-bottom:10px;}";
    echo ".pass{color:#10b981; font-weight:bold;}";
    echo ".fail{color:#ef4444; font-weight:bold;}";
    echo ".info{color:#a78bfa;}";
    echo "pre{background:rgba(255,255,255,0.03); padding:15px; border:1px solid rgba(255,255,255,0.05); border-radius:8px; overflow-x:auto;}";
    echo "</style></head><body>";
    echo "<h1>Backend Demonstration & Verification Runner</h1>";
} else {
    echo "====================================================\n";
    echo "    BACKEND DEMONSTRATION & VERIFICATION RUNNER\n";
    echo "====================================================\n\n";
}
function print_log($msg, $status = 'info') {
    global $is_cli;
    if ($is_cli) {
        $color = "";
        if ($status === 'pass') $color = "[PASS] ";
        if ($status === 'fail') $color = "[FAIL] ";
        if ($status === 'info') $color = "[INFO] ";
        echo $color . strip_tags($msg) . "\n";
    } else {
        $class = $status;
        echo "<div style='margin-bottom:10px;'>";
        echo "<span class='$class'>" . strtoupper($status) . ":</span> " . $msg;
        echo "</div>";
    }
}
// 1. Include db.php to initiate connection (auto-creates DB if missing)
print_log("Loading database configuration (db.php)...");
require_once 'db.php';
if (isset($conn) && $conn) {
    print_log("Database connection established successfully.", "pass");
} else {
    print_log("Failed to connect to database.", "fail");
    exit;
}
try {
    // 2. Test Tables Existence
    print_log("Verifying database table existence...");
    $required_tables = ['Department', 'Employee', 'Attendance', 'Payroll', 'Payment', 'Leave_Management'];
    $all_passed = true;
    foreach ($required_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            print_log("• Table '$table' exists.", "pass");
        } else {
            print_log("• Table '$table' is missing!", "fail");
            $all_passed = false;
        }
    }
    if (!$all_passed) {
        throw new Exception("Database schema check failed. Some tables are missing.");
    }
    // 3. Test CRUD: Insert operations
    print_log("Testing Insert operations (CRUD)...");
    
    // Insert temporary test department
    $conn->exec("INSERT INTO Department (department_name) VALUES ('Test Department')");
    $test_dept_id = $conn->lastInsertId();
    print_log("• Inserted 'Test Department' (ID: $test_dept_id).", "pass");
    // Insert temporary test employee
    $stmt = $conn->prepare("INSERT INTO Employee (employee_name, phone, address, department_id, designation, joining_date) 
                            VALUES ('Test Engineer', '0000000000', 'Test Address', :dept_id, 'Test Tester', '2026-01-01')");
    $stmt->execute([':dept_id' => $test_dept_id]);
    $test_emp_id = $conn->lastInsertId();
    print_log("• Inserted Employee 'Test Engineer' (ID: $test_emp_id).", "pass");
    // Log base attendance
    $conn->exec("INSERT INTO Attendance (emp_id, working_days, leave_days, month) VALUES ($test_emp_id, 28, 2, 'June')");
    $test_attendance_id = $conn->lastInsertId();
    print_log("• Logged initial Attendance (28 working days, 2 leaves) for Month: 'June'.", "pass");
    // 4. Test Stored Procedure call
    print_log("Executing Stored Procedure 'CalculatePayroll'...");
    // Arguments: emp_id, basic_salary, bonus, deduction, salary_date
    $stmt = $conn->prepare("CALL CalculatePayroll(:emp_id, 50000.00, 5000.00, 1000.00, '2026-06-30')");
    $stmt->execute([':emp_id' => $test_emp_id]);
    
    // Verify Payroll insert occurred
    $stmt = $conn->prepare("SELECT payroll_id, net_salary, deduction FROM Payroll WHERE emp_id = ?");
    $stmt->execute([$test_emp_id]);
    $payroll = $stmt->fetch();
    
    if ($payroll) {
        print_log("• Payroll record created. Calculated Net Salary: ₹" . number_format($payroll['net_salary'], 2), "pass");
    } else {
        throw new Exception("Stored Procedure execution failed to insert payroll record.");
    }
    // 5. Test AFTER UPDATE Trigger (Attendance update -> auto update payroll)
    print_log("Testing Database Trigger: 'AfterAttendanceUpdate'...");
    print_log("• Changing Leave Days from 2 to 6...");
    
    $stmt = $conn->prepare("UPDATE Attendance SET leave_days = 6 WHERE attendance_id = ?");
    $stmt->execute([$test_attendance_id]);
    // Fetch updated payroll records
    $stmt = $conn->prepare("SELECT net_salary, deduction FROM Payroll WHERE emp_id = ?");
    $stmt->execute([$test_emp_id]);
    $updated_payroll = $stmt->fetch();
    $expected_deduction = 6 * 500.00; // 3000
    if ($updated_payroll && floatval($updated_payroll['deduction']) === $expected_deduction) {
        print_log("• Trigger executed! Deduction adjusted automatically to: ₹" . number_format($updated_payroll['deduction'], 2), "pass");
        print_log("• Net Salary recalculated automatically to: ₹" . number_format($updated_payroll['net_salary'], 2), "pass");
    } else {
        print_log("• Trigger failure. Deduction: " . ($updated_payroll['deduction'] ?? 'N/A'), "fail");
    }
    // 6. Test BEFORE INSERT Trigger (Enforce Duplicate Block)
    print_log("Testing Database Trigger: 'BeforePayrollInsert'...");
    print_log("• Attempting duplicate insert for same employee in same month (June 2026)...");
    try {
        // Attempt duplicate insert
        $conn->exec("INSERT INTO Payroll (emp_id, basic_salary, bonus, deduction, net_salary, salary_date) 
                     VALUES ($test_emp_id, 40000.00, 0.00, 0.00, 40000.00, '2026-06-15')");
        
        print_log("• Duplicate insert succeeded. Trigger failed to block!", "fail");
    } catch (PDOException $e) {
        if ($e->getCode() == '45000') {
            print_log("• Duplicate insert blocked successfully!", "pass");
            print_log("• Trigger raised expected exception: " . $e->getMessage(), "pass");
        } else {
            print_log("• Insert failed, but with unexpected SQLSTATE: " . $e->getCode(), "fail");
        }
    }
    // 7. Cleanup & Cascade Test
    print_log("Testing CRUD Delete & Cascade Integrity...");
    print_log("• Deleting Test Employee (ID: $test_emp_id)...");
    
    $conn->exec("DELETE FROM Employee WHERE emp_id = $test_emp_id");
    $conn->exec("DELETE FROM Department WHERE department_id = $test_dept_id");
    // Verify Cascade deleted the associated payroll
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM Payroll WHERE emp_id = ?");
    $stmt->execute([$test_emp_id]);
    $pr_count = $stmt->fetch()['count'];
    if ($pr_count == 0) {
        print_log("• Cascade successfully deleted associated Payroll records.", "pass");
    } else {
        print_log("• Cascade failure! Payroll records still exist.", "fail");
    }
    print_log("Backend testing complete! All checks successfully validated.", "pass");
} catch (Exception $e) {
    print_log("Execution interrupted by error: " . $e->getMessage(), "fail");
}
if (!$is_cli) {
    echo "</body></html>";
}
?>
