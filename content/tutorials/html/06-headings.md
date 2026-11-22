---
title: HTML Headings
module: 2
minutes: 7
---

Headings are the titles and subtitles of your page. They help readers scan for what they need, and they help search engines and screen readers understand how your content is organised.

## Six levels of headings

HTML has six heading elements, from `<h1>` (most important) down to `<h6>` (least important).

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Heading Levels</title>
</head>
<body>
  <h1>Heading level 1</h1>
  <h2>Heading level 2</h2>
  <h3>Heading level 3</h3>
  <h4>Heading level 4</h4>
  <h5>Heading level 5</h5>
  <h6>Heading level 6</h6>
</body>
</html>
```

By default, browsers show headings in bold, with `<h1>` the largest and `<h6>` the smallest. Each heading also starts on a new line with some space above and below.

## Headings describe structure, not size

The number in a heading is about **importance and hierarchy**, not about how big the text should look. Think of headings as the outline of a document, like the contents page of a book:

- One `<h1>` for the main topic of the page
- `<h2>` for the main sections
- `<h3>` for sub-sections inside an `<h2>` section, and so on

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Visiting Zanzibar</title>
</head>
<body>
  <h1>A Guide to Visiting Zanzibar</h1>

  <h2>Getting There</h2>
  <h3>By Air</h3>
  <p>Flights leave daily from Nairobi and Dar es Salaam.</p>
  <h3>By Ferry</h3>
  <p>Fast ferries run from Dar es Salaam several times a day.</p>

  <h2>Where to Stay</h2>
  <h3>Stone Town</h3>
  <p>The historic old town is full of narrow streets and markets.</p>
  <h3>Nungwi</h3>
  <p>A quiet beach village in the far north of the island.</p>
</body>
</html>
```

If you removed all the paragraphs from this page, the headings alone would still give a clear outline of the content. That is the goal.

## Why headings matter

**For readers.** Most people scan a page before reading it. Clear headings let them jump to the part they need.

**For accessibility.** Screen reader users often move through a page by jumping from heading to heading. A logical heading order makes the page easy to navigate without seeing it.

**For search engines.** Search engines use headings to understand what a page is about, which can help the right people find it.

## Rules of thumb

| Do | Avoid |
|---|---|
| Use one `<h1>` that names the page's main topic | Several competing `<h1>` headings |
| Go down one level at a time (`h2` then `h3`) | Jumping from `<h2>` straight to `<h5>` |
| Keep headings short and descriptive | Whole sentences or paragraphs in a heading |
| Use CSS to change heading size | Picking `<h4>` just because it looks smaller |
| Use headings for section titles | Using a heading to make random text bold |

> **Tip:** Browser extensions and the accessibility panel in Chrome DevTools can show you a page's heading outline. It is a quick way to check that your structure makes sense.

## Changing how headings look

If a heading looks too big or too small, change it with CSS rather than choosing a different level.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Styled Headings</title>
  <style>
    h1 { font-size: 28px; color: #0b6e4f; }
    h2 { font-size: 20px; color: #444; border-bottom: 2px solid #f4a261; }
  </style>
</head>
<body>
  <h1>Kakamega Forest Tours</h1>
  <h2>Morning Bird Walk</h2>
  <p>See over 300 species of birds with a local guide.</p>
</body>
</html>
```

## Horizontal rules

The `<hr>` element is often used between sections. It represents a change of topic (a *thematic break*) and is usually drawn as a horizontal line. It is a void element, so it has no closing tag.

```html
<h2>Fruits</h2>
<p>Mangoes, pawpaws and passion fruit.</p>
<hr>
<h2>Vegetables</h2>
<p>Sukuma wiki, cabbage and spinach.</p>
```

> **Note:** Do not confuse headings (`<h1>` to `<h6>`) with the `<head>` element. The `<head>` holds page information and is not visible; headings are visible titles in the body.

```exercise
Q: Make "Kenya Coast Hotels" the main heading of the page.
<___>Kenya Coast Hotels</___>
A: h1 | /h1
H: The most important heading level is number 1.
```

```exercise
Q: Add a sub-section heading one level below h2.
<h2>Menu</h2>
<___>Breakfast</___>
A: h3 | /h3
H: Go down exactly one level.
```
