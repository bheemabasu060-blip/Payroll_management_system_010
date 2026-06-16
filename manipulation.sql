-- ====================================================================
-- PAYROLL MANAGEMENT SYSTEM - 10 REQUIRED DATABASE QUERIES
-- ====================================================================
USE payroll_db;
-- 1. Retrieve all employee records
-- Fetches the complete employee roster.
SELECT employee_id, employee_name, designation, phone, joining_date 
FROM Employee;
-- 2. Display employees belonging to a specific department
-- Filters employees who are in the 'Engineering' department (department_id = 2).
SELECT e.employee_id, e.employee_name, e.designation, d.department_name
FROM Employee e
INNER JOIN Department d ON e.department_id = d.department_id
WHERE d.department_name = 'Engineering';
-- 3. Display employee and salary details (INNER JOIN)
-- Combines master employee information with their basic pay structure.
SELECT e.employee_id, e.employee_name, e.designation, s.basic_salary, s.hra, s.da, s.deductions
FROM Employee e
INNER JOIN Salary s ON e.employee_id = s.employee_id;
-- 4. Display employee, salary, and payroll details (3-table JOIN)
-- Combines master employee details, salary parameters, and generated monthly net pay.
SELECT 
    e.employee_id, 
    e.employee_name, 
    s.basic_salary, 
    p.gross_salary, 
    p.net_salary, 
    p.payroll_month,
    p.generated_date
FROM Employee e
INNER JOIN Salary s ON e.employee_id = s.employee_id
INNER JOIN Payroll p ON e.employee_id = p.employee_id;
-- 5. Count employees department-wise (GROUP BY)
-- Aggregates the number of staff members assigned to each department.
SELECT d.department_name, COUNT(e.employee_id) AS total_employees
FROM Department d
LEFT JOIN Employee e ON d.department_id = e.department_id
GROUP BY d.department_id, d.department_name;
-- 6. Display departments having more than 20 employees (HAVING)
-- Identifies large departments with structural headcount above 20.
-- Engineering should show up in the results because we inserted 22 employees.
SELECT d.department_name, COUNT(e.employee_id) AS total_employees
FROM Department d
INNER JOIN Employee e ON d.department_id = e.department_id
GROUP BY d.department_id, d.department_name
HAVING COUNT(e.employee_id) > 20;
-- 7. Retrieve employees whose salary exceeds average salary (Subquery)
-- Uses a subquery to find employees paid higher than the organizational average.
SELECT e.employee_id, e.employee_name, s.basic_salary
FROM Employee e
INNER JOIN Salary s ON e.employee_id = s.employee_id
WHERE s.basic_salary > (
    SELECT AVG(basic_salary) 
    FROM Salary
);
-- 8. Retrieve employees with attendance greater than a selected employee (Correlated Subquery)
-- Compares working days of other employees to a selected employee (e.g., employee_id = 1) for the same month.
SELECT e.employee_id, e.employee_name, a.working_days, a.attendance_month
FROM Employee e
INNER JOIN Attendance a ON e.employee_id = a.employee_id
WHERE a.working_days > (
    SELECT a2.working_days 
    FROM Attendance a2 
    WHERE a2.employee_id = 1 -- Selected reference employee
      AND a2.attendance_month = a.attendance_month
);
-- 9. Display all employees including those without payroll generation (LEFT JOIN)
-- Demonstrates outer join showing all active employees and their payroll generation status.
-- Interns or newly joined employees without logged attendance will show NULL values for payroll.
SELECT e.employee_id, e.employee_name, p.payroll_id, p.net_salary, p.payroll_month
FROM Employee e
LEFT JOIN Payroll p ON e.employee_id = p.employee_id;
-- 10. Retrieve departments with no employee assignments (NOT EXISTS)
-- Finds departments that are registered in the master list but have zero staff (e.g. 'Finance').
SELECT d.department_id, d.department_name
FROM Department d
WHERE NOT EXISTS (
    SELECT 1 
    FROM Employee e 
    WHERE e.department_id = d.department_id
);
