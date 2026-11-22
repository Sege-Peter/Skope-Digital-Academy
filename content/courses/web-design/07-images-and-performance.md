---
title: Images and Performance
module: 3
module_title: Images, Forms and Accessibility
minutes: 12
---

Photos sell products. A good photo of a chocolate cake does more for Baraka Bakery than a paragraph of text. But images are also the main reason websites load slowly, and in Kenya many visitors pay for every megabyte of data. A designer's job is to make images look good **and** load fast.

## Adding an image properly

```html
<img src="images/chocolate-cake.webp"
     alt="Two-tier chocolate birthday cake with white icing"
     width="800" height="600"
     loading="lazy">
```

| Attribute | Why it matters |
|---|---|
| `src` | The path to the image file |
| `alt` | Describes the image for blind users and when the image fails to load; also helps search engines |
| `width` and `height` | Let the browser reserve space, so the page doesn't jump while loading |
| `loading="lazy"` | Delays loading images further down the page until the visitor scrolls near them |

> **Tip:** Don't use `loading="lazy"` on the main image at the very top of the page; you want that one to appear immediately.

## Choosing the right format

| Format | Best for |
|---|---|
| JPG | Photos; good quality at small sizes |
| PNG | Logos and graphics that need sharp edges or transparency |
| WebP | Photos and graphics; usually smaller than JPG and PNG at similar quality, supported by modern browsers |
| SVG | Simple logos and icons; stays sharp at any size and is often tiny |

## Resize and compress

A photo straight from a phone camera can be several megabytes and thousands of pixels wide. A website rarely displays it wider than about 1200–1600 pixels.

1. **Resize** the photo to the largest size it will actually be shown (for example 1200px wide for a full-width banner, 600px for a card).
2. **Compress** it with a free tool such as Squoosh, TinyPNG or the export settings in Canva.
3. **Aim small:** as a rough guide, try to keep most photos under about 200 KB.

Doing this can turn a 4 MB page into a few hundred kilobytes, which is faster and cheaper for every visitor.

## Images that fit their box

Product photos come in different shapes. `object-fit: cover` crops them neatly to the same size, so a row of cards stays tidy. This example uses simple coloured placeholder images built into the code so that it runs anywhere.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Image cards</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #fffaf3; padding: 16px; }
    .cards { display: flex; flex-wrap: wrap; gap: 16px; }
    .card { flex: 1 1 200px; background: #fff; border: 1px solid #ecd9c3; border-radius: 8px; overflow: hidden; }
    .card img {
      width: 100%;
      height: 160px;
      object-fit: cover;   /* crop to fill the box without stretching */
      display: block;
    }
    .card p { margin: 0; padding: 12px; font-weight: bold; color: #7a3e1d; }
  </style>
</head>
<body>
  <div class="cards">
    <div class="card">
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Crect width='400' height='300' fill='%23c98b4f'/%3E%3C/svg%3E" alt="Placeholder for a photo of bread loaves" width="400" height="300">
      <p>Fresh bread</p>
    </div>
    <div class="card">
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='500'%3E%3Crect width='300' height='500' fill='%235a2e14'/%3E%3C/svg%3E" alt="Placeholder for a tall photo of a chocolate cake" width="300" height="500">
      <p>Chocolate cake</p>
    </div>
    <div class="card">
      <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='600' height='300'%3E%3Crect width='600' height='300' fill='%23e8b86d'/%3E%3C/svg%3E" alt="Placeholder for a wide photo of mandazi" width="600" height="300">
      <p>Mandazi</p>
    </div>
  </div>
</body>
</html>
```

The three placeholder images have different shapes (normal, tall and wide), yet the cards line up because each image is cropped to 160px tall.

## Writing good alt text

- Describe what matters in the image: "Wedding cake with three white tiers and red roses", not "image1" or "cake photo".
- Keep it short, usually one sentence.
- For purely decorative images, use an empty `alt=""` so screen readers skip them.

## Other speed tips

- Use only the fonts and font weights you need.
- Avoid huge background videos and auto-playing sliders with many large photos.
- Test your page with **PageSpeed Insights** (from Google) after publishing; it suggests improvements.
- Check the total page size in Chrome's **Network** tab (F12) with "Disable cache" ticked.

> **Warning:** Always get permission to use photos. Use the client's own photos or take them yourself with their consent, and ask before photographing customers or staff. Pictures of children need extra care and permission from parents.

```exercise
Q: Complete the attribute that describes an image for screen readers.
<img src="cake.webp" ___="Chocolate birthday cake">
A: alt
H: Short for "alternative text".
```

```exercise
Q: Fill in the value that delays loading images lower down the page.
<img src="mandazi.webp" alt="Mandazi" loading="___">
A: lazy
H: The image is "lazy" until you scroll near it.
```

## Practice task

Collect three to five photos for your business site. Resize each to the size it will be displayed, compress it to WebP or JPG, and write clear alt text. Write down each file's size before and after; aim to cut the total by at least half.
