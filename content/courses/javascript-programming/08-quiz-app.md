---
title: "Project: A Quiz App"
module: 4
module_title: Building Real Apps
minutes: 18
---

Welcome to the final week. You now know variables, functions, the DOM, events, arrays, objects and saving data. This week you put them together into two complete apps. First up: a **quiz app** that shows one question at a time, checks answers and gives a score. Schools, churches, youth groups and companies training staff all use quizzes like this.

## Step 1: describe the quiz as data

Each question is an object with the question text, a list of choices and the index of the correct choice:

```example javascript
const questions = [
  { q: "What is the capital of Kenya?", choices: ["Mombasa", "Nairobi", "Kisumu"], answer: 1 },
  { q: "Which lake borders Kisumu?", choices: ["Lake Victoria", "Lake Turkana"], answer: 0 }
];

for (const item of questions) {
  console.log(`${item.q} -> ${item.choices[item.answer]}`);
}
```

```output
What is the capital of Kenya? -> Nairobi
Which lake borders Kisumu? -> Lake Victoria
```

Keeping questions as data means anyone can add new questions without touching the logic.

## Step 2: track the state

The app needs to remember just two things: which question we are on (`current`) and the score (`score`). Everything on screen is drawn from those values plus the `questions` array.

## Step 3: build it

```example html
<!DOCTYPE html>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; max-width: 440px; margin: 20px auto; padding: 0 16px; }
    .choice { display: block; width: 100%; text-align: left; padding: 12px; margin: 8px 0; font-size: 16px;
              border: 2px solid #cbd5e1; border-radius: 8px; background: white; cursor: pointer; }
    .correct { border-color: #16a34a; background: #dcfce7; }
    .wrong { border-color: #dc2626; background: #fee2e2; }
    #next { padding: 10px 20px; font-size: 16px; display: none; }
    #progress { color: #64748b; }
  </style>
</head>
<body>
  <p id="progress"></p>
  <h2 id="question"></h2>
  <div id="choices"></div>
  <p id="feedback"></p>
  <button id="next">Next</button>

  <script>
    const questions = [
      { q: "What is the capital of Kenya?", choices: ["Mombasa", "Nairobi", "Kisumu"], answer: 1 },
      { q: "Which lake borders Kisumu?", choices: ["Lake Victoria", "Lake Turkana", "Lake Naivasha"], answer: 0 },
      { q: "Which tag holds JavaScript in a web page?", choices: ["<style>", "<js>", "<script>"], answer: 2 },
      { q: "What does console.log() do?", choices: ["Prints a message", "Deletes a file", "Opens a website"], answer: 0 }
    ];

    let current = 0;
    let score = 0;

    const questionEl = document.querySelector("#question");
    const choicesEl = document.querySelector("#choices");
    const feedbackEl = document.querySelector("#feedback");
    const nextBtn = document.querySelector("#next");

    function showQuestion() {
      const item = questions[current];
      document.querySelector("#progress").textContent = `Question ${current + 1} of ${questions.length}`;
      questionEl.textContent = item.q;
      feedbackEl.textContent = "";
      nextBtn.style.display = "none";
      choicesEl.innerHTML = "";

      item.choices.forEach(function (choice, index) {
        const btn = document.createElement("button");
        btn.className = "choice";
        btn.textContent = choice;
        btn.addEventListener("click", function () {
          checkAnswer(index);
        });
        choicesEl.appendChild(btn);
      });
    }

    function checkAnswer(picked) {
      const item = questions[current];
      const buttons = choicesEl.querySelectorAll("button");
      buttons.forEach(b => b.disabled = true);
      buttons[item.answer].classList.add("correct");

      if (picked === item.answer) {
        score = score + 1;
        feedbackEl.textContent = "Correct!";
      } else {
        buttons[picked].classList.add("wrong");
        feedbackEl.textContent = `Not quite. The answer is ${item.choices[item.answer]}.`;
      }
      nextBtn.style.display = "inline-block";
    }

    function showResult() {
      const percent = Math.round(score / questions.length * 100);
      document.querySelector("#progress").textContent = "Finished!";
      questionEl.textContent = `You scored ${score} out of ${questions.length} (${percent}%)`;
      choicesEl.innerHTML = "";
      feedbackEl.textContent = percent >= 70 ? "Well done, you passed!" : "Keep practising and try again.";
      nextBtn.textContent = "Restart";
    }

    nextBtn.addEventListener("click", function () {
      if (nextBtn.textContent === "Restart") {
        current = 0;
        score = 0;
        nextBtn.textContent = "Next";
        showQuestion();
        return;
      }
      current = current + 1;
      if (current < questions.length) {
        showQuestion();
      } else {
        nextBtn.style.display = "inline-block";
        showResult();
      }
    });

    showQuestion();
  </script>
</body>
</html>
```

## What to notice

- **Disabling buttons** after an answer stops a learner from clicking all three choices to find the right one.
- **Feedback** shows the correct answer in green, which helps people learn instead of just failing.
- **`querySelectorAll`** returns every matching element, so `buttons[item.answer]` picks the correct button by its position.
- The **70% pass mark** is a single condition you can change for a client's needs.

## Where quiz apps earn money

The same code can become many products. A driving school can use it for road-rules practice before learners sit the theory test. A tuition centre can run weekly revision quizzes. A company can check that new staff understood their training. In each case you only change the `questions` array and the colours; the logic stays the same. That is why separating data from logic is so valuable: one project becomes many paid jobs.

> **Tip:** Want the questions in a different order each time? Search for "shuffle an array JavaScript" and read about the Fisher–Yates shuffle. Learning to look things up is a real developer skill.

```exercise
Q: Get the text of the correct choice for a question object called item.
item.choices[item.___]
A: answer
```

```exercise
Q: Select every button inside choicesEl.
choicesEl.___("button")
A: querySelectorAll
```

## Practice task

Replace the questions with **8 questions about your county or favourite subject** (for example Kenyan geography for Form 2 revision). Then add one improvement: show the learner's best score using `readStore` and `writeStore` from [the localStorage lesson](saving-with-localstorage).
