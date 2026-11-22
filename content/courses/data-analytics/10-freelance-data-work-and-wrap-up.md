---
title: Freelance Data Work and Course Wrap-up
module: 4
minutes: 15
---

You don't need a full-time job to start earning from data skills. Many small businesses, NGOs, SACCOs and students need short pieces of data work – and few people around them know how to do it.

## Services beginners can offer

| Service | Typical client | Skills from this course |
|---|---|---|
| **Data entry and cleaning** | Shops with paper records, schools, churches | Tidy tables, TRIM/PROPER, removing duplicates |
| **Spreadsheet set-up** | A small business tracking sales, stock or rent | Formulas, data validation, simple templates |
| **Monthly reports** | A shop owner who wants to know what sells | Pivot tables, charts, the What–Why–Now what story |
| **Survey analysis** | NGOs, community groups, university students | Google Forms, cleaning, averages, percentages, charts |
| **Simple dashboards** | Growing businesses | Charts in Google Sheets; later Looker Studio or Power BI |
| **SQL / Python tasks** | Tech companies, online clients | Queries and scripts – once you have practised more |

## Finding clients

1. **Start local.** Offer a free or low-cost first job to a business you know, in return for an honest review and permission to show the work (with private details removed).
2. **Show a portfolio.** 2–3 projects in a shared Google Drive folder: a cleaned dataset (before and after), a pivot-table report, a chart with a clear story. Free public datasets from KNBS or Kaggle are good practice material.
3. **Use platforms.** LinkedIn (post your projects), Upwork and Fiverr (search "data entry", "Excel", "Google Sheets"). Competition is strong, so a specific offer such as "I set up sales trackers for Kenyan small shops" stands out.
4. **Agree in writing.** What you will deliver, by when, the price, and how payment happens (e.g. 50% upfront by M-Pesa).

Prices vary widely by client, task size and your experience – start modestly, track your hours, and raise your rates as your reviews grow.

> **Warning:** Data jobs attract scams too. Never pay a "registration" or "software" fee to get work, never share your M-Pesa PIN or passwords, and be careful with "data entry" offers that promise very high pay for very little work. Protect client data: don't share files with anyone else and delete them when the job is done if the client asks.

## Course recap

- Analysts follow **Ask → Collect → Clean → Analyse → Visualise → Share**.
- Know your **data types**: discrete, continuous, nominal, ordinal.
- **Clean** before you analyse: duplicates, formats, blanks, outliers.
- Spreadsheets: **sort, filter, SUM, AVERAGE, COUNTIF, SUMIF** and **pivot tables**.
- Statistics: **mean, median, mode, range** – use the median when there are outliers.
- Charts: **bar** for comparison, **line** for time; tell a **What–Why–Now what** story.
- **SQL**: SELECT, WHERE, ORDER BY, GROUP BY, JOIN.
- **Python**: lists, loops, dictionaries, `csv` and `statistics`.

## Practice task

Plan your first portfolio project: choose a dataset (your own records, a survey you run, or a public dataset), write the business question it will answer, and list the steps you'll take using this course's process. Set yourself a deadline of two weeks. Then take the final quiz below.

## Final quiz

You need 70% to complete the course and generate your certificate.

```quiz
Q: What is the first step of the data analysis process?
- Make a chart
- Ask what decision or question the analysis should answer *
- Delete duplicate rows
- Write SQL

Q: "Customer satisfaction: poor, fair, good, excellent" is which type of data?
- Continuous
- Discrete
- Ordinal *
- Nominal

Q: A spreadsheet shows "Nairobi", "nairobi " and "NAIROBI". What is the problem?
- Missing values
- Inconsistent text formatting *
- An outlier
- A pivot table error

Q: What does =COUNTIF(A2:A9,"Kisumu") do?
- Adds all values in Kisumu
- Counts the cells in A2:A9 that equal "Kisumu" *
- Finds the average for Kisumu
- Sorts the column by Kisumu

Q: In a pivot table of total sales per branch, which field goes in Values?
- branch
- The sales amount *
- The date format
- The sheet name

Q: What is the median of 4, 9, 2, 7, 5?
- 5 *
- 7
- 2
- 5.4

Q: A set of daily earnings has one extremely large day. Which measure best shows a typical day?
- Mean
- Median *
- Range
- Maximum

Q: Which chart is best for showing monthly sales over a year?
- Pie chart
- Line chart *
- Scatter plot
- Histogram

Q: Which SQL keyword filters rows?
- SELECT
- ORDER BY
- WHERE *
- FROM

Q: Which SQL query counts orders for each status?
- SELECT status FROM orders ORDER BY status;
- SELECT status, COUNT(*) FROM orders GROUP BY status; *
- SELECT COUNT(status) FROM customers;
- SELECT status FROM orders WHERE COUNT(status) > 1;

Q: In Python, why must you use int(row["amount"]) after reading a CSV?
- CSV values arrive as text and must be converted to numbers *
- int() rounds the amount to the nearest hundred
- It makes the file smaller
- CSV files cannot store numbers at all

Q: A "client" asks you to pay KES 1,500 for data-entry software before you can start. You should:
- Pay, since software is needed
- Decline – it is a common scam pattern *
- Send your M-Pesa PIN instead
- Pay half now and half later
```
