---
title: HTML Accessibility Basics
module: 5
minutes: 11
---

**Accessibility** (often shortened to *a11y*) means building pages that everyone can use, including people who are blind, have low vision, cannot use a mouse, are deaf, or have learning difficulties. Good HTML does most of the work for you.

## Who benefits?

- A blind student in Kisumu using a **screen reader** that reads the page aloud
- Someone with a broken arm who can only use the **keyboard**
- An older person who needs to **zoom** the text to 200%
- A farmer checking prices on a **cracked phone screen** in bright sunlight
- A viewer who is **deaf** or watching a video without sound

Accessibility features help these people directly, and they usually make the site better for everyone else too.

## 1. Use the right element for the job

Native HTML elements come with built-in accessibility. A `<button>` can be reached with the Tab key, pressed with Enter or Space, and is announced as "button" by screen readers. A `<div>` styled to look like a button gets none of that.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Buttons</title>
  <style>
    .fake { display: inline-block; background: #2a9d8f; color: white; padding: 8px 14px; cursor: pointer; }
    button { background: #2a9d8f; color: white; padding: 8px 14px; border: none; font-size: 16px; }
  </style>
</head>
<body>
  <p>Press Tab repeatedly. Which one can you reach with the keyboard?</p>
  <div class="fake" onclick="alert('Div clicked')">Fake button (div)</div>
  <button type="button" onclick="alert('Button clicked')">Real button</button>
</body>
</html>
```

Only the real button receives keyboard focus. Rule of thumb: use `<a>` for going somewhere, and `<button>` for doing something.

## 2. Give every image a text alternative

Describe meaningful images with `alt`, and give decorative images `alt=""` so screen readers skip them. See the lesson on [images](images) for examples.

## 3. Label every form control

Every input needs a `<label>` connected with `for` and `id`. Group related radio buttons and checkboxes in a `<fieldset>` with a `<legend>`.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Accessible Form</title>
</head>
<body>
  <form>
    <label for="idno">National ID number</label>
    <input type="text" id="idno" name="idno" inputmode="numeric" aria-describedby="idno-help" required>
    <p id="idno-help">Enter the ID number printed on the front of your card, without spaces.</p>

    <fieldset>
      <legend>Preferred contact</legend>
      <label><input type="radio" name="contact" value="sms"> SMS</label>
      <label><input type="radio" name="contact" value="call"> Phone call</label>
    </fieldset>

    <button type="submit">Continue</button>
  </form>
</body>
</html>
```

The `aria-describedby` attribute links the help text to the input, so screen readers read it out when the field is focused.

## 4. Use headings and landmarks

A logical heading order (one `<h1>`, then `<h2>`, `<h3>`...) and semantic regions such as `<header>`, `<nav>`, `<main>` and `<footer>` let screen reader users jump around the page quickly. These were covered in [headings](headings) and [semantic layout](semantic-elements).

## 5. Set the page language

`<html lang="en">` (or `lang="sw"` for Kiswahili) tells screen readers which voice and pronunciation to use. If a phrase is in another language, mark it: `<span lang="sw">Hakuna matata</span>`.

## 6. Write link text that makes sense alone

"Click here" repeated ten times is useless when a screen reader lists all the links. Write "Download the admission form" instead.

## 7. Make sure colours have enough contrast

Light grey text on a white background is hard for many people to read, especially outdoors on a phone. Aim for a contrast ratio of at least **4.5 to 1** for normal text. Free contrast checkers are available online, and Chrome DevTools shows the ratio when you inspect text.

Also, never use colour as the *only* way to show information. "Fields in red are required" fails for colour-blind users; add the word "required" or an asterisk with an explanation.

## 8. Keep keyboard focus visible

When you press Tab, browsers draw an outline around the focused element. Do not remove it with CSS (`outline: none`) unless you replace it with an equally clear style.

## A word on ARIA

**ARIA** (Accessible Rich Internet Applications) is a set of attributes like `aria-label`, `aria-describedby` and `role` that add extra information for assistive technology. It is powerful, but the first rule of ARIA is: **if a native HTML element does the job, use it instead.** Incorrect ARIA can make a page *less* accessible.

A good, simple use is labelling an icon-only button:

```html
<button type="button" aria-label="Close menu">✕</button>
```

## Quick checklist

| Check | How |
|---|---|
| Keyboard only | Unplug the mouse and use Tab, Shift+Tab, Enter and Space |
| Zoom | Zoom to 200% and check nothing overlaps or disappears |
| Images | Every `<img>` has a suitable `alt` |
| Forms | Every field has a visible, connected label |
| Headings | One `<h1>`, no skipped levels |
| Contrast | Text is easy to read on its background |
| Media | Videos have captions |

> **Tip:** Chrome's built-in **Lighthouse** tool (in DevTools) runs an automatic accessibility audit. Automated tools catch only some problems, so always test with the keyboard too.

```exercise
Q: Give the icon-only button an accessible name.
<button type="button" ___="Search">🔍</button>
A: aria-label
H: It is an ARIA attribute that provides a label.
```

```exercise
Q: Mark the Kiswahili phrase so screen readers pronounce it correctly.
<p>Our motto is <span ___="sw">Pamoja tunaweza</span>.</p>
A: lang
H: The same attribute you put on the html element.
```
