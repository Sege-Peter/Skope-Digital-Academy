---
title: CSS Colours
module: 2
module_title: Styling Basics
minutes: 12
---

Colour is usually the first thing people notice on a website. CSS lets you write colours in several ways. They all produce the same result on screen, so the choice depends on what is easiest for the job.

The two properties you will use most are `color`, which sets the text colour, and `background-color`, which fills the area behind an element.

## Colour names

CSS understands about 140 named colours, such as `red`, `navy`, `orange`, `teal`, `tomato` and `gold`. Names are easy to remember but give you limited choice. Brand colours rarely match a named colour exactly.

## Hex colours

A hex colour starts with `#` followed by six hexadecimal digits: two for red, two for green and two for blue. Each pair runs from `00` (none) to `ff` (full).

- `#ff0000` is pure red.
- `#000000` is black and `#ffffff` is white.
- `#2e7d32` is a deep green.

When each pair has two identical digits, you can use a three-digit shorthand: `#ffcc00` can be written `#fc0`. Hex is the most common format in design tools, so you will often copy colours in this form.

## RGB colours

`rgb()` takes the same three channels as numbers from 0 to 255. `rgb(46, 125, 50)` is the same green as `#2e7d32`.

To make a colour see-through, add a fourth value for **alpha** (opacity) between 0 and 1. Modern browsers accept it inside `rgb()` after a slash, and the older `rgba()` form with commas still works everywhere.

```css
color: rgb(46 125 50);           /* modern space-separated form */
background-color: rgb(0 0 0 / 0.5);  /* 50% transparent black */
background-color: rgba(0, 0, 0, 0.5); /* the same, older form */
```

## HSL colours

`hsl()` describes a colour the way people think about it:

| Part | Range | Meaning |
|---|---|---|
| Hue | 0 to 360 | position on the colour wheel: 0 red, 120 green, 240 blue |
| Saturation | 0% to 100% | 0% is grey, 100% is the most vivid |
| Lightness | 0% to 100% | 0% is black, 50% is normal, 100% is white |

HSL makes it easy to build a family of shades. Keep the hue the same and change only the lightness to get lighter and darker versions of one brand colour.

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  div {
    padding: 12px;
    margin: 6px 0;
    font-family: Arial, sans-serif;
    color: white;
  }
  .name { background-color: seagreen; }
  .hex  { background-color: #2e7d32; }
  .rgb  { background-color: rgb(46 125 50); }
  .hsl  { background-color: hsl(123 46% 34%); }
</style>
</head>
<body>
  <div class="name">Named colour: seagreen</div>
  <div class="hex">Hex: #2e7d32</div>
  <div class="rgb">RGB: rgb(46 125 50)</div>
  <div class="hsl">HSL: hsl(123 46% 34%)</div>
</body>
</html>
```

The last three boxes are the same colour written three ways.

## Building shades with HSL

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  .swatch {
    display: inline-block;
    width: 90px;
    height: 60px;
    color: white;
    font: 14px Arial, sans-serif;
    text-align: center;
    line-height: 60px;
  }
  .s1 { background-color: hsl(25 90% 25%); }
  .s2 { background-color: hsl(25 90% 40%); }
  .s3 { background-color: hsl(25 90% 55%); }
  .s4 { background-color: hsl(25 90% 70%); color: #333; }
  .s5 { background-color: hsl(25 90% 85%); color: #333; }
</style>
</head>
<body>
  <h3>Sunset over Lake Naivasha</h3>
  <span class="swatch s1">25%</span><span class="swatch s2">40%</span><span class="swatch s3">55%</span><span class="swatch s4">70%</span><span class="swatch s5">85%</span>
</body>
</html>
```

Only the lightness changes, so the five swatches look like one orange family.

## Transparency and contrast

The `transparent` keyword is fully see-through. For partial transparency, use an alpha value as shown above, or the `opacity` property, which fades the whole element *including its text*.

> **Warning:** Always check that text is easy to read against its background. Light grey on white or dark blue on black may look stylish but is hard to read, especially on a phone in bright sunlight.

> **Tip:** Pick three to five colours for a site and reuse them. In the [CSS Variables](css-variables) lesson you will learn to store them in one place.

```exercise
Q: Complete the hex code for pure white.
color: #___;
A: ffffff / fff
H: Every channel is at full strength.
```

```exercise
Q: Make the background half-transparent black using rgb with an alpha value.
background-color: rgb(0 0 0 / ___);
A: 0.5 / .5 / 50%
```
