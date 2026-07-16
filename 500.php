<?php
// Deliberately standalone — does not require db.php/auth.php, since a 500
// error may be caused by the database itself being unreachable and this
// page must still render.
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something went wrong</title>
<meta name="robots" content="noindex">
<style>
  body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .box { text-align: center; padding: 2rem; }
  .code { font-size: 3.5rem; font-weight: 700; color: #cbd5e1; }
  a { color: #4f46e5; text-decoration: none; }
  a:hover { text-decoration: underline; }
</style>
</head>
<body>
  <div class="box">
    <div class="code">500</div>
    <h1>Something went wrong on our end</h1>
    <p>Please try again in a moment.</p>
    <p><a href="/">Go home</a></p>
  </div>
</body>
</html>
