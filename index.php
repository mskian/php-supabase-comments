<?php

include './store.php';
(new DevCoder\DotEnv('./.env'))->load();

session_set_cookie_params([
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=63072000');
header('X-Robots-Tag: noindex, nofollow', true);

function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getFullUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . $host . $scriptName, '/'); 
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function displayNotification(string $type, string $message): string {
    $notificationType = 'is-light';

    switch ($type) {
        case 'success':
            $notificationType = 'is-success';
            break;
        case 'danger':
            $notificationType = 'is-danger';
            break;
        case 'warning':
            $notificationType = 'is-warning';
            break;
        case 'info':
            $notificationType = 'is-info';
            break;
    }

    return <<<HTML
    <div class="notification $notificationType">
        <p class="mb-4 mt-4">$message</p>
        <button class="delete" onclick="this.parentElement.remove();"></button>
    </div>
    HTML;
}

$csrfToken = generateCsrfToken();
$commentsPerPage = 10;
$page = isset($_GET['page']) 
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) 
    : 1;
$page = ($page === false) ? 1 : $page;
$page = sanitizeInput($page);
$offset = ($page - 1) * $commentsPerPage;

$notifications = [];

// Supabase Project URL and Project API Keys
$supabaseUrl = getenv('supabaseUrl');
$apiKey = getenv('apiKey');
$pkey = getenv('pkey');
$table = getenv('table');

// Cloudflare turnstile sitekey and secretKey
$secretKey = getenv('secretKey');
$siteKey = getenv('siteKey');
$url = getenv('url');

function insertCommentToSupabase($name, $comment) {
    global $supabaseUrl, $apiKey, $table, $pkey;

    $data = [
        'name' => $name,
        'comment' => $comment,
        'created_at' => (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
    ];

    $url = $supabaseUrl . '/rest/v1/' . $table;
    $headers = [
        'apikey: ' . $pkey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return json_decode($response);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $honeypot = $_POST['honeypot'] ?? '';
    $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';

    if (!empty($honeypot)) {
        $notifications[] = displayNotification('danger', 'Spam detected.');
    } elseif (!validateCsrfToken($submittedToken)) {
        $notifications[] = displayNotification('danger', 'Invalid CSRF token. Please try again.');
    } elseif (empty($turnstileResponse)) {
        $notifications[] = displayNotification('danger', 'Please complete the CAPTCHA.');
    } else {
        $data = [
            'secret' => $secretKey,
            'response' => $turnstileResponse,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $notifications[] = displayNotification('danger', 'Error while validating CAPTCHA');
            curl_close($ch);
            exit;
        }

        curl_close($ch);

        $result = json_decode($response);

        if (!$result || !$result->success) {
            $notifications[] = displayNotification('danger', 'CAPTCHA validation failed. Please try again.');
        } else {
            $name = sanitizeInput($_POST['name'] ?? '');
            $comment = sanitizeInput($_POST['comment'] ?? '');

            if (empty($name) || empty($comment)) {
                $notifications[] = displayNotification('danger', 'Name and comment cannot be empty.');
            } elseif (strlen($name) > 50 || strlen($comment) > 500) {
                $notifications[] = displayNotification('danger', 'Name must be under 50 characters and comment under 500 characters.');
            } else {
                try {
                    insertCommentToSupabase($name, $comment);
                    $_SESSION['notification'] = ['type' => 'success', 'message' => 'Comment added successfully!'];
                    header('Location: /');
                    exit;
                } catch (Exception $e) {
                    $notifications[] = displayNotification('danger', 'Failed to add comment: ' . htmlspecialchars($e->getMessage()));
                }
            }
        }
    }
}

function fetchCommentsFromSupabase($page, $commentsPerPage) {
    global $supabaseUrl, $apiKey, $table, $pkey;

    //$url = $supabaseUrl . '/rest/v1/' . $table . '?limit=' . $commentsPerPage . '&offset=' . (($page - 1) * $commentsPerPage);
    $url = $supabaseUrl . '/rest/v1/' . $table 
    . '?order=created_at.desc'
    . '&limit=' . $commentsPerPage
    . '&offset=' . (($page - 1) * $commentsPerPage);
    $headers = [
        'apikey: ' . $pkey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return json_decode($response, true);
}


$comments = fetchCommentsFromSupabase($page, $commentsPerPage);

function fetchTotalCommentsCount() {
    global $supabaseUrl, $apiKey, $table, $pkey;

    $url = $supabaseUrl . '/rest/v1/' . $table . '?select=count';
    $headers = [
        'apikey: ' . $pkey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return 0;
    }

    curl_close($ch);
    $result = json_decode($response, true);
    return $result[0]['count'] ?? 0;
}

$totalComments = fetchTotalCommentsCount();
$totalPages = ceil($totalComments / $commentsPerPage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>User Comment Forum</title>
<meta name="description" content="User Comment Forum: Share your messages, feedback, quotes, and status.">
<?php $current_page = htmlspecialchars("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", ENT_QUOTES, 'UTF-8');
    echo '<link rel="canonical" href="' . $current_page . '" />';
?>

<link rel="shortcut icon" type="image/x-icon" href="<?php echo getFullUrl() . '/favicon.ico'; ?>" />
<link rel="icon" type="image/png" sizes="196x196" href="<?php echo getFullUrl() . '/192.png'; ?>" />
<link rel="apple-touch-icon" href="<?php echo getFullUrl() . '/180.png'; ?>" />

<link rel="preconnect" href="https://cdnjs.cloudflare.com">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/1.0.3/css/bulma.min.css" integrity="sha512-4EnjWdm80dyWrJ7rh/tlhNt6fJL52dSDSHNEqfdVmBLpJLPrRYnFa+Kn4ZZL+FRkDL5/7lAXuHylzJkpzkSM2A==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anek+Tamil:wght@100..800&display=swap" rel="stylesheet">
<style>
html, body {
  min-height: 100vh;
  margin: 0;
  padding: 0;
  overflow-x: hidden;
  font-family: "Anek Tamil", sans-serif;
}
body {
  background:rgb(196, 245, 250);
  padding-bottom: 20px;
}
.section {
  padding: 2rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.box {
  position: relative;
  background:rgb(248, 245, 176);
  margin-bottom: 20px;
  padding: 30px;
  padding-top: 40px;
  padding-bottom: 40px;
  letter-spacing: .02em;
  transition: box-shadow 0.3s ease;
  font-family: "Anek Tamil", sans-serif;
  letter-spacing: .04em;
  box-shadow: 0 3px 4px rgba(0, 0, 0, 0.3);
}
.title {
    color:rgb(58, 6, 51);
    font-family: "Anek Tamil", sans-serif;
    font-weight: 600;
    text-align: center;
}
input {
  font-family: "Anek Tamil", sans-serif;
}
.input {
  font-family: "Anek Tamil", sans-serif;
}
.notification {
  font-family: "Anek Tamil", sans-serif;
}
textarea {
    font-family: "Anek Tamil", sans-serif;
}
.textarea {
    font-family: "Anek Tamil", sans-serif;
}
button {
    font-family: "Anek Tamil", sans-serif;
}
.button {
    font-family: "Anek Tamil", sans-serif;
}
.notification {
    font-family: "Anek Tamil", sans-serif;
}
.paginationprevious, .paginationnext {
        cursor: pointer;
    }

    .pagebuttons {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
    }
    .paginationprevious[disabled], .paginationnext[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
    }
    @media (max-width: 768px) {
        .pagebuttons {
            gap: 1rem;
        }
    }
.notification.success {
    background-color: #4CAF50;
    color: white;
}
.quote {
    font-family: "Anek Tamil", sans-serif;
    font-weight: 600;
    color: #333;
    line-height: 1.8;
    margin: 20px auto;
    position: relative;
    padding: 40px 35px;
    background: #fff8e5;
    border-left: 5px solid #ffab00;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    word-wrap: break-word;
    overflow-wrap: break-word;
    transition: transform 0.2s ease-in-out;
}
.quote:hover {
    transform: translateY(-3px);
}
.quote cite {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: #6F1E51;
}
.quote a {
    font-size: 1rem;
    font-weight: 600;
    color: #6F1E51;
}
@media (max-width: 768px) {
    .quote {
        font-size: 1rem;
        padding: 40px 20px;
    }
    .quote:before,
    .quote:after {
        font-size: 2rem;
    }
}
.date {
  font-family: "Anek Tamil", sans-serif;
  font-size: 0.8rem;
  font-weight: 700;
  color: #34495e;
  text-transform: uppercase;
  opacity: 0.8;
  letter-spacing: 1px;
}
</style>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
<section class="section mt-5">
<div class="container">
<div class="columns is-centered">
<div class="column is-three-fifths">
            <?php
                if (isset($_SESSION['notification'])):
                 $notification = $_SESSION['notification'];
                 $type = $notification['type'];
                 $message = $notification['message'];
             ?>
            <div class="notification <?= $type ?>">
               <p class="mb-4 mt-4"><?= $message ?></p>
               <button class="delete" onclick="this.parentElement.remove();"></button>
             </div>
            <?php
               unset($_SESSION['notification']);
               endif;
            ?>
            <?php foreach ($notifications as $notification): ?>
                <?= $notification ?>
            <?php endforeach; ?>
            <form action="" method="post" id="commentForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <div style="display: none;">
                    <input type="text" name="honeypot" value="">
                </div>
                <div class="field">
                    <label class="label has-text-dark">Name:</label>
                    <div class="control">
                        <input class="input is-warning" type="text" name="name" maxlength="50" placeholder="Your Name">
                    </div>
                </div>
                <div class="field">
                    <label class="label has-text-dark mt-5">Comment:</label>
                    <div class="control">
                        <textarea class="textarea is-warning" name="comment" maxlength="500" placeholder="your comments"></textarea>
                    </div>
                </div>
               <div class="field">
                    <div class="control">
                        <div class="cf-turnstile" data-sitekey="<?= $siteKey ?>"></div>
                    </div>
               </div>
                <div class="field">
                    <div class="control">
                        <button class="button is-danger" type="submit">Submit</button>
                    </div>
                </div>
            </form>
            <br />
            <?php if (is_array($comments) && count($comments) > 0): ?>
                <?php foreach ($comments as $row): ?>
                    <blockquote class="quote">
                      <article id="<?= htmlspecialchars($row['id']) ?>">
                       <div class="content">
                       <p class="has-text-dark">
                       <cite><a href="/post.php?id=<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['name']) ?></a>:  <span class="date"><em id="created_at_<?= htmlspecialchars($row['id']) ?>" data-time="<?= htmlspecialchars($row['created_at']) ?>"></em></span></cite>
                       <br />
                       <?= nl2br(htmlspecialchars($row['comment'])) ?>
                       </p>
                       </div>
                       </article>
                    </blockquote>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No comments to show.</p>
                <?php endif; ?>
                <?php if ($totalComments > 0): ?>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination is-centered" role="navigation">
            <div class="pagebuttons">
                <?php
                $start = (($page - 1) * $commentsPerPage) + 1;
                $end = min($page * $commentsPerPage, $totalComments);
                ?>
                <!-- <p class="has-text-black"><?= $start ?> to <?= $end ?> of <?= $totalComments ?> comments</p>-->
                <a 
                    class="paginationprevious button is-warning <?= $page == 1 ? 'is-disabled' : '' ?>" 
                    href="<?= $page == 1 ? '#' : '?page=' . max(1, $page - 1) ?>" 
                    <?= $page == 1 ? 'aria-disabled="true" disabled' : '' ?>>
                    Previous
                </a>
                <a 
                    class="paginationnext button is-warning <?= $page == $totalPages ? 'is-disabled' : '' ?>" 
                    href="<?= $page == $totalPages ? '#' : '?page=' . min($totalPages, $page + 1) ?>" 
                    <?= $page == $totalPages ? 'aria-disabled="true" disabled' : '' ?>>
                    Next
                </a>
            </div>
            </nav>
        <?php endif; ?>
                <?php else: ?>
                    <p>No comments to show. Please add a comment</p>
                <?php endif; ?>
</div>
</div>
</div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', () => {
            const deleteButtons = document.querySelectorAll('.notification .delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', () => {
                    button.parentNode.remove();
                });
            });

            const form = document.getElementById('commentForm');
            form.addEventListener('submit', () => {
                window.onbeforeunload = null;
            });
        });
    function formatToHumanReadable(isoString) {
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata',
            timeZoneName: 'short',
        };
        return new Date(isoString).toLocaleString('en-IN', options);
    }
    document.querySelectorAll('[id^="created_at_"]').forEach(function(element) {
        const isoDate = element.getAttribute('data-time');
        const humanReadableDate = formatToHumanReadable(isoDate);
        element.textContent = humanReadableDate;
    });
</script>
</body>
</html>