---
title: CSS Combinators and Specificity
module: 4
minutes: 15
---

This lesson answers two questions. First, how do you select an element based on where it sits in relation to others? That is the job of **combinators**. Second, when two rules disagree, which one wins? That is decided by the **cascade** and **specificity**.

## The four combinators

A combinator is the symbol (or space) between two selectors.

| Combinator | Written | Selects |
|---|---|---|
| Descendant | `nav a` | every `a` anywhere inside `nav`, at any depth |
| Child | `ul > li` | `li` elements that are *direct* children of `ul` |
| Next sibling | `h2 + p` | the one `p` that comes immediately after an `h2` |
| Subsequent siblings | `h2 ~ p` | every `p` that comes after an `h2` with the same parent |

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; }
  .menu a { color: #2e7d32; }             /* descendant */
  .menu > li { border-left: 4px solid #2e7d32; padding-left: 6px; } /* child */
  h2 + p { font-weight: bold; }          /* next sibling */
  h2 ~ p { color: #555; }                /* all later siblings */
</style>
</head>
<body>
  <ul class="menu">
    <li><a href="#">Home</a></li>
    <li>Counties
      <ul>
        <li><a href="#">Machakos</a></li>
        <li><a href="#">Kitui</a></li>
      </ul>
    </li>
  </ul>

  <h2>Daily update</h2>
  <p>This first paragraph after the heading is bold.</p>
  <p>This one is grey but not bold.</p>
  <p>So is this one.</p>
</body>
</html>
```

All links in the menu are green because `.menu a` reaches any depth. Only the outer list items get a green bar, because `.menu > li` stops at direct children.

## The cascade: how conflicts are settled

When more than one rule sets the same property on the same element, the browser decides in this order:

1. **Importance:** declarations marked `!important` beat normal ones.
2. **Specificity:** the more specific selector wins.
3. **Order:** if specificity is equal, the rule that appears later wins.

## Calculating specificity

Think of specificity as a score with three columns, **(ids, classes, elements)**. Compare from left to right; the first column that differs decides the winner.

| Selector part | Adds to column |
|---|---|
| `#id` | ids |
| `.class`, `[attr]`, `:hover` and other pseudo-classes | classes |
| `p`, `div`, `::before` and other pseudo-elements | elements |
| `*` and combinators | nothing |

| Selector | Score |
|---|---|
| `p` | (0, 0, 1) |
| `.intro` | (0, 1, 0) |
| `p.intro` | (0, 1, 1) |
| `nav ul li a` | (0, 0, 4) |
| `.menu a:hover` | (0, 2, 1) |
| `#main .intro` | (1, 1, 0) |

A single id beats any number of classes, and a single class beats any number of elements. Inline `style` attributes beat all selectors.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  #notice { color: green; }      /* (1,0,0) wins */
  .alert.urgent { color: red; }  /* (0,2,0) */
  p.alert { color: orange; }     /* (0,1,1) */
  p { color: blue; }             /* (0,0,1) */

  .box { background-color: #e3f2fd; padding: 10px; }
  div { background-color: #ffcdd2; } /* loses: lower specificity even though it comes later */
</style>
</head>
<body>
  <p id="notice" class="alert urgent">Which colour am I? Green, because the id is most specific.</p>
  <p class="alert urgent">Without an id, two classes win: red.</p>
  <p class="alert">One class plus one element: orange.</p>
  <p>Just an element: blue.</p>
  <div class="box">The class beats the element selector, so I am light blue.</div>
</body>
</html>
```

Try deleting `id="notice"` from the first paragraph and run again.

## Inheritance

Some properties, mainly text properties like `color`, `font-family` and `line-height`, are **inherited**: children take the value from their parent unless something else sets it. Layout properties like `border`, `padding` and `width` are not inherited. That is why setting `font-family` on `body` changes the whole page.

Any directly targeted rule, however weak, beats an inherited value.

## !important

Adding `!important` after a value makes it win over normal declarations regardless of specificity:

```css
.hidden { display: none !important; }
```

> **Warning:** Use `!important` sparingly. Once you start using it, the only way to override it is another `!important`, and your stylesheet quickly becomes a fight. Fix specificity problems by simplifying selectors instead.

> **Tip:** Keep specificity low and even. Styling mostly with single classes makes your CSS predictable and easy to override.

```exercise
Q: Select only list items that are direct children of .nav.
.nav ___ li { display: inline-block; }
A: >
```

```exercise
Q: Select the paragraph that comes immediately after an h3.
h3 ___ p { margin-top: 0; }
A: +
```
