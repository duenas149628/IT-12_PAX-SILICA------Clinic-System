# System security and acceptance checks

Run these checks against synthetic records in a local or staging database. Record the date, application revision, tester, and pass/fail result for each release.

## Account creation and identity

- Register a patient through the public form. Exactly one `users` row and one linked `patients` row must be created in the same operation; the patient number must be unique.
- Register a patient through the staff form. The account must be linked to the patient record, and the temporary password `1234` must be shown only once; the account holder must change it before accessing protected pages.
- Log in with a staff-created patient or staff account. The first protected request must route to password change. Before changing the password, direct requests to dashboards, patient records, appointments, and reports must remain blocked. After changing it, those routes must work for the account's role.
- Create one account for each staff member. Confirm that two staff members use different login emails and that their actions appear under different user IDs in the activity log. Retire any shared login only after individual accounts have been verified.
- Submit an email already in use. The app must not create another account or an unlinked patient record.

## Authorization and privacy

- While logged in as a patient, attempt to open every staff page. Each attempt must be denied or redirected to login.
- Create two synthetic patient accounts. Patient A must be unable to view or modify Patient B's profile, appointments, or visit history, including by changing an ID in a URL or submitted form.
- Confirm staff can perform staff workflows, while unauthenticated visitors cannot read patient or appointment data.
- Submit each POST form with a missing or invalid CSRF token. The server must reject it with HTTP 400 and make no data change.
- Submit five invalid login attempts from one IP within 15 minutes. The next attempt must receive HTTP 429. A valid login must clear the failure count.
- Check the database: passwords must be password hashes, never plaintext. Check the audit file: it must contain actor IDs and event/record IDs but no passwords, raw IP addresses, or clinical notes.

## Appointment and record integrity

- Enter pending requests for synthetic patients from the staff **New appointment** form. Each request must be linked to the selected patient and visible in the pending filter.
- Confirm or reschedule a request into a free time range. Attempt an overlapping confirmed time for the same date; the app must reject it.
- Complete an appointment and create a visit record. Verify the visit record is linked to the correct patient and appointment and is visible only to authorized staff and that patient.
- Verify patient creation, appointment changes, and visit edits each create an audit event attributed to the logged-in staff member.

## Backup and recovery

- Create encrypted backups daily, retain daily copies for at least 30 days and monthly copies for at least 12 months, and keep a second copy separate from the clinic workstation.
- At least once every quarter, restore a backup into an isolated database. Record the restore duration; it must be at most 4 hours, and the recovered data must be no more than 24 hours behind the live database at the selected recovery point.
- After restore, verify the `users`, `patients`, `appointments`, and `visit_records` tables, their relationships, patient login, staff login, role checks, and one representative appointment workflow.
