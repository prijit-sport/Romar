-- Sample data for modules/maintenance.php
USE romar_dormitory;

INSERT IGNORE INTO assets (asset_name, asset_tag, category, purchase_date, status, location, notes) VALUES 
('แล็ปท็อป Dell Latitude', 'LT001', 'computer', '2024-01-15', 'active', 'Room 101', 'Used by IT team'),
('Printer HP LaserJet', 'PR001', 'printer', '2023-11-20', 'active', 'Office', 'Main printer');

INSERT INTO asset_repairs (asset_id, repair_date, problem_desc, status, technician, vendor, repair_cost, repair_detail) VALUES 
(1, '2024-03-10', 'Screen flickering', 'completed', 'John Doe', '', 2500.00, 'Replaced LCD panel'),
(1, '2024-03-25', 'Battery not charging', 'in_progress', 'Jane Smith', 'Dell Support', 3500.00, 'Diagnostic - awaiting parts'),
(2, '2024-03-20', 'Paper jam frequent', 'pending', '', 'HP Service', 1200.00, 'Needs roller replacement - pending approval');
