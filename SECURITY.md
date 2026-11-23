# Security – Skope Digital Academy

How the academy is protected, and what to do when something goes wrong.
Live site: https://skopedigital.co.ke · Server: AWS EC2 (Ubuntu, Apache, PHP 8.5, MariaDB) behind Cloudflare.

## What is in place

| Area | Protection |
|---|---|
| Passwords | bcrypt hashes; reset links expire in 1 hour |
| Sign-in | 5 failed attempts per email (20 per IP) in 15 minutes locks sign-in for 15 minutes; admins get an email when an account is locked |
| Admins | Sign-in check by email: after the password, a 6-digit code and a one-click link are emailed (expire in 10 minutes, work once, 5 tries). Skipped only while the academy mailbox is disconnected, so admins are never locked out. Turn off with the setting `admin_2fa_required` = 0. Admin sessions end after 60 minutes idle (others 120) |
| Sessions | HttpOnly + Secure + SameSite=Lax cookies, strict session ids, new id at every sign-in |
| Forms | CSRF token on every form; sign-up and password reset are rate limited |
| Code | Prepared SQL statements, escaped output, server-side prices, local-only redirects |
| Uploads | Type and size checks; nothing in `uploads/` can run as PHP or a script |
| Payments | Paystack webhook signature checked (constant-time) |
| Secrets | DB password, AI key and the encryption key live in `includes/private/` (never in git). Gmail tokens are encrypted in the database |
| Headers | CSP, HSTS, Referrer-Policy, Permissions-Policy, nosniff, frame-ancestors |
| Network | Cloudflare proxy (HTTPS only, TLS 1.2+, HSTS, Full-strict SSL for the academy hosts, rate limiting on sign-in pages). The server firewall (ufw) only accepts web traffic from Cloudflare; SSH is key-only |
| Server | Automatic security updates (unattended-upgrades) |
| Backups | Daily encrypted database backup at 02:30, kept 14 days on the server and emailed to the academy mailbox; weekly uploads archive kept 5 weeks |
| Monitoring | Cloudflare origin-down alerts; `/healthz` for uptime monitors; low-disk and security alerts by email |

## If something goes wrong

1. **Contain**: in Cloudflare, turn on *Under Attack Mode* (Security > Settings) or set `FORCE_MAINTENANCE` if the site itself is misbehaving.
2. **Rotate what may be exposed**
   - Admin passwords: Admin > Profile > Password (the emailed sign-in check then protects the account).
   - AI (Gemini) key: create a new key in Google AI Studio, paste it in Admin > Settings > AI, delete the old key.
   - Paystack keys: Paystack dashboard > Settings > API keys, then update `includes/paystack.php` on the server.
   - Gmail: Admin > Webmail > Settings > Disconnect, then Connect Gmail again (and remove the app at myaccount.google.com/permissions if needed).
   - Database password: change it in MariaDB and in `includes/private/config.php` on the server.
3. **Check**: Admin > Activity log, `/var/log/apache2/`, `/var/log/skopedigital-academy/php.log`, Cloudflare Security > Events.
4. **Restore** (see below) if data was damaged.
5. **Tell people** whose data was affected, and note what happened and what was changed.

## Restore a backup

On the server (backups in `/var/backups/sda`, passphrase in `/root/.sda-backup-pass`):

```bash
sudo -i
cd /var/backups/sda
gunzip -c sda-db-YYYYMMDD-HHMM.sql.gz | mysql sda
```

From the emailed copy (`.enc` attachment in the academy mailbox), on any machine with OpenSSL and the passphrase
(a copy is kept outside git at `includes/private/backup-passphrase.txt` on the admin PC):

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass file:backup-passphrase.txt -in sda-db-YYYYMMDD-HHMM.sql.gz.enc | gunzip > sda.sql
```

## Admin can't receive the sign-in email

Check Admin > Webmail > Settings (Gmail connected). If no admin can sign in, turn the check off on the server: `sudo mysql sda -e "INSERT INTO platform_settings (setting_key, setting_value) VALUES ('admin_2fa_required','0') ON DUPLICATE KEY UPDATE setting_value='0'"`, fix the mailbox, then set it back to `1`.

## Reporting a problem

Email info@skopedigital.co.ke with details. Please do not test against live learner data.
