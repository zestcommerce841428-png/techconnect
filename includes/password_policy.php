<?php
/**
 * Password policy.
 *
 * Length alone is not a policy: "password", "12345678" and "qwertyui" all pass
 * an 8-character minimum and are the first things any attacker tries. This
 * blocks the passwords that are actually guessed, rather than demanding symbol
 * soup — NIST 800-63B explicitly recommends checking against common/breached
 * lists and dropping composition rules, because forced complexity pushes people
 * toward "Password1!" and sticky notes.
 *
 * Deliberately local: no HaveIBeenPwned call. An external lookup on every signup
 * adds latency, a network dependency and a privacy question, for a list whose
 * head — the part attackers actually try first — fits in this file.
 */

/** Most-guessed passwords and keyboard walks, normalised to lowercase. */
function common_passwords(): array
{
    static $list = null;
    if ($list !== null) return $list;
    $raw = [
        // Global top offenders
        'password', 'password1', 'password123', '123456', '1234567', '12345678', '123456789', '1234567890',
        '12345', '111111', '000000', '123123', '654321', '666666', '121212', '112233', '789456', '987654321',
        'qwerty', 'qwerty123', 'qwertyui', 'qwertyuiop', 'asdfgh', 'asdfghjkl', 'zxcvbnm', '1qaz2wsx', 'qazwsx',
        'abc123', 'abcd1234', 'a1b2c3d4', 'iloveyou', 'admin', 'admin123', 'administrator', 'root', 'toor',
        'letmein', 'welcome', 'welcome1', 'monkey', 'dragon', 'sunshine', 'princess', 'football', 'baseball',
        'superman', 'batman', 'trustno1', 'master', 'shadow', 'michael', 'jennifer', 'jordan', 'harley',
        'passw0rd', 'p@ssword', 'p@ssw0rd', 'secret', 'login', 'test123', 'guest', 'changeme', 'default',
        'whatever', 'freedom', 'starwars', 'computer', 'internet', 'samsung', 'google', 'facebook', 'linkedin',
        // Common in India specifically — these are guessed here far more than the global list suggests
        'india123', 'bharat123', 'krishna', 'krishna123', 'ganesh', 'ganesha', 'shivam', 'omsairam', 'saibaba',
        'jaimatadi', 'jaishreeram', 'radhakrishna', 'hanuman', 'mahadev', 'bajrangbali', 'chennai', 'mumbai',
        'delhi123', 'kolkata', 'bangalore', 'hyderabad', 'india@123', 'india2024', 'rahul123', 'amitabh',
        'sachin', 'sachin123', 'dhoni', 'dhoni07', 'viratkohli', 'cricket', 'cricket123', 'bollywood',
        'ilovemyindia', 'password@123', 'welcome@123', 'admin@123', 'qwerty@123', 'abcd@1234',
    ];
    return $list = array_flip($raw);
}

/**
 * Validates a password. Returns [] when acceptable, or a list of problems.
 *
 * @param string $password The candidate.
 * @param array  $context  Personal strings it must not contain (username, email).
 */
function validate_password(string $password, array $context = []): array
{
    $errors = [];
    $min = max(8, (int) setting('password_min_length', '8'));

    if (mb_strlen($password) < $min) {
        $errors[] = 'Password must be at least ' . $min . ' characters.';
        return $errors; // no point piling on further complaints
    }
    if (mb_strlen($password) > 200) {
        // Bcrypt truncates past 72 bytes anyway; a huge input is a DoS vector.
        $errors[] = 'Password must be 200 characters or fewer.';
        return $errors;
    }

    $lower = mb_strtolower($password);

    if (isset(common_passwords()[$lower])) {
        $errors[] = 'That password is one of the most commonly used — an attacker would try it within seconds. Please choose something else.';
    }

    // Trailing digits are the usual disguise: "password1", "krishna123".
    $stripped = rtrim($lower, '0123456789!@#$');
    if ($stripped !== $lower && $stripped !== '' && isset(common_passwords()[$stripped])) {
        $errors[] = 'That is a common password with numbers added — attackers try those variations first. Please choose something else.';
    }

    // Short password built ON a common one ("dhoni07x", "qwertyX1"). Cracking
    // tools apply exactly these mutation rules to the same wordlist, so the
    // variation buys almost nothing. Only enforced under 14 characters: a long
    // passphrase that merely happens to begin with a dictionary word
    // ("password-monsoon-chai-77") is genuinely strong, and rejecting it would
    // push people toward something worse.
    if (!$errors && mb_strlen($password) < 14) {
        foreach (array_keys(common_passwords()) as $common) {
            if (mb_strlen((string) $common) >= 5 && str_starts_with($lower, (string) $common)) {
                $errors[] = 'That is a small variation on a very common password. Attackers test these automatically — please choose something less predictable.';
                break;
            }
        }
    }

    if (preg_match('/^(.)\1+$/u', $password)) {
        $errors[] = 'Password cannot be the same character repeated.';
    }
    if (preg_match('/^(?:0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef)/i', $password)) {
        $errors[] = 'Password cannot start with a simple sequence like "1234" or "abcd".';
    }

    // Personal data: the first thing a targeted attacker tries.
    foreach ($context as $item) {
        $item = trim((string) $item);
        if ($item === '') continue;
        // Compare against the local part of an email, not the whole address.
        $item = mb_strtolower(explode('@', $item)[0]);
        if (mb_strlen($item) >= 3 && str_contains($lower, $item)) {
            $errors[] = 'Password must not contain your username or email address.';
            break;
        }
    }

    if (count(count_chars($password, 1)) < 4) {
        $errors[] = 'Password uses too few distinct characters.';
    }

    return array_values(array_unique($errors));
}
