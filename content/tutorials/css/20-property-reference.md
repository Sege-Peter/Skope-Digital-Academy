---
title: CSS Property Reference
module: 6
module_title: Reference and Quiz
minutes: 10
---

This page lists the CSS properties used most often, grouped by topic. Use it as a quick reminder while you build. Each group links back to the lesson that explains it in detail.

## Colour and background

See [Colours](colours) and [Backgrounds](backgrounds).

| Property | What it does | Example value |
|---|---|---|
| `color` | text colour | `#333`, `rgb(0 0 0)` |
| `background-color` | solid fill behind the element | `#f5f5f5` |
| `background-image` | image or gradient behind the element | `linear-gradient(red, blue)` |
| `background-size` | how big the background image is | `cover`, `contain` |
| `background-position` | where the background image sits | `center`, `top right` |
| `background-repeat` | whether the image tiles | `no-repeat` |
| `background` | shorthand for all background properties | `#000 url(a.jpg) center / cover no-repeat` |
| `opacity` | transparency of the whole element | `0.5` |

## Borders and shadows

See [Borders](borders).

| Property | What it does | Example value |
|---|---|---|
| `border` | width, style and colour of all four sides | `1px solid #ccc` |
| `border-top` / `-right` / `-bottom` / `-left` | one side only | `3px solid gold` |
| `border-radius` | rounds the corners | `8px`, `50%` |
| `outline` | line outside the border that takes no space | `2px solid orange` |
| `box-shadow` | shadow around the box | `0 2px 6px rgb(0 0 0 / 0.2)` |

## Box model and sizing

See [The Box Model](box-model) and [Units](units).

| Property | What it does | Example value |
|---|---|---|
| `width` / `height` | size of the box | `300px`, `50%` |
| `max-width` / `min-width` | upper or lower width limit | `1100px` |
| `max-height` / `min-height` | upper or lower height limit | `100vh` |
| `padding` | space inside the border | `10px 20px` |
| `margin` | space outside the border | `0 auto` |
| `box-sizing` | whether width includes padding and border | `border-box` |
| `overflow` | what happens to content that does not fit | `hidden`, `auto`, `scroll` |
| `aspect-ratio` | keeps a width-to-height ratio | `16 / 9` |

## Text and fonts

See [Text and Fonts](text-fonts).

| Property | What it does | Example value |
|---|---|---|
| `font-family` | typeface, with fallbacks | `"Poppins", Arial, sans-serif` |
| `font-size` | size of the text | `1rem`, `18px` |
| `font-weight` | thickness of the letters | `400`, `bold` |
| `font-style` | italic or normal | `italic` |
| `font` | shorthand for font properties | `italic 700 18px/1.5 Arial` |
| `line-height` | space between lines | `1.6` |
| `text-align` | horizontal alignment | `center` |
| `text-decoration` | underline, line-through or none | `none` |
| `text-transform` | letter case | `uppercase` |
| `letter-spacing` | space between letters | `1px` |
| `text-shadow` | shadow behind text | `1px 1px 2px black` |
| `white-space` | how spaces and line breaks are handled | `nowrap` |
| `list-style` | bullet type for lists | `none`, `square` |

## Display and positioning

See [Display and Visibility](display-visibility) and [Position](position).

| Property | What it does | Example value |
|---|---|---|
| `display` | how the box takes part in layout | `block`, `inline-block`, `flex`, `grid`, `none` |
| `visibility` | hides the element but keeps its space | `hidden` |
| `position` | positioning method | `relative`, `absolute`, `fixed`, `sticky` |
| `top` / `right` / `bottom` / `left` | offsets for positioned elements | `0`, `20px` |
| `z-index` | stacking order of overlapping elements | `10` |
| `float` | pushes an element to one side so text wraps around it | `left` |
| `cursor` | mouse pointer shape | `pointer` |

## Flexbox

See [Flexbox Container](flexbox-container) and [Flexbox Items](flexbox-items).

| Property | What it does | Example value |
|---|---|---|
| `flex-direction` | direction of the main axis | `row`, `column` |
| `flex-wrap` | lets items wrap onto new lines | `wrap` |
| `justify-content` | spacing along the main axis | `space-between`, `center` |
| `align-items` | alignment on the cross axis | `center`, `stretch` |
| `align-content` | spacing of wrapped lines | `flex-start` |
| `gap` | space between items | `16px` |
| `flex` | grow, shrink and basis of an item | `1`, `0 0 200px` |
| `align-self` | cross-axis alignment for one item | `flex-end` |
| `order` | visual order of an item | `-1` |

## Grid

See [Grid](grid).

| Property | What it does | Example value |
|---|---|---|
| `grid-template-columns` | column sizes | `repeat(3, 1fr)` |
| `grid-template-rows` | row sizes | `auto 1fr auto` |
| `grid-template-areas` | named layout map | `"header header" "side main"` |
| `grid-area` | places an item in a named area | `header` |
| `grid-column` / `grid-row` | which lines an item spans | `1 / -1`, `span 2` |
| `place-items` | aligns all items in their cells | `center` |

## Effects and motion

See [Transitions and Animations](transitions-animations).

| Property | What it does | Example value |
|---|---|---|
| `transition` | animates property changes | `all 0.3s ease` |
| `transform` | moves, scales or rotates | `translateY(-5px) scale(1.05)` |
| `animation` | runs a `@keyframes` animation | `spin 1s linear infinite` |
| `filter` | visual effects such as blur or greyscale | `grayscale(100%)` |

## At-rules and functions

| Item | What it does | Example |
|---|---|---|
| `@media` | applies CSS under conditions | `@media (min-width: 768px)` |
| `@keyframes` | defines animation steps | `@keyframes fade { ... }` |
| `@import` | loads another stylesheet | `@import url("base.css");` |
| `var()` | reads a custom property (see [CSS Variables](css-variables)) | `var(--brand)` |
| `calc()` | does maths with mixed units | `calc(100% - 40px)` |
| `clamp()` | a value between a minimum and maximum | `clamp(1rem, 4vw, 2rem)` |
| `min()` / `max()` | picks the smaller or larger value | `min(90%, 1000px)` |

## Selector cheat sheet

See [Syntax and Selectors](syntax-selectors), [Pseudo-classes and Pseudo-elements](pseudo-classes-elements) and [Combinators and Specificity](combinators-specificity).

| Selector | Selects |
|---|---|
| `*` | every element |
| `p` | all paragraphs |
| `.card` | elements with class `card` |
| `#top` | the element with id `top` |
| `a[target]` | links with a `target` attribute |
| `nav a` | links anywhere inside `nav` |
| `ul > li` | direct children only |
| `h2 + p` | the paragraph right after an `h2` |
| `a:hover` | a link under the mouse |
| `li:nth-child(odd)` | odd list items |
| `p::first-line` | the first line of each paragraph |
