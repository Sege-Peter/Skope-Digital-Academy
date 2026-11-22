---
title: HTML Tag Reference
module: 6
module_title: Reference and Quiz
minutes: 10
---

This page lists the HTML elements you are most likely to use, grouped by purpose. Use it as a quick reminder while you build. Each element is current HTML5; obsolete tags are listed at the end so you can recognise them in old code.

## Document structure

| Tag | Description |
|---|---|
| `<!DOCTYPE html>` | Declares an HTML5 document; must be the first line |
| `<html>` | The root element that wraps the whole page |
| `<head>` | Container for information about the page |
| `<body>` | Container for all visible content |
| `<!-- ... -->` | A comment, ignored by the browser |

## Metadata (inside head)

| Tag | Description |
|---|---|
| `<title>` | Title shown on the browser tab and in search results |
| `<meta>` | Page information such as charset, viewport and description |
| `<link>` | Connects an external resource, such as a stylesheet or icon |
| `<style>` | CSS written inside the page |
| `<script>` | JavaScript code, or a link to a script file |
| `<noscript>` | Content shown when JavaScript is turned off |
| `<base>` | Base URL for all relative links in the page |

## Sections and layout

| Tag | Description |
|---|---|
| `<header>` | Introductory content for the page or a section |
| `<nav>` | A block of major navigation links |
| `<main>` | The main, unique content of the page |
| `<section>` | A themed group of content, usually with a heading |
| `<article>` | A self-contained piece of content, such as a post or story |
| `<aside>` | Content related to, but separate from, the main content |
| `<footer>` | Closing content for the page or a section |
| `<address>` | Contact information |
| `<h1>` to `<h6>` | Headings, from most to least important |
| `<div>` | Generic block container with no meaning |

## Text content

| Tag | Description |
|---|---|
| `<p>` | A paragraph |
| `<br>` | A line break |
| `<hr>` | A thematic break between paragraphs or sections |
| `<pre>` | Preformatted text; spaces and line breaks are kept |
| `<blockquote>` | A long quotation from another source |
| `<ul>` | An unordered (bulleted) list |
| `<ol>` | An ordered (numbered) list |
| `<li>` | A list item |
| `<dl>` | A description list |
| `<dt>` | A term in a description list |
| `<dd>` | The description of a term |
| `<figure>` | Self-contained content such as an image or chart |
| `<figcaption>` | A caption for a figure |

## Inline text

| Tag | Description |
|---|---|
| `<a>` | A hyperlink |
| `<strong>` | Text of strong importance |
| `<em>` | Emphasised text (stress) |
| `<b>` | Text that draws attention without extra importance |
| `<i>` | Text in an alternative voice, such as a foreign word |
| `<mark>` | Highlighted text |
| `<small>` | Side comments and fine print |
| `<del>` | Deleted text |
| `<ins>` | Inserted text |
| `<s>` | Text that is no longer accurate |
| `<sub>` | Subscript text |
| `<sup>` | Superscript text |
| `<code>` | A fragment of computer code |
| `<kbd>` | Keyboard input, such as a key name |
| `<samp>` | Sample output from a program |
| `<var>` | A variable in maths or programming |
| `<q>` | A short inline quotation |
| `<cite>` | The title of a creative work |
| `<abbr>` | An abbreviation or acronym |
| `<time>` | A date or time |
| `<data>` | Content with a machine-readable value |
| `<span>` | Generic inline container with no meaning |
| `<wbr>` | A suggested place to break a long word |

## Images and media

| Tag | Description |
|---|---|
| `<img>` | An image |
| `<picture>` | Offers several image sources for different screens or formats |
| `<source>` | A media file option for `<picture>`, `<video>` or `<audio>` |
| `<video>` | A video player |
| `<audio>` | An audio player |
| `<track>` | Captions or subtitles for media |
| `<iframe>` | Embeds another web page |
| `<embed>` | Embeds external content such as a PDF |
| `<object>` | Embeds an external resource |
| `<canvas>` | A drawing area for graphics made with JavaScript |
| `<svg>` | Scalable vector graphics written inside the page |
| `<map>` | An image map with clickable areas |
| `<area>` | A clickable area inside an image map |

## Tables

| Tag | Description |
|---|---|
| `<table>` | A table |
| `<caption>` | A title for the table |
| `<thead>` | Groups the header rows |
| `<tbody>` | Groups the body rows |
| `<tfoot>` | Groups the footer (summary) rows |
| `<tr>` | A table row |
| `<th>` | A header cell |
| `<td>` | A data cell |
| `<colgroup>` | Groups columns for styling |
| `<col>` | Properties for one column inside a colgroup |

## Forms

| Tag | Description |
|---|---|
| `<form>` | A form that collects user input |
| `<input>` | An input control; its type is set by the `type` attribute |
| `<label>` | A caption for a form control |
| `<textarea>` | A multi-line text box |
| `<select>` | A drop-down list |
| `<option>` | An option in a select or datalist |
| `<optgroup>` | A group of options in a select |
| `<button>` | A clickable button |
| `<fieldset>` | Groups related form controls |
| `<legend>` | A caption for a fieldset |
| `<datalist>` | A list of suggested values for an input |
| `<output>` | The result of a calculation |
| `<progress>` | A progress bar |
| `<meter>` | A measurement within a known range, such as battery level |

## Interactive

| Tag | Description |
|---|---|
| `<details>` | A disclosure widget the user can open and close |
| `<summary>` | The visible heading of a details element |
| `<dialog>` | A dialog box or pop-up window |
| `<template>` | Hidden HTML that JavaScript can copy and use later |

## Obsolete tags to avoid

You may meet these in old websites. Do not use them in new pages.

| Tag | Use instead |
|---|---|
| `<font>` | CSS `font-family`, `color` and `font-size` |
| `<center>` | CSS `text-align: center` or Flexbox |
| `<big>` | CSS `font-size` |
| `<strike>` | `<del>` or `<s>` |
| `<tt>` | `<code>`, `<kbd>` or CSS |
| `<frame>`, `<frameset>` | `<iframe>` or a normal page layout |
| `<marquee>` | CSS animation, or no moving text at all |
| `<acronym>` | `<abbr>` |

> **Tip:** Bookmark this page. When you are unsure which element fits, look for the one whose description matches the *meaning* of your content, then style it with CSS.
