CREATE DATABASE IF NOT EXISTS ict_requests;
USE ict_requests;
CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id VARCHAR(50) UNIQUE,
  fullname VARCHAR(100),
  phone VARCHAR(20),
  email VARCHAR(100),
  office VARCHAR(100),
  request_type VARCHAR(100),
  request_text TEXT,
  attachment VARCHAR(255),
  service_time VARCHAR(50),
  priority VARCHAR(20) DEFAULT 'Normal',
  status VARCHAR(20) DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
