<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('contact', 5, 600)) {
        $errors[] = 'Too many messages sent. Please try again later.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
            $errors[] = 'Please fill in all fields with a valid email.';
        } else {
            send_mail(SMTP_FROM, SITE_NAME, 'Contact form: ' . $name,
                '<p><strong>From:</strong> ' . e($name) . ' (' . e($email) . ')</p><p>' . nl2br(e($message)) . '</p>');
            $sent = true;
        }
    }
}

$pageTitle = 'Contact — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto bg-white border rounded-lg p-6">
  <h1 class="text-xl font-semibold mb-4">Contact us</h1>
  <?php if ($sent): ?>
    <p class="text-sm text-green-700">Thanks — we'll get back to you soon.</p>
  <?php else: ?>
    <?php foreach ($errors as $err): ?>
      <div class="mb-3 rounded border border-red-300 bg-red-50 text-red-800 px-3 py-2 text-sm"><?= e($err) ?></div>
    <?php endforeach; ?>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <label for="contact_name" class="sr-only">Your name</label>
      <input id="contact_name" type="text" name="name" required placeholder="Your name" class="w-full border rounded px-3 py-2">
      <label for="contact_email" class="sr-only">Your email</label>
      <input id="contact_email" type="email" name="email" required placeholder="Your email" class="w-full border rounded px-3 py-2">
      <label for="contact_message" class="sr-only">Message</label>
      <textarea id="contact_message" name="message" required rows="5" placeholder="Message" class="w-full border rounded px-3 py-2"></textarea>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Send</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
