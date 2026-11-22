---
title: The CSS Box Model
module: 2
minutes: 14
---

To the browser, every element on a page is a rectangular box. The **box model** describes the layers that make up that box and how they add up to its final size. Understanding it is the key to controlling spacing and layout.

## The four layers

From the inside out:

| Layer | What it is | Property |
|---|---|---|
| Content | the text, image or child elements | `width`, `height` |
| Padding | space between the content and the border | `padding` |
| Border | the line around the padding | `border` |
| Margin | space outside the border, separating the box from its neighbours | `margin` |

Padding takes the element's background colour. Margin is always transparent.

## Padding and margin values

Both `padding` and `margin` accept one to four values, following the same pattern:

| Written as | Meaning |
|---|---|
| `padding: 10px;` | all four sides 10px |
| `padding: 10px 20px;` | top and bottom 10px, left and right 20px |
| `padding: 5px 10px 15px;` | top 5px, left and right 10px, bottom 15px |
| `padding: 5px 10px 15px 20px;` | top, right, bottom, left (clockwise) |

You can also set one side directly: `margin-top`, `padding-left` and so on.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; background: #eceff1; }
  .box {
    width: 260px;
    padding: 20px;
    border: 6px solid #1565c0;
    margin: 30px;
    background-color: #bbdefb;
  }
</style>
</head>
<body>
  <div class="box">
    Content area. The light blue includes the padding. The grey gap around the border is the margin.
  </div>
  <div class="box">A second box, 30px away.</div>
</body>
</html>
```

> **Note:** When a bottom margin meets the top margin of the next block, they **collapse** into one gap the size of the larger margin. The two boxes above are 30px apart, not 60px. This only happens with vertical margins on normal block elements, not inside Flexbox or Grid.

## Centring with auto margins

A block element with a set `width` can be centred horizontally with `margin: 0 auto;`. The browser splits the leftover space equally between the left and right margins.

## Width, height and their limits

`width` and `height` set the size of the box. In real pages it is safer to use limits that let the box adapt:

- `max-width: 600px;` lets the box shrink on small screens but never grow beyond 600px.
- `min-height: 200px;` lets the box grow with its content but never get shorter than 200px.

## box-sizing: the most useful reset

By default, `width` sets only the **content** width. Padding and border are added on top. So a box with `width: 300px`, `padding: 20px` and a `5px` border is actually 350px wide. This surprises almost every beginner.

Setting `box-sizing: border-box` changes the rule: `width` now includes the padding and border, and the content shrinks to fit. A 300px box stays 300px.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  div {
    width: 300px;
    padding: 20px;
    border: 5px solid #2e7d32;
    margin-bottom: 12px;
    background-color: #e8f5e9;
  }
  .content-box { box-sizing: content-box; }
  .border-box  { box-sizing: border-box; }
  .ruler {
    width: 300px;
    height: 8px;
    padding: 0;
    border: none;
    background-color: #ef6c00;
  }
</style>
</head>
<body>
  <div class="ruler"></div>
  <div class="content-box">content-box: 300 + 40 + 10 = 350px wide</div>
  <div class="border-box">border-box: exactly 300px wide</div>
</body>
</html>
```

The orange ruler is exactly 300px. Only the `border-box` version lines up with it.

Most developers add this rule at the top of every stylesheet so all elements behave the predictable way:

```css
*, *::before, *::after {
  box-sizing: border-box;
}
```

> **Tip:** Open your browser's developer tools (F12), select an element and look at the box model diagram. It shows the exact content, padding, border and margin of anything on the page.

```exercise
Q: Centre the 500px wide container horizontally.
.container { width: 500px; margin: 0 ___; }
A: auto
```

```exercise
Q: Make width include padding and border.
* { box-sizing: ___; }
A: border-box
H: The value names the outer edge that width should measure to.
```
