---
title: Colour and Typography for the Web
module: 2
module_title: Look, Layout and Phones
minutes: 12
---

Two websites can have exactly the same content, yet one looks professional and the other looks messy. Most of the difference comes from **colour** and **typography** (the choice and arrangement of fonts). The good news: a few simple rules get you most of the way.

## Building a colour palette

Keep it simple. A typical small business site needs only:

| Role | How much to use | Baraka Bakery |
|---|---|---|
| Primary (brand) colour | Header, headings, key details | Warm brown `#7a3e1d` |
| Accent colour | Buttons and calls to action only | Deep green `#1e7b34` |
| Background | Most of the page | Cream `#fffaf3` |
| Text colour | Body text | Dark grey `#333333` |

A common guideline is roughly **60-30-10**: about 60% background/neutral, 30% primary, 10% accent. The accent stands out *because* it is used rarely.

> **Tip:** Start from the client's logo, shop sign or uniform colours. Tools like Coolors or Adobe Color can suggest matching shades, and Canva's palette generator can pull colours from a photo.

## Contrast: can people read it?

Text must stand out clearly from its background. Light grey text on white, or yellow on white, is hard to read, especially on a phone in bright Kisumu sunshine. The **WCAG** accessibility guidelines recommend a contrast ratio of at least **4.5:1** for normal body text. Free online "contrast checker" tools tell you the ratio for any two colours.

## Colour codes

| Format | Example | Note |
|---|---|---|
| Hex | `#7a3e1d` | Most common; `#` and six characters |
| RGB | `rgb(122, 62, 29)` | Red, green, blue from 0 to 255 |
| Named | `white`, `brown` | Quick, but limited choice |

Store your palette in **CSS variables** so you can change a colour everywhere at once:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Palette</title>
  <style>
    :root {
      --primary: #7a3e1d;
      --accent: #1e7b34;
      --bg: #fffaf3;
      --text: #333333;
    }
    body { background: var(--bg); color: var(--text); font-family: Georgia, serif; padding: 20px; }
    h1 { color: var(--primary); }
    .button { background: var(--accent); color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; }
    .bad { color: #dddddd; }
  </style>
</head>
<body>
  <h1>Baraka Bakery</h1>
  <p>This dark grey text is easy to read on cream.</p>
  <p class="bad">This very light grey text is hard to read. Avoid it.</p>
  <a class="button" href="#">Order now</a>
</body>
</html>
```

Try changing `--primary` to `#1d4e7a` and watch every heading change at once.

## Typography basics

- **Use at most two fonts**: one for headings, one for body text. Often one font in different weights is enough.
- **Serif** fonts (with small "feet", like Georgia) feel traditional or elegant. **Sans-serif** fonts (like Arial or Poppins) feel clean and modern, and are easy to read on screens.
- **Body text size:** around 16px or larger. Many Kenyan users browse on small phones.
- **Line height:** about 1.5 to 1.7 makes paragraphs comfortable to read.
- **Line length:** very long lines are tiring; limit text blocks to roughly 60–75 characters with `max-width`.
- **Hierarchy:** headings should be clearly bigger or bolder than body text, so visitors can scan.

## Using Google Fonts

Google Fonts offers free fonts you can link in the `<head>`:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fonts</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: "Poppins", Arial, sans-serif; font-size: 16px; line-height: 1.6; padding: 20px; max-width: 640px; }
    h1 { font-weight: 700; font-size: 2rem; margin-bottom: 4px; color: #7a3e1d; }
    .tagline { font-size: 1.1rem; color: #666666; margin-top: 0; }
  </style>
</head>
<body>
  <h1>Baraka Bakery</h1>
  <p class="tagline">Fresh bread and cakes in Kisumu</p>
  <p>Every loaf is mixed, shaped and baked by hand before sunrise. Order birthday and wedding cakes at least two days in advance.</p>
</body>
</html>
```

The list `"Poppins", Arial, sans-serif` is a **font stack**: if the first font fails to load, the browser tries the next.

> **Note:** Each extra font or weight adds download size. Two fonts with two weights each is plenty.

```exercise
Q: Complete the CSS that uses the variable named primary.
h1 { color: ___(--primary); }
A: var
H: Short for variable.
```

```exercise
Q: Fill in the recommended minimum contrast ratio for normal body text.
At least ___:1
A: 4.5
H: Four and a half.
```

## Practice task

Choose a palette (primary, accent, background, text) and two fonts for your business site. Put the colours in CSS variables, check your text and button colours with a free online contrast checker, and adjust any pair that scores below 4.5:1.
