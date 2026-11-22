---
title: Typography Basics
module: 2
module_title: Type, Layout and Canva
minutes: 14
---

**Typography** is the art of choosing and arranging text. Most designs are mainly words – prices, dates, names, phone numbers – so typography decides whether people can read your design quickly or give up.

## Font vs typeface

People use "font" and "typeface" loosely. Strictly, a **typeface** is the design family (for example Montserrat) and a **font** is one style of it (Montserrat Bold). In everyday work, "font" is fine.

## The main font categories

| Category | Look | Feels | Good for |
|---|---|---|---|
| **Serif** | Small "feet" on the letters (e.g. Times New Roman, Merriweather) | Traditional, serious, trustworthy | Law firms, books, formal invitations |
| **Sans-serif** | No feet (e.g. Arial, Montserrat, Poppins) | Clean, modern, friendly | Most social media, websites, posters |
| **Script** | Like handwriting | Elegant or personal | Wedding cards, beauty brands – short words only |
| **Display** | Decorative, unusual | Fun, loud | Big headlines only |

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { padding: 16px; }
  p { font-size: 22px; margin: 10px 0; }
  .serif { font-family: Georgia, "Times New Roman", serif; }
  .sans  { font-family: Arial, Helvetica, sans-serif; }
  .script { font-family: "Brush Script MT", cursive; }
  .mono  { font-family: "Courier New", monospace; }
</style>
</head>
<body>
  <p class="serif">Serif: Karibu Kisumu Bookshop</p>
  <p class="sans">Sans-serif: Karibu Kisumu Bookshop</p>
  <p class="script">Script: Karibu Kisumu Bookshop</p>
  <p class="mono">Monospace: Karibu Kisumu Bookshop</p>
</body>
</html>
```

## Rules for beginners

1. **Use a maximum of two fonts** in one design: one for headings, one for body text. Many great designs use only one font in different weights.
2. **Pair contrasting fonts**: a bold sans-serif heading with a simple body font, or a serif heading with a sans-serif body.
3. **Never write long text in script or display fonts.** They are hard to read.
4. **Avoid ALL CAPITALS for long sentences.** Capitals are fine for short headings.
5. **Size for the medium.** Text that looks fine on a laptop may be too small on a phone or a poster across the road.

## Size, weight and spacing

Typography hierarchy comes from:

- **Size** – the headline is much bigger than body text.
- **Weight** – bold, regular, light.
- **Spacing** – space between lines (**line height** or leading) and between letters (**letter spacing** or tracking).

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; padding: 16px; max-width: 360px; }
  h1 { font-size: 34px; margin: 0; letter-spacing: 1px; }
  .sub { font-size: 18px; font-weight: bold; color: #c0392b; margin: 6px 0 14px; }
  .body { font-size: 15px; line-height: 1.6; color: #333; }
</style>
</head>
<body>
  <h1>BACK TO SCHOOL</h1>
  <p class="sub">Uniforms from all Thika schools</p>
  <p class="body">Sweaters, shirts, skirts and trousers in all sizes. Visit us near the main stage or order on WhatsApp for delivery.</p>
</body>
</html>
```

Try changing `line-height: 1.6` to `1` in the editor. The paragraph becomes cramped and harder to read.

## Where to get fonts

Canva includes many fonts. For other tools, **Google Fonts** offers free fonts that can be used in commercial projects (check each font's licence). Do not download "free" fonts from random sites – they may not allow commercial use or may contain malware.

```exercise
Q: Name the font categories.
Letters with small "feet" are ___ fonts. Letters without feet are ___ fonts.
A: serif | sans-serif / sans serif / sans
```

```exercise
Q: Complete the rule.
Use a maximum of ___ fonts in one design.
A: two / 2
```

## Practice task

Design (on paper or in Canva) the same short announcement twice – "Kiprono's Barbershop, now open Sundays, 9am to 6pm" – once with good typography (maximum two fonts, clear hierarchy, readable sizes) and once breaking the rules. Show both to a friend and ask which one they can read faster.
