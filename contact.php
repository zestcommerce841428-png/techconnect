<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$sent = false;
$errors = [];
$name = $email = $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if (honeypot_tripped()) {
        $sent = true; // bot: fake success, send nothing
    } elseif (!rate_limit('contact', 5, 600)) {
        $errors[] = 'Too many messages sent. Please try again later.';
    } else {
        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address doesn\'t look valid — please check it (e.g. you@example.com).';
        }
        if (mb_strlen($message) < 10) {
            $errors[] = 'Your message is too short — please write at least 10 characters.';
        }
        if (!$errors) {
            $ok = send_mail(SMTP_FROM, SITE_NAME, 'Contact form: ' . $name,
                '<p><strong>From:</strong> ' . e($name) . ' (' . e($email) . ')</p><p>' . nl2br(e($message)) . '</p>',
                $email);
            if ($ok) {
                $sent = true;
            } else {
                $errors[] = 'Sorry, your message could not be sent right now. Please try again later or email us directly.';
            }
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
      <?= csrf_field() . honeypot_field() ?>
      <label for="contact_name" class="sr-only">Your name</label>
      <input id="contact_name" type="text" name="name" required maxlength="100" value="<?= e($name) ?>" placeholder="Your name" class="w-full border rounded px-3 py-2">
      <label for="contact_email" class="sr-only">Your email</label>
      <input id="contact_email" type="email" name="email" required maxlength="255" value="<?= e($email) ?>" placeholder="Your email" class="w-full border rounded px-3 py-2">
      <label for="contact_message" class="sr-only">Message</label>
      <textarea id="contact_message" name="message" required minlength="10" rows="5" placeholder="Message (at least 10 characters)" class="w-full border rounded px-3 py-2"><?= e($message) ?></textarea>
      <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded">Send</button>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
