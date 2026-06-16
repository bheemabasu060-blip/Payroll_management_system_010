-- ====================================================================
-- PAYROLL MANAGEMENT SYSTEM - STORED PROCEDURES
-- ====================================================================
USE payroll_db;
DROP PROCEDURE IF EXISTS CalculateEmployeePayroll;
DROP PROCEDURE IF EXISTS GenerateSalaryStatement;
DELIMITER //
-- Procedure 1: Calculate Employee Payroll
-- Inputs: Employee ID, Working Days, Leaves Taken, Overtime Hours, Month (YYYY-MM)
-- Outputs: Gross Salary, Net Salary
-- It fetches base salary parameters and records the payroll run.
CREATE PROCEDURE CalculateEmployeePayroll(
    IN p_employee_id INT,
    IN p_working_days INT,
    IN p_leaves_taken INT,
    IN p_overtime_hours INT,
    IN p_month VARCHAR(7),
    OUT p_gross_salary DECIMAL(10, 2),
    OUT p_net_salary DECIMAL(10, 2)
)
BEGIN
    DECLARE v_basic DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_hra DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_da DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_deductions DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_overtime_rate DECIMAL(10, 2) DEFAULT 250.00; -- ₹250 per overtime hour
    DECLARE v_daily_rate DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_leave_penalty DECIMAL(10, 2) DEFAULT 0.00;
    -- Fetch Salary components for the employee
    SELECT basic_salary, hra, da, deductions 
    INTO v_basic, v_hra, v_da, v_deductions
    FROM Salary 
    WHERE employee_id = p_employee_id;
    -- Calculate Gross Salary = Basic + HRA + DA + Overtime Payout
    SET p_gross_salary = v_basic + v_hra + v_da + (p_overtime_hours * v_overtime_rate);
    -- Calculate Daily Rate for leave deductions (Basic / 30 days)
    IF v_basic > 0 THEN
        SET v_daily_rate = v_basic / 30.00;
    END IF;
    
    SET v_leave_penalty = p_leaves_taken * v_daily_rate;
    -- Calculate Net Salary = Gross - Base Deductions - Leave Penalty
    SET p_net_salary = p_gross_salary - v_deductions - v_leave_penalty;
    -- Guarantee Net Salary is not negative
    IF p_net_salary < 0 THEN
        SET p_net_salary = 0.00;
    END IF;
    -- Insert into Payroll table (Trigger BeforePayrollInsert will validate duplicates)
    INSERT INTO Payroll (employee_id, gross_salary, net_salary, generated_date, payroll_month)
    VALUES (p_employee_id, p_gross_salary, p_net_salary, CURDATE(), p_month);
END //
-- Procedure 2: Generate Final Salary Statement
-- Inputs: Employee ID, Month (YYYY-MM)
-- Outputs: Returns a detailed record set representing the salary slip
CREATE PROCEDURE GenerateSalaryStatement(
    IN p_employee_id INT,
    IN p_month VARCHAR(7)
)
BEGIN
    SELECT 
        e.employee_id,
        e.employee_name,
        d.department_name,
        e.designation,
        e.phone,
        e.joining_date,
        s.basic_salary,
        s.hra,
        s.da,
        s.deductions AS standard_deductions,
        a.working_days,
        a.leaves_taken,
        a.overtime_hours,
        (a.overtime_hours * 250.00) AS overtime_payout,
        (a.leaves_taken * (s.basic_salary / 30.00)) AS leave_deduction,
        p.payroll_id,
        p.gross_salary,
        p.net_salary,
        p.generated_date,
        p.payroll_month,
        pay.payment_id,
        pay.amount_paid,
        pay.payment_date,
        pay.payment_mode
    FROM Employee e
    LEFT JOIN Department d ON e.department_id = d.department_id
    LEFT JOIN Salary s ON e.employee_id = s.employee_id
    LEFT JOIN Attendance a ON e.employee_id = a.employee_id AND a.attendance_month = p_month
    LEFT JOIN Payroll p ON e.employee_id = p.employee_id AND p.payroll_month = p_month
    LEFT JOIN Payment pay ON p.payroll_id = pay.payroll_id
    WHERE e.employee_id = p_employee_id;
END //
DELIMITER ;
