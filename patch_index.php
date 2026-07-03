<?php
$content = file_get_contents("api/index.php");

// 1. ADD translate_cat function
$transFunc = <<<PHP
function translate_cat(\$slug) {
    \$map = [
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
    return \$map[strtolower(\$slug)] ?? ucfirst(\$slug);
}
PHP;
$content = str_replace("function e(\$str) { return htmlspecialchars(\$str ?? '', ENT_QUOTES, 'UTF-8'); }", "function e(\$str) { return htmlspecialchars(\$str ?? '', ENT_QUOTES, 'UTF-8'); }\n\n" . $transFunc, $content);

// 2. REPLACEMENTS FOR TRANSLATION
$content = str_replace("ucfirst(\$sec)", "translate_cat(\$sec)", $content);
$content = str_replace("ucfirst(\$sub)", "translate_cat(\$sub)", $content);
$content = str_replace("ucfirst(\$post['section'])", "translate_cat(\$post['section'])", $content);
$content = str_replace("ucfirst(\$post['subsection'])", "translate_cat(\$post['subsection'])", $content);
$content = str_replace("ucfirst(\$matchedSection)", "translate_cat(\$matchedSection)", $content);
$content = str_replace("ucfirst(\$matchedSubsection)", "translate_cat(\$matchedSubsection)", $content);

$replaces = [
    ">Home<" => ">Inicio<",
    ">Latest Posts<" => ">Últimas publicaciones<",
    "No posts yet." => "Aún no hay publicaciones.",
    "Read more &rarr;" => "Leer más &rarr;",
    "In {\$catDisplay} on " => "En {\$catDisplay} el ",
    "In {\$bc} on " => "En {\$bc} el ",
    "Post not found." => "Publicación no encontrada.",
    "No posts in this section." => "No hay publicaciones en esta sección.",
    "No posts in this subsection." => "No hay publicaciones en esta subsección.",
    "The page you requested could not be found." => "La página que solicitaste no pudo ser encontrada.",
    "> in " => "> en "
];
foreach ($replaces as $from => $to) {
    $content = str_replace($from, $to, $content);
}

// 3. CSS AND LAYOUT REPLACEMENT
$cssStart = strpos($content, "<style>");
$cssEnd = strpos($content, "</style>") + 8;

$newCSS = <<<CSS
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
CSS;
$content = substr_replace($content, $newCSS, $cssStart, $cssEnd - $cssStart);

// Replace "style='font-weight: bold; font-family: sans-serif; font-size: 0.9rem;'" with retro
$content = str_replace("style='font-weight: bold; font-family: sans-serif; font-size: 0.9rem;'", "style='font-family: \"VT323\", monospace; font-size: 1.4rem;'", $content);

// 4. HTML LAYOUT REPLACEMENTS
$content = str_replace("<main>", "<div class=\"layout\">\n    <main>", $content);

$aside = <<<HTML
</main>
    <aside class="sidebar">
        <h3>Categorías</h3>
        <ul>
            <?php foreach (\$navTree as \$sec => \$subs): ?>
                <li><a href="/<?= urlencode(\$sec) ?>">&gt; <?= e(translate_cat(\$sec)) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <h3>Últimas</h3>
        <ul>
            <?php \$count=0; foreach (\$allPosts as \$p): if(\$count++>=5) break; ?>
                <li><a href="<?= \$p['url'] ?>">&gt; <?= e(\$p['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>
    </div>
HTML;
$content = str_replace("</main>", $aside, $content);

// Wrap the loops in posts-grid
$homeLoop = <<<PHP
    foreach (\$allPosts as \$post) {
        render_post_card(\$post);
    }
PHP;
$homeLoopRep = <<<PHP
    echo "<div class='posts-grid'>";
    foreach (\$allPosts as \$post) {
        render_post_card(\$post);
    }
    echo "</div>";
PHP;
$content = str_replace($homeLoop, $homeLoopRep, $content);

$sectionLoop = <<<PHP
    foreach (\$allPosts as \$post) {
        if (\$post['section'] === \$matchedSection) {
            \$hasPosts = true;
            render_post_card(\$post);
        }
    }
PHP;
$sectionLoopRep = <<<PHP
    echo "<div class='posts-grid'>";
    foreach (\$allPosts as \$post) {
        if (\$post['section'] === \$matchedSection) {
            \$hasPosts = true;
            render_post_card(\$post);
        }
    }
    echo "</div>";
PHP;
$content = str_replace($sectionLoop, $sectionLoopRep, $content);

$subLoop = <<<PHP
    foreach (\$allPosts as \$post) {
        if (\$post['section'] === \$matchedSection && \$post['subsection'] === \$matchedSubsection) {
            \$hasPosts = true;
            render_post_card(\$post);
        }
    }
PHP;
$subLoopRep = <<<PHP
    echo "<div class='posts-grid'>";
    foreach (\$allPosts as \$post) {
        if (\$post['section'] === \$matchedSection && \$post['subsection'] === \$matchedSubsection) {
            \$hasPosts = true;
            render_post_card(\$post);
        }
    }
    echo "</div>";
PHP;
$content = str_replace($subLoop, $subLoopRep, $content);

file_put_contents("api/index.php", $content);
echo "Perfectly patched.";
?>
