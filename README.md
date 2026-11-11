# TaraCommute + Students REST API Backend

A production-ready PHP and MySQL backend powering the **TaraCommute** commuter application and teacher's **Students Management REST API**. Built for seamless integration with Ionic Angular / Capacitor mobile and web clients.

---

## 🌟 Features

- **Authentication & Security (`auth.php`)**:
  - Secure account registration & login with **BCrypt** password hashing (cost factor 12).
  - Brute-force protection: automatic 5-minute account lockout after 5 consecutive failed login attempts.
  - Bearer token-based session management (`auth_token`).
  - Auto-migration helper that ensures database columns exist across updates.
- **Commute History & Fare Tracking (`history.php`)**:
  - Log completed commuter trips (mode, origin, destination, distance in km, fare amount).
  - Query trip history filtered by student number.
  - Delete trip records.
- **Campus & Transit Hub Locations (`locations.php`)**:
  - Searchable list of campus gates, transit hubs, terminals, and nearby malls with GPS coordinates (latitude / longitude).
- **Students CRUD Management (`students.php`)**:
  - Full REST API (`GET`, `POST`, `PUT`, `DELETE`) for student records (`student_no`, `full_name`, `course`, `year_level`).
  - Backwards-compatible with class assignments and grading requirements.
- **Dual-Environment Auto Detection (`config.php`)**:
  - Automatically switches credentials between **Local XAMPP** and **Live Shared Hosting** (e.g. `taracommute-api.elementfx.com`).
- **Full CORS Support**: Pre-configured headers for Ionic (`capacitor://localhost`, `http://localhost:8100`, etc.) and web apps.

---

## 📁 Repository Structure

```text
http-backend/
├── api/
│   ├── auth.php            # User authentication, registration, session verification
│   ├── config.php          # Database PDO connection & environment detector
│   ├── history.php         # Commute trip logging and history retrieval
│   ├── init_db.php         # Web runner to apply schemas and verify tables
│   ├── locations.php       # Campus & transit hubs directory and search
│   └── students.php        # Teacher's student CRUD REST API
├── sql/
│   ├── taracommute_hosting.sql # Shared hosting schema (DirectAdmin / cPanel / phpMyAdmin)
│   ├── taracommute_schema.sql  # Local XAMPP schema (includes CREATE DATABASE)
│   └── students_api.sql        # Legacy single-table students schema
└── README.md
```

---

## 🚀 Getting Started

### Local Setup (XAMPP / MAMP)

1. Clone or copy this repository into your web server root:
   - **XAMPP Windows:** `C:\xampp\htdocs\http-backend` (or `D:\XAMPP\htdocs\http-backend`)
2. Start **Apache** and **MySQL** via the XAMPP Control Panel.
3. Import the database schema:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Click **Import** and select [`sql/taracommute_schema.sql`](sql/taracommute_schema.sql).
   - *Alternatively:* Navigate to `http://localhost/http-backend/api/init_db.php` in your browser.
4. Verify endpoints by visiting:
   ```text
   http://localhost/http-backend/api/students.php
   http://localhost/http-backend/api/locations.php
   ```

### Live Shared Hosting Deployment (x10hosting / DirectAdmin)

1. **Create Database in DirectAdmin / cPanel**:
   - Go to **Account Manager** → **MySQL Management** → **Create Database**.
   - Note the assigned database name, username, and password.
2. **Import Hosting Schema**:
   - Open **phpMyAdmin**, click your database in the left sidebar (e.g. `rsjxgeti_taradb`).
   - Go to **Import** and upload [`sql/taracommute_hosting.sql`](sql/taracommute_hosting.sql).
3. **Upload Files**:
   - Upload the contents of `api/` into your server's `public_html/api/`.
4. **Configure Credentials in `api/config.php`**:
   - Verify that `DB_NAME`, `DB_USER`, and `DB_PASS` match your live database details.

---

## 📡 API Reference

Base URL (Local): `http://localhost/http-backend/api`  
Base URL (Production): `https://taracommute-api.elementfx.com/api`

---

### 1. Authentication (`/api/auth.php`)

#### Register User
`POST /api/auth.php?action=register`

**Request Body:**
```json
{
  "student_no": "2026-0001",
  "username": "juandc",
  "password": "Password123",
  "full_name": "Juan Dela Cruz",
  "email": "juan.delacruz@campus.edu.ph",
  "course": "BSIT",
  "year_level": 4,
  "school": "Araullo University (Main Campus)"
}
```

**Success Response (`201 Created`):**
```json
{
  "success": true,
  "message": "Account created and saved to MySQL database successfully!",
  "token": "4a7c...",
  "token_type": "Bearer",
  "data": {
    "id": 1,
    "student_no": "2026-0001",
    "username": "juandc",
    "full_name": "Juan Dela Cruz",
    "email": "juan.delacruz@campus.edu.ph",
    "course": "BSIT",
    "year_level": 4
  }
}
```

#### User Login
`POST /api/auth.php?action=login`

**Request Body:**
```json
{
  "username": "juandc",
  "password": "Password123"
}
```
*(Accepts either `username`, `student_no`, or `email` as the account identifier).*

**Success Response (`200 OK`):**
```json
{
  "success": true,
  "message": "Login successful",
  "token": "e2f1...",
  "token_type": "Bearer",
  "data": {
    "id": 1,
    "student_no": "2026-0001",
    "username": "juandc",
    "full_name": "Juan Dela Cruz",
    "email": "juan.delacruz@campus.edu.ph",
    "course": "BSIT"
  }
}
```

#### Verify Active Session Token
`GET /api/auth.php?action=verify`

**Header:**
```text
Authorization: Bearer <YOUR_TOKEN>
```

#### Logout
`POST /api/auth.php?action=logout`

**Header:**
```text
Authorization: Bearer <YOUR_TOKEN>
```

#### List Directory Users
`GET /api/auth.php?action=users`

#### Get Single User by Student Number
`GET /api/auth.php?action=user&student_no=2026-0001`

---

### 2. Commute History (`/api/history.php`)

#### List Recent Trips
`GET /api/history.php`  
`GET /api/history.php?student_no=2026-0001`

**Success Response (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "student_no": "2026-0001",
      "mode": "tricycle",
      "origin": "Sumacab Campus",
      "destination": "Santa Rosa Crossing",
      "distance_km": "3.40",
      "fare_amount": "80.00",
      "created_at": "2026-11-12 01:23:00"
    }
  ]
}
```

#### Log a New Commute Trip
`POST /api/history.php`

**Request Body:**
```json
{
  "student_no": "2026-0001",
  "mode": "tricycle",
  "origin": "Sumacab Campus",
  "destination": "SM Mega Center",
  "distance_km": 6.1,
  "fare_amount": 130.00
}
```

#### Delete a Trip
`DELETE /api/history.php?id=1`

---

### 3. Campus & Transit Locations (`/api/locations.php`)

#### Get All Locations
`GET /api/locations.php`

#### Search Locations by Keyword
`GET /api/locations.php?search=Sumacab`

**Success Response (`200 OK`):**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Sumacab Campus",
      "category": "Campus",
      "latitude": "15.47400000",
      "longitude": "120.95700000",
      "distance_km": "2.50"
    }
  ]
}
```

---

### 4. Students REST API CRUD (`/api/students.php`)

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `GET` | `/api/students.php` | List all students (ordered newest first) |
| `GET` | `/api/students.php?id={id}` | Get student by database ID |
| `POST` | `/api/students.php` | Create a new student |
| `PUT` | `/api/students.php?id={id}` | Update student details |
| `DELETE` | `/api/students.php?id={id}` | Delete student |

#### Example POST / PUT Body
```json
{
  "student_no": "2026-0099",
  "full_name": "Maria Santos",
  "course": "BSCS",
  "year_level": 3
}
```

---

## 📱 Frontend Integration (Ionic Angular)

In your Angular project, configure your environment file (`src/environments/environment.ts` or `environment.prod.ts`):

```typescript
export const environment = {
  production: true,
  // Point to live API server:
  apiBaseUrl: 'https://taracommute-api.elementfx.com/api'
  // Or for local testing:
  // apiBaseUrl: 'http://localhost/http-backend/api'
};
```

---

## 🚦 HTTP Status Codes

- `200 OK`: Request succeeded.
- `201 Created`: Resource created successfully.
- `400 Bad Request`: Missing or malformed input.
- `401 Unauthorized`: Invalid credentials or missing session token.
- `404 Not Found`: Resource does not exist.
- `409 Conflict`: Duplicate student number or username.
- `422 Unprocessable Entity`: Validation failure.
- `429 Too Many Requests`: Account locked out due to security rate limit.
- `500 Internal Server Error`: Server or database failure.
