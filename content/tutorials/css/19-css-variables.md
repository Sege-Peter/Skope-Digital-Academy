---
title: CSS Variables (Custom Properties)
module: 5
minutes: 12
---

Imagine your client asks you to change their brand green to a slightly different shade, and that colour appears in forty places across the stylesheet. **CSS variables**, officially called **custom properties**, solve this. You store a value once, give it a name, and reuse the name everywhere.

## Declaring and using a variable

A custom property name starts with two dashes. You read it with the `var()` function.

```css
:root {
  --brand: #2e7d32;
  --radius: 8px;
}

.btn {
  background-color: var(--brand);
  border-radius: var(--radius);
}
```

`:root` is a pseudo-class that matches the `<html>` element. Variables declared there are available to every element on the page, which makes it the usual home for site-wide values.

> **Note:** Custom property names are case-sensitive. `--Brand` and `--brand` are two different variables.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  :root {
    --brand: #2e7d32;
    --brand-light: #e8f5e9;
    --text: #263238;
    --radius: 10px;
    --space: 16px;
  }
  body {
    font-family: Arial, sans-serif;
    color: var(--text);
    padding: var(--space);
  }
  h1 { color: var(--brand); }
  .card {
    background-color: var(--brand-light);
    border: 2px solid var(--brand);
    border-radius: var(--radius);
    padding: var(--space);
    margin-bottom: var(--space);
  }
  .btn {
    background-color: var(--brand);
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: var(--radius);
  }
</style>
</head>
<body>
  <h1>Kilimo Bora Co-operative</h1>
  <div class="card">Members meet every first Tuesday in Embu.</div>
  <button class="btn">Join today</button>
</body>
</html>
```

Change `--brand` to `#6a1b9a` and run it. The heading, border and button all turn purple at once.

## Fallback values

`var()` accepts a second value, used if the variable is not defined:

```css
color: var(--accent, orange);
```

## Variables follow the cascade

Custom properties are inherited and can be redefined at any level. A value set on an element applies to that element and its children, overriding the one from `:root`. This lets you create variations without writing new rules.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  :root {
    --bg: #ffffff;
    --fg: #212121;
    --accent: #1565c0;
  }
  .dark {
    --bg: #1e1e1e;
    --fg: #f5f5f5;
    --accent: #90caf9;
  }
  .panel {
    background-color: var(--bg);
    color: var(--fg);
    border: 2px solid var(--accent);
    padding: 16px;
    margin: 10px 0;
    border-radius: 8px;
    font-family: Arial, sans-serif;
  }
  .panel a { color: var(--accent); }

  .alert { --accent: #c62828; }
</style>
</head>
<body>
  <div class="panel">Light panel with a <a href="#">blue link</a>.</div>
  <div class="dark">
    <div class="panel">Dark panel: same CSS rule, different variables. <a href="#">Link</a></div>
  </div>
  <div class="panel alert">Alert panel: only the accent was changed. <a href="#">Details</a></div>
</body>
</html>
```

The `.panel` rule is written once. Each panel looks different only because the variables around it are different. This is exactly how many websites build a dark mode: they redefine a handful of colour variables inside `@media (prefers-color-scheme: dark)` or on a `.dark` class.

## Using variables in calculations

Variables work inside `calc()`, so you can derive values from them:

```css
:root { --space: 8px; }
.section { padding: calc(var(--space) * 3); }  /* 24px */
```

## Changing variables with JavaScript

Because custom properties live in the page at run time (unlike variables in tools like Sass), JavaScript can read and change them, and the page updates instantly:

```javascript
document.documentElement.style.setProperty('--brand', '#ef6c00');
```

## Good habits

| Habit | Why |
|---|---|
| Put global values in `:root` | one place to find and change them |
| Name variables by purpose (`--brand`, `--text-muted`) rather than look (`--green`) | names stay correct when the colour changes |
| Use variables for colours, spacing, radii and font sizes | these repeat most often |
| Provide fallbacks for optional variables | the page still works if one is missing |

> **Tip:** Combine variables with the [Responsive Design](responsive-design) lesson: redefine `--space` or font size variables inside a media query and the whole layout adjusts.

```exercise
Q: Declare a global variable named main-color.
:___ { --main-color: teal; }
A: root
```

```exercise
Q: Use the variable as the heading colour, with navy as the fallback.
h1 { color: ___(--main-color, navy); }
A: var
```
