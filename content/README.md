# Tutorial content format

Free tutorials (the W3Schools-style library at `/learn`) live here as plain files and are
loaded into the database with:

    php import_tutorials.php            # add new tutorials/lessons, update changed ones
    php import_tutorials.php --dry      # show what would change

Each tutorial is one folder: `content/tutorials/<slug>/`.

## tutorial.json

```json
{
  "title": "HTML Tutorial",
  "short": "HTML",
  "description": "One or two sentences shown on the tutorials page.",
  "category": "Technology",
  "level": "beginner",
  "order": 1
}
```

`short` is the label on the tutorials bar (keep it to one word). `order` sorts the bar.

## Lesson files

One Markdown file per lesson, named `NN-lesson-slug.md` (`01-introduction.md`, `02-editors.md`, …).
The number sets the order. Each starts with front matter:

```
---
title: HTML Introduction
module: 1
module_title: Getting Started
minutes: 10
---
```

* `module` groups lessons in the sidebar (1, 2, 3…); `module_title` names the group
  (only needed on the first lesson of each module).
* `minutes` is the estimated reading time.

## Lesson body (Markdown subset)

Do not repeat the title as a `#` heading; it comes from the front matter.

| Write | Renders as |
|---|---|
| `## Heading`, `### Sub-heading` | section headings |
| blank-line separated text | paragraphs |
| `**bold**`, `*italic*`, `` `code` `` | inline formatting |
| `[text](https://…)` or `[text](lesson-slug)` | links (a bare `lesson-slug` links to that lesson of the same tutorial) |
| `- item` / `1. item` | lists |
| pipe tables (`\| a \| b \|` + `\|---\|---\|`) | tables (use for reference pages) |
| `> **Note:** …` / `> **Tip:** …` / `> **Warning:** …` | coloured callout boxes |
| ```` ```html ```` … ```` ``` ```` | a highlighted code sample (display only) |

### Runnable examples

````
```example html
<!DOCTYPE html>
<html>
<body>
  <h1>My First Heading</h1>
</body>
</html>
```
````

Renders as an **Example** box with a **Try it Yourself »** button that opens the code in the
online editor. Supported languages and how they run:

| Language tag | Runs in the editor? |
|---|---|
| `html` | Yes – full page in a sandbox (put CSS in `<style>` and JS in `<script>`) |
| `css` | Yes – applied to a small demo page; prefer `html` with a `<style>` block so learners see the markup |
| `javascript` | Yes – `console.log` output is shown under the result |
| `python` | Yes – real Python 3 (Pyodide) in the browser; standard library only; **no `input()`** |
| `sql` | Yes – SQLite against the Skope Shop sample database (`sql/_sample_db.sql`) |
| `php` | No – shown with its output (add an `output` block, below) |

To show the expected result under an example (required for `php`, useful for `python`):

````
```output
Hello, Kisumu!
```
````

An `output` block directly after an `example` block is attached to it.

### Exercises

````
```exercise
Q: Add the missing tag so the text is bold.
<___>Karibu!</___>
A: b | b
H: The tag name is a single letter.
```
````

* `Q:` the instruction (one line).
* Following lines: the code, with `___` (three underscores) for each blank.
* `A:` the answers, in order, separated by ` | `. Alternatives for one blank are separated by ` / `
  (e.g. `A: let / const`). Answers are compared ignoring case and surrounding spaces.
* `H:` optional hint.

### Quizzes

Usually in the last lesson of a tutorial:

````
```quiz
Q: What does HTML stand for?
- Hyper Text Markup Language *
- High Tech Modern Language
- Hyperlink and Text Management Language

Q: Which tag makes the largest heading?
- <h6>
- <h1> *
- <head>
```
````

Questions are separated by a blank line; mark the correct option with a trailing ` *`.

## Writing guidelines

* Original wording only – never copy text from other tutorial sites.
* Short paragraphs, one idea per section, an example for every concept.
* Examples must be correct and actually run in the editor as described above.
* Kenyan / East African names and places are welcome in examples.
