-- ============================================
-- KaajKoro - Database
-- Student Freelancing and Skill Sharing Platform
-- Import this file in phpMyAdmin before running the project.
-- ============================================

CREATE DATABASE IF NOT EXISTS kaajkoro_db;
USE kaajkoro_db;

-- ---------- drop the old tables (so we can import again) ----------

DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS meetings;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS requests;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS proposals;
DROP TABLE IF EXISTS job_skills;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS user_skills;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;

-- ---------- USER ----------

CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20)  NOT NULL,
    university  VARCHAR(100) NOT NULL,
    city        VARCHAR(50)  NOT NULL,
    bio         TEXT,
    education   VARCHAR(200),
    experience  TEXT,
    portfolio   VARCHAR(150),
    status      VARCHAR(20)  DEFAULT 'active',
    joined      DATE
);

-- ---------- ADMIN ----------

CREATE TABLE admins (
    admin_id  INT AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(100) NOT NULL,
    email     VARCHAR(100) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL
);

-- ---------- SKILL ----------

CREATE TABLE skills (
    skill_id    INT AUTO_INCREMENT PRIMARY KEY,
    skill_name  VARCHAR(60) NOT NULL UNIQUE
);

-- ---------- HAS (user  M : N  skill) ----------

CREATE TABLE user_skills (
    user_id   INT NOT NULL,
    skill_id  INT NOT NULL,
    PRIMARY KEY (user_id, skill_id),
    FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

-- ---------- JOB ----------

CREATE TABLE jobs (
    job_id       INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT NOT NULL,
    title        VARCHAR(150) NOT NULL,
    description  TEXT NOT NULL,
    budget       INT NOT NULL,
    deadline     DATE NOT NULL,
    job_status   VARCHAR(20) DEFAULT 'open',
    hired_id     INT DEFAULT 0,
    created_at   DATE,
    FOREIGN KEY (client_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- REQUIRES (job  M : N  skill) ----------

CREATE TABLE job_skills (
    job_id    INT NOT NULL,
    skill_id  INT NOT NULL,
    PRIMARY KEY (job_id, skill_id),
    FOREIGN KEY (job_id)   REFERENCES jobs(job_id)     ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

-- ---------- PROPOSAL ----------

CREATE TABLE proposals (
    proposal_id  INT AUTO_INCREMENT PRIMARY KEY,
    job_id       INT NOT NULL,
    user_id      INT NOT NULL,
    bid          INT NOT NULL,
    letter       TEXT NOT NULL,
    status       VARCHAR(20) DEFAULT 'pending',
    created_at   DATE,
    FOREIGN KEY (job_id)  REFERENCES jobs(job_id)   ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- EXCHANGE POST ----------

CREATE TABLE posts (
    post_id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    offer_skill_id  INT NOT NULL,
    want_skill_id   INT NOT NULL,
    description     TEXT NOT NULL,
    post_status     VARCHAR(20) DEFAULT 'open',
    created_at      DATE,
    FOREIGN KEY (user_id)        REFERENCES users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (offer_skill_id) REFERENCES skills(skill_id),
    FOREIGN KEY (want_skill_id)  REFERENCES skills(skill_id)
);

-- ---------- EXCHANGE REQUEST ----------

CREATE TABLE requests (
    request_id  INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    message     TEXT NOT NULL,
    status      VARCHAR(20) DEFAULT 'pending',
    created_at  DATE,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- MESSAGE ----------

CREATE TABLE messages (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    from_id     INT NOT NULL,
    to_id       INT NOT NULL,
    text        TEXT NOT NULL,
    sent_at     DATETIME,
    FOREIGN KEY (from_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (to_id)   REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- MEETING ----------

CREATE TABLE meetings (
    meeting_id  INT AUTO_INCREMENT PRIMARY KEY,
    from_id     INT NOT NULL,
    to_id       INT NOT NULL,
    date_time   DATETIME NOT NULL,
    mode        VARCHAR(20) DEFAULT 'online',
    place       VARCHAR(200) NOT NULL,
    topic       TEXT,
    status      VARCHAR(20) DEFAULT 'scheduled',
    FOREIGN KEY (from_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (to_id)   REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- REVIEW ----------
-- job_id = 0 means the review came from a skill exchange, not from a paid job

CREATE TABLE reviews (
    review_id    INT AUTO_INCREMENT PRIMARY KEY,
    from_id      INT NOT NULL,
    to_id        INT NOT NULL,
    job_id       INT DEFAULT 0,
    rating       INT NOT NULL,
    comment      TEXT NOT NULL,
    review_date  DATE,
    status       VARCHAR(20) DEFAULT 'visible',
    FOREIGN KEY (from_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (to_id)   REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- REPORT ----------
-- type = user / job / post / review

CREATE TABLE reports (
    report_id   INT AUTO_INCREMENT PRIMARY KEY,
    from_id     INT NOT NULL,
    type        VARCHAR(20) NOT NULL,
    target_id   INT NOT NULL,
    reason      TEXT NOT NULL,
    status      VARCHAR(20) DEFAULT 'pending',
    created_at  DATE,
    FOREIGN KEY (from_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- NOTIFICATION ----------

CREATE TABLE notifications (
    notif_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    text        VARCHAR(255) NOT NULL,
    link        VARCHAR(100) NOT NULL,
    created_at  DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------- ACTIVITY ----------

CREATE TABLE activities (
    activity_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    text         VARCHAR(255) NOT NULL,
    created_at   DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- ============================================
-- SAMPLE DATA
-- The password of every student is  123456
-- The password of the admin is      admin123
-- (they are saved with password_hash, that is why they look like this)
-- ============================================

INSERT INTO skills (skill_id, skill_name) VALUES
(1,  'Web Development'),
(2,  'Graphic Design'),
(3,  'Photoshop'),
(4,  'Python'),
(5,  'PHP and MySQL'),
(6,  'Content Writing'),
(7,  'Video Editing'),
(8,  'Android Development'),
(9,  'UI/UX Design'),
(10, 'Data Analysis'),
(11, 'Digital Marketing'),
(12, 'Translation'),
(13, 'Math Tutoring');

INSERT INTO users (user_id, name, email, password, phone, university, city, bio, education, experience, portfolio, status, joined) VALUES
(1, 'Rawan Ahmed', 'rawan@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01711-223344', 'AIUB', 'Dhaka', 'CSE student. I build websites and design posters.', 'BSc in Computer Science, AIUB', '2 years of freelance web design work.', 'behance.net/rawan', 'active', '2025-01-14'),
(2, 'Nusrat Jahan', 'nusrat@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01812-556677', 'North South University', 'Dhaka', 'Graphic designer and illustrator. Now learning Python.', 'BBA, North South University', 'Designed logos for 9 student startups.', 'dribbble.com/nusrat', 'active', '2025-02-03'),
(3, 'Tanvir Hossain', 'tanvir@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01911-889900', 'BUET', 'Dhaka', 'Backend developer. PHP and MySQL are my main tools.', 'BSc in EEE, BUET', 'Built inventory systems for two local shops.', 'github.com/tanvirh', 'active', '2025-01-28'),
(4, 'Sadia Islam', 'sadia@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01633-445566', 'BRAC University', 'Dhaka', 'Content writer and Bangla to English translator.', 'English Literature, BRAC University', 'Wrote 200 blog articles and product descriptions.', 'medium.com/@sadiaislam', 'active', '2025-03-11'),
(5, 'Rafiul Karim', 'rafiul@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01755-334455', 'Daffodil International University', 'Dhaka', 'Video editor. I edit reels, promos and presentations.', 'Multimedia and Creative Technology, DIU', 'Edited 60 short videos for YouTube creators.', 'youtube.com/@rafiuledits', 'active', '2025-04-02'),
(6, 'Mahi Chowdhury', 'mahi@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01599-667788', 'Chittagong University', 'Chattogram', 'Android developer using Java. Looking for team projects.', 'CSE, Chittagong University', 'Published 3 apps on the Play Store.', 'github.com/mahicw', 'active', '2025-02-19'),
(7, 'Farhan Rahman', 'farhan@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01722-991122', 'Independent University Bangladesh', 'Dhaka', 'Marketing student. I hire students for design work.', 'BBA in Marketing, IUB', 'Managed a clothing brand page with 40k followers.', '', 'active', '2025-01-08'),
(8, 'Ishrat Binte Noor', 'ishrat@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01966-223311', 'Jahangirnagar University', 'Savar', 'Data science student. Python, pandas and charts.', 'Statistics, Jahangirnagar University', 'Cleaned survey data for 5 research groups.', 'kaggle.com/ishratn', 'active', '2025-05-21'),
(9, 'Zubair Alam', 'zubair@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01844-556600', 'Rajshahi University', 'Rajshahi', '3D artist working with Blender.', 'Fine Arts, Rajshahi University', 'Freelance 3D product renders.', 'artstation.com/zubair', 'suspended', '2025-06-09'),
(10, 'Priya Das', 'priya@kaajkoro.com', '$2y$10$Q652VZQg.G20PgMREUke7.JAbaAE8jmeSdY8EvHfbucwOnWucoxCa', '01677-889911', 'Khulna University', 'Khulna', 'UI/UX designer. Figma, wireframe and prototype.', 'CSE, Khulna University', 'Designed 12 mobile app interfaces.', 'figma.com/@priyadas', 'active', '2025-03-30');

INSERT INTO admins (admin_id, name, email, password) VALUES
(1, 'KaajKoro Admin', 'admin@kaajkoro.com', '$2y$10$l938wuqchAaOl2V83TSNsuy4Cmgn1GIw8r8Yx9Yu/9BBdgIb27Rhu');

INSERT INTO user_skills (user_id, skill_id) VALUES
(1,1),(1,2),(1,3),
(2,2),(2,3),(2,9),
(3,1),(3,4),(3,5),
(4,6),(4,12),
(5,7),
(6,4),(6,8),
(7,11),
(8,4),(8,10),
(9,2),
(10,2),(10,9);

INSERT INTO jobs (job_id, client_id, title, description, budget, deadline, job_status, hired_id, created_at) VALUES
(1, 7, 'Logo design for a small clothing brand', 'I need a clean and modern logo for my clothing page. Please give me 2 or 3 ideas first, then we will finalize one. Source file must be given at the end.', 3500, '2026-09-05', 'open', 0, '2026-08-14'),
(2, 4, 'Build a simple portfolio website', 'A 4 page static portfolio website with home, about, works and contact page. It must work on mobile also. No database needed.', 6000, '2026-09-12', 'open', 0, '2026-08-16'),
(3, 7, 'Edit 5 Instagram reels for a fashion page', 'Five short reels of 20 to 30 seconds each. I need good cuts, captions and background music. Raw video will be given by Google Drive.', 4500, '2026-08-30', 'working', 5, '2026-08-06'),
(4, 1, 'Write 10 blog posts about student life', 'I am looking for a writer who can write 10 articles about student life in Bangladesh. Each article will be 600 to 800 words. Only original writing please.', 5000, '2026-09-20', 'open', 0, '2026-08-18'),
(5, 6, 'Clean and analyse a survey dataset', 'One CSV file with 1200 survey answers. It needs cleaning, simple statistics and 4 or 5 charts with a short summary in one page.', 3000, '2026-08-29', 'working', 8, '2026-08-05'),
(6, 10, 'Convert a Figma design into a webpage', 'I have a one page Figma design of a landing page. I need it in HTML and CSS, same as the design and working on mobile also.', 4000, '2026-09-08', 'open', 0, '2026-08-19'),
(7, 10, 'Bangla to English translation of 15 pages', 'Fifteen pages of a research report need translation from Bangla to English. The writing style must be academic.', 2500, '2026-08-27', 'done', 4, '2026-07-22'),
(8, 7, 'Poster design for a university club event', 'One A3 poster for a programming contest. Club logo and sponsor logos will be given by me.', 1200, '2026-08-25', 'done', 2, '2026-07-18'),
(9, 6, 'Small Android app for attendance', 'A simple Android app where a teacher can take attendance and export it as a CSV file. Offline app is fine, no server needed.', 9000, '2026-09-25', 'open', 0, '2026-08-20');

INSERT INTO job_skills (job_id, skill_id) VALUES
(1,2),(1,3),
(2,1),
(3,7),
(4,6),
(5,4),(5,10),
(6,1),(6,9),
(7,12),
(8,2),
(9,8);

INSERT INTO proposals (proposal_id, job_id, user_id, bid, letter, status, created_at) VALUES
(1, 1, 2, 3500, 'I have designed logos for nine student startups. I can give you three ideas in four days and all source files at the end.', 'pending', '2026-08-15'),
(2, 1, 1, 3000, 'I do logo and poster work in Photoshop and Illustrator. I can start today and show you the first idea tomorrow.', 'pending', '2026-08-15'),
(3, 2, 1, 5500, 'I have made many landing pages before. Your four pages can be done in one week with mobile design also.', 'pending', '2026-08-17'),
(4, 2, 3, 6000, 'I mostly work on backend but a static site is easy for me. I can also host it for you for free.', 'pending', '2026-08-17'),
(5, 3, 5, 4500, 'I edit reels every week for two YouTube channels. I will follow the same style your page is using now.', 'accepted', '2026-08-07'),
(6, 5, 8, 3000, 'I clean survey data with Python and pandas regularly. You will get the clean file, the charts and a summary.', 'accepted', '2026-08-06'),
(7, 6, 10, 4000, 'I design this type of page myself, so I can convert it very accurately with correct spacing.', 'pending', '2026-08-20'),
(8, 4, 4, 4800, 'I have written more than two hundred articles. Ten posts about student life will take me twelve days.', 'pending', '2026-08-19'),
(9, 7, 4, 2500, 'Academic translation is the work I do most. Fifteen pages will take me five days.', 'accepted', '2026-07-23'),
(10, 8, 2, 1200, 'Event posters are my favourite work. I will send two drafts before the final one.', 'accepted', '2026-07-19');

INSERT INTO posts (post_id, user_id, offer_skill_id, want_skill_id, description, post_status, created_at) VALUES
(1, 2, 3, 4, 'I can teach Photoshop from the basics up to photo editing and poster design. I want to learn Python from zero.', 'open', '2026-08-12'),
(2, 3, 5, 2, 'I will teach PHP and MySQL with a login system and a small CRUD project. I need someone to design a portfolio banner for me.', 'open', '2026-08-13'),
(3, 10, 9, 13, 'I can design the full UI of your app or website in Figma. I am weak in calculus so I need a tutor.', 'open', '2026-08-15'),
(4, 5, 7, 1, 'I will edit videos for your channel or presentation. I want to learn how to build a website with HTML, CSS and JavaScript.', 'open', '2026-08-17'),
(5, 4, 6, 3, 'I will write or check your assignment, CV and cover letter. I want to learn Photoshop for social media posts.', 'matched', '2026-08-08'),
(6, 8, 10, 7, 'I can teach data analysis with Python and pandas. I want to learn video editing to make tutorial videos.', 'open', '2026-08-18'),
(7, 1, 1, 11, 'I can build you a personal website and teach front end basics. I want to learn how to run a Facebook page properly.', 'done', '2026-07-25');

INSERT INTO requests (request_id, post_id, user_id, message, status, created_at) VALUES
(1, 1, 3, 'I can teach you Python from the basics, two days in a week. Can we start from next Sunday?', 'pending', '2026-08-14'),
(2, 1, 8, 'I use Python every day for data work. I would like to learn Photoshop from you in exchange.', 'pending', '2026-08-16'),
(3, 2, 2, 'I can design your portfolio banner and a CV template. I am interested to learn PHP.', 'pending', '2026-08-14'),
(4, 3, 3, 'I give math tuition to first year students. Happy to exchange it for UI design.', 'accepted', '2026-08-16'),
(5, 5, 2, 'I will teach you Photoshop if you check my scholarship essay.', 'accepted', '2026-08-09'),
(6, 7, 7, 'I run social media pages for a clothing brand. I can show you everything about page growth.', 'accepted', '2026-07-26');

INSERT INTO messages (message_id, from_id, to_id, text, sent_at) VALUES
(1, 7, 1, 'Assalamu alaikum. I saw your proposal for the logo job. Can you show me some earlier logo work?', '2026-08-16 09:12:00'),
(2, 1, 7, 'Walaikum assalam. Yes, I have a Behance page with 8 logos. I can send you two source files also.', '2026-08-16 09:20:00'),
(3, 7, 1, 'That is good. If I select you today, how much time will you need?', '2026-08-16 09:26:00'),
(4, 1, 7, 'Three ideas in four days, then two times revision on the one you choose. So around one week.', '2026-08-16 09:31:00'),
(5, 7, 1, 'Fine. Let us do a short online meeting tomorrow evening to finalize everything.', '2026-08-20 18:02:00'),
(6, 3, 1, 'Bhai, I saw your skill exchange post. Do you still want to learn marketing?', '2026-08-18 13:00:00'),
(7, 1, 3, 'Yes I do. And I can teach you front end in return. When are you free?', '2026-08-18 13:14:00'),
(8, 3, 1, 'Weekend is better for me. Saturday afternoon maybe?', '2026-08-19 20:41:00'),
(9, 10, 1, 'Do you take Figma to HTML conversion work? I posted a job for it yesterday.', '2026-08-20 10:05:00'),
(10, 5, 7, 'The first two reels are ready. I am sharing the Drive link now.', '2026-08-19 16:30:00');

INSERT INTO meetings (meeting_id, from_id, to_id, date_time, mode, place, topic, status) VALUES
(1, 7, 1, '2026-08-22 19:00:00', 'online', 'https://meet.google.com/kaaj-koro-01', 'Finalize the logo idea and colours.', 'scheduled'),
(2, 1, 3, '2026-08-23 15:30:00', 'online', 'https://zoom.us/j/9988776655', 'First front end class for the skill exchange.', 'scheduled'),
(3, 6, 8, '2026-08-18 11:00:00', 'online', 'https://meet.google.com/data-review', 'Check the cleaned dataset together.', 'done'),
(4, 10, 3, '2026-08-24 17:00:00', 'offline', 'AIUB Campus Cafeteria', 'First math tuition class.', 'scheduled');

INSERT INTO reviews (review_id, from_id, to_id, job_id, rating, comment, review_date, status) VALUES
(1, 7, 2, 8, 5, 'Very good poster. She sent two drafts and accepted every change without any problem.', '2026-07-30', 'visible'),
(2, 2, 7, 8, 4, 'Requirements were clear and he paid on time. I will work with him again.', '2026-07-31', 'visible'),
(3, 10, 4, 7, 5, 'Delivered before the deadline and the academic writing style was correct.', '2026-08-02', 'visible'),
(4, 6, 8, 5, 5, 'Very organised work. The charts were clean and the summary was easy to understand.', '2026-08-19', 'visible'),
(5, 3, 1, 0, 5, 'Taught front end with patience and explained every line of code. Good exchange partner.', '2026-08-10', 'visible'),
(6, 7, 1, 0, 4, 'Replies fast and communicates well.', '2026-08-12', 'visible'),
(7, 5, 9, 0, 1, 'This user is a fraud, everybody should avoid him completely!!!', '2026-08-14', 'visible'),
(8, 1, 3, 0, 5, 'Explained PHP sessions better than my course teacher did.', '2026-08-11', 'visible');

INSERT INTO reports (report_id, from_id, type, target_id, reason, status, created_at) VALUES
(1, 5, 'user',   9, 'Bad behaviour : This user took advance payment and after that he stopped replying to all my messages.', 'pending', '2026-08-13'),
(2, 2, 'review', 7, 'Bad behaviour : The review has bad language and it is not about any real work.', 'pending', '2026-08-15'),
(3, 3, 'job',    4, 'Other : The budget looks too low for this much writing work.', 'solved', '2026-08-19');

INSERT INTO notifications (notif_id, user_id, text, link, created_at) VALUES
(1, 1, 'Farhan Rahman sent you a message about the logo design job.', 'messages.php', '2026-08-20 18:02:00'),
(2, 1, 'A meeting is scheduled with Farhan Rahman on 22 Aug at 7:00 PM.', 'meetings.php', '2026-08-20 18:10:00'),
(3, 1, 'Your proposal for the portfolio website job is still pending.', 'proposals.php', '2026-08-18 09:00:00'),
(4, 1, 'Tanvir Hossain gave you a 5 star review.', 'reviews.php', '2026-08-10 19:30:00'),
(5, 7, 'You received 2 new proposals for your logo design job.', 'my-jobs.php', '2026-08-15 18:25:00');

INSERT INTO activities (activity_id, user_id, text, created_at) VALUES
(1, 1, 'Logged in to KaajKoro', '2026-08-21 09:02:00'),
(2, 1, 'Sent a proposal for the portfolio website job', '2026-08-17 10:02:00'),
(3, 1, 'Posted a new job: Write 10 blog posts about student life', '2026-08-18 12:00:00'),
(4, 1, 'Updated profile information', '2026-08-16 20:14:00'),
(5, 1, 'Completed a skill exchange with Farhan Rahman', '2026-08-05 18:00:00');
