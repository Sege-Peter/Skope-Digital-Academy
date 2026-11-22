---
title: Installing PHP and Your First Script
module: 1
minutes: 12
---

To run PHP you need three things: a **web server** (such as Apache), the **PHP engine**, and usually a **database** (MySQL or MariaDB). Installing them one by one is fiddly, so most beginners use a bundle that installs all three together. The most popular bundle is **XAMPP**.

## Option 1: XAMPP (recommended for beginners)

XAMPP is free and works on Windows, Linux and macOS. The name stands for cross-platform (X), Apache, MariaDB/MySQL, PHP and Perl.

1. Download XAMPP from the official site, [apachefriends.org](https://www.apachefriends.org). Choose a version that includes **PHP 8.2** or newer.
2. Run the installer. On Windows, keep the default folder `C:\xampp` (avoid `C:\Program Files`, which has strict permissions).
3. Open the **XAMPP Control Panel** and click **Start** next to **Apache**. Start **MySQL** too if you will use a database later.
4. Open your browser and visit `http://localhost`. If you see the XAMPP welcome page, the server is running.

> **Note:** If Apache will not start, another program (often Skype or IIS) may already be using port 80. Close that program, or change Apache's port in the XAMPP config and visit `http://localhost:8080` instead.

## Where to put your files

Apache serves files from a folder called the **document root**. In XAMPP on Windows this is:

```text
C:\xampp\htdocs
```

Anything you save inside `htdocs` can be opened in the browser through `http://localhost/`. For example, the file `C:\xampp\htdocs\school\index.php` is available at `http://localhost/school/index.php`.

> **Warning:** Always open PHP files through `http://localhost/...`. If you double-click the file and it opens as `file:///C:/xampp/...`, the browser reads the file directly and PHP never runs. You will just see your raw code or a blank page.

## Your first PHP script

Create a folder `C:\xampp\htdocs\hello` and inside it a file named `index.php`. Type the following using any code editor (VS Code is a good free choice):

```example php
<!DOCTYPE html>
<html>
<head>
  <title>My first PHP page</title>
</head>
<body>
  <h1><?php echo "Hello, Kisumu!"; ?></h1>
  <p>Today we learn PHP.</p>
</body>
</html>
```

```output
<!DOCTYPE html>
<html>
<head>
  <title>My first PHP page</title>
</head>
<body>
  <h1>Hello, Kisumu!</h1>
  <p>Today we learn PHP.</p>
</body>
</html>
```

Save the file and visit `http://localhost/hello/`. You will see the heading "Hello, Kisumu!". Right-click and choose **View page source**: the PHP tag has disappeared and only the HTML above is left. That is the server doing its job.

> **Tip:** When a URL points to a folder, Apache looks for `index.php` (or `index.html`) inside it automatically. That is why `http://localhost/hello/` works without typing the file name.

## Option 2: The command line

PHP is not only for web pages. You can also run scripts directly in a terminal, which is handy for testing small pieces of code. With XAMPP on Windows, the PHP program is `C:\xampp\php\php.exe`.

Check your version first:

```text
C:\xampp\php\php.exe -v
PHP 8.2.12 (cli) (built: Oct 24 2023 21:15:15) (ZTS Visual C++ 2019 x64)
```

Now save this as `C:\xampp\htdocs\hello\cli.php`:

```example php
<?php
echo "PHP version check passed.\n";
echo "2 + 3 = " . (2 + 3) . "\n";
```

```output
PHP version check passed.
2 + 3 = 5
```

Run it with `C:\xampp\php\php.exe C:\xampp\htdocs\hello\cli.php`. The `\n` inside double quotes is a **newline** character, which moves the cursor to the next line in a terminal. In a browser, newlines in HTML are shown as plain spaces, so on web pages you use `<br>` or paragraph tags instead.

> **Tip:** Add `C:\xampp\php` to your Windows **PATH** environment variable so you can simply type `php -v` and `php cli.php` from any folder.

## Option 3: The built-in development server

PHP includes a tiny web server for testing. Open a terminal in your project folder and run:

```text
php -S localhost:8000
```

Then visit `http://localhost:8000`. This is perfect for quick experiments, but it is **not** meant for real websites. Use Apache or Nginx for those.

## Going live

When your site is ready, you upload the `.php` files to a hosting company. Most Kenyan and international shared hosts give you cPanel with PHP already installed, so there is nothing extra to set up. You simply copy your files into the `public_html` folder, which plays the same role as `htdocs`.

## Seeing errors while you learn

While developing, you want PHP to tell you when something goes wrong. XAMPP shows errors by default. On other setups you can add these two lines at the top of a script:

```php
<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
```

> **Warning:** Turn `display_errors` **off** on a live website. Error messages can reveal file paths, database names and other details that help attackers.

Next, let's look at the rules for writing PHP code in [PHP Syntax](syntax).

```exercise
Q: Fill in the XAMPP folder where your PHP files must be saved.
C:\xampp\___
A: htdocs
```

```exercise
Q: Complete the command that starts PHP's built-in server on port 8000.
php ___ localhost:8000
A: -S
H: It is a single capital letter flag.
```
