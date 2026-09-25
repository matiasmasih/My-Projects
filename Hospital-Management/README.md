# Hospital Management System — Run Guide

## Start the system

    sudo systemctl start apache2 mariadb

Open:

    http://localhost/hospital_management/login.php

## Login credentials

| Role | Email | Password |
|---|---|---|
| Manager | matiasmasih@gmail.com | aziz123 |
| Admin | aziz.noyan@outlook.com | aziz1234 |
| Doctor | aziznoyan50@gmail.com | doctor123 |
| Staff | aziz.noyan1994@gmail.com | staff123 |
| Patient | alice@patient.com | patient123 |
| Patient | aziz.rahman@test.com | patient123 |
| Patient | maria.garcia@test.com | patient123 |
| Patient | john.doe@test.com | patient123 |
| Patient | sophia.nguyen@test.com | patient123 |


## What each role does

**Manager** (teal) — full access to everything: patients, doctors, appointments, invoices, payments, pharmacy, medicines, wards, rooms, messages, admissions.

**Admin** (indigo) — full on users, roles, admin messages, own dashboard. View-only on everything else.

**Doctor** (green) — full on medical records and prescriptions. View-only on patients, doctors, appointments, medicines.

**Staff** (blue) — full on messages. View-only on patients, appointments, admissions, invoices, payments, rooms, wards, doctors.

**Patient** (sky blue) — own data only: appointments, prescriptions, records, invoices, doctors, messages, profile (with photo upload + password change).

## Where things are

    /var/www/html/hospital_management/

    config.php             → DB connection
    access_control.php     → role access rules
    uploads/avatars/       → profile photos
    vendor/                → mPDF + barcode libraries

Log file:   /var/log/apache2/error.log
Database:   hospital_management

## Database

    mysql -u root -pMatias413114312 hospital_management

## Reset any password

    php -r "echo password_hash('NewPassword123', PASSWORD_DEFAULT), PHP_EOL;"
    mysql -u root -pMatias413114312 hospital_management -e "UPDATE users SET password_hash='HASH_HERE' WHERE email='user@example.com';"

## Common fixes

| Problem | Fix |
|---|---|
| ERR_CONNECTION_REFUSED | sudo systemctl start apache2 |
| Database connection failed | sudo systemctl start mariadb |
| HTTP 500 | sudo tail -20 /var/log/apache2/error.log |
| Changes not showing | Ctrl + Shift + R |
| PHP changes not applied | sudo systemctl restart apache2 |

## Backup

    sudo tar -czf ~/hospital_backup_$(date +%Y%m%d).tar.gz /var/www/html/hospital_management/
    mysqldump -u root -pMatias413114312 hospital_management > ~/hospital_db_$(date +%Y%m%d).sql

## Project stats

    5 roles  ·  5 dashboards  ·  12 wards  ·  400 rooms  ·  1,106 beds
    8 medicines  ·  5 patients  ·  1 doctor  ·  9 users

## Tech stack

    PHP 8.1  ·  MariaDB 10.6  ·  Apache 2.4
    Bootstrap 5.3  ·  Chart.js  ·  mPDF  ·  Picqer Barcode
