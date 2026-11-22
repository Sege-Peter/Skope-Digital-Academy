---
title: JavaScript Output
module: 1
minutes: 9
---

A program is only useful if it can show you something. JavaScript has several ways to display data, and each suits a different job:

| Method | Where the output goes | Typical use |
|---|---|---|
| `console.log()` | The developer console | Checking values while you code |
| `element.textContent` | Inside an HTML element | Showing results to visitors |
| `element.innerHTML` | Inside an HTML element, as HTML | Inserting markup you control |
| `alert()` | A pop-up box | Quick messages (avoid in real apps) |

## console.log()

`console.log()` is the tool you will use most while learning. It prints whatever you give it, and you can pass several values separated by commas – they are printed with a space between them.

```example javascript
console.log("Matatu fare:", 100, "shillings");
console.log(2 + 3);
console.log("2" + "3");
```

```output
Matatu fare: 100 shillings
5
23
```

Look closely at the last two lines. Adding two numbers gives `5`, but "adding" two pieces of text joins them, giving `23`. You will learn why in [Operators](operators).

In a browser, open the console with **F12** (or **Ctrl+Shift+I**, or **Cmd+Option+I** on a Mac) and choose the *Console* tab. In this tutorial's editor, console output appears under the result automatically.

### Other console methods

The console has a few relatives of `log` that help organise your output:

```example javascript
console.info("Server started");
console.warn("Stock for unga is low");
console.error("Payment failed");
```

```output
Server started
Stock for unga is low
Payment failed
```

In browser developer tools, warnings appear in yellow and errors in red, which makes problems easy to spot.

## Writing into an HTML element

Visitors never look at the console, so to show them something you change the page itself. First select the element (usually by its `id`), then set its `textContent`.

```example html
<!DOCTYPE html>
<html>
<body>
  <h2>Order summary</h2>
  <p id="total"></p>

  <script>
    const items = 4;
    const pricePerItem = 250;
    document.getElementById("total").textContent =
      "You owe KES " + items * pricePerItem;
  </script>
</body>
</html>
```

### textContent vs innerHTML

`textContent` treats the value as plain text. `innerHTML` treats it as HTML, so tags are turned into real elements.

```example html
<!DOCTYPE html>
<html>
<body>
  <p id="a"></p>
  <p id="b"></p>

  <script>
    document.getElementById("a").textContent = "<strong>Nakuru</strong>";
    document.getElementById("b").innerHTML = "<strong>Nakuru</strong>";
  </script>
</body>
</html>
```

The first paragraph shows the tags literally; the second shows **Nakuru** in bold.

> **Warning:** Never put text typed by users into `innerHTML`. Someone could type a `<script>` or other harmful markup and it would become part of your page. Use `textContent` for anything that comes from a user.

## Writing straight to the page body

In small experiments, you can add elements directly to `document.body`. This is handy in `javascript` examples, which run inside an empty page.

```example javascript
const heading = document.createElement("h3");
heading.textContent = "Karibu Kisumu";
document.body.append(heading);
console.log("Heading added");
```

```output
Heading added
```

You will learn `createElement` and `append` in detail in [The DOM](dom) lesson.

## alert()

`alert("Hello")` opens a small pop-up box that the user must close before doing anything else. It is easy to use, but it blocks the whole page and annoys visitors, so professional sites rarely use it. The example editor on this site also blocks pop-ups, so we will use `console.log()` and page text instead throughout this tutorial.

> **Note:** You might also see `document.write()` in old tutorials. It can wipe out the whole page if called after loading, so avoid it in modern code.

## Displaying several values neatly

A **template literal** – text inside backticks `` ` `` – lets you drop values into a string with `${...}`. It is much tidier than joining pieces with `+`.

```example javascript
const person = "Wanjiru";
const town = "Nyeri";
console.log(`${person} lives in ${town}.`);
```

```output
Wanjiru lives in Nyeri.
```

Template literals are covered fully in [Strings](strings).

```exercise
Q: Print the value of the variable total to the console.
console.___(___);
A: log | total
```

```exercise
Q: Set the plain text of the element with id "msg".
document.getElementById("msg").___ = "Saved!";
A: textContent / innerText
H: Use the safe property that treats the value as plain text.
```
