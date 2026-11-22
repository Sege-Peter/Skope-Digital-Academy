---
title: CSS Borders and Rounded Corners
module: 2
minutes: 10
---

A border is a line drawn around an element, between its padding and its margin. Borders outline cards, separate sections, underline headings and highlight inputs.

## The three parts of a border

A border needs three things:

| Property | Example values | Meaning |
|---|---|---|
| `border-width` | `1px`, `4px`, `thin`, `thick` | how thick the line is |
| `border-style` | `solid`, `dashed`, `dotted`, `double`, `none` | what the line looks like |
| `border-color` | `#ccc`, `red`, `rgb(0 0 0 / 0.2)` | what colour it is |

> **Warning:** `border-style` is required. Without it the border does not appear at all, even if you set a width and colour. The default style is `none`.

## The border shorthand

Most of the time you set all three at once with `border`, in any order:

```css
.card { border: 1px solid #ddd; }
```

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  p {
    padding: 10px;
    font-family: Arial, sans-serif;
  }
  .solid  { border: 2px solid #1565c0; }
  .dashed { border: 2px dashed #ef6c00; }
  .dotted { border: 3px dotted #6a1b9a; }
  .double { border: 5px double #2e7d32; }
</style>
</head>
<body>
  <p class="solid">Solid: Mombasa</p>
  <p class="dashed">Dashed: Kisumu</p>
  <p class="dotted">Dotted: Eldoret</p>
  <p class="double">Double: Nyeri</p>
</body>
</html>
```

## Individual sides

Each side can be styled on its own with `border-top`, `border-right`, `border-bottom` and `border-left`. This is handy for underlines and accent bars.

```css
h2 { border-bottom: 3px solid #f9a825; }
.quote { border-left: 6px solid #00897b; }
```

## Rounded corners with border-radius

`border-radius` rounds the corners of an element. It works even without a border, because it also clips the background.

- One value rounds all four corners equally: `border-radius: 8px;`
- Four values go clockwise from the top-left: `border-radius: 10px 0 10px 0;`
- `border-radius: 50%` on a square element makes a perfect circle.
- A very large value such as `999px` on a wide element makes a "pill" shape.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .card {
    border: 1px solid #ddd;
    border-radius: 12px;
    padding: 16px;
    max-width: 300px;
    margin-bottom: 16px;
  }
  .avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background-color: #00897b;
    color: white;
    font-size: 26px;
    line-height: 70px;
    text-align: center;
  }
  .pill {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 999px;
    background-color: #e3f2fd;
    border: 1px solid #90caf9;
  }
  .note {
    border-left: 6px solid #f9a825;
    background-color: #fffde7;
    padding: 10px 14px;
  }
</style>
</head>
<body>
  <div class="card">
    <div class="avatar">AW</div>
    <h3>Achieng Wanjiru</h3>
    <span class="pill">Web Developer</span>
  </div>
  <p class="note">Tip: a left border makes a simple callout box.</p>
</body>
</html>
```

## Shadows go well with borders

Modern designs often replace a hard border with a soft shadow, or combine a light border with one. `box-shadow` takes a horizontal offset, a vertical offset, a blur radius, an optional spread and a colour:

```css
.card {
  border: 1px solid #eee;
  box-shadow: 0 4px 12px rgb(0 0 0 / 0.12);
}
```

A positive vertical offset puts the shadow below the box, as if light shines from above. Keep the shadow colour mostly transparent so it looks natural on any background. Shadows follow `border-radius`, so a rounded card gets rounded shadows too. Like outlines, shadows do not take up space or move neighbouring elements.

## Outline: the border that takes no space

`outline` looks like a border but is drawn outside the element and does not change its size or push other elements around. Browsers use outlines to show which link or input has keyboard focus.

> **Tip:** Never remove focus outlines with `outline: none` unless you replace them with another clear focus style. People who navigate with a keyboard depend on them.

```exercise
Q: Give the card a 1px solid grey border using the shorthand.
.card { border: 1px ___ grey; }
A: solid
H: It is the style part of the shorthand.
```

```exercise
Q: Turn the square image into a circle.
.photo { ___: 50%; }
A: border-radius
```
