---
title: CSS Syntax and Selectors
module: 1
minutes: 12
---

Every piece of CSS is made of **rules**. A rule says *which* elements to style and *how* to style them. Once you understand the shape of a rule, the rest of CSS is learning more properties and smarter selectors.

## The shape of a rule

```css
h1 {
  color: navy;
  font-size: 32px;
}
```

| Part | In the example | Meaning |
|---|---|---|
| Selector | `h1` | which elements the rule applies to |
| Declaration block | everything between `{` and `}` | the list of styles |
| Property | `color`, `font-size` | what you want to change |
| Value | `navy`, `32px` | what to change it to |

Each declaration is a property, a colon, a value and a semicolon. The semicolon after the last declaration is optional, but always adding it prevents mistakes when you add more lines later.

> **Note:** CSS ignores extra spaces and line breaks. Writing each declaration on its own line simply makes the code easier to read.

## Comments

A comment starts with `/*` and ends with `*/`. The browser ignores it. Use comments to explain your thinking or to switch a rule off temporarily.

```css
/* Brand colour for all main headings */
h1 { color: #2e7d32; }
```

## The element selector

The simplest selector is the name of an HTML element. `p` selects every paragraph, `li` selects every list item, and so on.

## The class selector

A class selector starts with a dot. It selects every element whose `class` attribute contains that name. Classes are the selector you will use most, because you can reuse a class on as many elements as you like.

## The id selector

An id selector starts with a hash `#`. An `id` must be unique on the page, so this selector targets exactly one element.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  p {
    font-family: Georgia, serif;
  }
  .highlight {
    background-color: #fff59d;
  }
  #headline {
    color: #c62828;
    text-transform: uppercase;
  }
</style>
</head>
<body>
  <h1 id="headline">Matatu fares to rise</h1>
  <p>Transport operators in Nairobi announced new fares on Monday.</p>
  <p class="highlight">Commuters on the Thika Road route will pay the most.</p>
  <p>Operators say the change follows higher fuel prices.</p>
</body>
</html>
```

## Combining and grouping selectors

You can attach a class to an element name: `p.highlight` selects only paragraphs that have the class `highlight`. An element can also have several classes, separated by spaces in the HTML, such as `class="card featured"`.

When several selectors share the same styles, list them with commas instead of repeating the rule. This is called **grouping**.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  h1, h2, h3 {
    font-family: Arial, sans-serif;
    color: #0d47a1;
  }
  .card {
    border: 1px solid #ccc;
    padding: 12px;
    margin-bottom: 10px;
  }
  .card.featured {
    border-color: #ef6c00;
    background-color: #fff3e0;
  }
</style>
</head>
<body>
  <h1>Short courses</h1>
  <h2>Starting this month</h2>
  <div class="card">Introduction to Excel</div>
  <div class="card featured">Web Design Bootcamp (most popular)</div>
  <div class="card">Digital Marketing Basics</div>
</body>
</html>
```

Notice `.card.featured` has no space between the two classes. That means "an element that has *both* classes". With a space, it would mean something different, which you will meet in [Combinators & Specificity](combinators-specificity).

## The universal selector

The asterisk `*` selects every element. It is often used for a small reset at the top of a stylesheet, for example `* { box-sizing: border-box; }`.

## Attribute selectors

Square brackets select elements by their attributes. `input[type="email"]` selects only email inputs, and `a[target]` selects links that have any `target` attribute.

| Selector | Selects |
|---|---|
| `p` | all `<p>` elements |
| `.price` | elements with `class="price"` |
| `#logo` | the element with `id="logo"` |
| `h1, h2` | all `<h1>` and all `<h2>` elements |
| `*` | every element |
| `input[type="text"]` | text inputs only |

> **Tip:** Prefer classes over ids for styling. Classes are reusable and easier to override, while ids are best kept for links and JavaScript.

```exercise
Q: Write the selector symbols so the first rule targets the class "menu" and the second targets the id "footer".
___menu { background: black; }
___footer { color: grey; }
A: . | #
H: Classes use a dot, ids use a hash.
```

```exercise
Q: Complete the declaration so paragraphs become green.
p { ___: green; }
A: color
H: The property name uses the American spelling.
```
