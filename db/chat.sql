USE pharmacy_mgmt;

CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    type ENUM('direct','group','broadcast') DEFAULT 'direct',
    created_by INT,
    branch_id INT NULL COMMENT 'NULL = all branches',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS chat_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP NULL,
    UNIQUE KEY unique_room_user (room_id, user_id),
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    msg_type ENUM('text','system') DEFAULT 'text',
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_online_status (
    user_id INT PRIMARY KEY,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_online TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default broadcast rooms
INSERT IGNORE INTO chat_rooms (id, name, type, created_by, branch_id) VALUES
(1, 'All Staff Broadcast', 'broadcast', 1, NULL),
(2, 'Main Branch Team', 'group', 1, 1),
(3, 'Managers Channel', 'group', 1, NULL);

-- Add all users to broadcast room
INSERT IGNORE INTO chat_room_members (room_id, user_id)
SELECT 1, id FROM users WHERE status='active';

-- Add branch 1 users to branch room
INSERT IGNORE INTO chat_room_members (room_id, user_id)
SELECT 2, id FROM users WHERE (branch_id=1 OR branch_id IS NULL) AND status='active';

-- Add managers/admins to managers channel
INSERT IGNORE INTO chat_room_members (room_id, user_id)
SELECT 3, u.id FROM users u JOIN roles r ON u.role_id=r.id WHERE r.name IN ('super_admin','branch_manager') AND u.status='active';

-- Init online status
INSERT IGNORE INTO user_online_status (user_id, is_online)
SELECT id, 0 FROM users;
