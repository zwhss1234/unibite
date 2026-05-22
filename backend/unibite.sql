-- ========================================
-- UniBite Database Schema
-- Φοιτητικό Σύστημα Ανταλλαγής Φαγητού
-- ========================================

CREATE DATABASE IF NOT EXISTS unibite_db;
USE unibite_db;

-- ========================================
-- 1. Users Table
-- ========================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('cook', 'consumer', 'admin') NOT NULL,
    credits INT DEFAULT 5,  -- Γ2: Νέοι χρήστες παίρνουν 5 credits
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 2. Ads Table (Αγγελίες Φαγητού)
-- ========================================
CREATE TABLE ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cook_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    credit_costs INT DEFAULT 1,
    description TEXT,
    image_path VARCHAR(255),
    total_portions INT NOT NULL,
    available_portions INT NOT NULL,
    allergens TEXT,
    pickup_location VARCHAR(255) NOT NULL,
    pickup_time VARCHAR(100) NOT NULL,
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cook_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 3. Requests Table (Αιτήματα)
-- ========================================
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    consumer_id INT NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'picked_up', 'no_show') DEFAULT 'pending',
    rating INT DEFAULT NULL,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_rating CHECK (rating >= 1 AND rating <= 5),
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (consumer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 4. Indexes for Performance
-- ========================================
CREATE INDEX idx_ads_created_at ON ads(created_at);
CREATE INDEX idx_ads_cook_id ON ads(cook_id);
CREATE INDEX idx_requests_ad_id ON requests(ad_id);
CREATE INDEX idx_requests_consumer_id ON requests(consumer_id);
CREATE INDEX idx_requests_status ON requests(status);

-- ========================================
-- 5. Sample Data (Test Data)
-- ========================================

-- Insert test users
INSERT INTO users (username, email, role, credits) VALUES 
('marios_cook', 'marios@uni.gr', 'cook', 5),
('elena_cook', 'elena@uni.gr', 'cook', 5),
('giorgos_cook', 'giorgos@uni.gr', 'cook', 5),
('anna_cook', 'anna@uni.gr', 'cook', 5),
('katerina', 'katerina@uni.gr', 'consumer', 5),
('nikos', 'nikos@uni.gr', 'consumer', 5);

-- Insert test ads
INSERT INTO ads (cook_id, title, credit_costs, description, total_portions, available_portions, allergens, pickup_location, pickup_time, created_at) VALUES
(1, 'Σπιτικό Παστίτσιο', 2, 'Σπιτικό παστίτσιο με κιμά και μπεσαμέλ', 4, 4, 'Γλουτένη, Λακτόζη', 'Εστία Κτίριο Β', '2026-05-01 14:00:00', NOW() - INTERVAL 2 HOUR),
(2, 'Σαλάτα Caesar', 1, 'Φρέσκια σαλάτα με κοτόπουλο και κρουτόν', 3, 2, 'Γλουτένη', 'Κτίριο Πληροφορικής', '2026-05-01 13:30:00', NOW() - INTERVAL 5 HOUR),
(3, 'Μουσακάς', 3, 'Παραδοσιακός μουσακάς με κιμά', 5, 0, 'Γάλα', 'Εστία Κτίριο Α', '2026-04-30 12:30:00', NOW() - INTERVAL 24 HOUR),
(1, 'Γεμιστά', 2, 'Ντοματωμένα γεμιστά με ρύζι', 6, 6, 'Καμία', 'Εστία Κτίριο Β', '2026-05-02 14:00:00', NOW() - INTERVAL 1 HOUR),
(2, 'Πίτσα Μαργαρίτα', 2, 'Σπιτική πίτσα με ντομάτα και μοτσαρέλα', 2, 2, 'Γλουτένη, Λακτόζη', 'Κτίριο Πληροφορικής', '2026-05-01 19:00:00', NOW() - INTERVAL 3 HOUR);

-- Insert test requests
INSERT INTO requests (ad_id, consumer_id, quantity, status, rating, received_at) VALUES
(1, 5, 2, 'pending', NULL, NULL),
(2, 5, 1, 'approved', NULL, NULL),
(1, 6, 1, 'picked_up', 5, NOW() - INTERVAL 1 DAY),
(2, 6, 1, 'picked_up', 4, NOW() - INTERVAL 2 DAY);

-- ========================================
-- 6. Views (Χρήσιμα Views)
-- ========================================

-- View: Ενεργές αγγελίες (Β1)
CREATE OR REPLACE VIEW active_ads AS
SELECT a.*, u.username as cook_name,
    CASE 
        WHEN a.available_portions > 0 THEN 'Active' 
        ELSE 'Inactive' 
    END as current_state
FROM ads a
JOIN users u ON a.cook_id = u.id
WHERE a.created_at >= NOW() - INTERVAL 48 HOUR;

-- View: Leaderboard (Δ2)
CREATE OR REPLACE VIEW leaderboard AS
SELECT u.id, u.username, COUNT(r.id) as total_given
FROM users u
JOIN ads a ON u.id = a.cook_id
JOIN requests r ON a.id = r.ad_id
WHERE r.status = 'picked_up'
GROUP BY u.id;

-- ========================================
-- 7. Stored Procedures
-- ========================================

-- Διαδικασία: Δημιουργία αιτήματος με transaction
DELIMITER //
CREATE PROCEDURE create_request(IN p_ad_id INT, IN p_consumer_id INT, IN p_quantity INT)
BEGIN
    DECLARE v_total_cost INT;
    DECLARE v_available INT;
    DECLARE v_user_credits INT;
    
    -- Get ad details
    SELECT credit_costs, available_portions INTO v_total_cost, v_available
    FROM ads WHERE id = p_ad_id;
    
    -- Get user credits
    SELECT credits INTO v_user_credits FROM users WHERE id = p_consumer_id;
    
    -- Validate
    IF v_available < p_quantity THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Δεν υπάρχουν αρκετές μερίδες';
    END IF;
    
    IF v_user_credits < (v_total_cost * p_quantity) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Δεν έχετε αρκετά Credits';
    END IF;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Deduct credits
    UPDATE users SET credits = credits - (v_total_cost * p_quantity)
    WHERE id = p_consumer_id;
    
    -- Reduce portions
    UPDATE ads SET available_portions = available_portions - p_quantity
    WHERE id = p_ad_id;
    
    -- Create request
    INSERT INTO requests (ad_id, consumer_id, quantity, status)
    VALUES (p_ad_id, p_consumer_id, p_quantity, 'pending');
    
    COMMIT;
END //
DELIMITER ;

-- Διαδικασία: Βαθμολογία & Πληρωμή μάγειρα (Β4)
DELIMITER //
CREATE PROCEDURE rate_and_pay(IN p_request_id INT, IN p_rating INT)
BEGIN
    DECLARE v_cook_id INT;
    DECLARE v_credit_costs INT;
    DECLARE v_quantity INT;
    DECLARE v_reward INT;
    
    -- Get request details
    SELECT a.cook_id, a.credit_costs, r.quantity
    INTO v_cook_id, v_credit_costs, v_quantity
    FROM requests r
    JOIN ads a ON r.ad_id = a.id
    WHERE r.id = p_request_id;
    
    -- Calculate reward (Β4)
    IF p_rating > 3 THEN
        SET v_reward = (v_credit_costs + 1) * v_quantity;
    ELSE
        SET v_reward = v_credit_costs * v_quantity;
    END IF;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Pay cook
    UPDATE users SET credits = credits + v_reward WHERE id = v_cook_id;
    
    -- Update request
    UPDATE requests 
    SET status = 'picked_up', rating = p_rating, received_at = NOW()
    WHERE id = p_request_id;
    
    COMMIT;
END //
DELIMITER ;

-- Διαδικασία: No-show penalty
DELIMITER //
CREATE PROCEDURE handle_no_show(IN p_consumer_id INT, IN p_ad_id INT)
BEGIN
    START TRANSACTION;
    
    -- Penalty: -1 credit
    UPDATE users SET credits = credits - 1 WHERE id = p_consumer_id;
    
    -- Update request status
    UPDATE requests 
    SET status = 'no_show' 
    WHERE consumer_id = p_consumer_id AND ad_id = p_ad_id AND status = 'pending';
    
    -- Return portion to ad
    UPDATE ads SET available_portions = available_portions + 1 WHERE id = p_ad_id;
    
    COMMIT;
END //
DELIMITER ;

-- ========================================
-- 8. Test Queries
-- ========================================

-- Feed: Ενεργές αγγελίες κάτω από 48 ώρες
SELECT * FROM active_ads;

-- Leaderboard: Top Donor
SELECT * FROM leaderboard ORDER BY total_given DESC LIMIT 1;

-- Stats: Επιτυχημένα γεύματα τον τελευταίο μήνα
SELECT COUNT(*) as successful_meals
FROM requests 
WHERE status = 'picked_up' 
AND received_at >= NOW() - INTERVAL 1 MONTH;

-- Test: Create request
CALL create_request(1, 5, 1);

-- Test: Rate and pay
CALL rate_and_pay(1, 5);

-- Test: No-show
CALL handle_no_show(5, 1);