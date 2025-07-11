CREATE DATABASE IF NOT EXISTS ris_lis;

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    hod_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table (For Admins, Doctors, Lab Technicians, etc.)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'lab_technician', 'radiologist', 'physiologist', 'receptionist', 'accountant', 'marketer', 'ict', 'media', 'inventory', 'result_collection', 'patient', 'staff') NOT NULL,
    department_id INT,
    specialization VARCHAR(100),
    phone VARCHAR(20),
    avatar VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Add HOD foreign key constraint after users table exists
ALTER TABLE departments
ADD CONSTRAINT fk_hod
FOREIGN KEY (hod_id) REFERENCES users(id) ON DELETE SET NULL;

-- Patients Table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(20),
    email VARCHAR(255) UNIQUE,
    barcode VARCHAR(255) UNIQUE NOT NULL,
    address TEXT,
    blood_group VARCHAR(10),
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tests Table (Laboratory Tests)
CREATE TABLE IF NOT EXISTS tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department_id INT,
    price DECIMAL(10,2) NOT NULL,
    priority ENUM('normal', 'urgent', 'critical') DEFAULT 'normal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Test Results Table (Laboratory Results)
CREATE TABLE IF NOT EXISTS test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    test_id INT,
    result TEXT,
    status ENUM('pending', 'verified', 'approved') DEFAULT 'pending',
    sample_status ENUM('collected', 'processing', 'completed', 'rejected') DEFAULT 'collected',
    technician_id INT,
    doctor_id INT,
    assigned_to INT,
    hod_verified ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    machine_id INT,
    signature TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Machine Integrations Table (for both lab and radiology equipment)
CREATE TABLE IF NOT EXISTS machine_integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    port INT NOT NULL,
    status ENUM('connected', 'disconnected') DEFAULT 'disconnected',
    type ENUM('laboratory', 'radiology', 'physiology') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add machine_id foreign key constraint after machine_integrations exists
ALTER TABLE test_results
ADD FOREIGN KEY (machine_id) REFERENCES machine_integrations(id) ON DELETE SET NULL;

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(5,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid', 'paid', 'pending') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    assigned_to INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    scanned_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT,
    appointment_id INT,
    patient_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'insurance', 'online') NOT NULL,
    transaction_id VARCHAR(255) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    description VARCHAR(255),
    payment_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- ========== RADIOLOGY MODULE ==========

-- Radiology Modalities Table
CREATE TABLE IF NOT EXISTS radiology_modalities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Scan Types Table
CREATE TABLE IF NOT EXISTS scan_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    modality_id INT NOT NULL,
    description TEXT,
    preparation_instructions TEXT,
    duration_minutes INT,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (modality_id) REFERENCES radiology_modalities(id) ON DELETE CASCADE
);

-- Radiology Orders Table
CREATE TABLE IF NOT EXISTS radiology_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    referring_doctor_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    priority ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
    clinical_notes TEXT,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (referring_doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Radiology Order Details Table
CREATE TABLE IF NOT EXISTS radiology_order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    scan_type_id INT NOT NULL,
    radiologist_id INT,
    technician_id INT,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
    scheduled_datetime DATETIME,
    completed_datetime DATETIME,
    report_available BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES radiology_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (scan_type_id) REFERENCES scan_types(id) ON DELETE CASCADE,
    FOREIGN KEY (radiologist_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Scan Images Table
CREATE TABLE IF NOT EXISTS scan_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_detail_id INT NOT NULL,
    image_path VARCHAR(512) NOT NULL,
    thumbnail_path VARCHAR(512),
    image_type ENUM('dicom', 'jpeg', 'png', 'tiff') NOT NULL,
    series_number INT,
    instance_number INT,
    acquisition_datetime DATETIME,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_detail_id) REFERENCES radiology_order_details(id) ON DELETE CASCADE
);

-- Radiology Reports Table
CREATE TABLE IF NOT EXISTS radiology_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_detail_id INT NOT NULL,
    radiologist_id INT NOT NULL,
    report_text TEXT NOT NULL,
    findings TEXT,
    impression TEXT,
    recommendations TEXT,
    status ENUM('draft', 'preliminary', 'final', 'amended', 'cancelled') DEFAULT 'draft',
    verified_by INT,
    verification_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_detail_id) REFERENCES radiology_order_details(id) ON DELETE CASCADE,
    FOREIGN KEY (radiologist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========== PHYSIOLOGY MODULE ==========

-- Physiology Test Types Table
CREATE TABLE IF NOT EXISTS physiology_test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category ENUM('ecg', 'eeg', 'emg', 'pft', 'audio', 'other') NOT NULL,
    description TEXT,
    preparation_instructions TEXT,
    duration_minutes INT,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Physiology Orders Table
CREATE TABLE IF NOT EXISTS physiology_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    referring_doctor_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    priority ENUM('routine', 'urgent', 'stat') DEFAULT 'routine',
    clinical_notes TEXT,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (referring_doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Physiology Test Details Table
CREATE TABLE IF NOT EXISTS physiology_test_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    test_type_id INT NOT NULL,
    physiologist_id INT,
    technician_id INT,
    status ENUM('pending', 'scheduled', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
    scheduled_datetime DATETIME,
    completed_datetime DATETIME,
    report_available BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES physiology_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (test_type_id) REFERENCES physiology_test_types(id) ON DELETE CASCADE,
    FOREIGN KEY (physiologist_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Physiology Test Results Table
CREATE TABLE IF NOT EXISTS physiology_test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_detail_id INT NOT NULL,
    result_data JSON NOT NULL,
    interpretation TEXT,
    quality_notes TEXT,
    artifacts_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_detail_id) REFERENCES physiology_test_details(id) ON DELETE CASCADE
);

-- Physiology Reports Table
CREATE TABLE IF NOT EXISTS physiology_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_detail_id INT NOT NULL,
    physiologist_id INT NOT NULL,
    report_text TEXT NOT NULL,
    findings TEXT,
    conclusion TEXT,
    recommendations TEXT,
    status ENUM('draft', 'preliminary', 'final', 'amended', 'cancelled') DEFAULT 'draft',
    verified_by INT,
    verification_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (test_detail_id) REFERENCES physiology_test_details(id) ON DELETE CASCADE,
    FOREIGN KEY (physiologist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========== COMMON DIAGNOSTIC TABLES ==========

-- Diagnostic Billing Table
CREATE TABLE IF NOT EXISTS diagnostic_billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    order_type ENUM('radiology', 'physiology', 'laboratory') NOT NULL,
    order_id INT NOT NULL,
    invoice_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    discount DECIMAL(5,2) DEFAULT 0,
    insurance_covered DECIMAL(10,2) DEFAULT 0,
    patient_responsibility DECIMAL(10,2) NOT NULL,
    status ENUM('unpaid', 'partially_paid', 'paid', 'insurance_pending') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);

-- Diagnostic Equipment Table
CREATE TABLE IF NOT EXISTS diagnostic_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    modality_id INT,
    serial_number VARCHAR(100),
    manufacturer VARCHAR(255),
    model VARCHAR(100),
    installation_date DATE,
    last_calibration_date DATE,
    next_calibration_date DATE,
    status ENUM('operational', 'maintenance', 'out_of_service') DEFAULT 'operational',
    department_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (modality_id) REFERENCES radiology_modalities(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Equipment Maintenance Log
CREATE TABLE IF NOT EXISTS equipment_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    maintenance_type ENUM('preventive', 'corrective', 'calibration', 'inspection') NOT NULL,
    maintenance_date DATE NOT NULL,
    performed_by VARCHAR(255),
    description TEXT,
    parts_replaced TEXT,
    cost DECIMAL(10,2),
    next_maintenance_date DATE,
    technician_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES diagnostic_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ========== SUPPORTING TABLES ==========

-- Inventory Table
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 0,
    unit VARCHAR(50),
    reorder_level INT DEFAULT 0,
    auto_reorder ENUM('enabled', 'disabled') DEFAULT 'enabled',
    supplier VARCHAR(100),
    last_restocked DATE,
    expiry_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Purchase Orders Table
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Work Shifts Table
CREATE TABLE IF NOT EXISTS work_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT NOT NULL,
    shift_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    shift_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Patient Drafts Table
CREATE TABLE IF NOT EXISTS patient_drafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    phone VARCHAR(20),
    email VARCHAR(255) UNIQUE,
    barcode VARCHAR(255) UNIQUE NOT NULL,
    address TEXT,
    services TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medical Records Table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_date DATE NOT NULL,
    diagnosis TEXT,
    treatment TEXT,
    prescription TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    room_type ENUM('general', 'private', 'icu', 'operation_theater', 'emergency') NOT NULL,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    capacity INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read') DEFAULT 'unread',
    is_read BOOLEAN DEFAULT FALSE,
    type ENUM('appointment', 'payment', 'system', 'alert', 'test_result', 'scan_result'),
    related_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit Logs Table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    details TEXT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Chat Messages Table
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_department_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Lab Performance Reports Table
CREATE TABLE IF NOT EXISTS lab_performance_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    total_tests INT DEFAULT 0,
    urgent_tests INT DEFAULT 0,
    rejected_results INT DEFAULT 0,
    technician_performance JSON,
    machine_downtime INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- Test Formulas Table
CREATE TABLE IF NOT EXISTS test_formulas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    formula TEXT NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Auto-reorder Trigger
DELIMITER //
CREATE TRIGGER auto_reorder_trigger
BEFORE UPDATE ON inventory_items
FOR EACH ROW
BEGIN
    IF NEW.quantity <= NEW.reorder_level AND NEW.auto_reorder = 'enabled' THEN
        INSERT INTO purchase_orders (
            item_id, item_name, quantity, status, created_at, updated_at, created_by
        )
        VALUES (
            NEW.id, NEW.name, 10, 'pending', NOW(), NOW(), 1
        );
    END IF;
END;
//
DELIMITER ;