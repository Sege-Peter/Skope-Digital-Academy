---
title: CSS Text and Fonts
module: 2
minutes: 14
---

Most of a website is text, so typography has a huge effect on how professional a page feels. CSS gives you control over the typeface, size, weight, spacing and alignment of text.

## Choosing a font with font-family

`font-family` takes a list of fonts separated by commas. The browser uses the first one it has, so the list acts as a set of fallbacks. Always end with a **generic family** that every device has.

```css
body { font-family: "Segoe UI", Roboto, Arial, sans-serif; }
```

| Generic family | Look | Examples |
|---|---|---|
| `serif` | small strokes at letter ends | Georgia, Times New Roman |
| `sans-serif` | clean, no strokes | Arial, Roboto, Helvetica |
| `monospace` | every letter the same width | Consolas, Courier New |
| `cursive` | handwriting style | Comic Sans MS |

Put quotation marks around font names that contain spaces.

## Size, weight and style

- `font-size` sets the size: `16px`, `1.25rem`, `120%`. You will learn about these units in the [Units](units) lesson.
- `font-weight` sets the thickness: `normal` (400), `bold` (700), or a number from 100 to 900.
- `font-style: italic;` slants the text.
- `line-height` sets the distance between lines. A unitless value such as `1.6` (1.6 times the font size) is easiest to read for paragraphs.

## Text properties

| Property | Example | Effect |
|---|---|---|
| `color` | `#333` | text colour |
| `text-align` | `left`, `center`, `right`, `justify` | horizontal alignment |
| `text-decoration` | `none`, `underline`, `line-through` | lines on text; `none` removes link underlines |
| `text-transform` | `uppercase`, `lowercase`, `capitalize` | changes letter case for display |
| `letter-spacing` | `2px` | space between letters |
| `word-spacing` | `4px` | space between words |
| `text-shadow` | `2px 2px 4px rgb(0 0 0 / 0.4)` | shadow behind text |

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body {
    font-family: Georgia, serif;
    color: #333;
    max-width: 600px;
    margin: 20px auto;
  }
  h1 {
    font-family: Arial, sans-serif;
    font-size: 32px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #b71c1c;
  }
  .byline {
    font-style: italic;
    color: #777;
  }
  p {
    font-size: 18px;
    line-height: 1.6;
  }
  .old-price {
    text-decoration: line-through;
    color: #999;
  }
</style>
</head>
<body>
  <h1>Safari Rally returns to Naivasha</h1>
  <p class="byline">By Otieno Mark, Nakuru</p>
  <p>Thousands of fans lined the roads around Lake Naivasha as drivers raced across dusty tracks and fesh-fesh sand.</p>
  <p>Ticket price: <span class="old-price">KSh 2,500</span> KSh 1,800</p>
</body>
</html>
```

## Using Google Fonts

Only a few fonts are installed on every device. To use a special font, load it from a font service. Google Fonts is free and popular.

1. Visit fonts.google.com and choose a font, for example *Poppins*.
2. Select the weights you need, then copy the `<link>` code it gives you.
3. Paste the link into your page's `<head>`, before your own styles.
4. Use the font name in `font-family`, with a fallback.

```example html
<!DOCTYPE html>
<html>
<head>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&family=Merriweather&display=swap" rel="stylesheet">
<style>
  h1 {
    font-family: "Poppins", Arial, sans-serif;
    font-weight: 700;
    color: #4a148c;
  }
  p {
    font-family: "Merriweather", Georgia, serif;
    line-height: 1.7;
  }
</style>
</head>
<body>
  <h1>Learn to Code in Kisumu</h1>
  <p>The heading uses Poppins and this paragraph uses Merriweather, both loaded from Google Fonts.</p>
</body>
</html>
```

> **Note:** `display=swap` in the link tells the browser to show the fallback font straight away and switch when the web font arrives. Visitors on slow connections can start reading immediately.

> **Tip:** Two font families per site is usually enough: one for headings and one for body text. Every extra font and weight adds to the download size.

## The font shorthand

`font` can set style, weight, size, line height and family in one line. Size and family are required and must come last, in that order:

```css
p { font: italic 700 18px/1.5 Arial, sans-serif; }
```

```exercise
Q: Remove the underline from links.
a { text-decoration: ___; }
A: none
```

```exercise
Q: Use Roboto, falling back to the generic family without serifs.
body { font-family: Roboto, ___; }
A: sans-serif
```
