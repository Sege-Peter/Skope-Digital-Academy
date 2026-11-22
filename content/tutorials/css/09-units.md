---
title: CSS Units
module: 2
minutes: 12
---

Many CSS properties need a length: widths, font sizes, margins, padding. A length is a number followed by a unit, such as `16px` or `2rem`. Choosing the right unit is what makes a design flexible on different screens.

> **Note:** There is no space between the number and the unit. `16 px` is invalid. The only length that can be written without a unit is `0`.

## Absolute units: px

A pixel (`px`) is a fixed size. `border: 1px solid` is always a thin line, and `width: 200px` is always 200 pixels. Pixels are perfect for things that should not scale, like borders and small shadows.

Other absolute units exist (`cm`, `mm`, `in`, `pt`) but are mainly used for printed pages.

## Percentages

A percentage is relative to something else, usually the **parent element**. `width: 50%` means half the width of the parent. This makes percentages great for fluid layouts that stretch and shrink with the screen.

## em and rem

Both are relative to a font size:

| Unit | Relative to | Example |
|---|---|---|
| `em` | the font size of the element itself (or its parent when used on `font-size`) | `padding: 1em` equals the text size |
| `rem` | the font size of the root `<html>` element (16px by default) | `2rem` = 32px by default |

`rem` is the safer choice for font sizes, because it always refers to the same root value. With `em`, sizes multiply when elements are nested: a list at `1.2em` inside another list at `1.2em` ends up 1.44 times bigger.

`em` is useful when spacing should grow with the text, such as padding on a button.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .btn {
    display: inline-block;
    padding: 0.5em 1.2em;
    background-color: #00695c;
    color: white;
    border-radius: 0.3em;
    margin: 4px;
  }
  .small { font-size: 0.8rem; }
  .medium { font-size: 1rem; }
  .large { font-size: 1.6rem; }

  .nest { font-size: 1.2em; }
</style>
</head>
<body>
  <p>Padding in em grows with the text:</p>
  <span class="btn small">Small</span>
  <span class="btn medium">Medium</span>
  <span class="btn large">Large</span>

  <p>Nested em sizes multiply:</p>
  <div class="nest">Level 1
    <div class="nest">Level 2
      <div class="nest">Level 3</div>
    </div>
  </div>
</body>
</html>
```

> **Tip:** Using `rem` for font sizes respects visitors who increase the default text size in their browser settings. Pixel font sizes ignore that preference in some browsers.

## Viewport units: vw and vh

Viewport units are relative to the browser window (the viewport):

- `1vw` is 1% of the viewport width.
- `1vh` is 1% of the viewport height.
- `100vh` is the full height of the window, which is handy for full-screen sections.

On mobile browsers the address bar can appear and disappear, which changes the real height. The newer `svh`, `lvh` and `dvh` units handle this; `100dvh` follows the visible height as it changes.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  .hero {
    min-height: 60vh;
    background: linear-gradient(135deg, #1a237e, #00838f);
    color: white;
    padding: 5vw;
  }
  .hero h1 {
    font-size: clamp(1.5rem, 6vw, 3.5rem);
    margin: 0;
  }
  .half {
    width: 50%;
    background-color: #ffe0b2;
    padding: 10px;
  }
</style>
</head>
<body>
  <div class="hero">
    <h1>Explore Lamu Old Town</h1>
    <p>Resize the window: the heading grows and shrinks with its width.</p>
  </div>
  <div class="half">I am always 50% of my parent's width.</div>
</body>
</html>
```

The heading uses `clamp(min, preferred, max)`. It follows `6vw` but never goes below `1.5rem` or above `3.5rem`, so it stays readable on tiny phones and does not become huge on wide monitors.

## Which unit when?

| Use | Good unit |
|---|---|
| Borders, thin lines, shadows | `px` |
| Font sizes | `rem` |
| Padding that should follow the text size | `em` |
| Column widths in a fluid layout | `%` or `fr` (see [Grid](grid)) |
| Full-screen sections | `vh` / `dvh` |
| Text that scales with the screen | `clamp()` with `vw` |

```exercise
Q: The root font size is 16px. Make headings 32px using the root-relative unit.
h1 { font-size: 2___; }
A: rem
```

```exercise
Q: Make the section exactly as tall as the browser window.
.intro { height: 100___; }
A: vh / dvh / svh
```
