---
title: HTML Page Structure
module: 1
minutes: 10
---

Every HTML page follows the same basic skeleton. Once you know it, you can start any new page in seconds. In this lesson we take the skeleton apart line by line.

## The complete skeleton

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mama Wanjiku's Kitchen</title>
</head>
<body>
  <h1>Mama Wanjiku's Kitchen</h1>
  <p>Home-made chapati and githeri in Nyeri town.</p>
</body>
</html>
```

This is the starting point we recommend for every page you build. Let us look at each part.

## The doctype

```html
<!DOCTYPE html>
```

The very first line tells the browser "this is a modern HTML5 document". It is not a tag and has no closing partner. Without it, browsers fall back to an old compatibility mode (called *quirks mode*) where some layouts behave strangely. Always put it first.

## The html element

```html
<html lang="en">
  ...
</html>
```

The `<html>` element wraps the whole page. Everything else sits inside it. It is called the **root element**.

The `lang` attribute states the main language of the page. Screen readers use it to choose the right pronunciation, and search engines use it to match pages to readers. Use `lang="en"` for English or `lang="sw"` for Kiswahili.

## The head element

The `<head>` holds information *about* the page. Almost nothing in the head appears inside the browser window. Common items are:

| Element | What it does |
|---|---|
| `<meta charset="UTF-8">` | Tells the browser how the text is encoded so all characters display correctly |
| `<meta name="viewport" ...>` | Makes the page fit phone screens properly |
| `<title>` | The text on the browser tab and in search results |
| `<link>` | Connects a CSS file or an icon |
| `<style>` | Holds CSS written directly in the page |
| `<script>` | Holds or loads JavaScript |

You will learn more about these in the lesson on [head, meta and SEO](head-meta).

> **Note:** The `<title>` is required in a valid HTML page. Make it short and descriptive, because it is what people see in their tabs, bookmarks and Google results.

## The body element

The `<body>` contains everything the visitor actually sees: headings, paragraphs, images, links, forms and so on. A page has exactly one `<head>` and one `<body>`, and the head always comes first.

## How the parts fit together

Think of the page as a family tree:

- `html` is the parent of `head` and `body`.
- `head` is the parent of `meta` and `title`.
- `body` is the parent of `h1` and `p`.

Browsers turn this tree into something called the **DOM** (Document Object Model). CSS and JavaScript both work by finding elements in that tree, so a clean, well-nested structure makes everything else easier.

## Comments

You can leave notes in your HTML that the browser will ignore. They start with `<!--` and end with `-->`.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Comments Demo</title>
</head>
<body>
  <!-- Main heading for the page -->
  <h1>Nakuru Farmers' Market</h1>

  <!-- TODO: add opening hours later -->
  <p>Fresh vegetables every Saturday.</p>

  <!--
  <p>This paragraph is hidden for now.</p>
  -->
</body>
</html>
```

Comments are useful for explaining tricky parts, marking unfinished work or temporarily hiding code.

> **Warning:** Comments are not secret. Anyone can see them with **View Page Source**, so never put passwords or private notes in them.

## What happens if you forget something?

Browsers are forgiving. If you leave out `<head>` or `<body>`, the browser quietly adds them for you. However, relying on this leads to confusing bugs and fails validation. Writing the full skeleton every time is a professional habit worth building early.

## Checking your page

The W3C runs a free online **Markup Validation Service** (validator.w3.org). Paste your HTML into it and it lists any errors, such as a missing closing tag or an unknown attribute. Use it whenever a page behaves oddly.

```exercise
Q: Complete the first line of every HTML5 page.
<!___ html>
A: DOCTYPE
H: It declares the document type.
```

```exercise
Q: Fill in the two main sections that go inside the html element.
<html lang="en">
<___>
  <title>Kisii Bookshop</title>
</head>
<___>
  <h1>Kisii Bookshop</h1>
</body>
</html>
A: head | body
H: The first holds information about the page; the second holds visible content.
```
