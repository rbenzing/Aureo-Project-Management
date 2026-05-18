-- Comprehensive Fake Data Generation Script

-- Disable foreign key checks to allow bulk insertion
SET FOREIGN_KEY_CHECKS = 0;
SET UNIQUE_CHECKS = 0;

-- Clear existing data (optional, use with caution)
DELETE FROM milestone_tasks;
DELETE FROM sprint_tasks;
DELETE FROM user_projects;
DELETE FROM tasks;
DELETE FROM sprints;
DELETE FROM milestones;
DELETE FROM projects;
DELETE FROM user_companies;
DELETE FROM companies;
DELETE FROM users WHERE id > 1;

-- Insert Companies (without user_id initially)
INSERT INTO companies (name, email, address, phone, website) VALUES 
('Tech Innovations Inc', 'info@techinnovations.com', '123 Tech Lane, San Francisco, CA', '+1-555-123-4567', 'https://techinnovations.com'),
('Digital Solutions Corp', 'contact@digitalsolutions.com', '456 Digital Drive, New York, NY', '+1-555-234-5678', 'https://digitalsolutions.com'),
('Global Systems Ltd', 'hr@globalsystems.com', '789 Global Street, Chicago, IL', '+1-555-345-6789', 'https://globalsystems.com'),
('Cloud Architects', 'support@cloudarchitects.com', '321 Cloud Way, Boston, MA', '+1-555-456-7890', 'https://cloudarchitects.com'),
('Startup Synergy', 'hello@startupsynergy.com', '654 Startup Blvd, Austin, TX', '+1-555-567-8901', 'https://startupsynergy.com');

-- Insert Users (25 total)
INSERT INTO users (
    role_id, 
    company_id, 
    first_name, 
    last_name, 
    email, 
    phone,
    password_hash, 
    is_active
) 
SELECT
    r.id AS role_id,
    c.id AS company_id,
    user_data.first_name,
    user_data.last_name,
    user_data.email,
    user_data.phone,
    '$argon2id$v=19$m=65536,t=4,p=1$dkcub0FTU2NENTRmdXBSeQ$cAZna9wkkfUbCM5PPRjh3KvEjga+dP56xqnOvfbam0U',
    1
FROM (
    VALUES
    ROW('Emma', 'Rodriguez', 'emma.rodriguez@techinnovations.com', '+1-555-123-4567'),
    ROW('Liam', 'Chen', 'liam.chen@digitalsolutions.com', '+1-555-234-5678'),
    ROW('Sophia', 'Patel', 'sophia.patel@globalsystems.com', '+1-555-345-6789'),
    ROW('Noah', 'Kim', 'noah.kim@cloudarchitects.com', '+1-555-456-7890'),
    ROW('Olivia', 'Martinez', 'olivia.martinez@startupsynergy.com', '+1-555-567-8901'),
    ROW('Daniel', 'Wong', 'daniel.wong@techinnovations.com', '+1-555-678-9012'),
    ROW('Ava', 'Gupta', 'ava.gupta@digitalsolutions.com', '+1-555-789-0123'),
    ROW('Ethan', 'Nakamura', 'ethan.nakamura@globalsystems.com', '+1-555-890-1234'),
    ROW('Isabella', 'Lee', 'isabella.lee@cloudarchitects.com', '+1-555-901-2345'),
    ROW('Mason', 'Gonzalez', 'mason.gonzalez@startupsynergy.com', '+1-555-012-3456'),
    ROW('Emily', 'Tanaka', 'emily.tanaka@techinnovations.com', '+1-555-123-4567'),
    ROW('Jacob', 'Singh', 'jacob.singh@digitalsolutions.com', '+1-555-234-5678'),
    ROW('Madison', 'Park', 'madison.park@globalsystems.com', '+1-555-345-6789'),
    ROW('Michael', 'Zhang', 'michael.zhang@cloudarchitects.com', '+1-555-456-7890'),
    ROW('Emma', 'Yamamoto', 'emma.yamamoto@startupsynergy.com', '+1-555-567-8901'),
    ROW('Alexander', 'Kumar', 'alexander.kumar@techinnovations.com', '+1-555-678-9012'),
    ROW('Mia', 'Choi', 'mia.choi@digitalsolutions.com', '+1-555-789-0123'),
    ROW('William', 'Suzuki', 'william.suzuki@globalsystems.com', '+1-555-890-1234'),
    ROW('Charlotte', 'Lin', 'charlotte.lin@cloudarchitects.com', '+1-555-901-2345'),
    ROW('Benjamin', 'Ramirez', 'benjamin.ramirez@startupsynergy.com', '+1-555-012-3456'),
    ROW('Amelia', 'Sato', 'amelia.sato@techinnovations.com', '+1-555-123-4567'),
    ROW('Logan', 'Mehta', 'logan.mehta@digitalsolutions.com', '+1-555-234-5678'),
    ROW('Harper', 'Kim', 'harper.kim@globalsystems.com', '+1-555-345-6789'),
    ROW('Lucas', 'Wang', 'lucas.wang@cloudarchitects.com', '+1-555-456-7890'),
    ROW('Evelyn', 'Rodriguez', 'evelyn.rodriguez@startupsynergy.com', '+1-555-567-8901')
) AS user_data (first_name, last_name, email, phone)
CROSS JOIN roles r
LEFT JOIN companies c ON SUBSTRING_INDEX(user_data.email, '@', -1) = SUBSTRING_INDEX(c.website, '//', -1)
WHERE r.name = 'admin';

-- Link Users to Companies
INSERT INTO user_companies (user_id, company_id)
SELECT u.id, u.company_id
FROM users u
WHERE u.id > 1;

-- Insert Projects (50 total)
INSERT INTO projects (
    company_id, 
    owner_id, 
    status_id, 
    name, 
    description, 
    start_date, 
    end_date
)
SELECT 
    c.id,
    u.id,
    sp.id,
    CONCAT('Project ', FLOOR(RAND() * 1000)),
    CONCAT('Description for Project ', FLOOR(RAND() * 1000)),
    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 365) DAY),
    DATE_ADD(NOW(), INTERVAL FLOOR(RAND() * 365) DAY)
FROM 
    companies c
CROSS JOIN 
    users u
CROSS JOIN 
    statuses_project sp
WHERE 
    u.id > 1 
    AND sp.name IN ('ready', 'in_progress')
LIMIT 50;

-- Insert Epics (2-3 per project)
INSERT INTO milestones (
    project_id, 
    status_id,
    title, 
    description, 
    milestone_type, 
    start_date, 
    due_date
)
SELECT 
    project_id,
    status_id,
    epic_title,
    epic_description,
    'epic',
    start_date,
    due_date
FROM (
    SELECT 
        p.id AS project_id,
        (SELECT id FROM statuses_milestone WHERE name = 'Not Started' LIMIT 1) AS status_id,
        CONCAT('Epic ', CHAR(64 + FLOOR(RAND() * 26)), ' for ', p.name) AS epic_title,
        CONCAT('Comprehensive epic description for ', p.name, ' focusing on key strategic objectives') AS epic_description,
        p.start_date,
        p.end_date AS due_date,
        ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY p.id) AS epic_order
    FROM 
        projects p
) AS ProjectEpics
WHERE 
    epic_order <= 3;

-- Sub-Epics (nested epics)
INSERT INTO milestones (
    project_id, 
    epic_id,
    status_id,
    title, 
    description, 
    milestone_type, 
    start_date, 
    due_date
)
SELECT 
    m.project_id,
    m.id,
    (SELECT id FROM statuses_milestone WHERE name = 'Not Started' LIMIT 1),
    CONCAT('Sub-Epic for ', m.title),
    CONCAT('Detailed sub-epic description breaking down ', m.title),
    'epic',
    m.start_date,
    m.due_date
FROM 
    milestones m
WHERE 
    m.milestone_type = 'epic'
    AND RAND() < 0.5;

-- Insert Sprints (4-6 per project)
INSERT INTO sprints (
    project_id, 
    status_id,
    name, 
    description, 
    start_date, 
    end_date
)
SELECT 
    p.id,
    ss.id,
    CONCAT('Sprint for ', p.name, ' - ', FLOOR(RAND() * 10)),
    CONCAT('Sprint description for ', p.name),
    DATE_SUB(p.start_date, INTERVAL FLOOR(RAND() * 30) DAY),
    DATE_ADD(p.start_date, INTERVAL FLOOR(RAND() * 30) DAY)
FROM 
    projects p
CROSS JOIN
    statuses_sprint ss
WHERE 
    ss.name IN ('planning', 'active')
GROUP BY 
    p.id;

INSERT INTO tasks (
    project_id, 
    assigned_to, 
    title, 
    description, 
    priority, 
    status_id, 
    estimated_time, 
    start_date, 
    due_date
)
SELECT 
    s.project_id,
    FLOOR(RAND() * 25) + 2,  -- Random user assignment
    CONCAT('Task for ', s.name, ' - ', FLOOR(RAND() * 1000)),
    CONCAT('Detailed task description for ', s.name),
    ELT(FLOOR(RAND() * 4) + 1, 'none', 'low', 'medium', 'high'),
    FLOOR(RAND() * 6) + 1,  -- Random status
    FLOOR(RAND() * 480) + 60,  -- Estimated time in minutes (1-8 hours)
    s.start_date,
    s.end_date
FROM 
    sprints s,
    (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
     UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
     UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
     UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20) AS task_count
GROUP BY 
    s.id, task_count.`1`;

-- Insert Subtasks (2-4 per task)
INSERT INTO tasks (
    project_id, 
    assigned_to, 
    parent_task_id,
    is_subtask,
    title, 
    description, 
    priority, 
    status_id, 
    estimated_time, 
    start_date, 
    due_date
)
SELECT 
    t.project_id,
    FLOOR(RAND() * 25) + 2,  -- Random user assignment
    t.id,
    1,  -- Mark as subtask
    CONCAT('Subtask for ', t.title, ' - ', FLOOR(RAND() * 100)),
    CONCAT('Detailed subtask description for ', t.title),
    ELT(FLOOR(RAND() * 4) + 1, 'none', 'low', 'medium', 'high'),
    FLOOR(RAND() * 6) + 1,  -- Random status
    FLOOR(RAND() * 240) + 30,  -- Estimated time in minutes (0.5-4 hours)
    t.start_date,
    t.due_date
FROM 
    tasks t,
    (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) AS subtask_count
WHERE 
    t.is_subtask = 0
GROUP BY 
    t.id, subtask_count.`1`;

-- Link Sprints with Tasks
INSERT INTO sprint_tasks (sprint_id, task_id)
SELECT 
    s.id, 
    t.id
FROM 
    sprints s
JOIN 
    tasks t ON s.project_id = t.project_id
WHERE 
    t.is_subtask = 0
    AND RAND() < 0.8;  -- 80% chance of task being in a sprint

-- Link Milestones with Tasks
INSERT INTO milestone_tasks (milestone_id, task_id)
SELECT 
    m.id, 
    t.id
FROM 
    milestones m
JOIN 
    tasks t ON m.project_id = t.project_id
WHERE 
    m.milestone_type = 'epic'
    AND t.is_subtask = 0
    AND RAND() < 0.7;  -- 70% chance of task being linked to an epic

-- Link Users to Projects
INSERT INTO user_projects (user_id, project_id)
SELECT 
    u.id, 
    p.id
FROM 
    users u,
    projects p
WHERE 
    u.id > 1
    AND RAND() < 0.3;  -- 30% chance of user being linked to a project

-- Verification Query
SELECT 
    (SELECT COUNT(*) FROM users WHERE id > 1) as total_users,
    (SELECT COUNT(*) FROM companies) as total_companies,
    (SELECT COUNT(*) FROM projects) as total_projects,
    (SELECT COUNT(*) FROM milestones WHERE milestone_type = 'epic') as total_epics,
    (SELECT COUNT(*) FROM sprints) as total_sprints;