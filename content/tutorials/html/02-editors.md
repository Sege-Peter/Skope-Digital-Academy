---
title: HTML Editors and Your First Page
module: 1
minutes: 10
---

An HTML file is just plain text saved with a `.html` ending. That means you can write HTML in almost any text editor. In this lesson you will choose an editor, write your first page, save it and open it in a browser.

## Choosing an editor

You can start with the simplest tool on your computer, but a code editor will make life much easier.

| Editor | Platform | Good for |
|---|---|---|
| Notepad | Windows | Absolute beginners; nothing to install |
| TextEdit | macOS | Quick tests (switch to plain text mode first) |
| Visual Studio Code | Windows, macOS, Linux | Free, colour highlighting, auto-complete, extensions |
| Online editors | Any browser | Practising on a phone or shared computer |

Most professional web developers use **Visual Studio Code** (often called VS Code). It is free, it colours your tags so mistakes stand out, and it can close tags for you automatically.

> **Tip:** If you are learning on a phone or in a cyber café, the **Try it Yourself** editor on this site works in any modern browser. You do not need to install anything to follow this tutorial.

## Step 1: Write the page

Open your editor and type (or paste) the following:

```example html
<!DOCTYPE html>
<html>
<head>
  <title>My First Page</title>
</head>
<body>
  <h1>Hello, Nairobi!</h1>
  <p>This is my very first web page.</p>
</body>
</html>
```

Each line has a purpose, and the next lesson explains them in detail. For now, notice that the visible content sits between `<body>` and `</body>`.

## Step 2: Save the file

Save the file with these rules in mind:

1. Give it a name that ends in `.html`, for example `index.html` or `first-page.html`.
2. Use lowercase letters and hyphens instead of spaces. `my-page.html` is better than `My Page.html`.
3. Choose **UTF-8** as the encoding if your editor asks. This makes sure characters like "é", "€" or emojis display correctly.

> **Warning:** In Notepad, change **Save as type** to **All Files**. Otherwise Windows may save your file as `index.html.txt`, and the browser will show the raw text instead of a web page.

The name `index.html` is special: web servers show it automatically when someone visits a folder, so it is the usual name for a home page.

## Step 3: Open it in a browser

Find the file in your file manager and double-click it. It will open in your default browser. You should see a large heading and a line of text.

Now change something. Replace "Nairobi" with your own town, save the file again and press **F5** (or the refresh button) in the browser. The page updates. This edit, save, refresh loop is how you will work all the time.

## Making changes and seeing results

Try editing this slightly longer page. Add a third paragraph about your favourite food and run it.

```example html
<!DOCTYPE html>
<html>
<head>
  <title>About Me</title>
</head>
<body>
  <h1>About Me</h1>
  <p>My name is Achieng and I live in Kisumu.</p>
  <p>I am learning HTML so I can build a website for my mother's tailoring shop.</p>
</body>
</html>
```

## Viewing the source of any page

Browsers let you look behind the scenes of any website:

- **View Page Source** (right-click, or press `Ctrl + U`) shows the HTML exactly as the server sent it.
- **Inspect** (right-click on an element, or press `F12`) opens Developer Tools, where you can see how the browser understood each element and even edit it live.

Changes you make in Developer Tools are temporary and only on your screen, so you cannot break a real website this way. Feel free to experiment.

## Good habits from day one

- **Indent nested tags** with two or four spaces so you can see what is inside what.
- **Close what you open.** If you type `<p>`, type `</p>` too.
- **Use lowercase tag names.** HTML does not care about case, but lowercase is the common style and easier to read.
- **Save often** and refresh to check your work in small steps.

Next, let us look closely at the [page structure](page-structure) you just typed.

```exercise
Q: Complete the file name so the browser treats it as a web page.
index.___
A: html
H: It is the same as the name of the language, in lowercase.
```

```exercise
Q: Fill in the tag that holds all the visible content of the page.
<___>
  <h1>Hello, Eldoret!</h1>
</___>
A: body | /body
H: The opening tag and the closing tag (with a slash).
```
