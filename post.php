<?php

include './store.php';
(new DevCoder\DotEnv('./.env'))->load();

header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Strict-Transport-Security: max-age=63072000');
header('X-Robots-Tag: noindex, nofollow', true);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$error_message = null;

if (!$id) {
    $error_message = "Invalid or missing ID. Please provide a valid numeric ID.";
} else {
    $url = getenv('supabaseUrl') ."/rest/v1/comments?id=eq." . urlencode($id);
    $headers = [
        "apikey:" . getenv('pkey'),
        "Authorization: Bearer" . getenv('apiKey'),
        "Content-Type: application/json"
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => true,
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        $error_message = "Failed to fetch data. Please try again later.";
    } else {
        $data = json_decode($response, true);
        if (!$data || !is_array($data)) {
            $error_message = "No valid data found for the specified ID.";
        }
    }
    curl_close($curl);
}

$post = isset($data) && is_array($data) ? $data[0] ?? null : null;

function getFullUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($protocol . $host . $scriptName, '/'); 
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>Post by <?= htmlspecialchars($post['name'] ?? 'N/A' ) ?></title>
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
</head>
<body>
    <section class="section">
        <div class="container">
        <div class="columns is-centered">
        <div class="column is-three-fifths">
                <?php if ($error_message): ?>
                    <p class="has-text-dark has-text-centered"><?= htmlspecialchars($error_message) ?></p>
                <?php elseif ($post): ?>
                    <blockquote class="quote mt-6">
                    <article id="<?= htmlspecialchars($post['id']) ?>">
                    <div class="content">
                    <p class="has-text-dark">
                    <cite><?= htmlspecialchars($post['name']) ?>:  <span class="date"><em id="created_at_<?= htmlspecialchars($post['id']) ?>" data-time="<?= htmlspecialchars($post['created_at']) ?>"></em></span></cite>
                    <br />
                    <?= nl2br(htmlspecialchars($post['comment'])) ?>
                   </p>
                   </div>
                   </article>
                   </blockquote>
               <?php else: ?>
                    <p class="has-text-dark has-text-centered">No post found with the specified ID.</p>
                <?php endif; ?>
        </div>
        </div>
        </div>
    </section>
<script>
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