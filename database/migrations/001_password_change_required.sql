-- Apply once before deploying account-creation and forced password-change flows.
ALTER TABLE users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER role;
