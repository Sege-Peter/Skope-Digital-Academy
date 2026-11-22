---
title: Publishing Your Website
module: 4
module_title: Going Live and Getting Clients
minutes: 12
---

Your Baraka Bakery site works on your computer. Now it needs to go **live**, so that anyone in Kisumu (or anywhere) can open it. This lesson explains the pieces involved in plain language: domains, hosting and the free options for practice sites.

## Three pieces of a live website

| Piece | What it is | Everyday comparison |
|---|---|---|
| Website files | Your HTML, CSS, images | The goods in a shop |
| Hosting | A server (always-on computer) that stores the files and sends them to visitors | The building you rent for the shop |
| Domain name | The address people type, such as `barakabakery.co.ke` | The shop's street address and sign |

You can have hosting without a domain (the host gives you a free address), but a business usually wants its own domain.

## Domain names

- Domains are **rented**, usually per year, from a **registrar**. Ending in `.co.ke`, `.ke` or `.com` are common choices for Kenyan businesses.
- `.ke` domains are managed by KENIC (the Kenya Network Information Centre), which lists accredited registrars on its website.
- Prices vary by registrar and change over time, so check the current price and, importantly, the **renewal** price before buying.

> **Warning:** Register the client's domain **in the client's name**, with their email address, and give them the login details. Keeping a client's domain in your own account can lead to serious disputes later. Also remind them to renew it every year; an expired domain takes the site and often the email offline.

## Hosting options

| Option | Good for | Notes |
|---|---|---|
| Free static hosting (for example GitHub Pages, Netlify, Cloudflare Pages) | Portfolio sites and simple HTML/CSS sites | Free tiers have limits; read their current terms |
| Shared hosting (often with cPanel) | Small business sites, sites with PHP forms or WordPress | Paid monthly or yearly; many Kenyan hosts offer it with local payment options |
| Website builders (Wix, Google Sites and similar) | Clients who want to edit everything themselves | Less design freedom; some features cost extra |

For your practice project, a free static host is perfect.

## Publishing a static site for free: the general steps

1. **Check your files.** The home page must be called `index.html`. Keep images in a folder such as `images/` and use relative paths (`images/cake.webp`, not `C:\Users\...`).
2. **Create an account** with a free static host.
3. **Upload** your folder. Most hosts let you drag and drop a folder, or connect a GitHub repository so the site updates whenever you push changes.
4. **Open the address** you are given, something like `yourname.example-host.app`.
5. **Test everything** on a real phone: links, WhatsApp button, form, images.

## Pre-launch checklist

```text
[ ] Every page has a clear <title> and the viewport meta tag
[ ] Phone number, WhatsApp link, email and location are correct (ask the client to confirm)
[ ] All links work; no "page not found" errors
[ ] Images are compressed and have alt text
[ ] Looks good on a small phone and on a laptop
[ ] Spelling checked, including the business name
[ ] Favicon (small tab icon) added
[ ] Form sends correctly, or links to WhatsApp correctly
```

## Being found on Google

A new site will not appear on Google immediately. You can help:

- Write a clear `<title>` and a meta description for each page:

```html
<title>Baraka Bakery – Fresh Bread and Cakes in Kisumu</title>
<meta name="description" content="Family bakery near Jubilee Market, Kisumu. Fresh bread daily and birthday cakes to order. Order on WhatsApp.">
```

- Mention the town and services naturally in your headings and text.
- Set up a free **Google Business Profile** for the client, with the website link. For local businesses, this often brings more visitors than the website itself.
- Add the site link to the business's Facebook, Instagram and WhatsApp Business profiles.

## After launch

Websites need small amounts of care: updating prices and photos, renewing the domain and hosting, and checking that the contact details still work. This ongoing care is a service you can offer, which we cover in the next lesson.

```exercise
Q: Complete the file name that a host opens as the home page.
___.html
A: index
H: It's the "index" of the site.
```

```exercise
Q: Fill in the word for the always-on computer service that stores your website files.
Domain = address, ___ = the rented space
A: hosting
H: The host "hosts" your files.
```

## Practice task

Publish your business site on a free static host. Open the live address on your phone and a friend's phone, go through the pre-launch checklist, and fix any problems. Write a meta description for your home page that mentions the business, the town and the main service.
