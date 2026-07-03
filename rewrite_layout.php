<?php
$content = file_get_contents("api/index.php");

// 1. Update max-width in CSS
$content = str_replace("max-width: 800px;", "max-width: 1200px;", $content);

// 2. Add new CSS rules
$newCss = <<<CSS
        
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
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
        }
        .sidebar li { margin-bottom: 0.8rem; }
        .sidebar a {
            font-family: 'VT323', monospace;
            font-size: 1.3rem;
            color: var(--text);
            border: none;
        }
        .sidebar a:hover {
            color: var(--secondary);
            text-shadow: 0 0 5px var(--secondary);
        }
CSS;

$content = preg_replace('/(\.card\s*\{[^}]+\})/', '', $content); // remove old .card
$content = str_replace("</style>", $newCss . "\n    </style>", $content);

// 3. Update main HTML structure
// Wrap <main> content in <div class="layout">
$content = str_replace("<main>", "<div class='layout'>\n    <main>", $content);
$content = str_replace("</main>", "</main>\n    <aside class='sidebar'>\n        <h3>Categorías</h3>\n        <ul>\n            <?php foreach (\$navTree as \$sec => \$subs): ?>\n                <li><a href='/<?= urlencode(\$sec) ?>'>&gt; <?= e(translate_cat(\$sec)) ?></a></li>\n            <?php endforeach; ?>\n        </ul>\n        <h3>Últimos Posts</h3>\n        <ul>\n            <?php \$count=0; foreach (\$allPosts as \$p): if(\$count++>=5) break; ?>\n                <li><a href='<?= \$p[\"url\"] ?>'>&gt; <?= e(\$p[\"title\"]) ?></a></li>\n            <?php endforeach; ?>\n        </ul>\n    </aside>\n    </div>", $content);

// 4. Update the loops to use posts-grid
$content = preg_replace("/(foreach \(\\$allPosts as \\$post\) \{\s*render_post_card\(\\$post\);\s*\})/", "echo \"<div class='posts-grid'>\";\n    $1\n    echo \"</div>\";", $content);

file_put_contents("api/index.php", $content);
echo "Layout updated.\n";
?>
