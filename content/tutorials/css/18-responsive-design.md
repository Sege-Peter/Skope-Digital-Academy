---
title: Responsive Design and Media Queries
module: 5
module_title: Responsive and Modern CSS
minutes: 15
---

In Kenya, as in most of Africa, the majority of people browse the web on a phone. **Responsive design** means building one page that adapts its layout to any screen, from a small Android phone to a wide office monitor.

Responsive design rests on three ideas:

1. A correct **viewport** setting.
2. **Fluid** sizes using `%`, `fr`, `rem`, `max-width` and Flexbox or Grid.
3. **Media queries** that change styles at certain screen widths.

## Step 1: the viewport meta tag

Without this tag, phones pretend to be about 980px wide and shrink the whole page, so text becomes tiny. Every responsive page needs it in the `<head>`:

```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

## Step 2: fluid building blocks

Many responsive problems disappear if you avoid fixed widths:

- Use `max-width` instead of `width` for containers: `max-width: 1100px; margin: 0 auto;`
- Make images shrink to fit: `img { max-width: 100%; height: auto; }`
- Let Flexbox wrap (`flex-wrap: wrap`) and let Grid use `repeat(auto-fit, minmax(...))`.

## Step 3: media queries

A media query applies a block of CSS only when a condition is true. The most common condition is the width of the viewport.

```css
@media (min-width: 768px) {
  .sidebar { display: block; }
}
```

| Query | Applies when |
|---|---|
| `(min-width: 768px)` | the viewport is 768px wide **or wider** |
| `(max-width: 600px)` | the viewport is 600px wide **or narrower** |
| `(min-width: 600px) and (max-width: 900px)` | between 600px and 900px |
| `(orientation: landscape)` | wider than tall |
| `(prefers-color-scheme: dark)` | the user's device is in dark mode |
| `print` | the page is being printed |

## Mobile first

The recommended approach is **mobile first**: write the basic styles for small screens with no media query, then add `min-width` queries to enhance the layout as space grows. Small phones get the simplest CSS, and you add complexity only where there is room for it.

```example html
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, sans-serif; }
  header { background-color: #004d40; color: white; padding: 16px; }
  .layout {
    display: grid;
    grid-template-columns: 1fr;   /* phones: one column */
    gap: 12px;
    padding: 12px;
  }
  .layout > div {
    background-color: #e0f2f1;
    padding: 16px;
    border-radius: 8px;
  }
  .sidebar { display: none; }

  /* Tablets and up: two columns */
  @media (min-width: 600px) {
    .layout { grid-template-columns: 1fr 1fr; }
  }

  /* Laptops and up: sidebar appears */
  @media (min-width: 900px) {
    .layout { grid-template-columns: 220px 1fr 1fr; }
    .layout > .sidebar { display: block; background-color: #b2dfdb; }
  }
</style>
</head>
<body>
  <header>Mama Mboga Online</header>
  <div class="layout">
    <div class="sidebar">Categories</div>
    <div>Sukuma wiki - KSh 30</div>
    <div>Tomatoes - KSh 120</div>
    <div>Onions - KSh 80</div>
    <div>Avocados - KSh 50</div>
  </div>
</body>
</html>
```

Open it with **Try it Yourself** and drag the edge of the result panel, or resize the browser. Under 600px you see one column, then two, then three with a sidebar.

## Common breakpoints

There is no official list, and the best breakpoints are where *your* design starts to look wrong. These are reasonable starting points:

| Name | Width |
|---|---|
| Small phones | under 480px |
| Large phones / small tablets | 600px |
| Tablets | 768px |
| Laptops | 1024px |
| Wide screens | 1280px and up |

## Responsive navigation and dark mode

Media queries can change anything: stack a menu vertically on phones, enlarge tap targets, or switch colours when the user prefers dark mode.

```example html
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body {
    font-family: Arial, sans-serif;
    background-color: #ffffff;
    color: #222;
  }
  nav ul {
    list-style: none;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  nav a {
    display: block;
    padding: 12px;
    background-color: #eeeeee;
    color: inherit;
    text-decoration: none;
    border-radius: 6px;
  }
  @media (min-width: 640px) {
    nav ul { flex-direction: row; }
  }
  @media (prefers-color-scheme: dark) {
    body { background-color: #121212; color: #eeeeee; }
    nav a { background-color: #333333; }
  }
</style>
</head>
<body>
  <nav>
    <ul>
      <li><a href="#">Home</a></li>
      <li><a href="#">Courses</a></li>
      <li><a href="#">Pricing</a></li>
      <li><a href="#">Contact</a></li>
    </ul>
  </nav>
  <p>Narrow screens stack the menu. Wide screens show it in a row. Switch your device to dark mode to see the colours change.</p>
</body>
</html>
```

> **Tip:** Test on a real phone as well as in the browser's device mode (press F12, then the phone icon). Real devices reveal small tap targets and slow loading that a desktop never shows.

> **Note:** Container queries (`@container`) are a newer feature that lets a component respond to the width of its *parent* instead of the whole screen. They are supported in all modern browsers and are worth exploring once you are comfortable with media queries.

```exercise
Q: Apply styles only on screens 768px wide or wider.
@media (___: 768px) { .menu { display: flex; } }
A: min-width
```

```exercise
Q: Complete the viewport meta tag.
<meta name="viewport" content="width=___, initial-scale=1">
A: device-width
```
