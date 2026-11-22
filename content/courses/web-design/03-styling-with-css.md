---
title: Styling with CSS
module: 1
minutes: 13
---

If HTML is the skeleton, **CSS** (Cascading Style Sheets) is the clothing: colours, fonts, spacing, borders and layout. In this lesson you will turn the plain Baraka Bakery page into something that looks like a real business website.

## How a CSS rule works

```css
h1 {
  color: #7a3e1d;
  font-size: 32px;
}
```

- `h1` is the **selector**: which elements to style.
- `color` and `font-size` are **properties**.
- `#7a3e1d` and `32px` are **values**.
- Each declaration ends with a semicolon `;`, and the rule is wrapped in curly braces.

## Where to put CSS

For learning, we put CSS inside a `<style>` element in the `<head>`. On a real multi-page site you would move it to a separate file, such as `style.css`, and link it on every page with `<link rel="stylesheet" href="style.css">`, so one change updates the whole site.

## Selectors you will use most

| Selector | Example | Styles |
|---|---|---|
| Element | `p` | Every paragraph |
| Class | `.card` | Every element with `class="card"` |
| ID | `#contact` | The one element with `id="contact"` |
| Descendant | `nav a` | Links inside the `nav` |
| Hover state | `a:hover` | Links when the mouse is over them |

Use **classes** for most styling. They can be reused on many elements.

## The box model

Every element is a box with four layers:

| Layer | What it is |
|---|---|
| Content | The text or image itself |
| Padding | Space *inside* the box, around the content |
| Border | A line around the padding |
| Margin | Space *outside* the box, between it and its neighbours |

Adding `box-sizing: border-box;` to everything makes widths easier to work with, because padding and border are included in the width you set.

## Styling the bakery page

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baraka Bakery</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      color: #333333;
      background: #fffaf3;
      line-height: 1.6;
    }
    header {
      background: #7a3e1d;
      color: #ffffff;
      padding: 16px 20px;
    }
    header h1 { margin: 0; font-size: 26px; }
    nav a { color: #ffe0b2; margin-right: 12px; text-decoration: none; }
    nav a:hover { text-decoration: underline; }
    section { padding: 24px 20px; }
    .hero { background: #fde7c9; text-align: center; }
    .card {
      background: #ffffff;
      border: 1px solid #ecd9c3;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 12px;
    }
    .card h3 { margin-top: 0; color: #7a3e1d; }
    .price { font-weight: bold; color: #1e7b34; }
    .button {
      display: inline-block;
      background: #1e7b34;
      color: #ffffff;
      padding: 12px 20px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
    }
    .button:hover { background: #155d27; }
  </style>
</head>
<body>
  <header>
    <h1>Baraka Bakery</h1>
    <nav><a href="#menu">Menu</a><a href="#contact">Contact</a></nav>
  </header>
  <section class="hero">
    <h2>Fresh bread and cakes, baked every morning</h2>
    <a class="button" href="https://wa.me/254700000000">Order on WhatsApp</a>
  </section>
  <section id="menu">
    <h2>Our favourites</h2>
    <article class="card">
      <h3>White bread (400g)</h3>
      <p>Soft and fresh, ready by 6 a.m.</p>
      <p class="price">KES 65</p>
    </article>
    <article class="card">
      <h3>Birthday cake (1kg)</h3>
      <p>Vanilla, chocolate or red velvet.</p>
      <p class="price">From KES 1,500</p>
    </article>
  </section>
</body>
</html>
```

> **Note:** The prices here are example figures for practice. On a client's site, always use the prices they give you and ask them to confirm.

## The "cascade"

When two rules style the same thing, CSS decides which wins:

1. A more **specific** selector wins (an ID beats a class, a class beats an element).
2. If they are equally specific, the rule written **later** wins.

If a style "isn't working", it is usually being overridden by another rule. Right-click the element in Chrome, choose **Inspect**, and the Styles panel shows which rules apply and which are crossed out.

## Units

- `px` – fixed pixels; good for borders and small details.
- `%` – a percentage of the parent's width.
- `rem` – relative to the base font size; great for text and spacing that scales.

```exercise
Q: Complete the CSS so all elements with class "card" get a white background.
___card { background: #ffffff; }
A: .
H: Class selectors start with a full stop.
```

```exercise
Q: Fill in the property that adds space inside a box, around its content.
.card { ___: 16px; }
A: padding
H: Margin is outside; this one is inside.
```

## Practice task

Add a `<style>` block to your own business's `index.html`. Style the header, the hero section with a clear call-to-action button, and the product or service cards. Use the browser's **Inspect** tool at least once to check which CSS rules are applied to your button.
