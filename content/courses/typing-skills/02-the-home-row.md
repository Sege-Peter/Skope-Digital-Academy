---
title: The Home Row
module: 1
minutes: 12
---

Every touch typist's fingers start and finish in the same place: the **home row**. It is the middle row of letters on the keyboard, and it is your base. From here, each finger reaches up or down to other keys and then comes straight back.

## Finding home

Place your fingers like this:

| Hand | Little finger | Ring finger | Middle finger | Index finger |
|---|---|---|---|---|
| Left | A | S | D | F |
| Right | ; (semicolon) | L | K | J |

Your **thumbs** rest lightly on the **space bar**. Most people press space with the thumb of their stronger hand.

Look closely at the **F** and **J** keys. Each has a small raised bump. These bumps let you find home position by touch, without looking. Whenever you lose your place, feel for the bumps and reset.

> **Tip:** Your index fingers also cover the keys next to them: the left index finger types **G**, and the right index finger types **H**. Reach sideways, press, and return to F and J.

## The golden rules

1. **Don't look at the keyboard.** This is the hardest rule and the most important. If you need to, cover your hands with a cloth or a sheet of paper.
2. **Every key belongs to one finger.** Always use the correct finger, even when another seems easier.
3. **Return home** after every reach.
4. **Tap lightly.** Keys need only a gentle press; banging them tires your hands.
5. **Keep a steady rhythm**, like a slow drumbeat, instead of rushing and stopping.

## Saying the letters

In the beginning, say each letter quietly in your head as you type it ("a – s – d – f"). This connects the letter to the finger. After a few days the connection becomes automatic.

## Home row drills

Type each line three times, slowly and evenly, without looking down. Press **Enter** at the end of each line.

```text
asdf jkl; asdf jkl; asdf jkl;
aaa sss ddd fff jjj kkk lll ;;;
fj dk sl a; fj dk sl a;
fg fg fg jh jh jh gh gh hg
as as ad ad ask ask all all
dad sad lad fad had glad flash
a sad lad; ask dad; all fall;
```

With only the home row you can already type real words: *ask, dad, sad, lad, fall, glass, flash, half, salad*.

## Practice tool: home row

Use this tool to practise. It colours each character green when correct and red when wrong, and shows your time, speed in **WPM** (words per minute), **accuracy** and the number of **mistakes** (including ones you fixed). Press **Try it Yourself** to open it, then click in the box and start typing. You will learn exactly how WPM and accuracy are calculated in lesson 6.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home Row Practice</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7fb; color: #222; padding: 16px; }
    .passage { font-family: "Courier New", monospace; font-size: 20px; line-height: 1.8; background: #fff;
               border: 1px solid #ccd; border-radius: 8px; padding: 12px; white-space: pre-wrap; }
    .ok { color: #1e7b34; }
    .bad { color: #fff; background: #c62828; }
    .next { background: #ffe082; }
    textarea { width: 100%; box-sizing: border-box; font-family: "Courier New", monospace; font-size: 18px;
               padding: 10px; margin-top: 12px; border: 1px solid #999; border-radius: 8px; }
    .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .stat { background: #fff; border: 1px solid #ccd; border-radius: 8px; padding: 8px 14px; min-width: 80px; }
    .stat b { display: block; font-size: 22px; }
    button { margin-top: 12px; padding: 10px 18px; font-size: 16px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Home row practice</h2>
  <p>Fingers on <b>A S D F</b> and <b>J K L ;</b>. Type the text exactly. The timer starts with your first key.</p>
  <div id="passage" class="passage"></div>
  <textarea id="input" rows="3" spellcheck="false" autocomplete="off" autocapitalize="off"
            placeholder="Start typing here..."></textarea>
  <div class="stats">
    <div class="stat">Time<b id="time">0s</b></div>
    <div class="stat">WPM<b id="wpm">0</b></div>
    <div class="stat">Accuracy<b id="acc">100%</b></div>
    <div class="stat">Mistakes<b id="err">0</b></div>
  </div>
  <p id="message"></p>
  <button id="restart">Start again</button>

  <script>
    const text = "a sad lad; a sad dad; all fall; ask dad; a lass asks; dad adds salad; flask; alfalfa; jaffa; a lad falls; dads ask all lads";

    const passage = document.getElementById("passage");
    const input = document.getElementById("input");
    const spans = [];
    let startTime = null, endTime = null, timer = null;
    let keysTyped = 0, mistakes = 0, lastLength = 0;

    // Show the passage one character per <span> so each can be coloured
    for (const ch of text) {
      const span = document.createElement("span");
      span.textContent = ch;
      passage.appendChild(span);
      spans.push(span);
    }

    function update() {
      const typed = input.value;
      for (let i = 0; i < spans.length; i++) {
        if (i < typed.length) {
          spans[i].className = typed[i] === text[i] ? "ok" : "bad";
        } else {
          spans[i].className = i === typed.length ? "next" : "";
        }
      }
      const seconds = startTime ? ((endTime || Date.now()) - startTime) / 1000 : 0;
      const minutes = seconds / 60;
      const wpm = minutes > 0 ? Math.round((typed.length / 5) / minutes) : 0;
      const accuracy = keysTyped > 0 ? Math.floor((keysTyped - mistakes) / keysTyped * 100) : 100;
      document.getElementById("time").textContent = Math.floor(seconds) + "s";
      document.getElementById("wpm").textContent = wpm;
      document.getElementById("acc").textContent = accuracy + "%";
      document.getElementById("err").textContent = mistakes;
      if (endTime) {
        document.getElementById("message").textContent =
          "Finished! " + wpm + " WPM at " + accuracy + "% accuracy. Press Start again to repeat.";
      }
    }

    input.addEventListener("input", function () {
      const typed = input.value;
      if (!startTime && typed.length > 0) {
        startTime = Date.now();
        timer = setInterval(update, 250);
      }
      // Count each new character, and each wrong one, even if fixed later
      for (let i = lastLength; i < typed.length; i++) {
        keysTyped++;
        if (typed[i] !== text[i]) mistakes++;
      }
      lastLength = typed.length;
      if (typed.length >= text.length && !endTime) {
        endTime = Date.now();
        clearInterval(timer);
        input.readOnly = true;
      }
      update();
    });

    input.addEventListener("paste", function (e) { e.preventDefault(); });

    document.getElementById("restart").addEventListener("click", function () {
      clearInterval(timer);
      startTime = null; endTime = null; timer = null;
      keysTyped = 0; mistakes = 0; lastLength = 0;
      input.value = "";
      input.readOnly = false;
      document.getElementById("message").textContent = "";
      update();
      input.focus();
    });

    update();
  </script>
</body>
</html>
```

> **Note:** Speed is not the goal yet. Aim for **95% accuracy or higher** with your eyes on the screen. If you are below that, slow down.

```exercise
Q: Fill in the home row keys for the left hand, from little finger to index finger.
___ ___ ___ ___
A: a | s | d | f
H: The first four letters of the middle row.
```

```exercise
Q: Name the two keys with small raised bumps that help you find home position.
___ and ___
A: f | j
H: Where your two index fingers rest.
```

## Practice task

Do 15 minutes of home row practice today: 5 minutes of the drill lines above, then 10 minutes with the practice tool. Do the tool at least three times and write down your accuracy each time. Remember: eyes on the screen, not on your fingers.
