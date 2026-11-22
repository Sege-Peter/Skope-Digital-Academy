---
title: HTML Page Structure
module: 1
minutes: 12
---

HTML is the skeleton of every web page. It tells the browser what each piece of content *is*: a heading, a paragraph, an image, a link, a navigation menu. In this lesson you will build the full structure of the Baraka Bakery home page.

## The basic page

Every HTML page has the same outer shape:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baraka Bakery – Kisumu</title>
</head>
<body>
  <h1>Baraka Bakery</h1>
  <p>Fresh bread and cakes, baked every morning.</p>
</body>
</html>
```

| Part | Job |
|---|---|
| `<!DOCTYPE html>` | Tells the browser this is a modern HTML page |
| `<html lang="en">` | Wraps everything; `lang` helps screen readers and search engines |
| `<head>` | Information *about* the page: title, character set, styles |
| `<meta name="viewport">` | Makes the page display properly on phones (always include it) |
| `<title>` | The text on the browser tab and in Google results |
| `<body>` | Everything visitors actually see |

## Semantic layout elements

Instead of using plain boxes for everything, HTML has **semantic** elements whose names describe their role. They make your code easier to read and help search engines and screen readers understand the page.

| Element | Use it for |
|---|---|
| `<header>` | The top area: logo, business name, navigation |
| `<nav>` | The main menu links |
| `<main>` | The main content of this page (only one per page) |
| `<section>` | A themed group of content with its own heading |
| `<article>` | A self-contained item, like a product card or blog post |
| `<footer>` | The bottom area: contacts, copyright, social links |

## Headings in order

Use one `<h1>` for the main title of the page, `<h2>` for each section, and `<h3>` for items inside a section. Don't pick headings because of their size; size can be changed later with CSS.

## The full home page structure

Here is Baraka Bakery's home page with real structure and content but no styling yet. Run it and notice that it already works and makes sense, even if it looks plain.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baraka Bakery – Fresh Bread and Cakes in Kisumu</title>
</head>
<body>
  <header>
    <h1>Baraka Bakery</h1>
    <nav>
      <a href="#menu">Menu</a> |
      <a href="#about">About</a> |
      <a href="#contact">Contact</a>
    </nav>
  </header>

  <main>
    <section>
      <h2>Fresh bread and cakes, baked every morning</h2>
      <p>Serving families and offices around Jubilee Market since 2015.</p>
      <a href="https://wa.me/254700000000">Order a cake on WhatsApp</a>
    </section>

    <section id="menu">
      <h2>Our favourites</h2>
      <article>
        <h3>White bread (400g)</h3>
        <p>Soft, fresh and ready by 6 a.m.</p>
      </article>
      <article>
        <h3>Mandazi (pack of 5)</h3>
        <p>Lightly sweet, perfect with chai.</p>
      </article>
      <article>
        <h3>Birthday cakes</h3>
        <p>Vanilla, chocolate or red velvet, made to order.</p>
      </article>
    </section>

    <section id="about">
      <h2>About us</h2>
      <p>Mama Akinyi started Baraka Bakery with one oven and a big dream.</p>
    </section>

    <section id="contact">
      <h2>Visit or call us</h2>
      <p>Open Monday to Saturday, 6:00 a.m. – 8:00 p.m.</p>
      <p>Phone: <a href="tel:+254700000000">0700 000 000</a></p>
    </section>
  </main>

  <footer>
    <p>&copy; 2026 Baraka Bakery, Kisumu</p>
  </footer>
</body>
</html>
```

> **Note:** The phone number `0700 000 000` is a placeholder. On a real site, use the client's real number, and check with them which number they want public.

## Links inside the page

The menu links like `href="#menu"` jump to the element with `id="menu"`. On a one-page site, this is how the navigation works. `tel:` links let phone users tap to call, and `https://wa.me/` followed by the number in international format (no `+` or spaces) opens a WhatsApp chat.

## Common beginner mistakes

- Forgetting to close tags, for example `<p>` without `</p>`.
- Using several `<h1>` headings on one page.
- Skipping the viewport `<meta>` tag, so the site looks tiny on phones.
- Writing the business name only inside an image, where search engines and screen readers cannot read it.

```exercise
Q: Complete the element that holds the main menu links.
<___>
  <a href="#menu">Menu</a>
</___>
A: nav | nav
H: Short for navigation.
```

```exercise
Q: Fill in the element used for the bottom area of the page.
<___><p>&copy; 2026 Baraka Bakery</p></___>
A: footer | footer
H: The opposite of header.
```

## Practice task

Build the HTML structure of your own business's home page using `header`, `nav`, `main`, at least three `section` elements and a `footer`. Use real content from your planning checklist. Save it as `index.html` in a folder called `my-business-site`.
