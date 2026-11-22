---
title: HTML Attributes
module: 2
minutes: 9
---

Tags tell the browser *what* an element is. **Attributes** give extra details about an element: where a link goes, which image to show, what language the text is in, and much more.

## How attributes are written

Attributes always go inside the **opening tag**, after the tag name. Each one is a name, an equals sign and a value in quotes:

```html
<a href="https://www.kenya.go.ke">Government of Kenya</a>
```

Here `href` is the attribute name and `https://www.kenya.go.ke` is its value. An element can have several attributes, separated by spaces:

```html
<img src="giraffe.jpg" alt="A giraffe in Nairobi National Park" width="300">
```

## Your first attributes

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attributes</title>
</head>
<body>
  <h1 title="Kenya's second largest city">Mombasa</h1>
  <p>Hover over the heading to see its title.</p>
  <p>Visit the <a href="https://www.magicalkenya.com">Magical Kenya</a> website.</p>
</body>
</html>
```

The `title` attribute adds a small tooltip when you hover with a mouse. The `href` attribute tells a link where to go.

## Common attributes

| Attribute | Used on | Purpose |
|---|---|---|
| `href` | `<a>`, `<link>` | The address to link to |
| `src` | `<img>`, `<script>`, `<video>` | The file to load |
| `alt` | `<img>` | Text description of an image |
| `width`, `height` | `<img>`, `<video>`, `<iframe>` | Size in pixels |
| `lang` | Any element, usually `<html>` | Language of the content |
| `title` | Any element | Extra advisory information (tooltip) |
| `id` | Any element | A unique name for the element |
| `class` | Any element | A group name, used for styling |
| `style` | Any element | CSS applied directly to the element |

Attributes that can be used on any element, such as `id`, `class`, `title`, `lang` and `style`, are called **global attributes**.

## The style attribute

The `style` attribute lets you add CSS to a single element. It is handy for quick experiments.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Style Attribute</title>
</head>
<body>
  <h1 style="color: green;">Kenya Shilling Rates</h1>
  <p style="background-color: #fff3cd; padding: 8px;">Rates are updated every morning at 9 am.</p>
  <p lang="sw">Karibu tena!</p>
</body>
</html>
```

> **Tip:** For real projects, put CSS in a `<style>` block or a separate file instead of the `style` attribute. That keeps your HTML clean and lets you change the look of many elements at once. You will see how in the lesson on [classes and IDs](classes-ids).

## Boolean attributes

Some attributes do not need a value at all. Simply writing the name switches the feature on. These are called **boolean attributes**.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Boolean Attributes</title>
</head>
<body>
  <p><input type="text" value="Read only name" disabled></p>
  <p><input type="checkbox" checked> Subscribe to the newsletter</p>
</body>
</html>
```

The first box is `disabled` (greyed out) and the checkbox starts `checked`. To turn the feature off, remove the attribute entirely. Writing `disabled="false"` does **not** enable the field, because the attribute is still present.

## Quoting rules

- Use double quotes `"` around values. Single quotes `'` also work.
- If a value contains a double quote, wrap it in single quotes: `title='The "Big" Sale'`.
- HTML allows leaving out quotes for simple values, but always quoting avoids errors when a value contains a space.

> **Warning:** A missing closing quote is one of the most common beginner bugs. If half your page suddenly disappears, check for a value like `href="page.html` without its final `"`.

## Lowercase names

Attribute names are not case-sensitive, so `HREF` and `href` behave the same. Stick to lowercase for consistency. Values, however, can be case-sensitive: a file called `Photo.JPG` on a web server may not be found if you write `photo.jpg`.

## Custom data attributes

You can invent your own attributes as long as they start with `data-`. Browsers ignore them, but JavaScript can read them.

```html
<li data-price="250" data-county="Kiambu">Avocados (1 kg)</li>
```

This is useful once you start adding interactivity to your pages.

```exercise
Q: Complete the link so it points to the address.
<a ___="https://www.safaricom.co.ke">Safaricom</a>
A: href
H: It is short for "hypertext reference".
```

```exercise
Q: Add the attribute that sets the language of the page to English.
<html ___="en">
A: lang
H: It is a short form of the word "language".
```
