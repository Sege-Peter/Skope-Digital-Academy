---
title: Working with Colour
module: 1
minutes: 14
---

Colour is often the first thing people notice. It creates a mood, helps people recognise a brand and guides the eye to important information. Using colour well is less about talent and more about following a few simple rules.

## Colour feelings (associations)

Colours carry common associations, though they vary between cultures and people:

| Colour | Often associated with | Common uses |
|---|---|---|
| Red | Energy, urgency, love, danger | Sales, food, alerts |
| Orange | Friendly, playful, affordable | Youth brands, offers |
| Yellow | Happy, attention-grabbing | Highlights, children's brands |
| Green | Nature, health, growth, money | Agriculture, health, finance |
| Blue | Trust, calm, professional | Banks, schools, hospitals, tech |
| Purple | Creativity, luxury | Beauty, events |
| Black | Elegance, power | Fashion, premium products |

Match colours to the message. A clinic poster in calm blues and greens feels safer than one in loud red.

## Building a simple palette

A **palette** is the set of colours used in a design or brand. A simple, reliable approach:

- **1 main colour** – the brand colour.
- **1 accent colour** – for buttons, prices and highlights.
- **Neutrals** – white, light grey, dark grey or near-black for backgrounds and text.

A common guideline is the **60-30-10 rule**: about 60% of the design in a dominant (often neutral or main) colour, 30% in a secondary colour, and 10% in an accent.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; margin: 0; padding: 16px; background: #f7f7f2; }
  .poster { max-width: 340px; background: #1f5f3f; color: #ffffff; padding: 24px; border-radius: 10px; }
  .poster h2 { margin: 0 0 8px; }
  .poster p { color: #e8efe9; }
  .price { display: inline-block; background: #f2b632; color: #1a1a1a; padding: 8px 14px; font-weight: bold; border-radius: 6px; }
</style>
</head>
<body>
  <div class="poster">
    <h2>Shamba Fresh Eggs</h2>
    <p>Free-range eggs from Kiambu, delivered every Saturday.</p>
    <span class="price">Order on WhatsApp</span>
  </div>
</body>
</html>
```

Green is the main colour, near-white is the text, and yellow is the small accent that pulls the eye to the action. Try changing `#f2b632` to another colour in the editor and see how the mood changes.

## Colour codes

Computers describe colours with codes. The most common in design tools is the **HEX code**: a `#` followed by six characters, such as `#1f5f3f`. Canva and most design apps let you type a HEX code so you can use exactly the same brand colour every time.

| Model | Used for | Example |
|---|---|---|
| **RGB / HEX** | Screens: phones, websites, social media | `#12355b` |
| **CMYK** | Printing with ink | Cyan, Magenta, Yellow, Black percentages |

> **Note:** Colours often look slightly different when printed. For important print jobs, ask the printer for a test print before printing hundreds of copies.

## Readability and contrast

Always check that text colour contrasts strongly with its background. Light yellow text on white, or dark blue text on black, may look fine on your laptop but becomes unreadable on a phone in sunlight. Free online "contrast checker" tools tell you if two colours are readable together.

> **Tip:** Some people are colour-blind and cannot easily tell certain colours apart, such as red and green. Do not rely on colour alone to show meaning – add words or icons too.

```exercise
Q: Complete the 60-30-10 rule: dominant, secondary, and accent percentages.
Dominant ___%, secondary ___%, accent ___%
A: 60 | 30 | 10
```

```exercise
Q: Which colour model is used for printing with ink?
___
A: CMYK
```

## Getting palette ideas

- Take colours from a photo of the product (Canva can suggest a palette from an image).
- Look at nature: a sunset over Lake Victoria, tea fields in Kericho, the Indian Ocean.
- Use free palette generator websites and save palettes you like.

## Practice task

Create a palette (main, accent and two neutrals) with HEX codes for three different businesses: a children's school, a butchery and a beauty salon. For each, write one sentence explaining why the colours fit.
