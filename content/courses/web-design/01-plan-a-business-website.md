---
title: Plan a Website for a Local Business
module: 1
module_title: Planning and Building Pages
minutes: 11
---

In this course you will design a complete website for a small business, step by step. Our example client is **Baraka Bakery**, a family bakery near Kisumu's Jubilee Market run by Mama Akinyi. You can follow along with Baraka Bakery or choose a real business near you: a salon, a boda boda SACCO, a cyber café or a tailor.

Good web design starts with planning, not with code.

## Step 1: Understand the business

Sit with the owner (in person or on a call) and ask:

| Question | Baraka Bakery's answer |
|---|---|
| What do you sell? | Bread, cakes, mandazi, birthday and wedding cakes to order |
| Who are your customers? | Families nearby, office workers, people planning events |
| What should a visitor do on the site? | Call or WhatsApp to order a cake |
| What do customers often ask? | Prices, opening hours, location, delivery |
| What makes you different? | Baked fresh every morning since 2015 |

## Step 2: Set one main goal

A small business website usually has **one main goal**, called the **call to action**. For Baraka Bakery it is: *get people to order a cake on WhatsApp*. Every page should make that action easy to find.

> **Tip:** If the owner says "I want everything", ask: "If the site could only do one thing for you, what would it be?" Design around that answer.

## Step 3: Plan the pages

Small businesses rarely need more than four or five pages. Draw a **sitemap**, a simple map of the pages:

```text
Home
 ├── Menu & Prices
 ├── Celebration Cakes (gallery + order)
 ├── About Us
 └── Contact (map, hours, WhatsApp, form)
```

For very small businesses, a **one-page site** with sections (Menu, About, Contact) is often enough and is cheaper for the client.

## Step 4: Collect the content

Content is the words, photos, prices and contact details. Missing content is the number one reason web projects get delayed. Make a checklist and send it to the client early:

```text
[ ] Logo (or business name if no logo)
[ ] 5–10 clear photos of products and the shop
[ ] Price list
[ ] Opening hours
[ ] Phone, WhatsApp number, email, location / Google Maps link
[ ] A short "About us" story (2–3 sentences)
[ ] Social media links
```

> **Warning:** Do not copy photos from Google Images or other businesses' websites. They usually belong to someone else. Use the client's own photos, take new ones with their permission, or use free stock photo sites that allow commercial use.

## Step 5: Sketch the home page

Sketch the phone version of the home page on paper before writing any code:

```text
+------------------------+
| BARAKA BAKERY   [Menu] |
+------------------------+
| [ big photo of cakes ] |
| Fresh bread & cakes    |
| baked daily in Kisumu  |
| [Order on WhatsApp]    |
+------------------------+
| 3 favourite products   |
+------------------------+
| Hours & location       |
+------------------------+
| Footer: phone, socials |
+------------------------+
```

## A first look in code

Here is a very first draft of the home page so you can see where we are going. You will understand every line by the end of this module.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baraka Bakery – Fresh Bread and Cakes in Kisumu</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; text-align: center; }
    header { background: #7a3e1d; color: white; padding: 20px; }
    .button { display: inline-block; background: #1e7b34; color: white;
              padding: 12px 20px; border-radius: 6px; text-decoration: none; }
  </style>
</head>
<body>
  <header>
    <h1>Baraka Bakery</h1>
    <p>Fresh bread and cakes, baked every morning in Kisumu</p>
  </header>
  <p>Planning a birthday or wedding? We bake cakes to order.</p>
  <a class="button" href="https://wa.me/254700000000">Order on WhatsApp</a>
</body>
</html>
```

```exercise
Q: Fill in the term for the one main action you want visitors to take.
Every page should have a clear call to ___.
A: action
H: It "calls" the visitor to do something.
```

```exercise
Q: Complete the name of a simple map of all the pages on a website.
Draw a ___ before you build.
A: sitemap / site map
H: Site + map.
```

## Practice task

Choose your project business (Baraka Bakery or a real one near you). Write its answers to the five questions in Step 1, decide its one main goal, draw a sitemap and sketch the phone version of the home page. Keep these; you will build this site throughout the course.
