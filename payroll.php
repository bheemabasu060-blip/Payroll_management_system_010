<?php
// ====================================================================
// PAYROLL OPERATIONS & STORED PROCEDURE DEMO (payroll.php)
// ====================================================================
require_once '../db/connection.php';
require_once 'sidebar.php';
$message = "";
$message_type = "";
$payslip = null;
// 1. HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Action A: Run CalculateEmployeePayroll Stored Procedure
    if (isset($_POST['action']) && $_POST['action'] === 'calculate_proc') {
        $employee_id = intval($_POST['employee_id']);
        $working_days = intval($_POST['working_days']);
        $leaves_taken = intval($_POST['leaves_taken']);
        $overtime_hours = intval($_POST['overtime_hours']);
        $month = $_POST['payroll_month']; // Format 'YYYY-MM'
        try {
            // First prepare the OUT parameters. In PDO MySQL, we execute the call and then query session vars.
            $stmt = $conn->prepare("CALL CalculateEmployeePayroll(:emp, :working, :leaves, :ot, :month, @gross, @net)");
            $stmt->execute([
                'emp' => $employee_id,
                'working' => $working_days,
                'leaves' => $leaves_taken,
                'ot' => $overtime_hours,
                'month' => $month
            ]);
            
            // Retrieve outputs from MySQL session variables
            $outputs = $conn->query("SELECT @gross AS gross_salary, @net AS net_salary")->fetch();
            $gross = $outputs['gross_salary'];
            $net = $outputs['net_salary'];
            $message = "Stored Procedure executed successfully! Output: Gross = ₹" . number_format($gross, 2) . ", Net = ₹" . number_format($net, 2);
            $message_type = "success";
        } catch (PDOException $e) {
            // This catches check constraint failures or trigger errors (e.g. BeforePayrollInsert trigger error)
            $message = "Database Exception: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // Action B: Run GenerateSalaryStatement Stored Procedure (Payslip compile)
    if (isset($_POST['action']) && $_POST['action'] === 'view_payslip') {
        $employee_id = intval($_POST['employee_id']);
        $month = $_POST['payroll_month'];
        try {
            // Call procedure
            $stmt = $conn->prepare("CALL GenerateSalaryStatement(:emp, :month)");
            $stmt->execute(['emp' => $employee_id, 'month' => $month]);
            $payslip = $stmt->fetch();
            
            // Close cursor to allow subsequent queries if needed
            $stmt->closeCursor();
            if (!$payslip) {
                $message = "No payroll record found for the selected employee in month '$month'. Generate payroll first.";
                $message_type = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error generating statement: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
// 2. HANDLE DELETE ACTION
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM Payroll WHERE payroll_id = :id");
        $stmt->execute(['id' => $delete_id]);
        $message = "Payroll record deleted successfully.";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting payroll: " . $e->getMessage();
        $message_type = "danger";
    }
}
// 3. FETCH LIST DATA
try {
    // Fetch employees for dropdown
    $employees = $conn->query("SELECT e.employee_id, e.employee_name, s.basic_salary, s.hra, s.da, s.deductions 
                               FROM Employee e 
                               INNER JOIN Salary s ON e.employee_id = s.employee_id 
                               ORDER BY e.employee_name ASC")->fetchAll();
    // Fetch payroll reports list
    $payroll_reports = $conn->query("SELECT p.*, e.employee_name 
                                     FROM Payroll p 
                                     INNER JOIN Employee e ON p.employee_id = e.employee_id 
                                     ORDER BY p.payroll_month DESC, e.employee_name ASC")->fetchAll();
} catch (PDOException $e) {
    $employees = [];
    $payroll_reports = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll & Procedures - PayFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Sidebar navigation -->
    <?php render_sidebar('payroll'); ?>
    <!-- Main Content panel -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Payroll runs & Procedures</h1>
                <p class="page-subtitle">Execute stored SQL procedures, view salary statements, and test trigger constraints.</p>
            </div>
        </div>
        <!-- Notification Banner -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <div><?php echo $message_type === 'success' ? '✔' : '⚠'; ?></div>
                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        <!-- Split Grid: Stored Procedure runner and Trigger validation scenario -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; align-items: start;">
            
            <!-- Card 1: Execute Stored Procedure CalculateEmployeePayroll -->
            <div class="card">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 8px;">Run Payroll Generation (Stored Procedure)</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px;">
                    Executes the database routine `CalculateEmployeePayroll` using input parameters and returns computed gross/net outputs.
                </p>
                <form method="POST" action="payroll.php">
                    <input type="hidden" name="action" value="calculate_proc">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="proc_employee_id">Employee</label>
                            <select id="proc_employee_id" name="employee_id" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['employee_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="proc_month">Month</label>
                            <input type="month" id="proc_month" name="payroll_month" required value="<?php echo date('Y-m'); ?>">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="proc_working">Working Days</label>
                            <input type="number" id="proc_working" name="working_days" required min="0" max="31" placeholder="E.g. 29">
                        </div>
                        <div class="form-group">
                            <label for="proc_leaves">Leaves Taken</label>
                            <input type="number" id="proc_leaves" name="leaves_taken" required min="0" max="31" placeholder="E.g. 1">
                        </div>
                        <div class="form-group">
                            <label for="proc_ot">Overtime Hours</label>
                            <input type="number" id="proc_ot" name="overtime_hours" required min="0" placeholder="E.g. 8" value="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 15px; width: 100%;">CALL CalculateEmployeePayroll</button>
                </form>
            </div>
            <!-- Card 2: Trigger Demonstration Scenarios -->
            <div class="card" style="border-color: rgba(99, 102, 241, 0.25);">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 8px;">Trigger Verification Arena</h2>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 20px;">
                    Test constraints and trigger automations built inside MySQL.
                </p>
                <!-- Trigger A Explanation -->
                <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #6ee7b7; font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <span>✔</span> Trigger 1: Auto-Payroll on Attendance
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.82rem; line-height: 1.5;">
                        Go to the <strong>Attendance</strong> tab, log monthly attendance for any employee, and verify that a matching row is auto-generated in the Payroll reports table below!
                    </p>
                </div>
                <!-- Trigger B Test -->
                <div style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.15); border-radius: 12px; padding: 16px;">
                    <h3 style="color: #fca5a5; font-size: 0.95rem; font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <span>⚠</span> Trigger 2: Prevent Duplicate Payroll Runs
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.82rem; line-height: 1.5; margin-bottom: 12px;">
                        Attempt to insert double payroll records for the same employee in the same month. Select an employee who already has a generated payroll below:
                    </p>
                    <form method="POST" action="payroll.php">
                        <input type="hidden" name="action" value="calculate_proc">
                        <div style="display: flex; gap: 10px;">
                            <select name="employee_id" required style="flex-grow: 1; padding: 8px 12px; font-size: 0.85rem;">
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['employee_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="month" name="payroll_month" required value="<?php echo date('Y-m'); ?>" style="width: 130px; padding: 8px 12px; font-size: 0.85rem;">
                        </div>
                        <!-- Preset values to trigger standard insertion -->
                        <input type="hidden" name="working_days" value="28">
                        <input type="hidden" name="leaves_taken" value="0">
                        <input type="hidden" name="overtime_hours" value="0">
                        <button type="submit" class="btn btn-danger" style="padding: 8px 16px; font-size: 0.85rem; width: 100%; margin-top: 10px;">Trigger Duplicate Insertion Error</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- Payslip Modal Display if statement fetched -->
        <?php if ($payslip): ?>
            <div class="card" style="margin-bottom: 40px; border-color: var(--success); background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, rgba(30, 41, 59, 0.45) 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
                    <div>
                        <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.4rem;">Final Salary Statement</h2>
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Compile Slip ID: PAY-0<?php echo $payslip['payroll_id']; ?> (Month: <?php echo htmlspecialchars($payslip['payroll_month']); ?>)</p>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">🖨 Print Statement</button>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; line-height: 1.6; font-size: 0.92rem;">
                    <!-- Left: Employee HR details -->
                    <div>
                        <h3 style="color: var(--primary); font-size: 1.05rem; margin-bottom: 10px; border-bottom: 1px dashed var(--border-color); padding-bottom: 4px;">Employee Details</h3>
                        <p><strong style="color:var(--text-muted);">Employee ID:</strong> <?php echo $payslip['employee_id']; ?></p>
                        <p><strong style="color:var(--text-muted);">Employee Name:</strong> <?php echo htmlspecialchars($payslip['employee_name']); ?></p>
                        <p><strong style="color:var(--text-muted);">Department:</strong> <?php echo htmlspecialchars($payslip['department_name'] ?? 'Unassigned'); ?></p>
                        <p><strong style="color:var(--text-muted);">Designation:</strong> <?php echo htmlspecialchars($payslip['designation']); ?></p>
                        <p><strong style="color:var(--text-muted);">Phone:</strong> <?php echo htmlspecialchars($payslip['phone']); ?></p>
                        <p><strong style="color:var(--text-muted);">Joining Date:</strong> <?php echo htmlspecialchars($payslip['joining_date']); ?></p>
                    </div>
                    <!-- Right: Financial details -->
                    <div>
                        <h3 style="color: var(--primary); font-size: 1.05rem; margin-bottom: 10px; border-bottom: 1px dashed var(--border-color); padding-bottom: 4px;">Earnings & Deductions</h3>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Basic Salary:</span>
                            <span>₹<?php echo number_format($payslip['basic_salary'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>HRA:</span>
                            <span>₹<?php echo number_format($payslip['hra'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>DA:</span>
                            <span>₹<?php echo number_format($payslip['da'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Overtime Pay (<?php echo $payslip['overtime_hours']; ?> hrs):</span>
                            <span>+ ₹<?php echo number_format($payslip['overtime_payout'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #fca5a5;">
                            <span>Base Deductions:</span>
                            <span>- ₹<?php echo number_format($payslip['standard_deductions'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; color: #fca5a5; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                            <span>Leave Penalties (<?php echo $payslip['leaves_taken']; ?> leaves):</span>
                            <span>- ₹<?php echo number_format($payslip['leave_deduction'], 2); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.05rem; margin-bottom: 10px;">
                            <span>Gross Salary:</span>
                            <span>₹<?php echo number_format($payslip['gross_salary'], 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem; color: var(--secondary);">
                            <span>Net Salary:</span>
                            <span>₹<?php echo number_format($payslip['net_salary'], 2); ?></span>
                        </div>
                    </div>
                </div>
                <!-- Payout Details -->
                <div style="margin-top: 25px; border-top: 1px dashed var(--border-color); padding-top: 15px; font-size: 0.9rem;">
                    <h3 style="color: var(--primary); font-size: 1.05rem; margin-bottom: 8px;">Disbursement Status</h3>
                    <?php if ($payslip['payment_id']): ?>
                        <p><span class="badge badge-success">PAID</span> via <strong><?php echo htmlspecialchars($payslip['payment_mode']); ?></strong> on <strong><?php echo htmlspecialchars($payslip['payment_date']); ?></strong> (Txn Ref: TXN-0<?php echo $payslip['payment_id']; ?>)</p>
                    <?php else: ?>
                        <p><span class="badge badge-warning">PENDING DISBURSEMENT</span> - Navigate to the <strong>Disbursements</strong> tab to process this salary payout.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <!-- Payroll Reports List Table -->
        <div class="card">
            <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 15px;">Payroll Records & Slips</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Payroll ID</th>
                            <th>Employee Name</th>
                            <th>Salary Month</th>
                            <th>Gross Salary (₹)</th>
                            <th>Net Salary (₹)</th>
                            <th>Calculation Date</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payroll_reports) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted);">No payroll sheets compiled yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payroll_reports as $report): ?>
                                <tr>
                                    <td><strong>PAY-0<?php echo $report['payroll_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($report['employee_name']); ?></td>
                                    <td><span class="badge badge-purple"><?php echo htmlspecialchars($report['payroll_month']); ?></span></td>
                                    <td>₹<?php echo number_format($report['gross_salary'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($report['net_salary'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($report['generated_date']); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <!-- Payslip view form -->
                                            <form method="POST" action="payroll.php">
                                                <input type="hidden" name="action" value="view_payslip">
                                                <input type="hidden" name="employee_id" value="<?php echo $report['employee_id']; ?>">
                                                <input type="hidden" name="payroll_month" value="<?php echo $report['payroll_month']; ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">View Statement</button>
                                            </form>
                                            <a href="payroll.php?delete_id=<?php echo $report['payroll_id']; ?>" class="btn btn-danger btn-delete-confirm" style="padding: 6px 12px; font-size: 0.8rem;">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Script imports -->
    <script src="js/main.js"></script>
</body>
</html>
