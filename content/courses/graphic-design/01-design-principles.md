---
title: What Graphic Design Is and Four Key Principles
module: 1
module_title: Design Foundations
minutes: 15
---

**Graphic design** is arranging words, images, shapes and colour to communicate a message clearly. A church poster, a matatu sticker, a school's report card, a chapati kiosk's price list and an Instagram advert are all graphic design. Good design is not about being "fancy" – it is about the viewer understanding the message quickly.

Businesses in Kenya constantly need posters, social media posts, menus, business cards, banners and logos. That is why design is a skill you can start earning from with only a phone or a basic computer.

## Principle 1: Contrast

**Contrast** means making things clearly different so they stand out: dark vs light, big vs small, bold vs thin. Low contrast makes text hard to read, especially on a phone in sunlight.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; display: flex; gap: 16px; padding: 16px; }
  .box { width: 220px; padding: 20px; border-radius: 8px; }
  .low  { background: #f2f2f2; color: #d0d0d0; }
  .high { background: #12355b; color: #ffffff; }
</style>
</head>
<body>
  <div class="box low"><h3>Low contrast</h3><p>Grey on light grey is hard to read.</p></div>
  <div class="box high"><h3>High contrast</h3><p>White on dark blue is easy to read.</p></div>
</body>
</html>
```

## Principle 2: Alignment

**Alignment** means lining elements up along invisible lines. When text and images share a common edge, the design looks organised and professional. Random positions look messy, even if each item is nice on its own.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; display: flex; gap: 24px; padding: 16px; }
  .card { width: 230px; border: 1px solid #ccc; padding: 16px; }
  .messy h3 { margin-left: 30px; } .messy p { text-align: center; } .messy small { display: block; text-align: right; }
  .neat { text-align: left; }
</style>
</head>
<body>
  <div class="card messy"><h3>Mama Pendo Cafe</h3><p>Chai and mandazi from 6am</p><small>Kisumu, Oginga Odinga St</small></div>
  <div class="card neat"><h3>Mama Pendo Cafe</h3><p>Chai and mandazi from 6am</p><small>Kisumu, Oginga Odinga St</small></div>
</body>
</html>
```

The right-hand card uses one left edge for everything, so the eye moves smoothly down.

## Principle 3: Hierarchy

**Hierarchy** shows what to read first, second and third. Use size, weight, colour and position to rank information. On an event poster the order is usually: event name, then date and place, then details, then contact.

```text
1st  BIG:    YOUTH TALENT SHOW
2nd  Medium: Saturday 14th, 2pm, Nakuru Social Hall
3rd  Small:  Singing, dance, spoken word. Entry free.
4th  Small:  Register: 07XX XXX XXX
```

If everything is big and bold, nothing stands out.

## Principle 4: Whitespace

**Whitespace** (or negative space) is empty space around and between elements. It does not have to be white. Beginners often fill every corner, but space gives the design room to breathe and makes the important parts easier to see. Luxury brands use lots of whitespace; crowded designs can look cheap.

> **Tip:** When a design feels "off", check these four principles in order: Is there enough contrast? Is everything aligned? Is the most important thing the most visible? Is there enough space?

```exercise
Q: Name the principles.
Making the event name biggest so it is read first is ___. Leaving empty space around elements is ___.
A: hierarchy | whitespace / white space / negative space
```

```exercise
Q: Which principle is broken when grey text sits on a light grey background?
___
A: contrast
```

## Practice task

Find three posters or adverts in your area (on walls, matatus, WhatsApp statuses or Facebook). Photograph them if allowed. For each, give a score out of 5 for contrast, alignment, hierarchy and whitespace, and write one change that would improve it the most.
