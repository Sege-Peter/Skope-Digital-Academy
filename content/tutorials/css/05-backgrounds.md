---
title: CSS Backgrounds
module: 2
minutes: 11
---

Every element has a background area that sits behind its content and padding. CSS can fill it with a colour, an image, a gradient, or a combination of these.

## background-color

The simplest background is a solid colour. You met it in the [Colours](colours) lesson.

```css
body { background-color: #fafafa; }
.alert { background-color: #ffebee; }
```

## Gradients

A gradient is a smooth blend between two or more colours. CSS treats it as an image, so it goes in `background-image` (or the `background` shorthand), not in `background-color`.

- `linear-gradient(direction, colour1, colour2, ...)` blends in a straight line.
- `radial-gradient(colour1, colour2, ...)` blends outward from a centre point.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  .box {
    height: 100px;
    margin-bottom: 12px;
    border-radius: 8px;
    color: white;
    font: bold 18px Arial, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .linear {
    background-image: linear-gradient(to right, #ff6f00, #c2185b);
  }
  .angle {
    background-image: linear-gradient(135deg, #00897b, #1e88e5, #5e35b1);
  }
  .radial {
    background-image: radial-gradient(circle, #ffd54f, #e65100);
  }
</style>
</head>
<body>
  <div class="box linear">Sunrise in Malindi</div>
  <div class="box angle">Indian Ocean</div>
  <div class="box radial">Savannah sun</div>
</body>
</html>
```

## Background images

`background-image: url("photo.jpg")` places an image behind an element. A few more properties control how it behaves:

| Property | Common values | What it does |
|---|---|---|
| `background-repeat` | `repeat`, `no-repeat`, `repeat-x`, `repeat-y` | whether the image tiles |
| `background-position` | `center`, `top right`, `20px 50%` | where the image sits |
| `background-size` | `auto`, `cover`, `contain`, `200px` | how big the image is drawn |
| `background-attachment` | `scroll`, `fixed` | whether it scrolls with the page |

The two `background-size` keywords matter most:

- `cover` scales the image until it fills the whole box. Some of it may be cropped.
- `contain` scales it until the whole image fits inside the box. Some empty space may remain.

> **Tip:** When you place text over a photo, always set a `background-color` too. It shows while the image loads and if the image fails, so the text stays readable.

## The background shorthand

You can set several background properties in one line with `background`. The order is flexible, but the size must come straight after the position, separated by a slash.

```css
.hero {
  background: #333 url("nairobi.jpg") center / cover no-repeat;
}
```

## Layering a gradient over an image

`background-image` accepts several layers separated by commas. The first layer is drawn on top. A popular trick is to darken a photo with a semi-transparent gradient so white text stands out. The example below uses a patterned gradient in place of a photo so it works without any image file.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  .hero {
    height: 220px;
    padding: 30px;
    color: white;
    font-family: Arial, sans-serif;
    background-color: #1b5e20;
    background-image:
      linear-gradient(rgb(0 0 0 / 0.55), rgb(0 0 0 / 0.55)),
      repeating-linear-gradient(45deg, #43a047 0 20px, #2e7d32 20px 40px);
  }
  .hero h1 { margin-top: 0; }
</style>
</head>
<body>
  <div class="hero">
    <h1>Tea Farms of Kericho</h1>
    <p>The dark layer on top keeps this text readable over a busy background.</p>
  </div>
</body>
</html>
```

Delete the first gradient line (and its comma) and run again. Without the dark layer, the text is much harder to read.

## Background on the whole page

Setting a background on `body` colours the entire browser window, even below short content. That makes it a good place for a page-wide colour.

```exercise
Q: Make the background image fill the box completely, cropping if needed.
.banner { background-size: ___; }
A: cover
H: The other keyword, contain, would leave gaps.
```

```exercise
Q: Stop the background image from tiling.
.logo { background-repeat: ___; }
A: no-repeat
```
