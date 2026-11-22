---
title: Layout and Composition
module: 2
minutes: 14
---

**Layout** is where you place each element on the page. Two designs can use the same words, photo and colours but feel completely different because of the layout. A good layout guides the eye in a clear path and makes the message easy to follow.

## How people look at a design

People do not read a poster word by word. They scan. In most designs, the eye goes first to the biggest, brightest or highest-contrast element, then moves along a path. In languages read left to right, such as English and Kiswahili, people often scan from the top-left, across, and down. Use this: put the most important message where the eye lands first, and the call to action at the end of the path.

## Grids

A **grid** is a set of invisible columns and rows used to place elements. Grids make alignment easy and keep many designs consistent. Canva and other tools can show guides and rulers to help.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; padding: 16px; background: #eee; }
  .flyer { max-width: 380px; background: #fff; padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .title { grid-column: 1 / 3; font-size: 26px; font-weight: bold; color: #12355b; }
  .photo { background: #c9d8e8; height: 110px; display: flex; align-items: center; justify-content: center; color: #12355b; }
  .info p { margin: 0 0 6px; font-size: 14px; }
  .cta { grid-column: 1 / 3; background: #e67e22; color: #fff; text-align: center; padding: 10px; font-weight: bold; }
</style>
</head>
<body>
  <div class="flyer">
    <div class="title">Kisumu Computer Classes</div>
    <div class="photo">Photo here</div>
    <div class="info">
      <p>Basic computer skills</p>
      <p>Typing and MS Office</p>
      <p>Evening and weekend classes</p>
    </div>
    <div class="cta">Call 07XX XXX XXX to register</div>
  </div>
</body>
</html>
```

This flyer uses a simple two-column grid: a full-width title, a photo and details side by side, and a full-width call to action at the bottom where the eye finishes.

## The rule of thirds

Imagine the canvas divided into a 3 × 3 grid. Placing important items (a face, a product, a headline) near the lines or where they cross often looks more interesting than placing everything dead centre. Most phone cameras can show this grid when you take photos – switch it on.

## Proximity: group related things

Items that belong together should be **close** together; unrelated items should be separated by space. Keep the date, time and venue in one group, and contact details in another. This is called **proximity**, and it helps the viewer understand the structure without thinking.

## Margins and safe areas

Leave a **margin** of empty space around the edges. Text pushed to the very edge looks cramped and may be cut off when printed or cropped by an app. For print, printers often ask for a **bleed** (extra background beyond the trim line) and a **safe zone** (keep text away from the edge) – always ask the printer for their exact requirements.

## Balance

**Symmetrical** layouts (mirror images) feel formal and stable – good for wedding invitations and certificates. **Asymmetrical** layouts (different sides, but still balanced in visual weight) feel modern and lively – good for social media and youth events.

```exercise
Q: Name the principles from this lesson.
Keeping related items close together is called ___. Dividing the canvas into a 3 x 3 grid is the rule of ___.
A: proximity | thirds
```

```exercise
Q: Name the print term described below.
Extra background that goes beyond the trim line for printing is called the ___.
A: bleed
```

## A quick layout checklist

- Is there one clear focal point?
- Does the eye follow a path ending at the call to action?
- Are related items grouped and aligned?
- Are there margins around the edges?
- Does the design still work when viewed small, as on a phone?

## Practice task

Sketch three different layouts (thumbnails, each about the size of a matchbox) for a poster: "Eldoret Youth Football Tournament – Saturday 9am – Kipchoge Keino Stadium – Register via WhatsApp." Use a different layout idea for each (centred/symmetrical, two-column grid, rule of thirds). Choose the best one and explain why in two sentences.
