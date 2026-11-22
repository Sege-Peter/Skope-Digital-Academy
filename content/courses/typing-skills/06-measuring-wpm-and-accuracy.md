---
title: Measuring WPM and Accuracy
module: 2
minutes: 13
---

To improve, you need to measure. Typing speed is measured in **words per minute (WPM)**, and quality is measured as **accuracy**. Employers, typing tests and job platforms use these two numbers, so it is worth understanding exactly how they are calculated.

## What counts as a "word"?

Words have different lengths ("a" versus "Mombasa"), so typing tests use a **standard word of 5 characters**. Spaces and punctuation count as characters too. So typing 250 characters counts as 250 ÷ 5 = 50 words, whatever the actual words were.

## Gross WPM

**Gross WPM** is your raw speed, ignoring mistakes:

```text
Gross WPM = (characters typed / 5) / minutes

Example: 300 characters in 2 minutes
Words  = 300 / 5 = 60
Gross  = 60 / 2  = 30 WPM
```

## Net WPM

**Net WPM** subtracts a penalty for mistakes you left uncorrected, so it shows your *useful* speed:

```text
Net WPM = Gross WPM - (uncorrected errors / minutes)

Example: Gross 30 WPM, 4 uncorrected errors in 2 minutes
Penalty = 4 / 2 = 2
Net     = 30 - 2 = 28 WPM
```

Different typing test websites use slightly different formulas, so your score may vary a little between sites. Always compare yourself on the **same** test.

## Accuracy

**Accuracy** is the percentage of keystrokes that were correct, as you saw in the last lesson. The tools in this course count every wrong keystroke, even if you fix it later:

```text
Accuracy = (correct keystrokes / total keystrokes) x 100
```

## A WPM calculator

Here is the same maths in JavaScript. Change the numbers and run it again to check your own results.

```example javascript
function typingResult(charsTyped, uncorrectedErrors, seconds) {
  const minutes = seconds / 60;
  const gross = (charsTyped / 5) / minutes;
  const net = gross - uncorrectedErrors / minutes;
  return { gross: Math.round(gross), net: Math.round(Math.max(0, net)) };
}

console.log(typingResult(300, 4, 120)); // 300 chars, 4 errors, 2 minutes
console.log(typingResult(200, 0, 60));  // 200 chars, no errors, 1 minute
```

```output
{ gross: 30, net: 28 }
{ gross: 40, net: 40 }
```

## Take the one-minute test

This tool gives you exactly 60 seconds, then shows your gross WPM, net WPM, accuracy and mistakes. The passage is longer than you can type in a minute, so just keep going until the time runs out. Do it three times and use your **average**, because one test can be lucky or unlucky.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>One-Minute Typing Test</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7fb; color: #222; padding: 16px; }
    .passage { font-family: "Courier New", monospace; font-size: 18px; line-height: 1.8; background: #fff;
               border: 1px solid #ccd; border-radius: 8px; padding: 12px; white-space: pre-wrap; }
    .ok { color: #1e7b34; }
    .bad { color: #fff; background: #c62828; }
    .next { background: #ffe082; }
    textarea { width: 100%; box-sizing: border-box; font-family: "Courier New", monospace; font-size: 18px;
               padding: 10px; margin-top: 12px; border: 1px solid #999; border-radius: 8px; }
    .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .stat { background: #fff; border: 1px solid #ccd; border-radius: 8px; padding: 8px 14px; min-width: 80px; }
    .stat b { display: block; font-size: 22px; }
    #message { font-weight: bold; }
    button { margin-top: 12px; padding: 10px 18px; font-size: 16px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>One-minute typing test</h2>
  <p>You have 60 seconds. The clock starts with your first key. Type accurately; mistakes reduce your net speed.</p>
  <div id="passage" class="passage"></div>
  <textarea id="input" rows="4" spellcheck="false" autocomplete="off" autocapitalize="off"
            placeholder="Start typing here..."></textarea>
  <div class="stats">
    <div class="stat">Time left<b id="left">60s</b></div>
    <div class="stat">Gross WPM<b id="wpm">0</b></div>
    <div class="stat">Net WPM<b id="net">0</b></div>
    <div class="stat">Accuracy<b id="acc">100%</b></div>
    <div class="stat">Mistakes<b id="err">0</b></div>
  </div>
  <p id="message"></p>
  <button id="restart">Start again</button>

  <script>
    const DURATION = 60; // seconds
    const text = "Kisumu is a busy city on the shores of Lake Victoria. Every morning, fishermen bring their catch to the market at Dunga, " +
      "and traders load fresh tilapia onto boda bodas heading into town. Shops open early, matatus fill the roads, and students hurry to class. " +
      "In the afternoon, the sun is hot and the lake breeze is welcome. By evening, families gather to share a meal of ugali and fish, " +
      "talk about the day, and plan for tomorrow. Good typists can describe a scene like this quickly and without mistakes.";

    const passage = document.getElementById("passage");
    const input = document.getElementById("input");
    const spans = [];
    let startTime = null, endTime = null, timer = null;
    let keysTyped = 0, mistakes = 0, lastLength = 0;

    for (const ch of text) {
      const span = document.createElement("span");
      span.textContent = ch;
      passage.appendChild(span);
      spans.push(span);
    }

    function finish() {
      endTime = Math.min(Date.now(), startTime + DURATION * 1000);
      clearInterval(timer);
      input.readOnly = true;
    }

    function update() {
      if (startTime && !endTime && Date.now() - startTime >= DURATION * 1000) finish();
      const typed = input.value;
      let wrongNow = 0; // errors still in the text (not fixed)
      for (let i = 0; i < spans.length; i++) {
        if (i < typed.length) {
          const right = typed[i] === text[i];
          spans[i].className = right ? "ok" : "bad";
          if (!right) wrongNow++;
        } else {
          spans[i].className = i === typed.length ? "next" : "";
        }
      }
      const seconds = startTime ? ((endTime || Date.now()) - startTime) / 1000 : 0;
      const minutes = seconds / 60;
      const gross = minutes > 0 ? Math.round((typed.length / 5) / minutes) : 0;
      const net = minutes > 0 ? Math.max(0, Math.round((typed.length / 5 - wrongNow) / minutes)) : 0;
      const accuracy = keysTyped > 0 ? Math.floor((keysTyped - mistakes) / keysTyped * 100) : 100;
      document.getElementById("left").textContent = Math.max(0, Math.ceil(DURATION - seconds)) + "s";
      document.getElementById("wpm").textContent = gross;
      document.getElementById("net").textContent = net;
      document.getElementById("acc").textContent = accuracy + "%";
      document.getElementById("err").textContent = mistakes;
      if (endTime) {
        document.getElementById("message").textContent = "Finished! Gross " + gross + " WPM, net " + net +
          " WPM, accuracy " + accuracy + "%. Write these down, then press Start again.";
      }
    }

    input.addEventListener("input", function () {
      if (endTime) return;
      const typed = input.value;
      if (!startTime && typed.length > 0) {
        startTime = Date.now();
        timer = setInterval(update, 200);
      }
      for (let i = lastLength; i < typed.length; i++) {
        keysTyped++;
        if (typed[i] !== text[i]) mistakes++;
      }
      lastLength = typed.length;
      if (typed.length >= text.length) finish(); // finished the whole passage early
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

> **Note:** In the first few seconds the WPM number jumps around, because it is calculated from very little data. Look at the final result after the full minute.

## What do the numbers mean?

These ranges are rough guides, not official standards:

| Net WPM | What it usually means |
|---|---|
| Under 20 | Beginner; keep focusing on correct fingers and accuracy |
| 20–35 | Improving; fine for everyday use |
| 35–50 | Comfortable for most office work |
| 50–70 | Fast; competitive for data-entry and transcription |
| 70+ | Very fast |

Job adverts for typing-heavy roles often state a minimum speed (for example 35 or 40 WPM) together with high accuracy. Read each advert carefully for the exact requirement.

## Keep a progress log

Write your results in a notebook or spreadsheet after every test:

| Date | Gross WPM | Net WPM | Accuracy | Notes |
|---|---|---|---|---|
| 2 Dec | 18 | 15 | 91% | Still looking at numbers |
| 9 Dec | 24 | 22 | 95% | Better rhythm |
| 16 Dec | 29 | 28 | 97% | Problem key: B |

Seeing steady progress on paper keeps you motivated during slow weeks.

```exercise
Q: You typed 400 characters in 2 minutes. Fill in your gross WPM.
Gross WPM = ___
A: 40
H: 400 / 5 = 80 words, then divide by 2 minutes.
```

```exercise
Q: A standard "word" in typing tests is how many characters?
___ characters
A: 5 / five
H: It is the number you divide by.
```

## Practice task

Take the one-minute test three times today and record the average gross WPM, net WPM and accuracy in a progress log. Set a realistic target for one month from now, for example "+10 WPM at 95% accuracy or higher".
