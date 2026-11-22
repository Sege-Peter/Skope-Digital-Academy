---
title: HTML Links
module: 2
minutes: 11
---

Links are what turn separate pages into a web. With one element, `<a>`, you can send visitors to another website, another page on your own site, a section further down the page, an email address or even a phone number.

## The anchor element

A link is created with the `<a>` (anchor) element. The `href` attribute holds the destination, and the content between the tags is the clickable text.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Links</title>
</head>
<body>
  <p>Read the latest news on <a href="https://www.bbc.com/swahili">BBC Swahili</a>.</p>
  <p>Learn about wildlife at the <a href="https://www.kws.go.ke">Kenya Wildlife Service</a>.</p>
</body>
</html>
```

By default, unvisited links are blue and underlined, visited links are purple, and the mouse pointer changes to a hand when you hover over them. All of this can be changed with CSS.

## Absolute and relative URLs

There are two ways to write the address in `href`:

| Type | Example | Use it for |
|---|---|---|
| Absolute URL | `https://www.example.com/about.html` | Pages on another website |
| Relative URL | `about.html` | Pages on your own site |
| Relative, in a sub-folder | `courses/html.html` | A file inside a folder next to this page |
| Relative, one folder up | `../index.html` | A file in the parent folder |
| Root-relative | `/contact.html` | A file starting from the top of your site |

An absolute URL includes the scheme (`https://`) and the domain. A relative URL is worked out from the location of the current page, so your links keep working even if you move the whole site to a new domain.

## Opening a link in a new tab

The `target` attribute controls where the link opens. `target="_blank"` opens it in a new tab.

```html
<a href="https://www.google.com/maps" target="_blank">Open Google Maps</a>
```

> **Tip:** Use new tabs sparingly. Many people prefer to decide for themselves, and unexpected tabs can confuse screen reader users. A common, reasonable use is linking to a document or an external site from inside a form someone is filling in.

Modern browsers automatically protect the original page when using `_blank`. You may still see `rel="noopener"` added in older code; it does no harm.

## Links to sections of the same page

Give any element an `id`, then link to it with `#` followed by the id. This is handy for long pages and tables of contents.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Page Sections</title>
</head>
<body>
  <h1>Kenyan Counties</h1>
  <p>Jump to: <a href="#coast">Coast</a> | <a href="#western">Western</a></p>

  <h2 id="coast">Coast Region</h2>
  <p>Mombasa, Kilifi, Kwale, Lamu, Tana River and Taita-Taveta.</p>
  <p style="height: 400px;">(Scroll down...)</p>

  <h2 id="western">Western Region</h2>
  <p>Kakamega, Bungoma, Busia and Vihiga.</p>
  <p><a href="#">Back to top</a></p>
</body>
</html>
```

You can also link to a section on another page: `href="counties.html#coast"`.

## Email and phone links

Special schemes start an action instead of opening a page:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Links</title>
</head>
<body>
  <h2>Contact Wambui Crafts</h2>
  <p>Email: <a href="mailto:hello@example.co.ke">hello@example.co.ke</a></p>
  <p>Call: <a href="tel:+254700000000">+254 700 000 000</a></p>
  <p>WhatsApp: <a href="https://wa.me/254700000000">Chat with us</a></p>
</body>
</html>
```

- `mailto:` opens the visitor's email app with the address filled in.
- `tel:` lets phone users tap to call. Write the number in international format without spaces.

## The download attribute

Add `download` to suggest that the browser saves the file instead of opening it.

```html
<a href="files/price-list.pdf" download>Download our price list (PDF)</a>
```

This works for files on your own site; browsers usually ignore it for files on other domains.

## Images and other elements as links

Anything can go inside `<a>`, including an image. Clicking the image follows the link.

```html
<a href="index.html">
  <img src="logo.png" alt="Skope Digital Academy home">
</a>
```

When an image is the only content of a link, its `alt` text becomes the link's name, so describe where the link goes.

## Writing good link text

The text inside a link should make sense on its own. Screen reader users often listen to a list of all the links on a page.

| Weak link text | Better link text |
|---|---|
| Click here | Download the 2026 school calendar |
| Read more | Read more about our Nakuru branch |
| www.example.com/fees/2026/form.pdf | Fees form (PDF) |

> **Warning:** Never put one link inside another. Nested `<a>` elements are invalid and browsers will split them in unpredictable ways.

```exercise
Q: Complete the link to the M-Pesa page.
<___ href="mpesa.html">Pay with M-Pesa</a>
A: a
H: The anchor element is a single letter.
```

```exercise
Q: Make the link open in a new tab.
<a href="https://www.kplc.co.ke" target="___">Kenya Power</a>
A: _blank
H: The value starts with an underscore.
```
