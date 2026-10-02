# Phase 4 operations

## Audit and activity log

The staff activity log is available at `/staff/activity_log.php`.

## Patient and staff account setup

Apply `database/migrations/001_password_change_required.sql` before deploying the account setup pages. Patients can self-register or be registered by staff; both paths create a user login and linked patient record. Staff-created patient and staff accounts use the temporary password `1234`, display it after creation, and require the account holder to replace it at first login. Create one staff login per staff member so appointment and record changes can be attributed to an individual account.

Staff can enter appointment requests received by phone, text, Facebook, or another offline channel from the **New appointment** navigation link. These requests are saved as pending and continue through the normal review and schedule-confirmation workflow.

## Automated encrypted database backups

Install `mysqldump`, `gzip`, and [`age`](https://age-encryption.org/). Create a MySQL option file such as `/etc/tooth-lounge/backup.cnf` with a dedicated read-only backup user and restrict it to the backup operator. Configure the scheduled job with:

```text
DB_NAME=tooth-lounge
MYSQL_CNF=/etc/tooth-lounge/backup.cnf
BACKUP_DIR=/mnt/private-clinic-backups/tooth-lounge
AGE_RECIPIENT=age1...
```

Run `bash /var/www/tooth-lounge/scripts/backup-database.sh` daily from cron or a systemd timer. The script streams a consistent MySQL dump through gzip and age encryption before writing the archive, uses restrictive file permissions, and leaves existing archives untouched. Store the age private key offline or in a separate protected key service. Monitor the scheduler exit status and available backup storage. Use a separate approved retention policy to expire old archives, and regularly perform an isolated restore as described in [SECURITY-BACKUP.md](SECURITY-BACKUP.md).

Example daily 2:15 AM cron entry (put the four backup variables in a mode-600 `/etc/tooth-lounge/backup.env` file):

```cron
15 2 * * * set -a && . /etc/tooth-lounge/backup.env && set +a && bash /var/www/tooth-lounge/scripts/backup-database.sh >> /var/log/tooth-lounge/backup.log 2>&1
```

## Audit/activity log

Set `AUDIT_LOG_PATH` to a persistent file outside the web root. The staff activity page shows the latest 500 events. Restrict file access, rotate the file before it grows too large, and retain rotated copies according to clinic policy. The log contains event names, user IDs, record IDs, timestamps, and hashed IP addresses; it does not contain raw IP addresses or clinical notes.
