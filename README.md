# 💼 IT Service Desk & Asset Management System

> An enterprise-grade IT Service Management (ITSM) platform built with **Oracle Database 21c XE**, **PL/SQL**, and **PHP + OCI8** — modelled after industry tools like ServiceNow and Jira Service Management.

![Oracle](https://img.shields.io/badge/Oracle-21c%20XE-F80000?style=flat-square&logo=oracle&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-Academic-blue?style=flat-square)
##YOUTUBE LINK- 
https://youtu.be/yuj1Vgep2vI

---

## 📖 Overview

This system manages the complete lifecycle of IT support tickets — from creation and automated engineer assignment through SLA monitoring and resolution — while maintaining a full hardware asset inventory with allocation tracking.

The database backend uses **12 relational tables in 3NF**, automated via **3 PL/SQL triggers** and **2 stored procedures**, populated with **200+ records per table** to simulate realistic enterprise workload. Three user roles (Admin, Engineer, User) enforce scoped access at the session, query, and UI levels.

---

## ✨ Features

### 🎫 Ticket Lifecycle Management
- Create tickets with priority-driven SLA auto-assignment (`HIGH = 4h`, `MEDIUM = 8h`, `LOW = 24h`)
- Auto-assignment to the least-loaded available engineer via `TRG_AUTO_ASSIGN`
- Status workflow: `OPEN → IN_PROGRESS → CLOSED`
- Full audit trail in `TICKET_HISTORY` on every status transition
- Role-restricted comment system (`Engineer` and `Admin` only can post)

### ⏱️ SLA Monitoring
- Real-time SLA classification: `SAFE` / `WARNING (≥90%)` / `BREACHED (≥100%)`
- Automated recalculation on every ticket event via `TRG_SLA_MONITOR`
- Weekend ticket restriction enforced at the DB trigger level (`TRG_NO_WEEKEND_TICKET`)
- SLA summary report via `SP_SLA_REPORT` using a `SYS_REFCURSOR` with window functions

### 💻 Asset Management
- Full hardware inventory (`Laptop`, `Desktop`, `Printer`) with status tracking (`AVAILABLE`, `IN_USE`, `MAINTENANCE`)
- Allocate assets to employees; record allocation + return dates in `ASSET_ALLOCATION`
- Admin-only operations; ternary relationship (asset ↔ employee ↔ allocating admin)

### 👥 Role-Based Access Control
| Capability | Admin | Engineer | User |
|---|:---:|:---:|:---:|
| View all tickets | ✅ | ❌ | ❌ |
| View assigned tickets | ✅ | ✅ | ❌ |
| View own tickets | ✅ | ✅ | ✅ |
| Update ticket status | ✅ | ✅ | ❌ |
| Post comments | ✅ | ✅ | ❌ |
| Asset management | ✅ | ✅ | ❌ |
| SLA dashboard | ✅ | ❌ | ❌ |

### 📊 Analytics Dashboard
- Chart.js donut chart for SLA status distribution
- Bar chart for ticket priority breakdown
- Live KPI cards: Total, Open, In Progress, Closed, Breached

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Database** | Oracle Database 21c XE · Schema: `IT_DESK` on `XEPDB1` |
| **DB Language** | SQL (DDL · DML · DQL) + PL/SQL |
| **Backend** | PHP 8.x with OCI8 Extension |
| **Web Server** | Apache via XAMPP |
| **Frontend** | HTML5 · CSS3 · Bootstrap 5 · Chart.js · Bootstrap Icons |
| **Version Control** | Git & GitHub |

---

## 🗄️ Database Design

### Schema — 12 Tables

```
┌─────────────────────────────────────────────────────────────────┐
│  STRONG ENTITIES                                                │
│  DEPARTMENT  ·  EMPLOYEE  ·  TICKET  ·  ASSET                  │
│  LOGIN_USERS  ·  ROLES  ·  SYSTEM_LOGS                         │
├─────────────────────────────────────────────────────────────────┤
│  WEAK / ASSOCIATIVE ENTITIES                                    │
│  TICKET_HISTORY  ·  TICKET_ACTIVITY  ·  TICKET_COMMENTS        │
│  ASSET_ALLOCATION  ·  USER_ROLES                               │
└─────────────────────────────────────────────────────────────────┘
```

### Key Table Highlights

**`TICKET`** — 12 columns including `CLOB` description, `CHECK` constraints on `PRIORITY` and `STATUS`, nullable `ASSIGNED_TO` (partial participation), and stored `SLA_STATUS` (denormalized for read performance).

**`EMPLOYEE`** — `IS_AVAILABLE CHAR(1)` flag drives trigger-based load balancing. `ROLE CHECK` constraint enforces valid values at DDL level.

**`ASSET_ALLOCATION`** — Resolves the M:N between `ASSET` and `EMPLOYEE`. Contains a ternary FK (`ALLOCATED_BY`) pointing to the admin who processed the allocation.

**`SYSTEM_LOGS`** — `PERFORMED_BY` is nullable to accommodate automated system events with no human actor.

### Entity Relationships

```
DEPARTMENT ──< EMPLOYEE ──< TICKET >── TICKET_HISTORY
                   │            └────── TICKET_ACTIVITY
                   │            └────── TICKET_COMMENTS
                   └──< ASSET ──< ASSET_ALLOCATION
LOGIN_USERS >──── USER_ROLES ────< ROLES
```

---

## ⚙️ PL/SQL Automation

### Triggers

#### `TRG_AUTO_ASSIGN` · `AFTER INSERT ON TICKET`
Assigns the least-loaded available engineer on every new ticket using a load-balancing subquery. Logs the event to `TICKET_ACTIVITY`. Handles `NO_DATA_FOUND` gracefully when no engineers are available.

```sql
SELECT EMP_ID FROM (
    SELECT E.EMP_ID, COUNT(T.TICKET_ID) AS OPEN_COUNT
    FROM EMPLOYEE E
    LEFT JOIN TICKET T ON T.ASSIGNED_TO = E.EMP_ID
        AND T.STATUS IN ('OPEN','IN_PROGRESS')
    WHERE E.ROLE = 'ENGINEER' AND E.IS_AVAILABLE = 'Y'
    GROUP BY E.EMP_ID ORDER BY OPEN_COUNT ASC
) WHERE ROWNUM = 1;
```

#### `TRG_SLA_MONITOR` · `AFTER INSERT OR UPDATE ON TICKET`
Recalculates `SLA_STATUS` for every non-closed ticket based on elapsed time vs. the `SLA_HOURS` threshold.

| Elapsed Time | Status |
|---|---|
| `< 90%` of SLA window | `SAFE` |
| `≥ 90%` of SLA window | `WARNING` |
| `≥ 100%` of SLA window | `BREACHED` |

#### `TRG_NO_WEEKEND_TICKET` · `BEFORE INSERT ON TICKET`
Blocks ticket creation on Saturday/Sunday using `TO_CHAR(SYSDATE, 'DY')`. Raises `ORA-20002` with a user-readable message surfaced via PHP's `oci_error()` handler.

---

### Stored Procedures

#### `SP_UPDATE_TICKET_STATUS`
Encapsulates ticket status transitions with full transactional integrity:
- `SELECT ... FOR UPDATE` — acquires row lock before modification
- Updates `STATUS`, `UPDATED_AT`, and `CLOSED_AT` (conditional on closure)
- Inserts a `TICKET_HISTORY` record on every call
- `COMMIT` on success · `ROLLBACK` on `WHEN OTHERS`
- Raises `ORA-20001` for invalid ticket IDs

#### `SP_SLA_REPORT`
Returns an `OUT SYS_REFCURSOR` with per-status ticket counts and percentage share using a `SUM(...) OVER ()` window function. Called from the PHP dashboard via `oci_bind_by_name()`.

---

## 🔍 Key SQL Queries

### Multi-table Ticket Listing
```sql
SELECT T.TICKET_ID, T.TITLE, T.PRIORITY, T.STATUS, T.SLA_STATUS,
       R.EMP_NAME AS REQUESTER,
       A.EMP_NAME AS ASSIGNED_ENGINEER,
       D.DEPT_NAME, T.CREATED_AT
FROM TICKET T
JOIN EMPLOYEE R ON T.REQUESTER_ID = R.EMP_ID       -- INNER: requester always exists
LEFT JOIN EMPLOYEE A ON T.ASSIGNED_TO = A.EMP_ID   -- LEFT: unassigned tickets must appear
JOIN DEPARTMENT D ON R.DEPT_ID = D.DEPT_ID
ORDER BY T.CREATED_AT DESC;
```

### Dashboard KPI Aggregation
```sql
SELECT
    COUNT(*) AS TOTAL,
    SUM(CASE WHEN STATUS = 'OPEN' THEN 1 ELSE 0 END)          AS OPEN_COUNT,
    SUM(CASE WHEN STATUS = 'IN_PROGRESS' THEN 1 ELSE 0 END)   AS IN_PROGRESS_COUNT,
    SUM(CASE WHEN SLA_STATUS = 'BREACHED' THEN 1 ELSE 0 END)  AS BREACHED_COUNT
FROM TICKET T JOIN EMPLOYEE E ON T.REQUESTER_ID = E.EMP_ID;
```

---

## 🚀 Getting Started

### Prerequisites
- Oracle Database 21c XE installed and running
- XAMPP (Apache + PHP 8.x)
- PHP OCI8 extension enabled in `php.ini`

### Setup

**1. Create the Oracle schema**
```sql
-- Connect as SYSDBA and create the user
CREATE USER IT_DESK IDENTIFIED BY itdesk123;
GRANT CONNECT, RESOURCE, CREATE VIEW TO IT_DESK;
```

**2. Run the SQL scripts** (in order)
```
sql/01_create_tables.sql      -- DDL: all 12 tables with constraints
sql/02_triggers.sql           -- PL/SQL: 3 triggers
sql/03_procedures.sql         -- PL/SQL: 2 stored procedures
sql/04_seed_data.sql          -- DML: 200+ records per table
```

**3. Configure the database connection**

Edit `config/db.php`:
```php
$conn = oci_connect('IT_DESK', 'itdesk123', 'localhost:1521/XEPDB1');
```

**4. Deploy to XAMPP**
```
Place the project folder in:   C:/xampp/htdocs/it-servicedesk/
Access at:                     http://localhost/it-servicedesk/
```

### Default Login Credentials

| Role | Username | Password |
|---|---|---|
| Admin | `admin@company.com` | `admin123` |
| Engineer | `engineer@company.com` | `eng123` |
| User | `user@company.com` | `user123` |

> ⚠️ Change these credentials before any deployment beyond local testing.

---

## 📁 Project Structure

```
it-servicedesk/
├── config/
│   └── db.php                  # OCI8 connection + error handling
├── components/
│   ├── sidebar.php             # Role-conditioned navigation
│   └── topbar.php              # User profile + breach notification badge
├── pages/
│   ├── tickets/                # Create, view, details, close, comment
│   ├── assets/                 # Listing, allocation, return
│   └── reports/                # SLA dashboard + analytics
├── assets/
│   └── css/style.css           # Global dark theme overrides
├── sql/
│   ├── 01_create_tables.sql
│   ├── 02_triggers.sql
│   ├── 03_procedures.sql
│   └── 04_seed_data.sql
└── index.php                   # Entry point + role-based redirect
```

---

## 🔐 Security Notes

- Passwords stored using PHP `password_hash()` with bcrypt; verified via `password_verify()`
- All queries use OCI8 bind variables (`oci_bind_by_name`) — no raw string interpolation
- Role-based data scoping enforced at the SQL query layer, not just the UI layer
- Oracle application errors (`ORA-20001`, `ORA-20002`) caught and sanitized before display

---

## ⚠️ Known Limitations

- `TRG_SLA_MONITOR` only recalculates on INSERT/UPDATE events. A production system would add an Oracle `DBMS_SCHEDULER` job for time-based breach detection on idle tickets.
- No email notifications for SLA breaches or assignments (future: Oracle `UTL_MAIL` or SMTP integration).
- Deployed on local XAMPP; scaling to concurrent users would require Oracle DRCP connection pooling.

---

## 🔮 Future Enhancements

- [ ] `DBMS_SCHEDULER` job for periodic SLA breach detection
- [ ] Email alerts via Oracle `UTL_MAIL` for breach events
- [ ] Knowledge base with Oracle Text full-text search (`CONTAINS`)
- [ ] PDF/Excel SLA compliance report export
- [ ] Mobile-responsive PWA for engineers on the go

---

## 📚 References

- [Oracle Database 21c PL/SQL Reference](https://docs.oracle.com/en/database/oracle/oracle-database/21/lnpls/)
- [Oracle Database 21c SQL Reference](https://docs.oracle.com/en/database/oracle/oracle-database/21/sqlrf/)
- [PHP OCI8 Manual](https://www.php.net/manual/en/book.oci8.php)
- Connolly & Begg — *Database Systems*, 6th ed., Pearson, 2015
- Ramakrishnan & Gehrke — *Database Management Systems*, 3rd ed., McGraw-Hill, 2003

---

*Academic project — Database Systems Lab (CSS 2212) · Manipal Institute of Technology · 2025–26*
