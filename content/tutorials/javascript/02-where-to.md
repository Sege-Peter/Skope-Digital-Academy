---
title: Where to Put JavaScript
module: 1
minutes: 8
---

Before writing bigger programs, you need to know where JavaScript code lives in a web page. There are three common places: inside a `<script>` tag in the HTML, in a separate `.js` file, and (rarely) directly in an HTML attribute.

## The `<script>` tag

Any JavaScript inside a `<script>` element runs when the browser reaches it while reading the page from top to bottom.

```example html
<!DOCTYPE html>
<html>
<body>
  <h2>Skope Shop</h2>
  <p id="message"></p>

  <script>
    document.getElementById("message").textContent = "Free delivery within Nairobi CBD!";
  </script>
</body>
</html>
```

Notice that the script comes *after* the `<p>` element. That matters: if the script ran before the paragraph existed, `getElementById` would find nothing.

## Script in `<head>` or `<body>`?

You can put scripts in either place, but the position changes *when* the code runs.

- A script at the **end of `<body>`** runs after all the elements above it have been created. This is the simplest safe choice.
- A script in the **`<head>`** runs before the body exists, so it cannot touch page elements unless you delay it (see `defer` below).

```example html
<!DOCTYPE html>
<html>
<head>
  <script>
    // The body does not exist yet, so we wait for the page to load
    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("town").textContent = "Eldoret";
    });
  </script>
</head>
<body>
  <p>Our newest branch is in <strong id="town">...</strong></p>
</body>
</html>
```

## External JavaScript files

On real websites, JavaScript usually lives in its own file with a `.js` extension. You link it with the `src` attribute:

```html
<!DOCTYPE html>
<html>
<head>
  <script src="js/app.js" defer></script>
</head>
<body>
  <h1>Welcome</h1>
</body>
</html>
```

And the file `js/app.js` contains plain JavaScript – no `<script>` tags:

```javascript
console.log("app.js has loaded");
```

External files have big advantages:

- HTML and JavaScript stay separate, so both are easier to read.
- One file can be shared by every page on your site.
- The browser **caches** the file, so later pages load faster – important for visitors on mobile data.

> **Warning:** A `<script>` tag with a `src` attribute ignores any code written between its opening and closing tags. Use one or the other, never both.

## The `defer` attribute

Adding `defer` to an external script tells the browser: "download this file now, but run it only after the HTML has been fully read". This lets you keep the `<script>` in the `<head>` while still safely accessing page elements. It is the recommended modern approach.

There is also `async`, which runs the script as soon as it downloads, in no guaranteed order. It suits independent scripts like analytics, but `defer` is the better default for your own code.

## Inline event attributes

You may see JavaScript written straight inside an HTML attribute such as `onclick`:

```example html
<!DOCTYPE html>
<html>
<body>
  <button onclick="this.textContent = 'Asante!'">Click to say thanks</button>
</body>
</html>
```

This works for tiny demos, but it mixes code with markup and becomes messy quickly. In the [Events](events) lesson you will learn the cleaner `addEventListener` approach.

## Comments

Comments are notes for humans; the browser ignores them. JavaScript has two kinds:

```example javascript
// A single-line comment starts with two slashes
console.log("Line one"); // a comment can follow code too

/*
  A multi-line comment
  can span several lines.
*/
console.log("Line two");
```

```output
Line one
Line two
```

> **Tip:** Use comments to explain *why* the code does something, not *what* it does. Good code should already make the "what" clear.

## Statements and semicolons

A JavaScript program is a list of **statements**, usually one per line, ending with a semicolon `;`. JavaScript can often insert missing semicolons for you, but writing them yourself avoids some rare, confusing bugs. This tutorial always uses them.

JavaScript is **case sensitive**: `price`, `Price` and `PRICE` are three different names, and `Console.log` will not work.

```exercise
Q: Link the external file "main.js" so that it runs after the HTML is read.
<script ___="main.js" ___></script>
A: src | defer
H: One attribute gives the file path; the other delays running it.
```

```exercise
Q: Complete the single-line comment.
___ Calculate the delivery fee
A: //
```
