---
title: Practice Routines That Work
module: 3
module_title: Practice and Shortcuts
minutes: 12
---

Typing is a physical skill, like riding a bicycle or playing the guitar. It improves through **regular, focused practice**, not through one long session once a week. In this lesson you will build a daily routine you can stick to, and use a practice tool that tracks your best results.

## Short and often beats long and rare

Fifteen to twenty minutes **every day** builds skill faster than two hours on a Saturday. Your hands and brain consolidate the movements between sessions, and short sessions keep you fresh enough to type correctly. Tired typing builds bad habits.

| Plan | Total per week | Usually works? |
|---|---|---|
| 20 minutes, 6 days a week | 2 hours | Yes – steady progress |
| 2 hours once a week | 2 hours | Less well – tiredness, forgetting between sessions |
| 5 minutes "when I remember" | Under 30 minutes | Very slow progress |

## A 20-minute daily routine

```text
1. Warm-up (3 min)
   Home row and finger drills, slowly. Check posture.

2. Problem keys (4 min)
   Drill your personal list of weak keys and words.

3. Accuracy round (5 min)
   Type a passage at a comfortable pace. Target: 97% or higher.

4. Speed round (5 min)
   Push slightly faster than comfortable. Keep accuracy at 95% or above.

5. Real-world typing (3 min)
   Copy something useful: an email, a receipt, a news paragraph.

Afterwards: write your best net WPM and accuracy in your progress log.
```

> **Tip:** Attach practice to something you already do every day, such as right after breakfast or before checking social media. Habits stick better when they follow a fixed trigger.

## The daily practice tool

This tool lets you choose a passage (stories, business text, numbers or an email) and a time of 1, 2 or 3 minutes. It shows your speed and accuracy, and remembers your **best net WPM** for rounds at 95% accuracy or above while the page is open. Rounds below 95% don't count towards your best, to remind you that accuracy comes first.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Typing Practice</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7fb; color: #222; padding: 16px; }
    .controls { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    select, button { padding: 8px 12px; font-size: 16px; }
    .passage { font-family: "Courier New", monospace; font-size: 18px; line-height: 1.8; background: #fff;
               border: 1px solid #ccd; border-radius: 8px; padding: 12px; margin-top: 12px; white-space: pre-wrap; }
    .ok { color: #1e7b34; }
    .bad { color: #fff; background: #c62828; }
    .next { background: #ffe082; }
    textarea { width: 100%; box-sizing: border-box; font-family: "Courier New", monospace; font-size: 18px;
               padding: 10px; margin-top: 12px; border: 1px solid #999; border-radius: 8px; }
    .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .stat { background: #fff; border: 1px solid #ccd; border-radius: 8px; padding: 8px 14px; min-width: 80px; }
    .stat b { display: block; font-size: 22px; }
    #message { font-weight: bold; }
    button { cursor: pointer; }
  </style>
</head>
<body>
  <h2>Daily typing practice</h2>
  <div class="controls">
    <label>Passage
      <select id="choice">
        <option value="0">Matatu morning</option>
        <option value="1">Starting a small business</option>
        <option value="2">Numbers and prices</option>
        <option value="3">Customer email</option>
      </select>
    </label>
    <label>Time
      <select id="minutes">
        <option value="1">1 minute</option>
        <option value="2">2 minutes</option>
        <option value="3">3 minutes</option>
      </select>
    </label>
    <button id="restart">Start again</button>
  </div>
  <div id="passage" class="passage"></div>
  <textarea id="input" rows="4" spellcheck="false" autocomplete="off" autocapitalize="off"
            placeholder="Start typing here..."></textarea>
  <div class="stats">
    <div class="stat">Time left<b id="left">60s</b></div>
    <div class="stat">Gross WPM<b id="wpm">0</b></div>
    <div class="stat">Net WPM<b id="net">0</b></div>
    <div class="stat">Accuracy<b id="acc">100%</b></div>
    <div class="stat">Best net WPM (95%+)<b id="best">-</b></div>
  </div>
  <p id="message"></p>

  <script>
    const passages = [
      "The matatu leaves the stage at half past six. The conductor calls out the route, the music is loud, and every seat fills quickly. " +
      "A student reads her notes, a trader counts his change, and an old man looks out of the window at the green hills. " +
      "Traffic slows near town, but the driver knows every shortcut. By seven, the passengers step out into a busy, bright morning.",
      "Starting a small business takes courage and planning. First, find a problem that people around you will pay to solve. " +
      "Next, work out your costs and set a fair price. Keep simple records of every sale and every expense, even on the first day. " +
      "Treat customers with respect, deliver on time, and ask happy customers to tell their friends. Small, steady steps build a strong business.",
      "Order 2041: 12 bags of cement at KES 780 each, total KES 9,360. Order 2042: 5 litres of paint at KES 1,150, total KES 5,750. " +
      "Delivery on 14/12/2026 to Plot 27, Kakamega Road. Call 0712 345 678 before 10:30 a.m. Paid 50% (KES 7,555) by M-Pesa.",
      "Dear Ms Wanjiku, thank you for your order of two school uniforms. We have received your payment and the items are ready. " +
      "You can collect them from our shop on Moi Avenue from Monday to Saturday, between 8 a.m. and 6 p.m. " +
      "If you would like delivery, please reply with your location. Kind regards, Brian, Customer Care."
    ];

    const passage = document.getElementById("passage");
    const input = document.getElementById("input");
    let text = "", spans = [], duration = 60;
    let startTime = null, endTime = null, timer = null;
    let keysTyped = 0, mistakes = 0, lastLength = 0, best = null;

    function setUp() {
      clearInterval(timer);
      text = passages[Number(document.getElementById("choice").value)];
      duration = Number(document.getElementById("minutes").value) * 60;
      startTime = null; endTime = null; timer = null;
      keysTyped = 0; mistakes = 0; lastLength = 0;
      passage.textContent = "";
      spans = [];
      for (const ch of text) {
        const span = document.createElement("span");
        span.textContent = ch;
        passage.appendChild(span);
        spans.push(span);
      }
      input.value = "";
      input.readOnly = false;
      document.getElementById("message").textContent = "";
      update();
    }

    function finish() {
      endTime = Math.min(Date.now(), startTime + duration * 1000);
      clearInterval(timer);
      input.readOnly = true;
    }

    function update() {
      if (startTime && !endTime && Date.now() - startTime >= duration * 1000) finish();
      const typed = input.value;
      let wrongNow = 0;
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
      document.getElementById("left").textContent = Math.max(0, Math.ceil(duration - seconds)) + "s";
      document.getElementById("wpm").textContent = gross;
      document.getElementById("net").textContent = net;
      document.getElementById("acc").textContent = accuracy + "%";
      if (endTime && !document.getElementById("message").textContent) {
        let note = accuracy < 95 ? " Accuracy is below 95%: slow down next round." : " Good accuracy: try to keep it while going a little faster.";
        if (accuracy >= 95 && (best === null || net > best)) best = net; // only count rounds at 95%+
        document.getElementById("best").textContent = best === null ? "-" : best;
        document.getElementById("message").textContent = "Round over: net " + net + " WPM, accuracy " + accuracy + "%." + note;
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
      if (typed.length >= text.length) finish();
      update();
    });

    input.addEventListener("paste", function (e) { e.preventDefault(); });
    document.getElementById("restart").addEventListener("click", function () { setUp(); input.focus(); });
    document.getElementById("choice").addEventListener("change", setUp);
    document.getElementById("minutes").addEventListener("change", setUp);

    setUp();
  </script>
</body>
</html>
```

## Keep it interesting

Practising the same passage every day eventually tests your memory, not your typing. Mix it up:

- Copy a paragraph from a newspaper, a textbook or a story you enjoy.
- Type out song lyrics or a short speech.
- Type your own diary entry or plans for the week, without looking at the keyboard.
- Use free typing websites and games for variety. Many offer lessons, tests and races against other typists.
- Practise in English and Kiswahili; both are common in Kenyan office work.

## Plateaus are normal

At some point your speed will seem to stop improving for a week or two. This is called a **plateau** and every typist meets one. To break through:

- Go back to accuracy rounds for a few days.
- Look for a new problem key; your weak spots change as you improve.
- Try short bursts: type one sentence as fast and accurately as you can, rest, repeat.
- Check your posture; tension in the shoulders or wrists slows you down.

## Avoid these habits

| Habit | Why it hurts |
|---|---|
| Looking down "just for a second" | Slows you and stops your fingers learning key positions |
| Practising only what you are good at | Weak keys stay weak |
| Typing with tense, heavy fingers | Tires your hands and causes errors |
| Skipping breaks | Leads to sore wrists and sloppy typing |

```exercise
Q: Fill in the name for a period when your speed stops improving for a while.
A typing ___
A: plateau
H: A flat area of high land.
```

```exercise
Q: Complete the recommended accuracy floor for a speed round.
Keep accuracy at ___% or above.
A: 95
H: The same number as the 95% rule.
```

## Practice task

Follow the 20-minute routine for the next five days, using the daily practice tool for the accuracy and speed rounds. Record your best net WPM and accuracy each day in your progress log, and write one sentence about what felt easier on day five than on day one.
