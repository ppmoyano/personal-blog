<?php
require_once __DIR__ . '/Parsedown.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (php_sapi_name() === 'cli-server' && preg_match('/\.(css|js|png|jpg|jpeg|gif)$/', $path)) {
    return false;
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
foreach ($navTree as &$subs) { sort($subs); }

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

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Modern Blog</title>
    <link href="https://fonts.googleapis.com/css2?family=VT323&family=Press+Start+2P&display=swap" rel="stylesheet">
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
        
        .post-content img { 
            max-width: 100%; 
            height: auto; 
            display: block; 
            margin: 1.5rem 0; 
            border: 2px solid var(--border);
            border-radius: 4px;
        }
        .post-content p { margin-bottom: 1.5rem; }
        .post-content blockquote { 
            margin: 1.5rem 0; 
            padding: 1rem; 
            background: rgba(0,0,0,0.3);
            border-left: 4px solid var(--secondary); 
            color: #ddd; 
            font-style: italic;
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
    </style>
</head>
<body>
    <header>
        <a href="/" class="logo">My Modern Blog</a>
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
        echo "<a href='{$post['url']}'><img src='".e($post['featured_image'])."' style='width: 100%; max-height: 300px; object-fit: cover; margin-bottom: 1rem;' /></a>";
    }
    echo "<h2><a href='{$post['url']}'>".e($post['title'])."</a></h2>";
    
    $catDisplay = "<a href='/".urlencode($post['section'])."'>".e(translate_cat($post['section']))."</a>";
    if ($post['subsection']) {
        $catDisplay .= " / <a href='/".urlencode($post['section'])."/".urlencode($post['subsection'])."'>".e(translate_cat($post['subsection']))."</a>";
    }
    
    echo "<div class='meta'>En {$catDisplay} el ".e($post['date'])."</div>";
    
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
        echo "<img src='".e($post['featured_image'])."' style='width: 100%; max-height: 400px; object-fit: cover; margin-bottom: 2rem;' />";
    }
    echo "<h1>".e($post['title'])."</h1>";
    echo "<div class='meta'>En {$bc} el ".e($post['date'])."</div>";
    echo "<div class='post-content'>".$post['content']."</div>";
    echo "</div>";
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
</html>
