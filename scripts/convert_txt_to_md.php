<?php
// Convert legacy .txt pages in data/pages to .md
// Also externalize FrontPage and SyntaxGuide to data/frontpage.md and data/syntaxguide.md if missing.
// Usage:
//   php scripts/convert_txt_to_md.php [--dry-run]

function join_path() { $args = func_get_args(); return preg_replace('#/+#','/', join('/', $args)); }
function read_all($p){ if(!file_exists($p))return null; $f=@fopen($p,'rb'); if(!$f)return null; $d=''; while(!feof($f)){$d.=fread($f,8192);} fclose($f); return $d; }
function write_all($p,$c){ $dir=dirname($p); if(!is_dir($dir)) @mkdir($dir,0775,true); $tmp=$p.'.tmp.'.mt_rand(); $f=@fopen($tmp,'wb'); if(!$f)return false; fwrite($f,$c); fclose($f); if(@rename($tmp,$p)) return true; $f=@fopen($p,'wb'); if(!$f)return false; fwrite($f,$c); fclose($f); @unlink($tmp); return true; }

$dry = false;
for ($i=1;$i<count($argv);$i++){ if ($argv[$i] === '--dry-run') $dry = true; }

$root = dirname(__FILE__) . '/..';
$pagesDir = join_path($root, 'data/pages');
$histDir = join_path($root, 'data/history');
$canonFront = join_path($root, 'data/frontpage.md');
$canonSyntax = join_path($root, 'data/syntaxguide.md');

echo "[info] Scanning: $pagesDir\n";
if (!is_dir($pagesDir)) { echo "[warn] pages dir not found.\n"; exit(0); }

$dh = @opendir($pagesDir);
if (!$dh) { echo "[error] cannot open pages dir.\n"; exit(1); }
$count = 0; $migrated = 0; $removed = 0;
while (($f = readdir($dh)) !== false) {
  if ($f === '.' || $f === '..') continue;
  if (substr($f,-4) !== '.txt') continue;
  $count++;
  $src = join_path($pagesDir, $f);
  $md = substr($f,0,strlen($f)-4).'.md';
  $dst = join_path($pagesDir,$md);
  echo "[conv] $f -> $md" . ($dry?" (dry)":"") . "\n";
  if (!$dry) {
    if (@rename($src,$dst)) { $migrated++; }
    else {
      $data = read_all($src);
      if ($data !== null && write_all($dst,$data)) { @unlink($src); $migrated++; }
      else { echo "[warn] failed to move $f\n"; }
    }
  }
}
closedir($dh);

// Externalize FrontPage
$frontPageFile = join_path($pagesDir, rawurlencode('FrontPage') . '.md');
if (!file_exists($canonFront)) {
  if (file_exists($frontPageFile)) {
    echo "[front] moving pages/FrontPage.md -> data/frontpage.md" . ($dry?" (dry)":"") . "\n";
    if (!$dry) {
      $data = read_all($frontPageFile);
      if ($data !== null && write_all($canonFront,$data)) { /* keep original page or remove? */ }
    }
  } else {
    echo "[front] seeding data/frontpage.md" . ($dry?" (dry)":"") . "\n";
    $seed = "# FrontPage\n\nWelcome to MiniWiki.\n\n- See [[SyntaxGuide]] ([View raw](/data/syntaxguide.md))\n";
    if (!$dry) write_all($canonFront,$seed);
  }
}

// Externalize SyntaxGuide if missing
$pageSyntaxFileTxt = join_path($pagesDir, rawurlencode('SyntaxGuide') . '.txt');
$pageSyntaxFileMd = join_path($pagesDir, rawurlencode('SyntaxGuide') . '.md');
if (!file_exists($canonSyntax)) {
  if (file_exists($pageSyntaxFileMd)) {
    echo "[syntax] moving pages/SyntaxGuide.md -> data/syntaxguide.md" . ($dry?" (dry)":"") . "\n";
    if (!$dry) { $data = read_all($pageSyntaxFileMd); if ($data!==null) write_all($canonSyntax,$data); }
  } else if (file_exists($pageSyntaxFileTxt)) {
    echo "[syntax] moving pages/SyntaxGuide.txt -> data/syntaxguide.md" . ($dry?" (dry)":"") . "\n";
    if (!$dry) { $data = read_all($pageSyntaxFileTxt); if ($data!==null) write_all($canonSyntax,$data); }
  } else {
    echo "[syntax] seeding data/syntaxguide.md" . ($dry?" (dry)":"") . "\n";
    $seed = "# Wiki Syntax Guide\n\nUse [[Home]] and [OpenAI](https://openai.com).\n";
    if (!$dry) write_all($canonSyntax,$seed);
  }
}

echo "[done] scanned=$count migrated=$migrated\n";
exit(0);

