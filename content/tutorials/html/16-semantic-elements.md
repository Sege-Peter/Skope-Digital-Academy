---
title: Semantic Layout Elements
module: 5
module_title: Modern HTML
minutes: 11
---

A **semantic** element is one whose name describes its meaning. `<div>` tells you nothing about its content, but `<nav>` clearly says "this is navigation" and `<footer>` says "this is the footer". HTML5 introduced a set of semantic elements for the main parts of a page.

## Why semantics matter

Two pages can look identical on screen while one is built from meaningful elements and the other only from `<div>`. The semantic version is better because:

- **Screen readers** let users jump straight to the navigation, the main content or the footer.
- **Search engines** can tell which part of the page is the real content.
- **Developers** (including you, six months later) can understand the code at a glance.

## The main layout elements

| Element | Use it for |
|---|---|
| `<header>` | Introductory content: logo, site name, sometimes navigation. Can also introduce an article or section. |
| `<nav>` | A block of major navigation links |
| `<main>` | The main, unique content of the page. Use only one visible `<main>` per page. |
| `<section>` | A themed group of content, usually with its own heading |
| `<article>` | A self-contained piece that would make sense on its own: a blog post, news story, product card, comment |
| `<aside>` | Content related to the main content but separate from it: a sidebar, a "did you know" box |
| `<footer>` | Closing content: copyright, contact details, related links. Can also end an article or section. |

## A typical page layout

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lakeside News</title>
  <style>
    body { font-family: sans-serif; margin: 0; }
    header, nav, main, aside, footer { padding: 12px 16px; }
    header { background: #264653; color: white; }
    nav { background: #e9f5f3; }
    nav a { margin-right: 12px; }
    aside { background: #fff3cd; }
    footer { background: #eee; font-size: 14px; }
    article { border-bottom: 1px solid #ddd; padding-bottom: 8px; }
  </style>
</head>
<body>
  <header>
    <h1>Lakeside News</h1>
    <p>Stories from around Lake Victoria</p>
  </header>

  <nav>
    <a href="#">Home</a>
    <a href="#">Business</a>
    <a href="#">Sports</a>
  </nav>

  <main>
    <section>
      <h2>Latest stories</h2>
      <article>
        <h3>New jetty opens in Homa Bay</h3>
        <p>Traders say the jetty will cut the time it takes to move fish to market.</p>
      </article>
      <article>
        <h3>Kisumu youth win coding challenge</h3>
        <p>A team of four students built an app that tracks boda boda fares.</p>
      </article>
    </section>

    <aside>
      <h2>Weather</h2>
      <p>Sunny with afternoon showers.</p>
    </aside>
  </main>

  <footer>
    <p>&copy; 2026 Lakeside News. All rights reserved.</p>
  </footer>
</body>
</html>
```

The page looks simple, but its structure is crystal clear to browsers, search engines and assistive technology.

## section vs article vs div

These three are easy to confuse. Ask yourself:

1. **Could this content be shared or reused on its own** (in a news feed, for example) and still make sense? Use `<article>`.
2. **Is it a themed part of the page with a heading**, like "Our Services" or "Testimonials"? Use `<section>`.
3. **Do you just need a box for styling or layout**, with no particular meaning? Use `<div>`.

> **Tip:** A `<section>` should almost always have a heading. If you cannot think of a sensible heading for it, a `<div>` is probably the better choice.

## Headers and footers inside articles

`<header>` and `<footer>` are not only for the whole page. An article can have its own header (title, author, date) and footer (tags, share links).

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Article Structure</title>
</head>
<body>
  <article>
    <header>
      <h2>Five tips for growing kale in small spaces</h2>
      <p>By Mercy Chebet, <time datetime="2026-03-14">14 March 2026</time></p>
    </header>
    <p>Kale (sukuma wiki) grows well in sacks and old containers...</p>
    <footer>
      <p>Filed under: Urban farming</p>
    </footer>
  </article>
</body>
</html>
```

The `<time>` element marks a date or time. Its `datetime` attribute holds a machine-readable version, while the content can be written in any friendly format.

## Other useful semantic elements

| Element | Meaning |
|---|---|
| `<figure>` and `<figcaption>` | A self-contained image, chart or code sample with a caption |
| `<time>` | A date or time |
| `<address>` | Contact information for the page or article author |
| `<details>` and `<summary>` | A disclosure widget the user can open and close |
| `<mark>` | Highlighted text |

`<details>` is especially handy because it needs no JavaScript:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Details and Summary</title>
</head>
<body>
  <h2>Frequently asked questions</h2>
  <details>
    <summary>Do you deliver to Kakamega?</summary>
    <p>Yes, we deliver to all towns in Western Kenya within two days.</p>
  </details>
  <details>
    <summary>Can I pay with M-Pesa?</summary>
    <p>Yes, use our Paybill number at checkout.</p>
  </details>
</body>
</html>
```

> **Note:** Semantic elements do not add any special styling (apart from `<details>`). They behave like `<div>` visually, so you still use CSS for layout. What they add is meaning.

```exercise
Q: Wrap the site's main menu in the right semantic element.
<___>
  <a href="/">Home</a>
  <a href="/shop">Shop</a>
</___>
A: nav | /nav
H: Short for "navigation".
```

```exercise
Q: Use the element for the unique main content of the page.
<___>
  <h1>Welcome to Naivasha Gardens</h1>
</___>
A: main | /main
H: There should only be one of these per page.
```
