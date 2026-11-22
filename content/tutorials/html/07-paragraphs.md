---
title: Paragraphs, Line Breaks and Text Formatting
module: 2
minutes: 11
---

Most web content is text. In this lesson you will learn how to split text into paragraphs, break lines where you need them, and mark words as important, emphasised, quoted or technical.

## Paragraphs

The `<p>` element defines a paragraph. Browsers start each paragraph on a new line and add some space above and below it.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Paragraphs</title>
</head>
<body>
  <p>Mount Kilimanjaro is the highest mountain in Africa.</p>
  <p>It stands in northern Tanzania, close to the border with Kenya.</p>
  <p>Thousands of climbers attempt to reach the summit every year.</p>
</body>
</html>
```

Remember that extra spaces and line breaks in your code are collapsed into a single space. Pressing Enter in your editor does not create a new paragraph; only a new `<p>` element does.

## Line breaks

Sometimes you need a new line *without* starting a new paragraph, for example in an address or a poem. Use the `<br>` element for that.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Line Breaks</title>
</head>
<body>
  <p>
    Skope Digital Academy<br>
    Oginga Odinga Street<br>
    Kisumu, Kenya
  </p>
</body>
</html>
```

> **Warning:** Do not use several `<br>` elements in a row to create space between blocks of content. That is a job for CSS (`margin` or `padding`). Use `<br>` only where a line break is part of the content itself.

## Preformatted text

The `<pre>` element keeps every space and line break exactly as you typed them, and shows the text in a monospace font. It is useful for code samples and text art.

```html
<pre>
Bus timetable
Nairobi   08:00
Nakuru    10:30
Eldoret   13:15
</pre>
```

## Text formatting elements

HTML has elements that give words special *meaning*. Browsers also give each a default look.

| Element | Meaning | Default look |
|---|---|---|
| `<strong>` | Strong importance, seriousness or urgency | **Bold** |
| `<em>` | Stress emphasis (changes how a sentence is read) | *Italic* |
| `<mark>` | Highlighted or relevant text | Yellow background |
| `<small>` | Side comments and fine print | Smaller text |
| `<del>` | Text that has been removed | Strikethrough |
| `<ins>` | Text that has been added | Underlined |
| `<sub>` | Subscript | Lowered, small |
| `<sup>` | Superscript | Raised, small |
| `<code>` | A piece of computer code | Monospace font |
| `<b>` | Draws attention, without extra importance | Bold |
| `<i>` | Alternative voice, e.g. a foreign word or a term | Italic |

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Text Formatting</title>
</head>
<body>
  <p><strong>Warning:</strong> the road to Amboseli is closed after heavy rain.</p>
  <p>I said we meet at <em>six</em>, not seven!</p>
  <p>Search results for "ugali": Best <mark>ugali</mark> in town.</p>
  <p>Price: <del>KSh 1,200</del> <ins>KSh 950</ins></p>
  <p>Water is H<sub>2</sub>O and the area is 50 m<sup>2</sup>.</p>
  <p>Type <code>print("Habari")</code> to say hello in Python.</p>
  <p><small>Terms and conditions apply.</small></p>
  <p>The word <i lang="sw">harambee</i> means "pulling together".</p>
</body>
</html>
```

## strong vs b, em vs i

The pairs look the same, but they mean different things:

- Use `<strong>` when the words are genuinely important, like a warning. Use `<b>` only to draw the eye without adding importance, such as product names in a review.
- Use `<em>` when spoken stress would change the meaning of a sentence. Use `<i>` for text in a different voice: foreign words, technical terms, or a ship's name.

Screen readers and search engines can pay attention to these meanings, so choose by meaning, not by appearance.

> **Tip:** If you only want text to *look* bold or italic for design reasons, use CSS (`font-weight` or `font-style`) instead of any of these elements.

## Quotations

For a short quote inside a sentence, use `<q>`; the browser adds quotation marks. For a longer quote that stands on its own, use `<blockquote>`, which is usually indented. The `<cite>` element marks the title of a work.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quotations</title>
</head>
<body>
  <p>As the saying goes, <q>Haba na haba hujaza kibaba</q>: little by little fills the pot.</p>

  <blockquote>
    <p>Education is the key that unlocks the golden door to freedom.</p>
  </blockquote>

  <p>My favourite novel is <cite>The River Between</cite> by Ngũgĩ wa Thiong'o.</p>
</body>
</html>
```

## Abbreviations

The `<abbr>` element marks an abbreviation. Add a `title` to give the full form, which appears as a tooltip.

```html
<p>The <abbr title="Kenya Revenue Authority">KRA</abbr> deadline is 30 June.</p>
```

## Avoid old presentational tags

Older tutorials may show tags like `<font>`, `<center>`, `<big>` or `<strike>`. These are obsolete in HTML5. Use CSS for fonts, alignment and sizes, and `<del>` or `<s>` for struck-out text.

```exercise
Q: Mark the warning word as strongly important.
<p><___>Danger:</___> deep water.</p>
A: strong | /strong
H: It is the meaningful version of bold.
```

```exercise
Q: Write the area unit "m squared" using the right element.
<p>The plot is 450 m<___>2</___>.</p>
A: sup | /sup
H: The 2 should be raised above the line.
```
