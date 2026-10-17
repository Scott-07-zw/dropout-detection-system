# MSU Dropout Prediction System (HTML · CSS · JavaScript · PHP · MySQL)

## Run it on XAMPP
1. Copy this folder to `C:\xampp\htdocs\msu-dps`.
2. In the XAMPP Control Panel start **Apache** and **MySQL**.
3. Open `http://localhost/msu-dps/setup.php` once. It creates the `msu_dps` database, the tables and demo data
   (`setup.php?reset=1` wipes and re-seeds).
4. Open `http://localhost/msu-dps/` and sign in. Demo password for every account: `Password@123`

| Role | Email |
|---|---|
| Administrator | admin@staff.msu.ac.zw |
| Lecturer (IS201, CS201) | lecturer@staff.msu.ac.zw |
| Lecturer (IS301, STAT201) | makoni@staff.msu.ac.zw |
| HOD / Dean | hod@staff.msu.ac.zw |

If your MySQL root has a password, edit `inc/config.php`. Delete `setup.php` and change the demo passwords before real use.
The Excel upload and PDF export load SheetJS and jsPDF from cdnjs, so the browser needs internet access for those two features.

## Structure
- `login.php`, `app.php`: pages. `api.php`: JSON API (all role checks happen here, on the server).
- `inc/risk.php`: the risk engine. `inc/bootstrap.php`: DB, sessions, audit helper. `assets/`: CSS, JS, logo/favicon.
- `docs/RISK-ENGINE.md`: rules and settings.

## Behaviour notes
- Attendance: a register opens when first saved. It is editable for **3 hours**; students left unmarked become **absent 4 hours** after it opened (so between 3 h and 4 h they show "not marked" and cannot be edited).
- Structure: 10 faculties > 16 departments > 94 programmes (see `setup.php`). Each programme has 16-20 generated demo students and 4 modules (about one semester's load) that all of them are enrolled in. The modules have no lecturer yet. Assign lecturers and create modules in Admin > Modules.
- Coursework = Test + Assignment 1 + Assignment 2, on one scale per faculty (Admin > Faculties & Departments): out of **40** (test 20, assignments 10 + 10, Red below 24) for Arts and Humanities, Education, Agriculture/Environment/Natural Resources, Built Environment/Art/Design and Engineering/Geosciences; out of **30** (test 15, assignments 7.5 + 7.5, Red below 18) for all other faculties.
- Accounts can only be created with the email domains listed in `inc/config.php` (`allowed_email_domains`: only @staff.msu.ac.zw).
- Upgrading an existing install: copy the new files over, then open `setup.php` (without `?reset=1`) once. Your data is kept and the faculties, programmes and students are added. For a clean demo, use `setup.php?reset=1` instead (wipes everything).
- Excel/CSV columns: `reg_number`, `test`, `assignment1`, `assignment2`.
- An unregistered student is always Red.
- Admin > Users: edit a user's name, email, role or department in place (their notes, modules and history are kept), reset a forgotten password (the user must choose their own at next sign-in; a reset also clears a lockout), activate or deactivate. Every user can change their own password from the sidebar.
- Lecturers can teach in several departments: tick them when creating or editing the account, or just assign the lecturer a module from another department in Admin > Modules (the department is added automatically). HODs head exactly one department.
- HOD: Dashboard or Department Report > preview, then download the departmental report as PDF or CSV (summary, by module, by lecturer, every Amber/Red student with the reason).
- Verification: pressing the browser's Back button from the account signs the user out and shows the login screen; opening `login.php` while signed in also ends the session. Pages are sent with no-cache headers, so Back/Forward never shows an old signed-in view.
- Inactivity timeout: after 60 minutes without use the user is signed out (server-enforced, with a "Still there?" warning 2 minutes before). Change it with `session_timeout` (seconds) in `inc/config.php`. Sessions are stored in the `sessions/` folder, which PHP must be able to write to.
- Maximum 5 active administrators. Admin creates all accounts.
- Usage reports: 7-week periods counted from the `system_start` setting (3 August 2026); reports can be downloaded at any time (an unfinished period is reported up to today).
