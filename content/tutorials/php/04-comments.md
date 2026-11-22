---
title: PHP Comments
module: 1
minutes: 7
---

A **comment** is text in your code that PHP ignores completely. Comments are written for humans: your future self, your classmates, or the developer who takes over your project. Good comments explain *why* the code does something, not just *what* it does.

## Why comment your code?

- To explain a tricky calculation, such as how a school fees balance or VAT is worked out.
- To leave notes for teammates ("this list must stay sorted by date").
- To temporarily switch off a line while you hunt for a bug.
- To document functions so editors like VS Code can show helpful tooltips.

## Single-line comments

PHP has two styles for one-line comments: `//` and `#`. Everything after them, up to the end of the line (or a closing `?>`), is ignored.

```example php
<?php
// Fees for the term, in Kenya shillings
$fees = 15000;

# A hash also starts a comment
$paid = 9000;

echo "Balance: " . ($fees - $paid); // printed for the parent
```

```output
Balance: 6000
```

Comments can sit on their own line or at the end of a line of code, as in the last statement above. The `//` style is by far the most common in modern PHP.

> **Note:** Since PHP 8, `#[` starts an **attribute** (special metadata), not a comment. To avoid surprises, prefer `//` for comments.

## Multi-line comments

For longer explanations, start with `/*` and end with `*/`. Everything in between is ignored, even across many lines.

```example php
<?php
/*
  Student report card
  -------------------
  Calculates the average of three exam scores
  and prints it rounded to one decimal place.
*/
$math = 78;
$english = 85;
$science = 69;

$average = ($math + $english + $science) / 3;
echo "Average: " . round($average, 1);
```

```output
Average: 77.3
```

You can also use `/* ... */` in the middle of a line, which is occasionally handy:

```php
$total = $price /* before discount */ * $qty;
```

> **Warning:** Multi-line comments cannot be nested. In `/* outer /* inner */ still outer? */`, the comment ends at the first `*/`, and PHP then tries to run `still outer? */` as code, causing a syntax error.

## Commenting out code

When debugging, you can disable a line without deleting it by turning it into a comment. Here the discount line is "commented out", so it is skipped:

```example php
<?php
$price = 2500;
// $price = $price * 0.9;   // 10% discount switched off for now
echo "Price: KSh $price";
```

```output
Price: KSh 2500
```

In VS Code, select some lines and press **Ctrl + /** to comment or uncomment them in one go.

## DocBlock comments

A special kind of multi-line comment starts with `/**` (two stars). These are called **DocBlocks**. Tools and editors read them to show documentation and catch mistakes. They usually sit right above a function or class.

```example php
<?php
/**
 * Converts Kenya shillings to US dollars.
 *
 * @param float $kes  Amount in KES
 * @param float $rate KES per 1 USD
 * @return float      Amount in USD
 */
function toUsd(float $kes, float $rate): float
{
    return round($kes / $rate, 2);
}

echo toUsd(6500, 130);
```

```output
50
```

You will learn about functions in the [Functions](functions) lesson. For now, just notice the shape of a DocBlock: each line starts with `*`, and tags such as `@param` and `@return` describe the inputs and result.

## Comments do not reach the browser

PHP comments are removed on the server, so visitors cannot see them in "View page source". HTML comments (`<!-- ... -->`) are different: they are sent to the browser and anyone can read them.

```example php
<!-- An HTML comment: visible in page source -->
<?php // A PHP comment: never sent ?>
<p>Done</p>
```

```output
<!-- An HTML comment: visible in page source -->
<p>Done</p>
```

> **Warning:** Never put passwords, API keys or private notes in HTML comments. Even in PHP comments, avoid storing secrets in code; keep them in a configuration file outside your public folder.

## Good commenting habits

- Keep comments short and up to date. A wrong comment is worse than no comment.
- Explain intent and business rules ("Fees are due before the 5th"), not obvious code (`$i++; // add 1 to i`).
- Use DocBlocks for functions and classes that others will call.

```exercise
Q: Turn this line into a single-line comment using two slashes.
___ This line is ignored by PHP
A: //
```

```exercise
Q: Complete the multi-line comment.
___ This comment
   spans two lines ___
A: /* | */
```
