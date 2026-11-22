---
title: HTML Introduction
module: 1
module_title: Getting Started
minutes: 8
---

HTML is the starting point for anyone who wants to build for the web. Every website you visit, from a news site in Nairobi to an online shop in Kampala, is built on HTML. In this tutorial you will learn it step by step, with examples you can edit and run straight away.

## What is HTML?

**HTML** stands for **HyperText Markup Language**. It is the language used to describe the *structure* of a web page.

- **HyperText** means text that contains links to other text. Clicking a link takes you to another page, and that idea is what makes the web a "web".
- **Markup** means you surround pieces of content with labels that say what each piece is: "this is a heading", "this is a paragraph", "this is a picture".
- **Language** means there is a fixed set of rules and words (called *tags*) that browsers understand.

HTML is not a programming language. It has no loops, no maths and no decisions. It simply describes content. That is good news: it is one of the easiest technical skills to pick up.

## A first look

Here is a very small HTML page. Press **Try it Yourself** to open it in the editor, change the text, and run it again.

```example html
<!DOCTYPE html>
<html>
<head>
  <title>Karibu</title>
</head>
<body>
  <h1>Welcome to Kisumu</h1>
  <p>Kisumu is a city on the shores of Lake Victoria.</p>
</body>
</html>
```

Notice the words inside angle brackets, such as `<h1>` and `<p>`. These are **tags**. The `<h1>` tag marks a main heading and the `<p>` tag marks a paragraph. Most tags come in pairs: an opening tag like `<p>` and a closing tag like `</p>` with a forward slash.

## How browsers use HTML

When you type an address into a browser such as Chrome, Firefox or Edge, this is roughly what happens:

1. The browser asks a web server for the page.
2. The server sends back a file of HTML (plain text).
3. The browser reads the HTML from top to bottom and builds a model of the page in memory.
4. The browser draws (renders) that model on the screen as headings, paragraphs, images and links.

You never see the tags themselves on a normal web page. The browser uses them as instructions and shows only the result.

> **Tip:** In most desktop browsers you can right-click any page and choose **View Page Source** to see the HTML behind it. It is a great way to learn.

## HTML, CSS and JavaScript

Modern websites are made from three languages that work together:

| Language | Job | Example |
|---|---|---|
| HTML | Structure and meaning of the content | "This is a heading, this is a list" |
| CSS | Appearance and layout | "Headings are green and centred" |
| JavaScript | Behaviour and interaction | "Show a message when the button is clicked" |

A simple way to remember it: HTML is the walls and rooms of a house, CSS is the paint and furniture, and JavaScript is the electricity that makes things work.

This tutorial focuses on HTML. You will see a little CSS in some examples, but only to make the result easier to see.

## A slightly bigger page

The example below uses a heading, two paragraphs and a list. Do not worry about every tag yet; each one gets its own lesson.

```example html
<!DOCTYPE html>
<html>
<head>
  <title>My Favourite Places</title>
</head>
<body>
  <h1>My Favourite Places in Kenya</h1>
  <p>Kenya has beaches, mountains and great cities.</p>
  <p>Here are three places I would like to visit:</p>
  <ul>
    <li>Diani Beach</li>
    <li>Mount Kenya</li>
    <li>Lamu Old Town</li>
  </ul>
</body>
</html>
```

## A short history

HTML was created by Tim Berners-Lee in the early 1990s so that scientists could share linked documents. Since then it has grown through several versions. Today we use **HTML5**, which is maintained as a "living standard" that is updated continuously rather than released in big numbered versions. Everything in this tutorial is current HTML5.

> **Note:** You do not need to install anything special to learn HTML. A browser and a text editor are enough, and in this tutorial the built-in editor is all you need.

## What you will learn

By the end of this tutorial you will be able to:

- Write a complete, valid HTML page from scratch
- Add headings, text, links, images, lists and tables
- Build forms that collect information
- Structure a page with semantic elements like `<header>` and `<footer>`
- Embed audio and video and make your pages accessible to everyone

Move on to the next lesson to set up an editor and write your [first page](editors).

```exercise
Q: Complete the full name of HTML.
HTML = HyperText ___ Language
A: Markup
H: It is the word that describes "labelling" content with tags.
```

```exercise
Q: Close the paragraph tag correctly.
<p>Mombasa is on the coast.<___>
A: /p
H: A closing tag repeats the tag name after a forward slash.
```
