# Phase 4 operations

## Enable notifications and reminders

Apply the schema migration once before using dashboards or changing appointment status:

```sh
mysql --defaults-extra-file=/etc/tooth-lounge/mysql.cnf tooth-lounge < database/migrations/001_notifications_and_reminders.sql
```

The app creates in-system notifications when patients submit requests and when staff update an appointment. Users can review and mark their notifications read at `/notifications.php`. The audit/activity log is available to staff at `/staff/activity_log.php`.

Appointment reminders are in-system notifications for confirmed appointments scheduled the following day. They are deduplicated per appointment and scheduled date. Configure the server timezone to the clinic's timezone and run the job daily, for example at 8:00 AM:

```cron
0 8 * * * cd /var/www/tooth-lounge && set -a && . /etc/tooth-lounge/runtime.env && set +a && /usr/bin/php scripts/send-appointment-reminders.php >> /var/log/tooth-lounge/reminders.log 2>&1
```

Keep `runtime.env` outside the repository and web root with permissions restricted to the service operator. It must provide `APP_ENV=production`, `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. The reminder job writes to the configured `AUDIT_LOG_PATH`. It does not send email or SMS.

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
