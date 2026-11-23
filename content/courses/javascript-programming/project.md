---
title: Interactive Price List for a Shop
pass_pct: 70
reviews_required: 3
---

## The situation

Achieng owns "Achieng's Cereals", a made-up shop in Kisii that sells maize, beans, rice and millet. Customers keep asking the price of things on WhatsApp. She wants a simple web page where people can search the products, filter by price and see how many items match. You will build it in plain JavaScript as a portfolio piece, using made-up products and example prices.

> **Note:** Use made-up details only. Do not put a real phone number, email, address, password or API key in your code or page. Write "07XX XXX XXX" if you add a contact line.

## What you will produce

1. **A working page** (HTML, CSS and JavaScript in one file, or three files) with:
   - an array of 8–10 product objects, each with at least a `name`, `price` (example KES figures) and `category`,
   - a list of products shown on the page by JavaScript,
   - a text box that filters products as the user types,
   - a second control (a "max price" box, a category dropdown or an "in stock only" tick-box),
   - a line saying how many products are showing, for example "4 products found",
   - a clear message when nothing matches.
2. **A bonus feature of your choice** (optional but recommended), such as saving the last search with `localStorage` or sorting by price.
3. **A short explanation** – 100–150 words: how the filtering works, which array method you used (`filter`, `map`, `reduce` or others), one bug you fixed, and one thing you would add next.

## Steps

1. Re-read the lessons on arrays and objects, the to-do list app and the product catalogue project.
2. Write the product array first and print it to the console to check it.
3. Show the products on the page, then add the search box.
4. Add the second filter and the count line.
5. Test: empty search, a word with capital letters, a word that matches nothing.
6. Put your work on CodePen or GitHub and write your explanation.

## What to submit

- Your code pasted in the text box **and/or** a public CodePen or GitHub (Pages) link.
- Your short explanation in the text box.
- Optional: a screenshot (PNG or JPG, max 5 MB).

## Tips

- Change the text to lowercase before comparing, so "Rice" and "rice" both match.
- Keep one function to draw the list and call it after every change.
- Use comments so a peer can follow your code.

```rubric
Data and display | An array of 8–10 objects is rendered correctly on the page | 4
Search and filters | Search plus a second filter both work and can be combined | 6
Feedback to user | Count line and a no-results message appear correctly | 3
Code quality and explanation | Readable names and comments, and an accurate 100–150 word explanation | 5
Privacy and presentation | Made-up details only, and the link or code opens and runs | 2
```
