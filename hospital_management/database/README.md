# Database — hospital_management

Two SQL files for restoring the hospital management database.

## schema.sql — Structure only

Clean install with no data. Just the table structures.

To use: create an empty database first, then import the schema.

## hospital_management_full.sql — Full dump (schema and data)

Complete copy including all rows.

To use: import it into an existing hospital_management database.

## Notes

- Both are plain-text SQL files
- Passwords in the users table are bcrypt-hashed (not reversible)
- Real DB credentials live in config.php, not in these dumps
- This repository is private — do not make it public without reviewing the data
