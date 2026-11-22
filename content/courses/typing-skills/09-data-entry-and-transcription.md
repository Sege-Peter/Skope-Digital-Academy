---
title: Typing for Data-Entry and Transcription Jobs
module: 4
module_title: Typing for Work
minutes: 14
---

Good typing opens doors to real work. Two of the most common typing-based jobs are **data entry** (copying information into systems or spreadsheets) and **transcription** (turning audio or video into written text). This lesson explains what each involves, the skills employers look for, and how to stay safe from the many scams in this area.

## Data entry

Data-entry clerks type information from one place into another: paper forms into a database, invoices into accounting software, survey answers into a spreadsheet, product details onto an online shop. You find this work in offices, hospitals, banks, SACCOs, NGOs, research projects and online.

What matters most:

- **Accuracy:** a wrong digit in a phone number, ID or amount can cause real problems.
- **Consistency:** following the format exactly (dates as 14/12/2026, names with capital letters, amounts with commas).
- **Speed with numbers:** the number row or numeric keypad without looking.
- **Spreadsheet skills:** Excel or Google Sheets basics, using Tab and Enter to move between cells.
- **Confidentiality:** customer and patient data is private.

> **Note:** Kenya's Data Protection Act protects personal data. If you handle people's details at work, follow your employer's rules on who may see the data, never copy it for personal use, and never share it on WhatsApp or social media.

## Data-entry drill

Practise copying customer records exactly. A record only counts as correct if **every** character matches, just like in a real job where one wrong digit makes the record wrong.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Entry Drill</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7fb; color: #222; padding: 16px; }
    .passage { font-family: "Courier New", monospace; font-size: 17px; line-height: 1.8; background: #fff;
               border: 1px solid #ccd; border-radius: 8px; padding: 12px; white-space: pre-wrap; }
    .ok { color: #1e7b34; }
    .bad { color: #fff; background: #c62828; }
    .next { background: #ffe082; }
    textarea { width: 100%; box-sizing: border-box; font-family: "Courier New", monospace; font-size: 17px;
               padding: 10px; margin-top: 12px; border: 1px solid #999; border-radius: 8px; }
    .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
    .stat { background: #fff; border: 1px solid #ccd; border-radius: 8px; padding: 8px 14px; min-width: 80px; }
    .stat b { display: block; font-size: 22px; }
    #message { font-weight: bold; }
    button { margin-top: 12px; padding: 10px 18px; font-size: 16px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Data entry drill: customer records</h2>
  <p>Copy each record exactly, including commas and capitals. Press <b>Enter</b> at the end of each line.
     A record only counts as correct if every character matches.</p>
  <div id="passage" class="passage"></div>
  <textarea id="input" rows="6" spellcheck="false" autocomplete="off" autocapitalize="off"
            placeholder="Start typing the first record here..."></textarea>
  <div class="stats">
    <div class="stat">Time<b id="time">0s</b></div>
    <div class="stat">WPM<b id="wpm">0</b></div>
    <div class="stat">Accuracy<b id="acc">100%</b></div>
    <div class="stat">Mistakes<b id="err">0</b></div>
    <div class="stat">Records correct<b id="records">0</b></div>
  </div>
  <p id="message"></p>
  <button id="restart">Start again</button>

  <script>
    const records = [
      "1001, Achieng Odhiambo, Kisumu, 0712 448 120, KES 2,450",
      "1002, Brian Kiprotich, Eldoret, 0723 905 316, KES 760",
      "1003, Fatuma Ali, Mombasa, 0734 211 587, KES 12,300",
      "1004, Peter Mwangi, Nyeri, 0745 630 092, KES 1,085",
      "1005, Grace Wairimu, Thika, 0701 377 864, KES 4,920"
    ];
    const text = records.join("\n");

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

    function countCorrectRecords(typed) {
      const lines = typed.split("\n");
      let count = 0;
      for (let i = 0; i < records.length; i++) {
        if (lines[i] === records[i]) count++;
      }
      return count;
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
      const correctRecords = countCorrectRecords(typed);
      document.getElementById("time").textContent = Math.floor(seconds) + "s";
      document.getElementById("wpm").textContent = wpm;
      document.getElementById("acc").textContent = accuracy + "%";
      document.getElementById("err").textContent = mistakes;
      document.getElementById("records").textContent = correctRecords + " / " + records.length;
      if (endTime) {
        document.getElementById("message").textContent = "Done in " + Math.floor(seconds) + " seconds. " +
          correctRecords + " of " + records.length + " records are exactly right" +
          (correctRecords === records.length ? ". Excellent, data you can trust!" : ". In a real job, every wrong record must be fixed.");
      }
    }

    input.addEventListener("input", function () {
      if (endTime) return;
      const typed = input.value;
      if (!startTime && typed.length > 0) {
        startTime = Date.now();
        timer = setInterval(update, 250);
      }
      for (let i = lastLength; i < typed.length; i++) {
        keysTyped++;
        if (typed[i] !== text[i]) mistakes++;
      }
      lastLength = typed.length;
      if (typed.length >= text.length) {
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

> **Tip:** Read each record in chunks ("1003 – Fatuma Ali – Mombasa"), type the chunk, then glance back. Your eyes stay mostly on the source, which is the point of touch typing.

## Transcription

Transcriptionists listen to recordings (interviews, meetings, lectures, podcasts, research focus groups) and type what is said. Kenyan transcribers are often valued for their English and Kiswahili skills.

There are two main styles:

| Style | What you type | Example |
|---|---|---|
| Clean verbatim | The meaning, without "umm", repeated words and false starts | "We will deliver the books on Monday." |
| Full verbatim | Exactly what was said, including fillers and pauses | "We, umm, we will deliver the – the books on Monday." |

Always follow the client's **style guide**: how to label speakers, how to show unclear words (often `[inaudible 00:03:15]`), and how to write timestamps.

What helps:

- **Good headphones** and a quiet place.
- **Strong spelling and grammar**, and the habit of checking names and terms online.
- **Playback control:** most transcription tools let you pause, rewind a few seconds and slow the audio with keyboard shortcuts, so your hands never leave the keyboard.
- **Typing speed:** audio is much faster than typing, so a higher WPM helps a lot. Even so, an hour of audio commonly takes several hours to transcribe for beginners.

## Where to find work

- **Local:** offices, schools, hospitals, county offices, research organisations and NGOs that advertise clerk or data-entry roles on job boards and LinkedIn.
- **Online:** freelance platforms like Upwork and Fiverr, and established transcription and data-annotation companies that run their own tests before accepting workers.
- **Your network:** researchers, students and journalists often need interviews transcribed.

Pay varies a lot by platform, accuracy, speed and the difficulty of the audio. Many beginners start with lower rates while they build ratings and a track record; treat any income figure you see online as an example, not a promise.

## Avoid the scams

Data entry and "typing jobs" attract more scams than almost any other online work. Watch for these warning signs:

| Warning sign | What really happens |
|---|---|
| "Pay a registration or activation fee to start" | You pay and the "job" disappears |
| "Earn KES 5,000 a day typing from home, no experience" | Unrealistic promises to hook you |
| Asking for your M-Pesa PIN, ID photos or passwords | Identity theft or emptying your account |
| "Buy our software or training kit before you can work" | The fee is the scam |
| Work offered only through a WhatsApp or Telegram group with no company details | Usually fake or unpaid work |
| Paying you to "verify" an account for someone else | Can involve you in fraud |

> **Warning:** Legitimate employers and platforms **do not** ask you to pay to get a job. Never share your M-Pesa PIN, OTP codes or passwords with anyone. If an offer seems too good to be true, it almost certainly is.

```exercise
Q: Fill in the transcription style that removes "umm" and repeated words.
___ verbatim
A: clean
H: The opposite of messy.
```

```exercise
Q: Complete the rule about legitimate jobs.
Real employers do not ask you to ___ to get a job.
A: pay
H: Think about a "registration fee".
```

## Practice task

Do the data-entry drill three times and record your time, accuracy and records correct. Then find a short (2–3 minute) clear English or Kiswahili speech or interview online and transcribe the first minute in clean verbatim. Note how long it took you, and which parts slowed you down.
