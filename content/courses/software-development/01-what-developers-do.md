---
title: What Software Developers Do
module: 1
module_title: How Software Gets Made
minutes: 10
---

When you send money with M-Pesa, book a matatu seat on an app or check your KCSE results online, you are using software that someone planned, built, tested and keeps running. The people who do that work are **software developers**. This course shows you how they work, step by step, so that you can join them.

## More than typing code

Many beginners imagine a developer sitting alone, typing code all day. In reality, writing code is only part of the job. A normal week for a junior developer in Nairobi might look like this:

| Activity | What it means |
|---|---|
| Understanding the problem | Talking to a client or teammate to find out what they actually need |
| Planning | Sketching screens, drawing flowcharts, breaking work into small tasks |
| Writing code | Building the feature in a language such as JavaScript, Python or PHP |
| Testing | Checking that the feature works, including when users do unexpected things |
| Debugging | Finding out why something is broken and fixing it |
| Reviewing | Reading a teammate's code and suggesting improvements |
| Communicating | Daily check-ins, writing updates, asking for help when stuck |

Good developers are good problem solvers and good communicators. A clever programmer who builds the wrong thing has wasted everyone's time.

## Kinds of developers

Software is a big field. Most people start general and then specialise.

- **Front-end (web) developer** – builds what users see in the browser: pages, buttons, forms. Uses HTML, CSS and JavaScript.
- **Back-end developer** – builds the server side: databases, logins, payments, APIs. Uses languages like PHP, Python, Node.js or Java.
- **Full-stack developer** – does both front-end and back-end, common in small companies and freelancing.
- **Mobile developer** – builds Android and iPhone apps.
- **QA (quality assurance) tester** – focuses on finding bugs before users do. A good entry point into the industry.
- **DevOps engineer** – looks after servers, deployment and keeping systems online.

> **Note:** You do not need to choose today. Front-end web work is a popular first step in Kenya because you can see results quickly and small businesses need websites.

## A tiny taste of the job

Here is a small piece of real code. Imagine a shop owner in Eldoret asks: "Can the system tell me the total for a customer's items?" A developer turns that request into a function, then checks that the answer is right.

```example javascript
// Items bought by a customer (price in KES)
const items = [
  { name: "Unga 2kg", price: 230 },
  { name: "Sugar 1kg", price: 180 },
  { name: "Milk 500ml", price: 65 }
];

function totalPrice(list) {
  let total = 0;
  for (const item of list) {
    total += item.price;
  }
  return total;
}

console.log("Total: KES " + totalPrice(items));
```

```output
Total: KES 475
```

Notice the three steps: understand the request, write the code, check the result. You will repeat that cycle thousands of times in your career.

## Skills that matter

Employers and clients look for a mix of skills:

- **Technical:** at least one programming language, Git, basic databases, reading error messages.
- **Thinking:** breaking big problems into small steps, paying attention to detail.
- **People:** clear writing, asking good questions, keeping promises about deadlines.

> **Warning:** Be careful of "bootcamps" or "agents" that promise a guaranteed developer job if you pay them a fee. Real employers hire based on what you can show, not on a payment. Everything in this course is free.

```exercise
Q: Complete the sentence about who builds what users see in the browser.
A ___-end developer builds pages, buttons and forms.
A: front
H: The opposite of back-end.
```

```exercise
Q: Fill in the job that focuses on finding bugs before users do.
A QA ___ checks that the software works.
A: tester
H: QA stands for quality assurance.
```

## Practice task

Pick three apps or websites you used this week (for example M-Pesa, a school portal, WhatsApp). For each one, write down in a notebook:

1. One thing a **front-end** developer built (something you can see).
2. One thing a **back-end** developer probably built (something hidden, like saving your messages).
3. One bug or annoyance you noticed that a developer could fix.
