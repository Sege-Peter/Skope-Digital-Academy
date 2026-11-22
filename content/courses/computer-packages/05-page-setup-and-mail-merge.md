---
title: Word – Page Setup, Printing and Mail Merge
module: 2
minutes: 14
---

A document can look perfect on screen and still come out of the printer wrong: text cut off, the last line alone on a new page, or the wrong paper size. This lesson covers page setup, printing and **mail merge** – a powerful way to create many personalised letters at once.

## Page setup

Most page settings live on the **Layout** tab (**Page Layout** in older versions).

| Setting | What it controls | Common choice in Kenya |
|---|---|---|
| Size | Paper size | **A4** (not *Letter*, which is the US default) |
| Orientation | Portrait (tall) or Landscape (wide) | Portrait for letters; landscape for wide tables |
| Margins | White space around the edge | Normal (2.54 cm) or 2 cm for CVs |
| Columns | Newspaper-style columns | Newsletters, church bulletins |

> **Warning:** Some computers come set to **Letter** size. If you print a Letter-sized document on A4 paper, the layout may shift. Check **Size** › **A4** before printing a customer's job.

## Headers, footers and page numbers

Go to **Insert** › **Header**, **Footer** or **Page Number**. Text in the header or footer repeats on every page – ideal for a company name, a report title or *Page 3 of 10*. Double-click the main text to leave the header area.

## Page breaks

Do not press **Enter** many times to push a chapter to a new page. When you edit text above, everything shifts. Instead insert a **page break** with **Ctrl + Enter** (or **Insert** › **Page Break**).

## Print preview and printing

Press **Ctrl + P** to open the print screen. Always check the preview on the right before printing.

| Option | Use it to |
|---|---|
| Copies | Print several copies |
| Pages | Print only some pages, e.g. `1-3` or `2,5` |
| Print one sided / both sides | Save paper on long reports |
| Pages per sheet | Fit 2 or 4 small pages on one sheet |

> **Tip:** In a cyber café, print one test page first and show the customer before printing 50 copies. A wasted ream of paper is your loss.

## What is mail merge?

Suppose Fatuma runs a chama of 40 members and wants to send each one a letter showing their name and savings balance. Typing 40 letters by hand is slow and error-prone. **Mail merge** joins two things:

1. A **main document** – the letter with blank spaces.
2. A **data source** – a list, usually an Excel sheet, with one row per person.

```text
Data source (Excel)
FirstName | Town    | Savings
Achieng   | Kisumu  | 12,500
Kiprono   | Kericho | 9,800
Wanjiru   | Nyeri   | 15,200
```

```text
Main document
Dear «FirstName»,

Thank you for being an active member of our chama. As at 31 March 2026
your total savings stand at KES «Savings». We look forward to seeing you
at our next meeting in «Town».
```

The words in «double angle brackets» are **merge fields**. Word replaces them with each person's data and produces one letter per row – 40 letters in seconds. The same method makes envelopes, labels, certificates and ID cards.

## The mail merge steps

Find these under the **Mailings** tab:

1. **Start Mail Merge** – choose *Letters* (or labels, envelopes).
2. **Select Recipients** › *Use an Existing List* – pick the Excel file.
3. **Insert Merge Field** – place each field where it belongs.
4. **Preview Results** – check a few records.
5. **Finish & Merge** – print, or create one new document with all the letters.

> **Note:** The first row of your Excel sheet must contain the column headings (*FirstName*, *Town*, *Savings*). Word uses them as field names.

```exercise
Q: Complete the facts about page setup.
Standard paper size in Kenya: ___
Shortcut for a page break: Ctrl + ___
A: A4 | Enter
```

```exercise
Q: Name the two parts of a mail merge.
1. The main ___
2. The data ___
A: document | source
```

## Practice task

Create a small Excel list of five friends with columns *FirstName* and *Town*. Then write a one-paragraph invitation to a (pretend) youth meeting in Word and use mail merge to produce five personalised invitations. Preview each before finishing.
