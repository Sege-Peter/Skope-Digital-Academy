---
title: Two-Factor Authentication
module: 2
minutes: 11
---

Even a strong password can be stolen through a phishing page or a data breach. **Two-factor authentication (2FA)** – also called **two-step verification** or **multi-factor authentication (MFA)** – adds a second lock. A criminal who has your password still cannot get in without the second factor.

## The three types of factor

| Factor | Meaning | Examples |
|---|---|---|
| Something you **know** | A secret in your head | Password, PIN |
| Something you **have** | An object you carry | Your phone, a security key |
| Something you **are** | A part of your body | Fingerprint, face |

2FA combines two **different** types. A password plus a code sent to your phone is 2FA (know + have). Two passwords would not be, because both are things you know.

## Common 2FA methods

| Method | How it works | Strength |
|---|---|---|
| SMS code | A 6-digit code is texted to your phone | Better than nothing, but weak against SIM-swap fraud and phishing |
| Authenticator app | An app (Google Authenticator, Microsoft Authenticator and others) shows a code that changes every 30 seconds | Stronger – works even without network and is not tied to your SIM |
| Push prompt | Your phone asks "Is this you trying to sign in?" | Convenient; only approve if **you** just tried to sign in |
| Passkey or security key | Your phone or a USB key proves who you are, often with fingerprint or face | Strongest; resists phishing |
| Backup codes | A list of one-time codes to use if you lose your phone | Essential for recovery |

> **Tip:** Where a service offers an authenticator app or passkey, choose it over SMS. That way a SIM swap alone cannot unlock your account.

## Turning it on

The exact menus change often, but the general paths are:

- **Google / Gmail:** Google Account › **Security** › **2-Step Verification**.
- **WhatsApp:** **Settings** › **Account** › **Two-step verification** (sets a 6-digit PIN).
- **Facebook and Instagram:** **Settings** › **Accounts Centre** › **Password and security** › **Two-factor authentication**.
- **Banking and M-Pesa apps:** use the app's own PIN, fingerprint or face unlock, and never share their codes.

When you turn on 2FA, the service usually gives you **backup codes**. Print or write them down and keep them somewhere safe at home, or store them in your password manager. Without them, losing your phone can lock you out for a long time.

## Never share your codes

The whole point of 2FA is that only **you** have the second factor. Fraudsters know this, so a very common trick is:

```text
Caller: "Hello, this is customer care. We are upgrading your account.
         You will receive a 6-digit code. Please read it to me so we
         can confirm it's you."
```

The code was actually triggered by the criminal trying to log in to your account. If you read it out, you hand them the key.

> **Warning:** A real company will **never** ask you to read out or forward a verification code. Messages that send these codes often say so in the SMS itself – read it.

## Push fatigue

If your phone keeps asking "Is this you signing in?" and you did not try to sign in, **someone has your password**. Tap **No**, then change your password immediately. Do not approve just to make the prompts stop.

```exercise
Q: Name the factor type.
A fingerprint is something you ___.
A password is something you ___.
Your phone is something you ___.
A: are | know | have
```

```exercise
Q: Which 2FA method is weaker against SIM-swap fraud?
___ codes
A: SMS / text
```

## Practice task

Turn on 2FA for your main email account and for WhatsApp today. If possible, use an authenticator app or passkey for your email. Save the backup codes somewhere safe and tell no one where they are.
