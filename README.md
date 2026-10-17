# Federal College of Animal Health and Production Technology — Student Registration Portal

Quick setup:

1. Import `db.sql` into your MySQL server (creates `moor_portal` database). This single file contains the full schema (users, announcements, admin_users).
2. Update DB credentials in `config.php` if needed.
3. Ensure PHP file uploads are allowed and `uploads/` is writable.
4. Configure PHP mail() or an SMTP library for outgoing email.
5. If you previously imported an older `db.sql` without the `post_utme_path` column, re-import the updated DB by dropping and recreating the database, for example:

```sql
DROP DATABASE IF EXISTS moor_portal;
SOURCE db.sql;
```

Or from the shell:

```bash
mysql -u root -p < db.sql
```
5. Start by visiting `http://localhost/student-course-registration/` in your browser.
