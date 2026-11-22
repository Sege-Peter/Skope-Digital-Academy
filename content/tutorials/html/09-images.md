---
title: HTML Images
module: 2
minutes: 11
---

Images make a page come alive: product photos, logos, maps and diagrams. In this lesson you will learn how to add images, describe them properly, control their size and pick the right file format.

## The img element

Images are added with the `<img>` element. It is a void element, so it has no closing tag. Two attributes are essential:

- `src` (source): the location of the image file
- `alt` (alternative text): a short description of the image

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Images</title>
</head>
<body>
  <h1>Our Placeholder Photo</h1>
  <img src="https://picsum.photos/400/250" alt="A random landscape photograph" width="400" height="250">
  <p>The photo above is loaded from another website.</p>
</body>
</html>
```

> **Note:** The example uses a free placeholder image service, so you will see a different photo each time. On your own site, `src` would point to your image file, such as `images/shop-front.jpg`.

## The alt attribute

The `alt` text is used when the image cannot be seen:

- Screen readers read it aloud to blind and partially sighted visitors.
- It appears in place of the image if the file fails to load, which happens often on slow mobile connections.
- Search engines use it to understand the image.

Good alt text describes what the image shows *and why it is there*, in a short phrase.

| Image | Weak alt | Better alt |
|---|---|---|
| Shop front photo | `alt="image1"` | `alt="Mama Njeri's grocery shop in Githurai"` |
| Company logo in a link to home | `alt="logo"` | `alt="Skope Digital Academy home"` |
| Chart of sales | `alt="chart"` | `alt="Sales rose from 200 to 450 bags between January and March"` |

If an image is purely decorative (a pattern or a divider), use an empty alt: `alt=""`. That tells screen readers to skip it. Never leave the attribute out completely.

## What happens when an image is missing?

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Broken Image</title>
</head>
<body>
  <p>This image file does not exist, so the browser shows the alt text:</p>
  <img src="no-such-file.jpg" alt="Fishermen pulling a boat onto the shore at Dunga Beach, Kisumu">
</body>
</html>
```

## Width and height

Set `width` and `height` in pixels (no `px` needed in the attribute). Even if you later change the size with CSS, including them lets the browser reserve the right amount of space before the image downloads. That stops the page from jumping around as it loads.

To make an image shrink on small screens, add a little CSS:

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Responsive Image</title>
  <style>
    img { max-width: 100%; height: auto; }
  </style>
</head>
<body>
  <h2>Responsive image</h2>
  <img src="https://picsum.photos/1200/600" alt="Wide landscape photograph" width="1200" height="600">
  <p>Resize the window: the image never overflows its container.</p>
</body>
</html>
```

> **Tip:** `max-width: 100%; height: auto;` is one of the most useful lines of CSS you will learn. It makes images fit phones without stretching them.

## Image paths

The `src` attribute follows the same rules as link addresses:

- `photo.jpg` is in the same folder as the page.
- `images/photo.jpg` is in an `images` folder next to the page.
- `../photo.jpg` is in the parent folder.
- `https://example.com/photo.jpg` is on another site.

File names on web servers are often case-sensitive, so `Photo.JPG` and `photo.jpg` may be different files. Keep names lowercase to avoid trouble.

## Choosing a file format

| Format | Best for | Notes |
|---|---|---|
| JPEG (`.jpg`) | Photographs | Small files, no transparency |
| PNG (`.png`) | Logos, screenshots, sharp edges | Supports transparency; larger files |
| WebP (`.webp`) | Photos and graphics | Smaller than JPEG and PNG; supported by all modern browsers |
| SVG (`.svg`) | Icons, logos, simple drawings | Vector: stays sharp at any size |
| GIF (`.gif`) | Simple animations | Limited colours; often replaced by video |

Many visitors in East Africa browse on mobile data, so keep images small. Resize photos to the size you actually display and compress them before uploading.

## Lazy loading

Add `loading="lazy"` to images further down a page. The browser then waits until the visitor scrolls near them before downloading.

```html
<img src="gallery/maasai-mara.jpg" alt="Wildebeest crossing the Mara River" width="800" height="533" loading="lazy">
```

## Images with captions

To give an image a visible caption, wrap it in `<figure>` and add `<figcaption>`.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Figure and Caption</title>
</head>
<body>
  <figure>
    <img src="https://picsum.photos/320/200" alt="Sample landscape" width="320" height="200">
    <figcaption>Figure 1: A view from the hills above Kericho tea estates.</figcaption>
  </figure>
</body>
</html>
```

> **Warning:** Only use images you have the right to use. Your own photos, images you have paid for, or freely licensed images (with credit where required) are safe choices.

```exercise
Q: Complete the image element with its two essential attributes.
<img ___="logo.png" ___="Kisumu Fresh Juices logo">
A: src | alt
H: One gives the file location; the other describes the image.
```

```exercise
Q: Make the browser wait to load this image until the visitor scrolls near it.
<img src="gallery/lamu.jpg" alt="Dhows in Lamu harbour" ___="lazy">
A: loading
H: The attribute name describes when the image is fetched.
```
