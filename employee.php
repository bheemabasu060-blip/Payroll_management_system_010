<?php
// ====================================================================
// EMPLOYEE & SALARY CRUD MANAGEMENT (employee.php)
// ====================================================================
require_once '../db/connection.php';
require_once 'sidebar.php';
$message = "";
$message_type = "";
// 1. HANDLE POST ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Employee & Salary Details
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $name = trim($_POST['employee_name']);
        $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $designation = trim($_POST['designation']);
        $phone = trim($_POST['phone']);
        $joining_date = $_POST['joining_date'];
        $basic = floatval($_POST['basic_salary']);
        $hra = floatval($_POST['hra']);
        $da = floatval($_POST['da']);
        $deductions = floatval($_POST['deductions']);
        try {
            $conn->beginTransaction();
            // Insert employee
            $stmt = $conn->prepare("INSERT INTO Employee (employee_name, department_id, designation, phone, joining_date) 
                                    VALUES (:name, :dept, :designation, :phone, :joining)");
            $stmt->execute([
                'name' => $name,
                'dept' => $dept_id,
                'designation' => $designation,
                'phone' => $phone,
                'joining' => $joining_date
            ]);
            $employee_id = $conn->lastInsertId();
            // Insert salary components
            $stmt = $conn->prepare("INSERT INTO Salary (employee_id, basic_salary, hra, da, deductions) 
                                    VALUES (:emp_id, :basic, :hra, :da, :deductions)");
            $stmt->execute([
                'emp_id' => $employee_id,
                'basic' => $basic,
                'hra' => $hra,
                'da' => $da,
                'deductions' => $deductions
            ]);
            $conn->commit();
            $message = "Employee record and salary components added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error adding record: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    // Update Employee & Salary Details
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $employee_id = intval($_POST['employee_id']);
        $name = trim($_POST['employee_name']);
        $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
        $designation = trim($_POST['designation']);
        $phone = trim($_POST['phone']);
        $joining_date = $_POST['joining_date'];
        $basic = floatval($_POST['basic_salary']);
        $hra = floatval($_POST['hra']);
        $da = floatval($_POST['da']);
        $deductions = floatval($_POST['deductions']);
        try {
            $conn->beginTransaction();
            // Update Employee details
            $stmt = $conn->prepare("UPDATE Employee 
                                    SET employee_name = :name, department_id = :dept, designation = :designation, phone = :phone, joining_date = :joining 
                                    WHERE employee_id = :emp_id");
            $stmt->execute([
                'name' => $name,
                'dept' => $dept_id,
                'designation' => $designation,
                'phone' => $phone,
                'joining' => $joining_date,
                'emp_id' => $employee_id
            ]);
            // Update Salary details
            $stmt = $conn->prepare("UPDATE Salary 
                                    SET basic_salary = :basic, hra = :hra, da = :da, deductions = :deductions 
                                    WHERE employee_id = :emp_id");
            $stmt->execute([
                'basic' => $basic,
                'hra' => $hra,
                'da' => $da,
                'deductions' => $deductions,
                'emp_id' => $employee_id
            ]);
            $conn->commit();
            $message = "Employee and salary details updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $conn->rollBack();
            $message = "Error updating record: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}
// 2. HANDLE DELETE ACTION
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        // Enforce cascade deletes on child records (foreign keys set to cascade delete on salary, attendance, etc.)
        $stmt = $conn->prepare("DELETE FROM Employee WHERE employee_id = :id");
        $stmt->execute(['id' => $delete_id]);
        $message = "Employee record deleted successfully (Cascade Delete triggered).";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $message_type = "danger";
    }
}
// 3. RETRIEVE RECORD TO EDIT IF SELECTED
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    try {
        $stmt = $conn->prepare("SELECT e.*, s.basic_salary, s.hra, s.da, s.deductions 
                                FROM Employee e 
                                INNER JOIN Salary s ON e.employee_id = s.employee_id 
                                WHERE e.employee_id = :id");
        $stmt->execute(['id' => $edit_id]);
        $edit_data = $stmt->fetch();
    } catch (PDOException $e) {
        // Silently fail or log
    }
}
// 4. FETCH ALL EMPLOYEES & DEPARTMENTS
try {
    $employees = $conn->query("SELECT e.*, d.department_name, s.basic_salary, s.hra, s.da, s.deductions 
                               FROM Employee e 
                               INNER JOIN Salary s ON e.employee_id = s.employee_id 
                               LEFT JOIN Department d ON e.department_id = d.department_id 
                               ORDER BY e.employee_id DESC")->fetchAll();
    $departments = $conn->query("SELECT * FROM Department ORDER BY department_name ASC")->fetchAll();
} catch (PDOException $e) {
    $employees = [];
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - PayFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Sidebar navigation -->
    <?php render_sidebar('employees'); ?>
    <!-- Main Content panel -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Employees Directory</h1>
                <p class="page-subtitle">Configure employee roster files and base salary structures.</p>
            </div>
        </div>
        <!-- Notification Banner -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <div><?php echo $message_type === 'success' ? '✔' : '⚠'; ?></div>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>
        <!-- Form and Live Preview Section -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px; align-items: start;">
            <!-- Form Card -->
            <div class="card">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 20px;">
                    <?php echo $edit_data ? 'Modify Employee Profile' : 'Register New Employee'; ?>
                </h2>
                <form method="POST" action="employee.php">
                    <input type="hidden" name="action" value="<?php echo $edit_data ? 'update' : 'add'; ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="employee_id" value="<?php echo $edit_data['employee_id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="employee_name">Full Name</label>
                            <input type="text" id="employee_name" name="employee_name" required placeholder="E.g. Rajesh Kumar" value="<?php echo $edit_data ? htmlspecialchars($edit_data['employee_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo ($edit_data && $edit_data['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endstyle; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="designation">Designation</label>
                            <input type="text" id="designation" name="designation" required placeholder="E.g. Tech Lead" value="<?php echo $edit_data ? htmlspecialchars($edit_data['designation']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" required placeholder="E.g. 9876543210" value="<?php echo $edit_data ? htmlspecialchars($edit_data['phone']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="joining_date">Joining Date</label>
                            <input type="date" id="joining_date" name="joining_date" required value="<?php echo $edit_data ? $edit_data['joining_date'] : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <h3 style="font-family: var(--font-heading); color: #fff; font-size: 1.1rem; margin: 25px 0 15px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Base Salary Structure</h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="basic_salary">Basic Salary (₹)</label>
                            <input type="number" id="basic_salary" name="basic_salary" required min="0" step="0.01" placeholder="E.g. 50000" value="<?php echo $edit_data ? $edit_data['basic_salary'] : '0.00'; ?>">
                        </div>
                        <div class="form-group">
                            <label for="hra">HRA (₹)</label>
                            <input type="number" id="hra" name="hra" required min="0" step="0.01" placeholder="E.g. 12500" value="<?php echo $edit_data ? $edit_data['hra'] : '0.00'; ?>">
                        </div>
                        <div class="form-group">
                            <label for="da">DA (₹)</label>
                            <input type="number" id="da" name="da" required min="0" step="0.01" placeholder="E.g. 8000" value="<?php echo $edit_data ? $edit_data['da'] : '0.00'; ?>">
                        </div>
                        <div class="form-group">
                            <label for="deductions">Standard Deductions (₹)</label>
                            <input type="number" id="deductions" name="deductions" required min="0" step="0.01" placeholder="E.g. 4000" value="<?php echo $edit_data ? $edit_data['deductions'] : '0.00'; ?>">
                        </div>
                    </div>
                    <div style="display: flex; gap: 15px; margin-top: 25px;">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_data ? 'Save Changes' : 'Register Employee'; ?></button>
                        <?php if ($edit_data): ?>
                            <a href="employee.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <!-- Live Calculation Preview Card -->
            <div class="card" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(30, 41, 59, 0.45) 100%);">
                <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.2rem; margin-bottom: 20px; text-align: center;">Live Salary Preview</h2>
                
                <div style="display: flex; flex-direction: column; gap: 18px; margin-top: 10px;">
                    <div style="border-bottom: 1px dashed var(--border-color); padding-bottom: 10px;">
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Estimated Gross</div>
                        <div id="preview_gross" style="font-family: var(--font-heading); font-size: 1.8rem; font-weight: 700; color: #fff;">₹0.00</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Basic + HRA + DA</div>
                    </div>
                    
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase;">Estimated Net Pay</div>
                        <div id="preview_net" style="font-family: var(--font-heading); font-size: 1.8rem; font-weight: 700; color: var(--secondary);">₹0.00</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Gross - Deductions</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Master Employees Table -->
        <div class="card">
            <h2 style="font-family: var(--font-heading); color: #fff; font-size: 1.3rem; margin-bottom: 15px;">Active Employees Master List</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Phone</th>
                            <th>Joining Date</th>
                            <th>Basic (₹)</th>
                            <th>HRA (₹)</th>
                            <th>DA (₹)</th>
                            <th>Deductions (₹)</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) === 0): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; color: var(--text-muted);">No employees registered yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><strong><?php echo $emp['employee_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($emp['employee_name']); ?></td>
                                    <td>
                                        <span class="badge badge-purple"><?php echo htmlspecialchars($emp['department_name'] ?? 'Unassigned'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['joining_date']); ?></td>
                                    <td>₹<?php echo number_format($emp['basic_salary'], 2); ?></td>
                                    <td>₹<?php echo number_format($emp['hra'], 2); ?></td>
                                    <td>₹<?php echo number_format($emp['da'], 2); ?></td>
                                    <td>₹<?php echo number_format($emp['deductions'], 2); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a href="employee.php?edit_id=<?php echo $emp['employee_id']; ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">Edit</a>
                                            <a href="employee.php?delete_id=<?php echo $emp['employee_id']; ?>" class="btn btn-danger btn-delete-confirm" style="padding: 6px 12px; font-size: 0.8rem;">Delete</a>
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
