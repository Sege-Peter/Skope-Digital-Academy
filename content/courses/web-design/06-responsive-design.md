---
title: Responsive Design for Phones
module: 2
minutes: 13
---

Most Kenyans who visit a small business website do so on a smartphone, often a budget Android phone on mobile data. A **responsive** website automatically adapts its layout to the screen size, so it is comfortable to use on a phone, a tablet or a laptop.

## The three ingredients

1. **The viewport meta tag**, which tells phones not to shrink the page:

```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

2. **Flexible sizes**: percentages, `max-width`, `rem` and Flexbox wrapping instead of fixed widths like `width: 1200px`.
3. **Media queries**: CSS rules that apply only when the screen is a certain size.

## Media queries

A media query wraps rules that should only apply under a condition:

```css
@media (min-width: 700px) {
  nav { display: flex; }
}
```

This means: "when the screen is **at least** 700px wide, apply these rules".

## Mobile first

The recommended approach is **mobile first**: write the basic styles for small screens, then use `min-width` media queries to add or change things for bigger screens. Phones get the simplest, lightest CSS, and you are forced to focus on the most important content.

## A responsive home page

Run this example, then use the editor to resize the result area (or open it on your phone). On a narrow screen the menu stacks under the name and the cards are in one column; on a wider screen the header goes into one row and the cards sit side by side.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baraka Bakery – Responsive</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #fffaf3; }
    img { max-width: 100%; height: auto; }

    /* Base styles: phones */
    header { background: #7a3e1d; color: #fff; padding: 12px 16px; text-align: center; }
    .logo { font-size: 22px; font-weight: bold; }
    nav { display: flex; justify-content: center; gap: 16px; margin-top: 6px; }
    nav a { color: #ffe0b2; text-decoration: none; padding: 8px 4px; }
    .hero { padding: 32px 16px; text-align: center; background: #fde7c9; }
    .hero h1 { font-size: 26px; margin: 0 0 12px; }
    .button { display: inline-block; background: #1e7b34; color: #fff; padding: 14px 22px;
              border-radius: 6px; text-decoration: none; font-weight: bold; }
    .cards { display: flex; flex-direction: column; gap: 12px; padding: 16px; }
    .card { background: #fff; border: 1px solid #ecd9c3; border-radius: 8px; padding: 16px; }

    /* Tablets and laptops */
    @media (min-width: 700px) {
      header { display: flex; justify-content: space-between; align-items: center; text-align: left; padding: 12px 32px; }
      nav { margin-top: 0; }
      .hero { padding: 64px 32px; }
      .hero h1 { font-size: 40px; }
      .cards { flex-direction: row; padding: 32px; }
      .card { flex: 1; }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">Baraka Bakery</div>
    <nav><a href="#menu">Menu</a><a href="#about">About</a><a href="#contact">Contact</a></nav>
  </header>
  <section class="hero">
    <h1>Fresh bread and cakes, every morning</h1>
    <a class="button" href="https://wa.me/254700000000">Order on WhatsApp</a>
  </section>
  <section class="cards" id="menu">
    <div class="card"><h3>Bread</h3><p>Baked before sunrise.</p></div>
    <div class="card"><h3>Mandazi</h3><p>Soft and lightly sweet.</p></div>
    <div class="card"><h3>Cakes</h3><p>For birthdays and weddings.</p></div>
  </section>
</body>
</html>
```

## Designing for thumbs

Phone users tap with their thumbs, so:

- Make buttons and links **big enough to tap**, around 44–48px tall, with space between them.
- Keep the main call-to-action near the top and repeat it lower down.
- Use **click-to-call** (`tel:`) and **WhatsApp** links; typing a number is annoying on a phone.
- Avoid content that only appears on mouse hover; phones have no hover.
- Keep body text at least 16px.

## Testing on real screens

- In Chrome, press **F12** (or right-click, **Inspect**) and click the phone-and-tablet icon to switch to **device mode**. Try several phone sizes.
- Test on at least one real, inexpensive Android phone if you can. Real devices reveal problems emulators miss.
- Check that nothing scrolls sideways. Horizontal scrolling on a phone usually means something has a fixed width that is too wide.

> **Tip:** `img { max-width: 100%; height: auto; }` is one of the most useful lines in CSS. It stops large images from breaking your layout on small screens.

```exercise
Q: Complete the media query so the rules apply on screens at least 700px wide.
@media (___: 700px) { ... }
A: min-width
H: "At least" means a minimum.
```

```exercise
Q: Fill in the name of the approach that styles phones first, then adds rules for bigger screens.
___ first
A: mobile
H: Think of the small screen in your pocket.
```

## Practice task

Make your business site responsive using the mobile-first approach: set base styles for phones, then add one `min-width` media query for larger screens. Test it in Chrome's device mode at three sizes (a small phone, a tablet and a laptop) and fix anything that scrolls sideways or is too small to tap.
