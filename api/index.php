<?php
ob_start(); // Buffer all output so warnings don't break header() redirects
require_once __DIR__ . '/Parsedown.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = urldecode($path);

if (php_sapi_name() === 'cli-server' && preg_match('/\.(css|js|png|jpg|jpeg|gif)$/', $path)) {
    return false;
}

if (strpos($path, '/content/files/') === 0) {
    $filePath = realpath(__DIR__ . '/..' . $path);
    $allowedDir = realpath(__DIR__ . '/../content/files');
    if ($filePath && $allowedDir && strpos($filePath, $allowedDir) === 0 && is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'txt'  => 'text/plain',
            'zip'  => 'application/zip',
        ];
        $contentType = $mimeTypes[strtolower($ext)] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

if ($path === '/admin/config.yml' || $path === '/config.yml') {
    header('Content-Type: application/x-yaml');
    echo file_get_contents(__DIR__ . '/../admin/config.yml');
    exit;
}

if (strpos($path, '/admin') === 0) {
    if ($path === '/admin') {
        header('Location: /admin/');
        exit;
    }
    echo file_get_contents(__DIR__ . '/../admin/index.html');
    exit;
}

function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function translate_cat($slug) {
    $map = [
        'music' => 'Música',
        'programming' => 'IT',
        'spirituality' => 'Espiritualidad',
        'family' => 'Familia',
        'songs' => 'Canciones',
        'drums' => 'Batería',
        'bands' => 'Bandas',
        'anecdotes' => 'Anécdotas',
        'travel' => 'Viajes',
        'life' => 'Vida'
    ];
    return $map[strtolower($slug)] ?? ucfirst($slug);
}

function format_date_es($dateStr) {
    if (empty($dateStr)) return '';
    try {
        $dt = new DateTime($dateStr);
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        $day = $dt->format('j');
        $monthNum = (int)$dt->format('n');
        $year = $dt->format('Y');
        return $day . ' de ' . ($months[$monthNum] ?? '') . ' del ' . $year;
    } catch (Exception $e) {
        return $dateStr;
    }
}

function get_comments($slug) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    if (!$supabaseUrl || !$supabaseKey) return [];
    $url = $supabaseUrl . '/rest/v1/comments?post_slug=eq.' . urlencode($slug) . '&approved=eq.true&order=created_at.asc&select=id,author_name,content,created_at';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
    ]);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

function save_comment($slug, $name, $email, $content) {
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_KEY');
    if (!$supabaseUrl || !$supabaseKey) return false;
    $url = $supabaseUrl . '/rest/v1/comments';
    $body = json_encode([
        'post_slug' => $slug,
        'author_name' => $name,
        'author_email' => $email,
        'content' => $content,
    ]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $httpCode >= 200 && $httpCode < 300;
}

function get_all_posts() {
    $posts = [];
    $dir = __DIR__ . '/../content/posts';
    if (!is_dir($dir)) return [];
    
    $parsedown = new Parsedown();
    
    foreach (glob($dir . '/*.md') as $file) {
        $content = file_get_contents($file);
        $meta = [];
        $body = $content;
        $slug = basename($file, '.md');
        
        if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $content, $matches)) {
            $yaml = $matches[1];
            $body = $matches[2];
            foreach (explode("\n", $yaml) as $line) {
                $parts = explode(":", $line, 2);
                if (count($parts) == 2) {
                    $meta[trim($parts[0])] = trim($parts[1], " \"'\r");
                }
            }
        }
        
        if (isset($meta['draft']) && strtolower($meta['draft']) === 'true') {
            continue; // Skip drafts
        }
        
        $section = strtolower(trim($meta['section'] ?? 'general'));
        $subsection = strtolower(trim($meta['subsection'] ?? ''));
        if ($subsection === '""' || $subsection === "''" || $subsection === 'null') $subsection = '';
        
        $posts[] = [
            'id' => $slug,
            'title' => $meta['title'] ?? 'Untitled',
            'date' => $meta['date'] ?? date('Y-m-d'),
            'section' => $section,
            'subsection' => $subsection,
            'featured_image' => $meta['featured_image'] ?? null,
            'excerpt' => $meta['excerpt'] ?? '',
            'content_raw' => $body,
            'content' => $parsedown->text($body),
            'url' => '/' . $section . ($subsection ? '/' . $subsection : '') . '/' . $slug
        ];
    }
    
    usort($posts, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
    return $posts;
}

$allPosts = get_all_posts();

// Build navigation tree
$navTree = [];
foreach ($allPosts as $p) {
    if (!isset($navTree[$p['section']])) {
        $navTree[$p['section']] = [];
    }
    if ($p['subsection'] && !in_array($p['subsection'], $navTree[$p['section']])) {
        $navTree[$p['section']][] = $p['subsection'];
    }
}
ksort($navTree);
foreach ($navTree as $sec => $subs) { 
    sort($subs); 
    $navTree[$sec] = $subs;
}

// Routing logic
$parts = explode('/', trim($path, '/'));
$route = '404';
$matchedPost = null;
$matchedSection = null;
$matchedSubsection = null;

if (empty($parts[0])) {
    $route = 'home';
} elseif (count($parts) === 1) {
    // Could be a section
    $sec = strtolower($parts[0]);
    if (isset($navTree[$sec])) {
        $route = 'section';
        $matchedSection = $sec;
    }
} elseif (count($parts) === 2) {
    $sec = strtolower($parts[0]);
    $sub_or_slug = strtolower($parts[1]);
    
    // Check if it's a post
    foreach ($allPosts as $p) {
        if ($p['section'] === $sec && $p['id'] === $sub_or_slug) {
            $route = 'post';
            $matchedPost = $p;
            break;
        }
    }
    
    if ($route === '404' && isset($navTree[$sec]) && in_array($sub_or_slug, $navTree[$sec])) {
        $route = 'subsection';
        $matchedSection = $sec;
        $matchedSubsection = $sub_or_slug;
    }
} elseif (count($parts) === 3) {
    $sec = strtolower($parts[0]);
    $sub = strtolower($parts[1]);
    $slug = strtolower($parts[2]);
    
    foreach ($allPosts as $p) {
        if ($p['section'] === $sec && $p['subsection'] === $sub && $p['id'] === $slug) {
            $route = 'post';
            $matchedPost = $p;
            break;
        }
    }
}

// Handle comment form submission
$commentSuccess = false;
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content'])) {
    $cSlug    = trim($_POST['post_slug'] ?? '');
    $cName    = trim($_POST['author_name'] ?? '');
    $cEmail   = trim($_POST['author_email'] ?? '');
    $cContent = trim($_POST['comment_content'] ?? '');
    if (empty($cName) || empty($cEmail) || empty($cContent)) {
        $commentError = 'Por favor completá todos los campos.';
    } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
        $commentError = 'El email no es válido.';
    } elseif (mb_strlen($cContent) < 3) {
        $commentError = 'El comentario es demasiado corto.';
    } else {
        if (save_comment($cSlug, $cName, $cEmail, $cContent)) {
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?commented=1');
            exit;
        } else {
            $commentError = 'Hubo un error al guardar el comentario. Intentá de nuevo.';
        }
    }
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My fake plastic blog</title>
    <link href="https://fonts.googleapis.com/css2?family=VT323&family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #111118; 
            --surface: #1e1e28; 
            --text: #e0e0e0; 
            --primary: #f8e71c; /* Yellow highlight */
            --secondary: #39ff14; /* Neon green */
            --accent: #ff4081; /* Pink/Red accent */
            --border: #444455;
        }
        body { 
            font-family: 'Courier New', Courier, monospace; 
            background: var(--bg); 
            color: var(--text); 
            margin: 0; 
            padding: 0; 
            line-height: 1.6; 
            font-size: 18px; 
            background-image: radial-gradient(#222 1px, transparent 1px);
            background-size: 4px 4px;
        }
        body { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        
        .layout { 
            display: grid; 
            grid-template-columns: 1fr 320px; 
            gap: 2rem; 
            align-items: start; 
        }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        header { 
            background: var(--surface);
            padding: 2rem; 
            border: 4px solid var(--border);
            border-radius: 8px;
            margin-bottom: 2rem; 
            display: flex; 
            flex-direction: column; 
            align-items: center;
            text-align: center;
            box-shadow: 4px 4px 0px #000;
        }
        header a.logo { 
            font-family: 'Press Start 2P', cursive; 
            text-decoration: none; 
            color: var(--primary); 
            font-size: 1.8rem; 
            text-shadow: 2px 2px 0px #000;
            margin-bottom: 1.5rem; 
            line-height: 1.4;
        }
        
        nav { width: 100%; }
        nav ul { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
        nav li { position: relative; }
        nav a { 
            font-family: 'VT323', monospace; 
            font-size: 1.5rem; 
            text-decoration: none; 
            color: var(--text); 
            text-transform: uppercase; 
            letter-spacing: 1px; 
        }
        nav a:hover, nav a.active { color: var(--secondary); text-shadow: 0 0 5px var(--secondary); }
        
        .nav-subs { 
            display: none; 
            position: absolute; 
            top: 100%; 
            left: 50%;
            transform: translateX(-50%);
            background: var(--surface); 
            border: 2px solid var(--border); 
            padding: 0.5rem; 
            z-index: 10; 
            min-width: 150px; 
            box-shadow: 4px 4px 0px #000;
        }
        nav li:hover .nav-subs { display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-subs a { font-size: 1.2rem; }
        
        main { display: flex; flex-direction: column; gap: 2rem; margin-bottom: 4rem; }
        h1, h2, h3 { 
            font-family: 'VT323', monospace; 
            font-weight: normal; 
            line-height: 1.2; 
            margin: 0 0 1rem; 
            color: var(--primary); 
        }
        h1 { font-size: 3rem; text-shadow: 2px 2px 0px #000; }
        h2 { font-size: 2.2rem; }
        
        a { color: var(--primary); text-decoration: none; border-bottom: 1px dotted var(--primary); }
        a:hover { color: var(--secondary); border-bottom-color: var(--secondary); text-shadow: 0 0 3px var(--secondary); }
        
        .card { 
            padding: 2rem; 
            background: var(--surface);
            border: 4px solid var(--border); 
            border-radius: 8px;
            box-shadow: 4px 4px 0px #000;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
            box-sizing: border-box;
            max-width: 100%;
            overflow-x: hidden;
        }
        .card > p, .card > div[style*="-webkit-box"] { flex: 1; }
        
        .sidebar {
            background: var(--surface);
            border: 4px solid var(--border);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 4px 4px 0px #000;
            position: sticky;
            top: 2rem;
        }
        .sidebar h3 {
            font-size: 1.8rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            font-family: 'VT323', monospace;
            color: var(--primary);
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
        }
        .sidebar li { margin-bottom: 0.8rem; }
        .sidebar a {
            font-family: 'VT323', monospace;
            font-size: 1.4rem;
            color: var(--text);
            border: none;
        }
        .sidebar a:hover {
            color: var(--secondary);
            text-shadow: 0 0 5px var(--secondary);
        }
        
        .meta { 
            font-family: 'VT323', monospace; 
            font-size: 1.2rem; 
            color: #aaa; 
            margin-bottom: 1.5rem; 
            text-transform: uppercase; 
        }
        .meta a { color: var(--accent); border-bottom-color: var(--accent); }
        .meta a:hover { color: var(--secondary); }
        
        /* Post Content Images */
        .post-content img { 
            max-width: 100%; 
            max-height: 500px;
            height: auto; 
            object-fit: cover;
            display: block; 
            margin: 1.5rem auto; 
            border: 2px solid var(--border);
            border-radius: 4px;
            box-shadow: 2px 2px 0px #000;
        }
        .post-content p { margin-bottom: 1.5rem; }
        .post-content iframe,
        .post-content video,
        .post-content embed,
        .post-content object {
            max-width: 100%;
            width: 100%;
            height: auto;
            aspect-ratio: 16 / 9;
            border: 2px solid var(--border);
            border-radius: 4px;
            margin: 1.5rem auto;
            display: block;
            box-shadow: 2px 2px 0px #000;
        }
        .post-content blockquote { 
            margin: 1.5rem 0; 
            padding: 1rem; 
            background: rgba(0,0,0,0.3);
            border-left: 4px solid var(--secondary); 
            color: #ddd; 
            font-style: italic;
        }
        
        /* Post Header & Hero Image Styles */
        .post-hero-wrapper {
            width: 100%;
            max-height: 380px;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            border-radius: 6px;
            border: 3px solid var(--border);
            margin-bottom: 2rem;
            background-color: #0b0b10;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 3px 3px 0px #000;
        }
        .post-hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }
        
        /* Card Hero Image Styles */
        .card-hero-wrapper {
            display: block;
            width: 100%;
            height: 200px;
            overflow: hidden;
            border-radius: 4px;
            border: 2px solid var(--border);
            margin-bottom: 1rem;
            background: #0b0b10;
            box-shadow: 2px 2px 0px #000;
        }
        .card-hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            transition: transform 0.3s ease;
        }
        .card-hero-wrapper:hover .card-hero-image {
            transform: scale(1.04);
        }
        
        @media (max-width: 900px) {
            .post-hero-wrapper {
                max-height: 280px;
                aspect-ratio: 16 / 9;
            }
        }
        @media (max-width: 600px) {
            .post-hero-wrapper {
                max-height: 200px;
                margin-bottom: 1.5rem;
                border-width: 2px;
            }
            .card-hero-wrapper {
                height: 160px;
            }
        }
        
        .breadcrumbs { 
            font-family: 'VT323', monospace; 
            font-size: 1.3rem; 
            margin-bottom: 2rem; 
            color: #aaa; 
            text-transform: uppercase; 
            background: var(--surface);
            padding: 0.5rem 1rem;
            border: 2px solid var(--border);
            display: inline-block;
            border-radius: 4px;
        }
        .breadcrumbs a { color: var(--text); border: none; }
        .breadcrumbs a:hover { color: var(--secondary); }
        
        /* Comments Section */
        .comments-section {
            margin-top: 2.5rem;
            border-top: 3px solid var(--border);
            padding-top: 2rem;
        }
        .comments-title {
            font-family: 'VT323', monospace;
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 1.5rem;
            text-shadow: 0 0 8px var(--secondary);
        }
        .comment-item {
            background: rgba(0,0,0,0.25);
            border: 2px solid var(--border);
            border-left: 4px solid var(--secondary);
            border-radius: 4px;
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
        }
        .comment-meta {
            font-family: 'VT323', monospace;
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 0.4rem;
        }
        .comment-author { color: var(--primary); font-weight: bold; }
        .comment-date { color: #888; margin-left: 0.5rem; }
        .comment-body { font-size: 0.95rem; color: var(--text); line-height: 1.6; }
        .no-comments {
            font-family: 'VT323', monospace;
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }
        /* Comment Form */
        .comment-form-wrapper {
            margin-top: 2rem;
            background: rgba(0,0,0,0.2);
            border: 2px solid var(--border);
            border-radius: 6px;
            padding: 1.5rem;
        }
        .comment-form-title {
            font-family: 'VT323', monospace;
            font-size: 1.6rem;
            color: var(--primary);
            margin-bottom: 1.2rem;
        }
        .comment-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 600px) {
            .comment-form-row { grid-template-columns: 1fr; }
        }
        .comment-form input,
        .comment-form textarea {
            width: 100%;
            background: #0d0d14;
            border: 2px solid var(--border);
            border-radius: 4px;
            color: var(--text);
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            padding: 0.6rem 0.8rem;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .comment-form input:focus,
        .comment-form textarea:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 6px rgba(57,255,20,0.2);
        }
        .comment-form textarea { min-height: 110px; resize: vertical; }
        .comment-form input::placeholder,
        .comment-form textarea::placeholder { color: #555; }
        .comment-submit {
            font-family: 'VT323', monospace;
            font-size: 1.4rem;
            background: transparent;
            border: 3px solid var(--secondary);
            color: var(--secondary);
            padding: 0.4rem 1.8rem;
            cursor: pointer;
            border-radius: 4px;
            margin-top: 0.8rem;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .comment-submit:hover {
            background: var(--secondary);
            color: #000;
            box-shadow: 0 0 10px var(--secondary);
        }
        .comment-alert-success {
            background: rgba(57,255,20,0.1);
            border: 2px solid var(--secondary);
            color: var(--secondary);
            font-family: 'VT323', monospace;
            font-size: 1.2rem;
            padding: 0.6rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .comment-alert-error {
            background: rgba(255,64,129,0.1);
            border: 2px solid var(--accent);
            color: var(--accent);
            font-family: 'VT323', monospace;
            font-size: 1.2rem;
            padding: 0.6rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .comment-disclaimer {
            font-size: 0.8rem;
            color: #666;
            margin: 0.8rem 0 1rem;
            font-style: italic;
        }
    </style>
</head>
<body>
    <header>
        <a href="/" class="logo">My fake plastic blog</a>
        <nav>
            <ul>
                <li><a href="/" class="<?= $route === 'home' ? 'active' : '' ?>">Inicio</a></li>
                <?php foreach ($navTree as $sec => $subs): ?>
                    <li>
                        <a href="/<?= e($sec) ?>" class="<?= ($matchedSection === $sec) ? 'active' : '' ?>"><?= e(translate_cat($sec)) ?></a>
                        <?php if (!empty($subs)): ?>
                            <div class="nav-subs">
                                <?php foreach ($subs as $sub): ?>
                                    <a href="/<?= e($sec) ?>/<?= e($sub) ?>"><?= e(translate_cat($sub)) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </header>
    <div class="layout">
    <main>
<?php

function render_post_card($post) {
    echo "<div class='card'>";
    if ($post['featured_image']) {
        echo "<a href='{$post['url']}' class='card-hero-wrapper'><img src='".e($post['featured_image'])."' class='card-hero-image' alt='".e($post['title'])."' /></a>";
    }
    echo "<h2><a href='{$post['url']}'>".e($post['title'])."</a></h2>";
    
    $catDisplay = "<a href='/".urlencode($post['section'])."'>".e(translate_cat($post['section']))."</a>";
    if ($post['subsection']) {
        $catDisplay .= " / <a href='/".urlencode($post['section'])."/".urlencode($post['subsection'])."'>".e(translate_cat($post['subsection']))."</a>";
    }
    
    echo "<div class='meta'>En {$catDisplay} el ".e(format_date_es($post['date']))."</div>";
    
    if ($post['excerpt']) {
        echo "<p style='margin-bottom: 1rem;'>".e($post['excerpt'])."</p>";
    } else {
        echo "<div style='display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 1rem;'>";
        echo strip_tags($post['content'], '<p><br><b><i><strong><em>');
        echo "</div>";
    }
    echo "<a href='{$post['url']}' style='font-family: \"VT323\", monospace; font-size: 1.4rem;'>Leer más &rarr;</a>";
    echo "</div>";
}

if ($route === 'home') {
    echo "<h1>Últimas publicaciones</h1>";
    if (empty($allPosts)) echo "<p>Aún no hay publicaciones.</p>";
    echo "<div class='posts-grid'>";
    foreach ($allPosts as $post) {
        render_post_card($post);
    }
    echo "</div>";
} elseif ($route === 'post') {
    $post = $matchedPost;
    
    $bc = "<a href='/".urlencode($post['section'])."'>".e(translate_cat($post['section']))."</a>";
    if ($post['subsection']) {
        $bc .= " > <a href='/".urlencode($post['section'])."/".urlencode($post['subsection'])."'>".e(translate_cat($post['subsection']))."</a>";
    }
    
    echo "<div class='breadcrumbs'><a href='/'>Inicio</a> > {$bc} > " . e($post['title']) . "</div>";
    echo "<div class='card'>";
    if ($post['featured_image']) {
        echo "<div class='post-hero-wrapper'><img src='".e($post['featured_image'])."' class='post-hero-image' alt='".e($post['title'])."' /></div>";
    }
    echo "<h1>".e($post['title'])."</h1>";
    echo "<div class='meta'>En {$bc} el ".e(format_date_es($post['date']))."</div>";
    echo "<div class='post-content'>".$post['content']."</div>";
    
    // --- Comments Section ---
    $comments = get_comments($post['id']);
    $commentCount = count($comments);
    echo "<div class='comments-section'>";
    echo "<div class='comments-title'>&#128172; COMENTARIOS ({$commentCount})</div>";
    
    if (isset($_GET['commented'])) {
        echo "<div class='comment-alert-success'>&#10003; ¡Comentario publicado! Gracias por participar.</div>";
    }
    if ($commentError) {
        echo "<div class='comment-alert-error'>&#9888; " . e($commentError) . "</div>";
    }
    
    if (empty($comments)) {
        echo "<div class='no-comments'>&gt; Aún no hay comentarios. ¡Sé el primero!</div>";
    } else {
        foreach ($comments as $c) {
            $cDate = format_date_es($c['created_at'] ?? '');
            echo "<div class='comment-item'>";
            echo "<div class='comment-meta'><span class='comment-author'>&gt; " . e($c['author_name']) . "</span><span class='comment-date'> &bull; " . e($cDate) . "</span></div>";
            echo "<div class='comment-body'>" . nl2br(e($c['content'])) . "</div>";
            echo "</div>";
        }
    }
    
    // Comment form
    $postUrl = strtok($_SERVER['REQUEST_URI'], '?');
    echo "<div class='comment-form-wrapper'>";
    echo "<div class='comment-form-title'>&gt;_ DEJÁ TU COMENTARIO</div>";
    echo "<form class='comment-form' id='comment-form' method='POST' action='" . e($postUrl) . "'>";
    echo "<input type='hidden' name='post_slug' value='" . e($post['id']) . "'>";
    echo "<div class='comment-form-row'>";
    echo "<input type='text' id='cf-name' name='author_name' placeholder='Tu alias o nombre' maxlength='80' required value='" . e($_POST['author_name'] ?? '') . "'>";
    echo "<input type='email' id='cf-email' name='author_email' placeholder='Tu email (no se publica)' maxlength='120' required value='" . e($_POST['author_email'] ?? '') . "'>";
    echo "</div>";
    echo "<textarea name='comment_content' placeholder='Escribí tu comentario aquí...' maxlength='2000' required>" . e($_POST['comment_content'] ?? '') . "</textarea>";
    echo "<p class='comment-disclaimer'>&#9432; Dejando un comentario aceptás recibir un email cada vez que haya un nuevo post.</p>";
    echo "<button type='submit' class='comment-submit'>ENVIAR &rarr;</button>";
    echo "</form>";
    echo "</div>";
    echo "</div>"; // end comments-section
    
    echo "</div>"; // end card
} elseif ($route === 'section') {
    echo "<div class='breadcrumbs'><a href='/'>Inicio</a> > " . e(translate_cat($matchedSection)) . "</div>";
    echo "<h1>".e(translate_cat($matchedSection))."</h1>";
    $hasPosts = false;
    echo "<div class='posts-grid'>";
    foreach ($allPosts as $post) {
        if ($post['section'] === $matchedSection) {
            $hasPosts = true;
            render_post_card($post);
        }
    }
    echo "</div>";
    if (!$hasPosts) echo "<p>No hay publicaciones en esta sección.</p>";
} elseif ($route === 'subsection') {
    echo "<div class='breadcrumbs'><a href='/'>Inicio</a> > <a href='/".urlencode($matchedSection)."'>" . e(translate_cat($matchedSection)) . "</a> > " . e(translate_cat($matchedSubsection)) . "</div>";
    echo "<h1>".e(translate_cat($matchedSubsection))." <small style='font-size:1rem;font-weight:normal;color:#666;'>in ".e(translate_cat($matchedSection))."</small></h1>";
    $hasPosts = false;
    echo "<div class='posts-grid'>";
    foreach ($allPosts as $post) {
        if ($post['section'] === $matchedSection && $post['subsection'] === $matchedSubsection) {
            $hasPosts = true;
            render_post_card($post);
        }
    }
    echo "</div>";
    if (!$hasPosts) echo "<p>No hay publicaciones en esta subsección.</p>";
} else {
    echo "<h1>404 Not Found</h1>";
    echo "<p>La página que solicitaste no pudo ser encontrada.</p>";
}
?>
    </main>
    <aside class="sidebar">
        <h3>Categorías</h3>
        <ul>
            <?php foreach ($navTree as $sec => $subs): ?>
                <li><a href="/<?= urlencode($sec) ?>">&gt; <?= e(translate_cat($sec)) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <h3>Últimas</h3>
        <ul>
            <?php $count=0; foreach ($allPosts as $p): if($count++>=5) break; ?>
                <li><a href="<?= $p['url'] ?>">&gt; <?= e($p['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>
    </div>
</body>
<script>
// Persist alias + email in localStorage so user doesn't have to retype
(function() {
    var nameInput  = document.getElementById('cf-name');
    var emailInput = document.getElementById('cf-email');
    var form       = document.getElementById('comment-form');
    if (!nameInput || !emailInput || !form) return;

    // Pre-fill from storage if the fields are empty (no server-side value)
    if (!nameInput.value) nameInput.value  = localStorage.getItem('cf_name')  || '';
    if (!emailInput.value) emailInput.value = localStorage.getItem('cf_email') || '';

    // Save on submit
    form.addEventListener('submit', function() {
        if (nameInput.value)  localStorage.setItem('cf_name',  nameInput.value);
        if (emailInput.value) localStorage.setItem('cf_email', emailInput.value);
    });
})();
</script>
</html>
