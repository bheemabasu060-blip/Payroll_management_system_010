<?php
// ====================================================================
// ATTENDANCE & LEAVE MANAGEMENT (attendance.php)
// ====================================================================
require_once '../db/connection.php';
require_once 'sidebar.php';
$message = "";
$message_type = "";
// 1. HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log new attendance
    if (isset($_POST['action']) && $_POST['action'] === 'log') {
        $employee_id = intval($_POST['employee_id']);
        $working_days = intval($_POST['working_days']);
        $leaves_taken = intval($_POST['leaves_taken']);
        $overtime_hours = intval($_POST['overtime_hours']);
        $month = $_POST['attendance_month']; // Format 'YYYY-MM'
        try {
            // This insert will activate AfterAttendanceInsert trigger (automatically creates Payroll record)
            $stmt = $conn->prepare("INSERT INTO Attendance (employee_id, working_days, leaves_taken, overtime_hours, attendance_month) 
                                    VALUES (:emp, :working, :leaves, :ot, :month)");
            $stmt->execute([
                'emp' => $employee_id,
                'working' => $working_days,
                'leaves' => $leaves_taken,
                'ot' => $overtime_hours,
                'month' => $month
            ]);
            $message = "Attendance logged successfully! Trigger auto-generated the Payroll record.";
            $message_type = "success";
        } catch (PDOException $e) {
            // Catch unique constraint violation or check constraint failures
            if ($e->getCode() == 23000) {
                $message = "Error: Attendance has already been logged for this employee in the selected month.";
            } else {
                $message = "Error logging attendance: " . $e->getMessage();
            }
            $message_type = "danger";
        }
    }
    // Edit logged attendance
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $attendance_id = intval($_POST['attendance_id']);
        $working_days = intval($_POST['working_days']);
        $leaves_taken = intval($_POST['leaves_taken']);
        $overtime_hours = intval($_POST['overtime_hours']);
        try {
            // Fetch employee and month before updating to show details
            $stmt = $conn->prepare("SELECT employee_id, attendance_month FROM Attendance WHERE attendance_id = :id");
            $stmt->execute(['id' => $attendance_id]);
            $att = $stmt->fetch();
            if ($att) {
                // This update will activate AfterAttendanceUpdate trigger (automatically recalculates and updates Payroll)
                $stmt = $conn->prepare("UPDATE Attendance 
                                        SET working_days = :working, leaves_taken = :leaves, overtime_hours = :ot 
                                        WHERE attendance_id = :id");
                $stmt->execute([
                    'working' => $working_days,
                    'leaves' => $leaves_taken,
                    'ot' => $overtime_hours,
                    'id' => $attendance_id
                ]);
                $message = "Attendance updated! Trigger recalculated and updated the corresponding Payroll record.";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Error updating attendance: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // Log Leave Request
    if (isset($_POST['action']) && $_POST['action'] === 'leave') {
        $employee_id = intval($_POST['employee_id']);
        $leave_type = $_POST['leave_type'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['approval_status'] ?? 'Pending';
        try {
            $stmt = $conn->prepare("INSERT INTO Leave_Request (employee_id, leave_type, start_date, end_date, approval_status) 
                                    VALUES (:emp, :type, :start, :end, :status)");
            $stmt->execute([
                'emp' => $employee_id,
                'type' => $leave_type,
                'start' => $start_date,
                'end' => $end_date,
                'status' => $status
            ]);
            $message = "Leave request logged successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error logging leave request: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // Update Leave Status
    if (isset($_POST['action']) && $_POST['action'] === 'leave_status') {
        $request_id = intval($_POST['request_id']);
        $status = $_POST['approval_status'];
        try {
            $stmt = $conn->prepare("UPDATE Leave_Request SET approval_status = :status WHERE request_id = :id");
            $stmt->execute(['status' => $status, 'id' => $request_id]);
            $message = "Leave request status updated to '$status'!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating leave status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
// 2. RETRIEVE RECORD TO EDIT IF SELECTED
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    try {
        $stmt = $conn->prepare("SELECT a.*, e.employee_name, s.basic_salary, s.hra, s.da, s.deductions 
                                FROM Attendance a 
                                INNER JOIN Employee e ON a.employee_id = e.employee_id 
                                INNER JOIN Salary s ON e.employee_id = s.employee_id 
                                WHERE a.attendance_id = :id");
        $stmt->execute(['id' => $edit_id]);
        $edit_data = $stmt->fetch();
    } catch (PDOException $e) {
        // fail silently
    }
}
// 3. FETCH DATA lists
try {
    // Fetch employee master list with salary details for preview calculations
    $employees = $conn->query("SELECT e.employee_id, e.employee_name, s.basic_salary, s.hra, s.da, s.deductions 
                               FROM Employee e 
                               INNER JOIN Salary s ON e.employee_id = s.employee_id 
                               ORDER BY e.employee_name ASC")->fetchAll();
    // Fetch attendance logs
    $attendance_logs = $conn->query("SELECT a.*, e.employee_name 
                                     FROM Attendance a 
                                     INNER JOIN Employee e ON a.employee_id = e.employee_id 
                                     ORDER BY a.attendance_month DESC, e.employee_name ASC")->fetchAll();
    // Fetch leave requests
    $leave_requests = $conn->query("SELECT lr.*, e.employee_name 
                                    FROM Leave_Request lr 
                                    INNER JOIN Employee e ON lr.employee_id = e.employee_id 
                                    ORDER BY lr.request_id DESC")->fetchAll();
} catch (PDOException $e) {
    $employees = [];
    $attendance_logs = [];
    $leave_requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance & Leave - PayFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Sidebar navigation -->
    <?php render_sidebar('attendance'); ?>
    <!-- Main Content panel -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Attendance & Leaves</h1>
                <p class="page-subtitle">Track staff attendance entries, leaves, and overtime metrics.</p>
            </div>
        </div>
        <!-- Notification Banner -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <div><?php echo $message_type === 'success' ? '✔' : '⚠'; ?></div>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        <!-- Attendance logs form -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px; align-items: start;">
            
            <div class="card">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 20px;">
                    <?php echo $edit_data ? "Update Attendance Profile - " . htmlspecialchars($edit_data['employee_name']) : 'Log Monthly Attendance'; ?>
                </h2>
                <form method="POST" action="attendance.php">
                    <input type="hidden" name="action" value="<?php echo $edit_data ? 'update' : 'log'; ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="attendance_id" value="<?php echo $edit_data['attendance_id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="employee_id">Employee Name</label>
                            <?php if ($edit_data): ?>
                                <input type="text" readonly value="<?php echo htmlspecialchars($edit_data['employee_name']); ?>">
                                <input type="hidden" id="employee_id" value="<?php echo $edit_data['employee_id']; ?>">
                            <?php else: ?>
                                <select id="employee_id" name="employee_id" required>
                                    <option value="">-- Choose Employee --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['employee_id']; ?>" 
                                                data-basic="<?php echo $emp['basic_salary']; ?>"
                                                data-hra="<?php echo $emp['hra']; ?>"
                                                data-da="<?php echo $emp['da']; ?>"
                                                data-ded="<?php echo $emp['deductions']; ?>">
                                            <?php echo htmlspecialchars($emp['employee_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="attendance_month">Month</label>
                            <input type="month" id="attendance_month" name="attendance_month" required 
                                   value="<?php echo $edit_data ? $edit_data['attendance_month'] : date('Y-m'); ?>"
                                   <?php echo $edit_data ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    <!-- Hidden fields to hold selected employee salary components for client JS calculations -->
                    <input type="hidden" id="base_basic" value="<?php echo $edit_data ? $edit_data['basic_salary'] : '0'; ?>">
                    <input type="hidden" id="base_hra" value="<?php echo $edit_data ? $edit_data['hra'] : '0'; ?>">
                    <input type="hidden" id="base_da" value="<?php echo $edit_data ? $edit_data['da'] : '0'; ?>">
                    <input type="hidden" id="base_ded" value="<?php echo $edit_data ? $edit_data['deductions'] : '0'; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="working_days">Working Days</label>
                            <input type="number" id="working_days" name="working_days" required min="0" max="31" placeholder="E.g. 28" value="<?php echo $edit_data ? $edit_data['working_days'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="leaves_taken">Leaves Taken</label>
                            <input type="number" id="leaves_taken" name="leaves_taken" required min="0" max="31" placeholder="E.g. 2" value="<?php echo $edit_data ? $edit_data['leaves_taken'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="overtime_hours">Overtime Hours</label>
                            <input type="number" id="overtime_hours" name="overtime_hours" required min="0" placeholder="E.g. 10" value="<?php echo $edit_data ? $edit_data['overtime_hours'] : '0'; ?>">
                        </div>
                    </div>
                    <div id="days_warning" style="font-size: 0.85rem; font-weight: 500; margin-top: 5px;"></div>
                    <div style="display: flex; gap: 15px; margin-top: 25px;">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_data ? 'Update Log' : 'Record Attendance'; ?></button>
                        <?php if ($edit_data): ?>
                            <a href="attendance.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <!-- Estimated Monthly Pay Preview -->
            <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(30, 41, 59, 0.45) 100%);">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.2rem; margin-bottom: 20px; text-align: center;">Monthly Pay Projection</h2>
                
                <div style="display: flex; flex-direction: column; gap: 18px; margin-top: 10px;">
                    <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 10px;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Gross Projection</div>
                        <div id="attend_gross" style="font-family: var(--font-heading); font-size: 1.8rem; font-weight: 700; color: #fff;">₹0.00</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Includes overtime at ₹250/hr</div>
                    </div>
                    
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Net Pay Projection</div>
                        <div id="attend_net" style="font-family: var(--font-heading); font-size: 1.8rem; font-weight: 700; color: var(--secondary);">₹0.00</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Includes leave deduction penalty</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Content split for Logs and Leave Requests -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px; align-items: start;">
            <!-- Attendance Summary Table -->
            <div class="card">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 15px;">Logged Attendance Logs</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Month</th>
                                <th>Working Days</th>
                                <th>Leaves</th>
                                <th>Overtime</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendance_logs) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">No attendance records logged yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance_logs as $log): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($log['employee_name']); ?></strong></td>
                                        <td><span class="badge badge-purple"><?php echo htmlspecialchars($log['attendance_month']); ?></span></td>
                                        <td><?php echo $log['working_days']; ?> days</td>
                                        <td><?php echo $log['leaves_taken']; ?> days</td>
                                        <td><?php echo $log['overtime_hours']; ?> hrs</td>
                                        <td style="text-align: center;">
                                            <a href="attendance.php?edit_id=<?php echo $log['attendance_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Leave Request logging & approvals -->
            <div class="card">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 20px;">Request Leave / Track Approvals</h2>
                <form method="POST" action="attendance.php" style="margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 25px;">
                    <input type="hidden" name="action" value="leave">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="leave_employee_id">Employee</label>
                            <select id="leave_employee_id" name="employee_id" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['employee_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="leave_type">Leave Type</label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="Casual">Casual Leave</option>
                                <option value="Sick">Sick Leave</option>
                                <option value="Earned">Earned Leave</option>
                                <option value="Maternity">Maternity Leave</option>
                                <option value="LWP">Leave Without Pay (LWP)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="approval_status">Initial Status</label>
                            <select id="approval_status" name="approval_status">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Apply Leave</button>
                </form>
                <!-- Leave Requests summary table -->
                <h3 style="font-family: var(--font-heading); color: #fff; font-size: 1.1rem; margin-bottom: 12px;">Active Leave Files</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($leave_requests) === 0): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No leave requests documented.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leave_requests as $req): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($req['employee_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($req['leave_type']); ?></td>
                                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($req['start_date']) . ' to ' . htmlspecialchars($req['end_date']); ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $status = $req['approval_status'];
                                                $badge_class = 'badge-warning';
                                                if ($status === 'Approved') $badge_class = 'badge-success';
                                                if ($status === 'Rejected') $badge_class = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" action="attendance.php" style="display: flex; gap: 4px; justify-content: center;">
                                                <input type="hidden" name="action" value="leave_status">
                                                <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                                <?php if ($status === 'Pending'): ?>
                                                    <button type="submit" name="approval_status" value="Approved" class="btn btn-success" style="padding: 4px 8px; font-size: 0.75rem;">Approve</button>
                                                    <button type="submit" name="approval_status" value="Rejected" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.75rem;">Reject</button>
                                                <?php elseif ($status === 'Approved'): ?>
                                                    <button type="submit" name="approval_status" value="Rejected" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.75rem;">Revoke</button>
                                                <?php else: ?>
                                                    <button type="submit" name="approval_status" value="Approved" class="btn btn-success" style="padding: 4px 8px; font-size: 0.75rem;">Approve</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Script imports -->
    <script src="js/main.js"></script>
</body>
</html>
