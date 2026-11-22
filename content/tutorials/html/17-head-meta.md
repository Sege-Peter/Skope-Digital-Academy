---
title: Head, Meta Tags and SEO Basics
module: 5
minutes: 10
---

The `<head>` section is invisible on the page, but it has a big effect on how your site appears in browser tabs, on phones, in Google results and when someone shares a link on WhatsApp or Facebook.

## A well-equipped head

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fresh Fish Delivery in Kisumu | Dunga Fresh</title>
  <meta name="description" content="Order fresh tilapia and omena from Lake Victoria, delivered to your door in Kisumu within two hours.">
  <style>
    body { font-family: sans-serif; padding: 16px; }
  </style>
</head>
<body>
  <h1>Dunga Fresh</h1>
  <p>Look at the tab: the title comes from the head.</p>
</body>
</html>
```

Let us go through each part.

## Character encoding

```html
<meta charset="UTF-8">
```

This tells the browser how the bytes of your file map to characters. UTF-8 covers every language and symbol, including Kiswahili, Amharic, Arabic and emojis. Put it as the first element in the head.

## The viewport

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

Without this line, phones pretend to be a wide desktop screen and shrink the whole page so the text becomes tiny. With it, the page uses the real width of the device. Since most people in Kenya browse on phones, never leave it out.

## The title

The `<title>` appears on the browser tab, in bookmarks, and as the clickable blue headline in search results.

Good titles:

- Are unique for every page on your site
- Put the most important words first
- Stay under about 60 characters so they are not cut off in search results
- Often end with the site or brand name, e.g. `Contact Us | Skope Digital Academy`

## The meta description

```html
<meta name="description" content="...">
```

Search engines often show this text under your title in results. It does not directly raise your ranking, but a clear, inviting description makes people more likely to click. Aim for about 150 characters that summarise the page honestly.

## Linking CSS, icons and scripts

| Code | Purpose |
|---|---|
| `<link rel="stylesheet" href="styles.css">` | Load an external CSS file |
| `<link rel="icon" href="favicon.png">` | The small icon on the browser tab |
| `<script src="app.js" defer></script>` | Load JavaScript; `defer` runs it after the HTML is read |
| `<style> ... </style>` | CSS written inside the page |

## Social sharing tags

When someone pastes your link into WhatsApp, Facebook, LinkedIn or X, those apps read **Open Graph** tags to build the preview card.

```html
<meta property="og:title" content="Dunga Fresh - Fish Delivery in Kisumu">
<meta property="og:description" content="Fresh tilapia and omena delivered in two hours.">
<meta property="og:image" content="https://www.example.co.ke/images/share.jpg">
<meta property="og:url" content="https://www.example.co.ke/">
<meta property="og:type" content="website">
```

The image should be an absolute URL and roughly 1200 by 630 pixels.

## Other useful meta tags

```example html
<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Karibu | Duka la Mama Atieno</title>
  <meta name="description" content="Duka la vyakula vya nyumbani mjini Kisumu.">
  <meta name="author" content="Atieno Odhiambo">
  <meta name="theme-color" content="#2a9d8f">
  <link rel="canonical" href="https://www.example.co.ke/">
</head>
<body>
  <h1>Karibu kwa Duka la Mama Atieno</h1>
  <p>Ukurasa huu umeandikwa kwa Kiswahili, kwa hivyo tumetumia lang="sw".</p>
</body>
</html>
```

- `author` names the person who wrote the page.
- `theme-color` tints the browser's address bar on some phones.
- `canonical` tells search engines which address is the "official" one if the same page can be reached at several URLs.
- `robots` with `content="noindex"` asks search engines not to list a page, useful for thank-you or admin pages.

> **Note:** You may see `<meta name="keywords">` in older pages. Major search engines ignore it today, so there is no need to add it.

## SEO basics in HTML

**SEO** (search engine optimisation) means helping search engines understand and show your pages. Most of it comes down to the good HTML habits you have already learned:

1. A unique, descriptive `<title>` and meta description on every page
2. One clear `<h1>` and a logical heading order
3. Meaningful link text instead of "click here"
4. Descriptive `alt` text on images
5. Semantic elements like `<main>`, `<nav>` and `<article>`
6. The correct `lang` on the `<html>` element
7. Fast pages: small images and little unnecessary code
8. Mobile friendly: the viewport meta tag and responsive images

> **Tip:** Write for people first. Pages stuffed with repeated keywords read badly and can actually be penalised. Clear, useful, well-structured content is what ranks best over time.

```exercise
Q: Complete the tag that makes the page fit phone screens.
<meta name="___" content="width=device-width, initial-scale=1.0">
A: viewport
H: It refers to the visible area of the screen.
```

```exercise
Q: Add a short summary that search engines can show under the title.
<meta name="___" content="Affordable computer classes in Bungoma.">
A: description
H: It describes the page.
```
