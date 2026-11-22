---
title: Google Analytics Concepts
module: 3
minutes: 15
---

Google Ads tells you what happened **before** and **at** the click. **Google Analytics** tells you what happened **after**: who visited the website, where they came from and what they did. The current version is **Google Analytics 4 (GA4)**. This lesson explains the core ideas so that the reports make sense.

## How it collects data

A business adds a small piece of tracking code (a "tag") to its website, either directly or through a tool such as Google Tag Manager. When someone visits, the tag sends information about their activity to the Analytics account. GA4 records activity as **events** – for example a page view, a scroll, a click or a form submission.

> **Note:** Analytics data is never perfectly complete. Ad blockers, privacy settings and cookie consent choices mean some visits are not recorded. Treat the numbers as a strong guide, not an exact count.

## Users and sessions

| Term | Meaning | Example |
|---|---|---|
| **User** | A person (more precisely, a browser or device) that visited | Achieng visits from her phone |
| **New user** | A user visiting for the first time in the measured period | Her first visit ever |
| **Session** | One visit: a group of activity within a period of time | She browses for 4 minutes, then leaves |
| **Event** | A single action | She views the price page, then taps "WhatsApp us" |

One user can have many sessions. If Achieng visits on Monday and again on Thursday, that is **1 user** and **2 sessions**.

## Engagement

GA4 counts an **engaged session** when a visit lasts a minimum amount of time, has a key event, or includes more than one page view (Google's help pages describe the exact rules). The **engagement rate** is the share of sessions that were engaged. A low engagement rate on an ad landing page may mean the page does not match the ad, or loads slowly.

## Traffic sources

Analytics shows **where** visitors came from. Common labels:

| Source / medium | Meaning |
|---|---|
| google / organic | Unpaid Google search results |
| google / cpc | Google Ads clicks |
| (direct) / (none) | Typed the address, bookmark, or source unknown |
| facebook.com / referral | Clicked a link on another website |
| Email, social | Visits from email campaigns or social media |

You can label your own links with **UTM parameters** so Analytics knows exactly which campaign sent the visitor:

```text
https://yourbusiness.co.ke/offer?utm_source=facebook&utm_medium=social&utm_campaign=back_to_school
```

```exercise
Q: Complete the UTM link labels for a WhatsApp broadcast campaign.
?utm_source=whatsapp&utm_medium=broadcast&___=december_offer
A: utm_campaign
H: The three common ones are utm_source, utm_medium and one that names the campaign.
```

## Conversions and key events

A **conversion** is a valuable action: an enquiry form sent, a WhatsApp button tapped, a call made, a purchase completed. In GA4, you mark the events that matter to the business as **key events** (older material calls these "conversions"; Google has used both terms). In Google Ads, the conversions you import or set up are what conversion-focused bidding strategies use to learn.

Without conversion tracking, you only know that people clicked – not whether the ads produced customers.

```exercise
Q: Achieng visited a website on Monday, Wednesday and Saturday from the same phone. How many users and sessions does that represent?
Users: ___   Sessions: ___
A: 1 | 3
```

## Privacy and consent

Analytics data is about real people. Businesses should tell visitors what data is collected, use it responsibly, and follow Kenya's Data Protection Act (guidance at odpc.go.ke) and Google's own policies. Never try to identify individual visitors or collect personal details such as phone numbers through Analytics.

## Practice task

Make three UTM-tagged links for a practice business: one for a Facebook post, one for a WhatsApp broadcast and one for an email newsletter. Use lowercase words and underscores or hyphens instead of spaces. Then list three actions on its website that should be tracked as key events, and explain why each matters.
