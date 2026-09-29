# Security, backup, and recovery

## Database backups

The clinic database contains patient and appointment information. Treat each backup as confidential health data.

1. Set a unique MySQL password for the application and use an account with only the privileges the application needs. Configure the application through the `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables; do not use a blank-password `root` account in production.
2. Create a protected MySQL option file for the backup operator, for example `C:\laragon\etc\tooth-lounge-backup.cnf`, with permissions limited to the operator:

   ```ini
   [client]
   user=backup_operator
   password=REPLACE_WITH_SECRET
   host=127.0.0.1
   ```

3. Run a daily backup to an access-restricted directory on a separate disk or backup service. Encrypt it at rest and in transit, and keep a second copy away from the clinic computer:

   ```powershell
   mysqldump --defaults-extra-file="C:\laragon\etc\tooth-lounge-backup.cnf" --single-transaction --routines --triggers "tooth-lounge" | gzip | age -r AGE_PUBLIC_KEY > "D:\ClinicBackups\tooth-lounge-YYYYMMDD.sql.gz.age"
   ```

   Replace the date and age recipient placeholders with the current date and the clinic backup encryption public key. Keep the matching decryption private key offline in a separate protected location. Do not put database passwords in the command line or in this repository. The pipeline encrypts the archive before writing it to disk.
4. Keep daily copies for 30 days and monthly copies for 12 months, or follow the clinic's longer retention policy. Restrict backup access to authorized operators and record each restore.
5. At least quarterly, restore a recent backup into a separate, isolated test database and verify the tables, representative patient/appointment records, and application login. A backup is not proven until a restore succeeds.

## Recovery

1. If the live database is damaged or unavailable, stop writes to the application and preserve the damaged database for investigation.
2. Select the newest known-good backup from before the incident. Record the backup timestamp and the expected recovery point.
3. Restore to a new database first, never over the only copy of the live database:

   ```powershell
   age -d -i "C:\laragon\etc\clinic-backup-key.txt" "D:\ClinicBackups\tooth-lounge-YYYYMMDD.sql.gz.age" | gzip -dc | mysql --defaults-extra-file="C:\laragon\etc\tooth-lounge-backup.cnf" "tooth-lounge-recovery"
   ```

   Create `tooth-lounge-recovery` before restoring. On Windows systems without `age` or `gzip`, decrypt and extract with the installed equivalents before importing with the MySQL client.
4. Validate table counts and sample records with an authorized staff member. Point the application at the recovered database only after validation, then confirm login, patient lookup, and appointment workflows.
5. Keep the original damaged database and the selected backup unchanged until recovery is signed off. Document the restore time, backup used, data loss window, validation, and any follow-up actions.

## Security smoke checks

For a persistent audit trail, set `AUDIT_LOG_PATH` to a restricted log file outside the web root on persistent storage. The development default writes to the operating-system temporary directory and may be cleared during cleanup or restart. Protect and retain this file under the clinic's audit policy; it contains user IDs, event details, and hashed IP addresses.

Run the DB-independent checks from the project root:

```powershell
php tests/security_smoke.php
```

Also verify in a local test database before release: a missing/invalid CSRF token is rejected; six failed logins from one IP result in HTTP 429 after the fifth failure; valid and invalid current passwords behave correctly on the password-change page; and password changes, login/logout, appointment changes, and patient/visit edits create audit entries. Confirm the audit file is outside the web root and can only be read by the application operator.
