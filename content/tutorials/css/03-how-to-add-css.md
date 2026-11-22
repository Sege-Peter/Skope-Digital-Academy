---
title: Three Ways to Add CSS
module: 1
minutes: 10
---

There are three ways to connect CSS to an HTML page: **inline**, **internal** and **external**. All three use the same properties and values. What changes is where the CSS lives and how many pages it can reach.

## 1. Inline styles

An inline style is written in the `style` attribute of a single element. It affects only that element.

```example html
<!DOCTYPE html>
<html>
<body>
  <h1 style="color: teal; font-family: Arial;">Nakuru Farmers Market</h1>
  <p style="background-color: #fffde7; padding: 10px;">Open every Saturday from 7am.</p>
  <p>This paragraph has no style.</p>
</body>
</html>
```

Inline styles are quick for testing, but they mix design into your content. If you have fifty headings, you would have to edit fifty attributes to change their colour. Use them rarely.

## 2. Internal stylesheet

An internal stylesheet sits inside a `<style>` element in the page's `<head>`. Its rules apply to the whole page. Most examples in this tutorial use this method because you can see the HTML and CSS together.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body {
    font-family: Verdana, sans-serif;
    margin: 20px;
  }
  h1 {
    color: teal;
  }
  .notice {
    background-color: #fffde7;
    padding: 10px;
  }
</style>
</head>
<body>
  <h1>Nakuru Farmers Market</h1>
  <p class="notice">Open every Saturday from 7am.</p>
  <p class="notice">Bring your own bag to save plastic.</p>
</body>
</html>
```

Both notices share one rule. Changing `.notice` changes them both.

## 3. External stylesheet

An external stylesheet is a separate file, usually ending in `.css`, linked from each page with a `<link>` element:

```html
<head>
  <link rel="stylesheet" href="styles.css">
</head>
```

The file `styles.css` contains only CSS, with no `<style>` tags:

```css
body {
  font-family: Verdana, sans-serif;
}
h1 {
  color: teal;
}
```

This is the method real websites use. Every page links to the same file, so a single edit restyles the whole site. Browsers also cache the file, so it only downloads once and later pages load faster.

> **Note:** The online editor runs a single page, so external files are not available there. When you build your own site, move the contents of your `<style>` block into a `.css` file and link it.

## Which one wins?

You can use all three methods on the same page. When they set the same property on the same element, the rule that is more specific or comes later wins. Inline styles are the most specific, so they beat both internal and external rules.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  p { color: blue; }
  p { color: green; }
</style>
</head>
<body>
  <p>I am green, because the second rule came later.</p>
  <p style="color: red;">I am red, because inline styles win.</p>
</body>
</html>
```

If the `<link>` to an external file appears *after* a `<style>` block, the external rules count as later. Order matters.

## Comparison

| Method | Where it lives | Reach | Best for |
|---|---|---|---|
| Inline | `style` attribute | one element | quick tests, one-off overrides |
| Internal | `<style>` in `<head>` | one page | single pages, demos, emails |
| External | separate `.css` file | many pages | real websites |

> **Warning:** Avoid relying on inline styles to fix problems. They are hard to override later and make your HTML harder to read.

```exercise
Q: Complete the tag that links an external stylesheet.
<link rel="___" href="main.css">
A: stylesheet
H: It tells the browser what kind of file is being linked.
```

```exercise
Q: Fill in the element that holds an internal stylesheet.
<___>
  h2 { color: purple; }
</___>
A: style | style
```
