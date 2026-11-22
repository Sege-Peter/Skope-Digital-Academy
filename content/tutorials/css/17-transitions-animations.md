---
title: CSS Transitions and Animations
module: 4
minutes: 16
---

Movement helps people notice what changed on a page. CSS has two tools for it:

- A **transition** smoothly animates a property when its value changes, for example on hover.
- An **animation** runs a sequence of steps defined with `@keyframes`, and can start on its own and repeat.

## Transitions

Without a transition, a hover colour changes instantly. With one, the browser fades between the old and new values over the time you choose.

The `transition` shorthand takes the property, duration, timing function and optional delay:

```css
.btn {
  background-color: #1565c0;
  transition: background-color 0.3s ease;
}
.btn:hover {
  background-color: #0d47a1;
}
```

Put the transition on the **normal state**, not only on `:hover`. Then it plays both when the mouse arrives and when it leaves.

| Part | Examples | Meaning |
|---|---|---|
| property | `background-color`, `transform`, `all` | what to animate |
| duration | `0.3s`, `200ms` | how long it takes |
| timing function | `ease`, `linear`, `ease-in`, `ease-out`, `ease-in-out` | the speed curve |
| delay | `0.1s` | wait before starting |

To transition several properties, separate them with commas: `transition: transform 0.2s, box-shadow 0.2s;`.

## transform

`transform` moves, scales or rotates an element **without affecting the layout** around it. It pairs perfectly with transitions.

| Function | Example | Effect |
|---|---|---|
| `translate(x, y)` | `translate(0, -6px)` | move |
| `scale(n)` | `scale(1.05)` | resize |
| `rotate(angle)` | `rotate(45deg)` | turn |

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; display: flex; gap: 20px; padding: 30px; background-color: #f5f5f5; }
  .card {
    width: 180px;
    padding: 20px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgb(0 0 0 / 0.1);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
  }
  .card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 20px rgb(0 0 0 / 0.15);
  }
  .icon {
    display: inline-block;
    font-size: 28px;
    transition: transform 0.4s ease-in-out;
  }
  .card:hover .icon { transform: rotate(360deg) scale(1.2); }
</style>
</head>
<body>
  <div class="card"><span class="icon">&#9733;</span><h3>Amboseli</h3><p>Hover me.</p></div>
  <div class="card"><span class="icon">&#9728;</span><h3>Watamu</h3><p>And me.</p></div>
</body>
</html>
```

> **Tip:** Animate `transform` and `opacity` whenever you can. Browsers can animate them very smoothly. Animating `width`, `height`, `top` or `margin` forces the page to recalculate layout on every frame and can stutter on cheaper phones.

## Animations with @keyframes

A keyframe animation has two parts. First, define the steps with `@keyframes` and a name. Use `from` and `to`, or percentages for more steps. Then attach it to an element with the `animation` properties.

| Property | Example | Meaning |
|---|---|---|
| `animation-name` | `pulse` | which `@keyframes` to use |
| `animation-duration` | `2s` | length of one cycle |
| `animation-timing-function` | `ease-in-out` | speed curve |
| `animation-delay` | `0.5s` | wait before starting |
| `animation-iteration-count` | `3`, `infinite` | how many times to play |
| `animation-direction` | `normal`, `alternate` | `alternate` plays forwards then backwards |
| `animation-fill-mode` | `forwards` | keep the final state when finished |

The shorthand combines them: `animation: pulse 2s ease-in-out infinite;`

```example html
<!DOCTYPE html>
<html>
<head>
<style>
  body { font-family: Arial, sans-serif; padding: 30px; }

  @keyframes pulse {
    0%   { transform: scale(1); }
    50%  { transform: scale(1.12); }
    100% { transform: scale(1); }
  }
  .live {
    display: inline-block;
    background-color: #d32f2f;
    color: white;
    padding: 6px 14px;
    border-radius: 999px;
    animation: pulse 1.5s ease-in-out infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }
  .loader {
    width: 40px;
    height: 40px;
    border: 5px solid #ddd;
    border-top-color: #00897b;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 20px 0;
  }

  @keyframes slide-in {
    from { opacity: 0; transform: translateX(-40px); }
    to   { opacity: 1; transform: translateX(0); }
  }
  .message {
    background-color: #e8f5e9;
    padding: 12px;
    border-left: 5px solid #2e7d32;
    animation: slide-in 0.8s ease-out 0.5s both;
  }
</style>
</head>
<body>
  <span class="live">LIVE</span> AFC Leopards vs Gor Mahia
  <div class="loader"></div>
  <div class="message">Payment received. Asante!</div>
</body>
</html>
```

The message uses `both` as its fill mode: it stays invisible during the 0.5s delay and keeps its final state after the animation ends. Press Run again to replay it.

## Respecting motion preferences

Some people get dizzy or distracted by movement and turn on a "reduce motion" setting on their device. You can respect it with a media query:

```css
@media (prefers-reduced-motion: reduce) {
  * {
    animation: none !important;
    transition: none !important;
  }
}
```

You will learn more about media queries in [Responsive Design](responsive-design).

> **Note:** Good motion is short and purposeful. Most interface transitions feel best between 150ms and 400ms.

```exercise
Q: Fade the background colour over half a second.
.link { ___: background-color 0.5s ease; }
A: transition
```

```exercise
Q: Complete the rule that defines an animation called bounce.
@___ bounce {
  from { transform: translateY(0); }
  to { transform: translateY(-20px); }
}
.ball { animation: bounce 1s ease-in-out ___ alternate; }
A: keyframes | infinite
```
