<?php
// ==========================================================
//  LOCAL SERVER HUB v2.1 — Pro Edition + Universal Preview
// ==========================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

 $target_dir = "./";
 $excluded = ['index.php', '_backup_xampp', 'dashboard', 'webalizer', 'xampp', '.', '..'];

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function typeStyle($is_folder, $ext) {
    if ($is_folder) return ['📁', 'from-amber-500/15 to-orange-500/5 border-amber-500/20'];
    if (in_array($ext, ['mp4','mkv','webm','mov','avi','m4v']))
        return ['🎬', 'from-violet-500/15 to-fuchsia-500/5 border-violet-500/20'];
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','ico']))
        return ['🖼️', 'from-emerald-500/15 to-teal-500/5 border-emerald-500/20'];
    if (in_array($ext, ['mp3','wav','flac','ogg','m4a']))
        return ['🎵', 'from-rose-500/15 to-pink-500/5 border-rose-500/20'];
    if (in_array($ext, ['zip','rar','7z','tar','gz','iso']))
        return ['🗜️', 'from-orange-500/15 to-red-500/5 border-orange-500/20'];
    if (in_array($ext, ['pdf']))
        return ['📕', 'from-red-500/15 to-rose-500/5 border-red-500/20'];
    if (in_array($ext, ['doc','docx','xls','xlsx','ppt','pptx','txt','csv','md','json','xml','html','css','js','php','py','java','c','cpp','log','ini','sql']))
        return ['📝', 'from-blue-500/15 to-cyan-500/5 border-blue-500/20'];
    return ['📦', 'from-slate-500/15 to-zinc-500/5 border-slate-500/20'];
}

// ---------- 1. ZIP FOLDER DOWNLOAD ----------
if (isset($_GET['zip_folder'])) {
    $folder_to_zip = basename($_GET['zip_folder']);
    $folder_path = $target_dir . $folder_to_zip;
    if (is_dir($folder_path) && $folder_to_zip !== '.' && $folder_to_zip !== '..') {
        $zip_name = $folder_to_zip . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder_path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $fp = $file->getRealPath();
                    $zip->addFile($fp, substr($fp, strlen(realpath($target_dir)) + 1));
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_name));
            flush(); readfile($zip_name); unlink($zip_name);
            exit;
        }
    }
}

// ---------- 2. CHUNK UPLOADER ----------
if (isset($_POST['action']) && $_POST['action'] == 'upload_chunk') {
    header('Content-Type: application/json');
    $fileName = basename($_POST['fileName']);
    $chunkIndex = (int)$_POST['chunkIndex'];
    $totalChunks = max(1, (int)$_POST['totalChunks']);
    $relativePath = isset($_POST['relativePath']) ? $_POST['relativePath'] : $fileName;

    $relativePath = str_replace(['..', "\0", '\\'], ['', '', '/'], $relativePath);
    $relativePath = ltrim($relativePath, '/');

    $full_target_path = $target_dir . $relativePath;
    $temp_file = $full_target_path . '.part' . $chunkIndex;
    $dir_of_file = dirname($full_target_path);
    if (!is_dir($dir_of_file)) { @mkdir($dir_of_file, 0777, true); }

    if (move_uploaded_file($_FILES['file_chunk']['tmp_name'], $temp_file)) {
        if ($chunkIndex == $totalChunks - 1) {
            $out = fopen($full_target_path, "wb");
            if ($out) {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $cf = $full_target_path . '.part' . $i;
                    $in = @fopen($cf, "rb");
                    if ($in) { stream_copy_to_stream($in, $out); fclose($in); unlink($cf); }
                }
                fclose($out);
            }
            echo json_encode(["status" => "success"]);
        } else { echo json_encode(["status" => "progress"]); }
    } else { echo json_encode(["status" => "error"]); }
    exit;
}

// ---------- 3. HAPUS FILE ----------
if (isset($_GET['delete'])) {
    $file_to_delete = basename($_GET['delete']);
    $file_path = $target_dir . $file_to_delete;
    if (file_exists($file_path) && $file_to_delete !== 'index.php' && !is_dir($file_path)) {
        unlink($file_path); header("Location: index.php"); exit;
    }
}

// ---------- 4. RAW FILE STREAM UNTUK PREVIEW (Range Request agar video seek mulus) ----------
if (isset($_GET['preview_raw'])) {
    $file = basename($_GET['preview_raw']);
    $path = $target_dir . $file;
    if (!file_exists($path) || is_dir($path)) { http_response_code(404); exit; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'mp4'=>'video/mp4','webm'=>'video/webm','mkv'=>'video/x-matroska','mov'=>'video/quicktime',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif',
        'webp'=>'image/webp','svg'=>'image/svg+xml','bmp'=>'image/bmp','ico'=>'image/x-icon',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','m4a'=>'audio/mp4','flac'=>'audio/flac',
        'pdf'=>'application/pdf',
        'txt'=>'text/plain','md'=>'text/plain','json'=>'application/json','xml'=>'text/xml',
        'html'=>'text/html','css'=>'text/css','js'=>'application/javascript','php'=>'text/plain',
        'csv'=>'text/csv','log'=>'text/plain','ini'=>'text/plain','sql'=>'text/plain'
    ];
    $mime = $mimes[$ext] ?? 'application/octet-stream';

    $size = filesize($path);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0');

    // Range request → video/audio bisa seek & PDF load cepat
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int)$m[1];
        $end = ($m[2] !== '') ? (int)$m[2] : $size - 1;
        if ($end >= $size || $start > $end) $end = $size - 1;
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$size");
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($path, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0) {
            $chunk = fread($fp, min(8192, $remaining));
            if ($chunk === false) break;
            print $chunk; $remaining -= strlen($chunk);
        }
        fclose($fp);
    } else {
        header('Content-Length: ' . $size);
        readfile($path);
    }
    exit;
}

// ---------- STATISTIK ----------
 $raw_entries = @scandir($target_dir) ?: [];
 $items = []; $total_size = 0; $folder_count = 0; $file_count = 0;

foreach ($raw_entries as $f) {
    if (in_array($f, $excluded, true) || str_contains($f, '.part')) continue;
    $items[] = $f;
    if (is_dir($target_dir . $f)) $folder_count++;
    else { $file_count++; $total_size += @filesize($target_dir . $f); }
}
usort($items, function($a, $b) use ($target_dir) {
    $ad = is_dir($target_dir . $a); $bd = is_dir($target_dir . $b);
    if ($ad !== $bd) return $ad ? -1 : 1;
    return strcasecmp($a, $b);
});

 $disk_free  = @disk_free_space($target_dir) ?: 0;
 $disk_total = @disk_total_space($target_dir) ?: 1;
 $disk_used_pct = round((($disk_total - $disk_free) / $disk_total) * 100, 1);
 $server_ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Server Hub — Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: {
            sans: ['Inter','sans-serif'], mono: ['JetBrains Mono','monospace']
        }}}};
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #09090b; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #52525b; }

        @keyframes fadeUp { from { opacity:0; transform:translateY(14px);} to {opacity:1; transform:translateY(0);} }
        .item-card { animation: fadeUp .45s ease both; }

        @keyframes shimmer { 0%{transform:translateX(-100%);} 100%{transform:translateX(250%);} }
        .shimmer-bar { position:relative; overflow:hidden; }
        .shimmer-bar::after {
            content:''; position:absolute; inset:0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
            animation: shimmer 1.6s infinite;
        }

        .dropzone-active { border-color: rgb(99 102 241) !important; background: rgba(99,102,241,.08) !important; }

        /* Preview thumbnail zoom */
        .thumb-wrap img, .thumb-wrap video { transition: transform .4s cubic-bezier(.21,1.02,.73,1); }
        .group:hover .thumb-wrap img, .group:hover .thumb-wrap video { transform: scale(1.08); }

        /* Lightbox */
        @keyframes lbIn { from { opacity:0; transform:scale(.92);} to {opacity:1; transform:scale(1);} }
        .lb-anim { animation: lbIn .25s cubic-bezier(.21,1.02,.73,1) both; }

        /* Kode preview */
        #previewCode { max-height: 62vh; overflow: auto; }
        #previewCode::-webkit-scrollbar-thumb { background: #52525b; }
    </style>
</head>
<body class="text-zinc-300 antialiased min-h-screen">

    <!-- Ambient Glow -->
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute -top-48 left-1/2 -translate-x-1/2 w-[900px] h-[420px] bg-indigo-600/15 blur-[130px] rounded-full"></div>
        <div class="absolute top-1/3 -right-32 w-[450px] h-[350px] bg-violet-600/10 blur-[110px] rounded-full"></div>
        <div class="absolute bottom-0 -left-32 w-[400px] h-[300px] bg-blue-600/8 blur-[100px] rounded-full"></div>
    </div>

    <!-- ═══ NAVBAR ═══ -->
    <nav class="sticky top-0 z-40 border-b border-white/5 bg-zinc-950/75 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-5 sm:px-8 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/30 ring-1 ring-white/20">
                    <span class="text-xl">💠</span>
                </div>
                <div>
                    <h1 class="text-base font-extrabold bg-gradient-to-r from-white via-zinc-100 to-zinc-500 bg-clip-text text-transparent tracking-tight">Local Server Hub</h1>
                    <p class="text-[10px] text-zinc-500 font-mono uppercase tracking-[0.2em]">Network Storage Control Panel</p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="hidden sm:flex items-center gap-2 text-[11px] font-mono text-zinc-300 bg-white/5 border border-white/10 px-3 py-2 rounded-xl">🌐 <?php echo htmlspecialchars($server_ip); ?></span>
                <span id="serverClock" class="hidden md:flex text-[11px] font-mono text-zinc-300 bg-white/5 border border-white/10 px-3 py-2 rounded-xl">⏱ --:--:--</span>
                <span class="flex items-center gap-2 text-[11px] font-bold text-emerald-300 bg-emerald-500/10 border border-emerald-500/25 px-3 py-2 rounded-xl tracking-wide">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-70"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span> ONLINE
                </span>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-5 sm:px-8 py-8">

        <!-- ═══ STATISTIK ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="item-card relative bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-5 overflow-hidden group hover:border-indigo-500/30 transition-all duration-300">
                <div class="absolute -right-5 -top-5 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/25 transition-all"></div>
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-base">💾</div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Total Data</p>
                        <p class="text-lg font-bold text-white font-mono truncate"><?php echo formatSize($total_size); ?></p>
                    </div>
                </div>
            </div>
            <div class="item-card relative bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-5 overflow-hidden group hover:border-violet-500/30 transition-all duration-300" style="animation-delay:.05s">
                <div class="absolute -right-5 -top-5 w-24 h-24 bg-violet-500/10 rounded-full blur-2xl group-hover:bg-violet-500/25 transition-all"></div>
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-base">🗂️</div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Item Tersimpan</p>
                        <p class="text-lg font-bold text-white font-mono"><?php echo $folder_count + $file_count; ?> <span class="text-xs text-zinc-500 font-medium">/ <?php echo $folder_count; ?> folder</span></p>
                    </div>
                </div>
            </div>
            <div class="item-card relative bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-5 overflow-hidden group hover:border-emerald-500/30 transition-all duration-300" style="animation-delay:.1s">
                <div class="absolute -right-5 -top-5 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/25 transition-all"></div>
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-base">📄</div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Total File</p>
                        <p class="text-lg font-bold text-white font-mono"><?php echo $file_count; ?> <span class="text-xs text-zinc-500 font-medium">file</span></p>
                    </div>
                </div>
            </div>
            <div class="item-card relative bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-5 overflow-hidden group hover:border-sky-500/30 transition-all duration-300" style="animation-delay:.15s">
                <div class="absolute -right-5 -top-5 w-24 h-24 bg-sky-500/10 rounded-full blur-2xl group-hover:bg-sky-500/25 transition-all"></div>
                <div class="flex items-center gap-3.5 mb-3">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-base">💽</div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-zinc-500 font-bold">Disk Bebas</p>
                        <p class="text-lg font-bold text-white font-mono truncate"><?php echo formatSize($disk_free); ?></p>
                    </div>
                </div>
                <div class="w-full bg-white/5 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-sky-500 to-indigo-500 rounded-full" style="width:<?php echo min(100, $disk_used_pct); ?>%"></div>
                </div>
                <p class="text-[10px] text-zinc-500 mt-1.5 font-mono text-right"><?php echo $disk_used_pct; ?>% terpakai</p>
            </div>
        </div>

        <!-- ═══ PANEL TRANSFER ═══ -->
        <div class="item-card bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-6 sm:p-7 mb-10 shadow-2xl shadow-black/20" style="animation-delay:.2s">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500/20 to-violet-500/10 border border-indigo-500/20 flex items-center justify-center">📤</div>
                <div>
                    <h3 class="text-sm font-bold text-white">Transfer File &amp; Folder</h3>
                    <p class="text-zinc-500 text-xs mt-0.5">Unggah file tunggal atau seluruh folder — mendukung file ratusan GB.</p>
                </div>
            </div>
            <label id="dropZone" for="hugeFileInput"
                class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-zinc-700/70 bg-zinc-950/40 rounded-2xl px-6 py-9 cursor-pointer hover:border-indigo-500/60 hover:bg-indigo-500/5 transition-all duration-300 group text-center">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-xl group-hover:scale-110 transition-transform duration-300">📂</div>
                <p class="text-sm font-semibold text-zinc-200" id="dropZoneTitle">Tarik &amp; letakkan file / folder di sini</p>
                <p class="text-[11px] text-zinc-500">atau <span class="text-indigo-400 font-semibold">klik untuk memilih</span></p>
            </label>
            <input type="file" id="hugeFileInput" webkitdirectory directory multiple class="hidden">
            <div id="progressContainer" class="hidden mt-5">
                <div class="flex justify-between text-[11px] font-semibold mb-2">
                    <span id="uploadStatus" class="text-zinc-400 font-mono truncate pr-4">Menganalisis struktur...</span>
                    <span id="uploadPercentage" class="text-indigo-400 font-mono font-bold shrink-0">0%</span>
                </div>
                <div class="w-full bg-white/5 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="shimmer-bar bg-gradient-to-r from-indigo-500 to-violet-500 h-full rounded-full transition-all duration-300" style="width:0%"></div>
                </div>
            </div>
            <div class="mt-5 flex items-center gap-3 flex-wrap">
                <button type="button" id="uploadButton" onclick="startFolderUpload()"
                    class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold px-6 py-3 rounded-xl transition-all duration-200 shadow-lg shadow-indigo-600/25 hover:-translate-y-0.5">
                    ⚡ Mulai Transfer
                </button>
                <span class="text-[11px] text-zinc-600 font-mono">Chunk 10 MB • Auto-path • Multi-file</span>
            </div>
        </div>

        <!-- ═══ TOOLBAR ═══ -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                📁 Storage Repository
                <span class="text-[10px] font-mono bg-white/5 border border-white/10 text-zinc-400 px-2 py-1 rounded-lg"><?php echo count($items); ?> item</span>
            </h3>
            <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-zinc-500 text-xs">🔍</span>
                <input type="text" id="searchInput" onkeyup="filterItems()" placeholder="Cari file..."
                    class="bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-zinc-600 focus:outline-none focus:border-indigo-500/60 transition-all w-56 sm:w-64 font-mono">
            </div>
        </div>

        <!-- ═══ GRID ITEM ═══ -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php
            if (empty($items)) {
                echo '<div class="col-span-full text-center py-20 bg-zinc-900/40 rounded-2xl border border-dashed border-white/10">
                    <div class="text-5xl mb-4 opacity-60">📭</div>
                    <p class="text-zinc-400 text-sm font-semibold">Direktori server masih kosong</p></div>';
            }

            $card_delay = 0;
            foreach ($items as $file) {
                $is_folder = is_dir($target_dir . $file);
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $is_video = in_array($ext, ['mp4','mkv','webm','mov','avi','m4v']);
                $is_image = in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','ico']);
                $is_audio = in_array($ext, ['mp3','wav','ogg','m4a','flac']);
                $is_pdf   = ($ext === 'pdf');
                $is_text  = in_array($ext, ['txt','md','json','xml','html','css','js','php','csv','log','ini','sql']);
                $previewable = $is_video || $is_image || $is_audio || $is_pdf || $is_text;

                [$icon, $icon_grad] = typeStyle($is_folder, $ext);
                $file_size = $is_folder ? 'Folder' : formatSize(@filesize($target_dir . $file) ?: 0);
                $mod_date = @filemtime($target_dir . $file) ? date('d M Y • H:i', @filemtime($target_dir . $file)) : '—';
                $safe_url = rawurlencode($file);
                $name_js  = htmlspecialchars(addslashes($file), ENT_QUOTES);

                // === THUMBNAIL / IKON ===
                if ($is_image) {
                    $thumb = '<div class="thumb-wrap h-36 -mx-5 -mt-5 mb-4 overflow-hidden rounded-t-2xl bg-black/40 cursor-pointer" onclick="openPreview(\'' . $safe_url . '\', \'image\', \'' . $name_js . '\')">
                        <img src="' . $safe_url . '" alt="' . $name_js . '" loading="lazy" class="w-full h-full object-cover">
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center bg-black/40">
                            <span class="text-white text-xs font-bold bg-white/10 backdrop-blur px-4 py-2 rounded-xl border border-white/20">🔍 Klik untuk Preview</span>
                        </div>
                    </div>';
                } elseif ($is_video) {
                    $thumb = '<div class="thumb-wrap relative h-36 -mx-5 -mt-5 mb-4 overflow-hidden rounded-t-2xl bg-black/60 cursor-pointer" onclick="openPreview(\'' . $safe_url . '\', \'video\', \'' . $name_js . '\')">
                        <video src="' . $safe_url . '" preload="metadata" muted class="w-full h-full object-cover opacity-80"></video>
                        <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-t from-black/60 to-transparent">
                            <span class="w-12 h-12 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white text-lg border border-white/30 group-hover:scale-110 transition-transform">▶️</span>
                        </div>
                    </div>';
                } else {
                    $thumb = '<div class="w-12 h-12 rounded-2xl bg-gradient-to-br ' . $icon_grad . ' border flex items-center justify-center text-xl group-hover:scale-110 transition-transform duration-300">' . $icon . '</div>';
                }

                // === TOMBOL AKSI ===
                $actions = '';
                if ($previewable) {
                    $ptype = $is_video ? 'video' : ($is_image ? 'image' : ($is_audio ? 'audio' : ($is_pdf ? 'pdf' : 'text')));
                    $actions .= '<button onclick="openPreview(\'' . $safe_url . '\', \'' . $ptype . '\', \'' . $name_js . '\')"
                        class="w-full text-center bg-indigo-500/10 hover:bg-indigo-600 text-indigo-300 hover:text-white text-[11px] font-bold py-2.5 px-4 rounded-xl transition border border-indigo-500/20 hover:border-indigo-500">👁️ Preview</button>';
                }
                if ($is_folder) {
                    $actions .= '
                        <a href="' . $safe_url . '/" class="' . ($previewable ? '' : 'w-full ') . 'text-center bg-white/5 hover:bg-white/10 text-zinc-300 text-[11px] font-bold py-2.5 px-4 rounded-xl transition border border-white/10 hover:border-white/20">🔍 Browse</a>
                        <a href="?zip_folder=' . urlencode($file) . '" class="' . ($previewable ? '' : 'w-full ') . 'text-center bg-white/5 hover:bg-white/10 text-zinc-300 text-[11px] font-bold py-2.5 px-4 rounded-xl transition border border-white/10 hover:border-white/20">📦 Unduh .ZIP</a>';
                } else {
                    $actions .= '<a href="' . $safe_url . '" download class="' . ($previewable ? '' : 'w-full ') . 'text-center bg-white/5 hover:bg-white/10 text-zinc-300 hover:text-white text-[11px] font-bold py-2.5 px-4 rounded-xl transition border border-white/10 hover:border-white/25">📥 Unduh</a>';
                }

                echo '
                <div class="item-card group relative bg-zinc-900/60 backdrop-blur border border-white/5 rounded-2xl p-5 flex flex-col justify-between hover:border-indigo-500/40 hover:bg-zinc-900/80 hover:-translate-y-1 hover:shadow-2xl hover:shadow-indigo-950/50 transition-all duration-300 overflow-hidden" data-name="' . strtolower(htmlspecialchars($file)) . '" style="animation-delay:' . ($card_delay * 0.04) . 's">
                    <div class="absolute -right-8 -top-8 w-28 h-28 bg-indigo-500/0 group-hover:bg-indigo-500/10 rounded-full blur-2xl transition-all duration-500"></div>
                    <div>
                        <div class="flex items-start justify-between mb-1">
                            ' . ($is_image || $is_video ? '<div></div>' : $thumb) . '
                            ' . (!$is_folder ? '<a href="?delete=' . urlencode($file) . '" onclick="return confirm(\'Hapus file ini?\\n' . $name_js . '\')" title="Hapus" class="text-zinc-600 hover:text-rose-400 text-sm p-1.5 rounded-lg hover:bg-rose-500/10 transition-all z-10">🗑️</a>' : '<div></div>') . '
                        </div>
                        ' . ($is_image || $is_video ? $thumb : '') . '
                        <h4 class="font-bold text-zinc-100 text-sm truncate group-hover:text-indigo-300 transition-colors" title="' . htmlspecialchars($file, ENT_QUOTES) . '">' . htmlspecialchars($file) . '</h4>
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                            <span class="text-[10px] font-mono text-zinc-500 bg-white/5 border border-white/5 px-2 py-0.5 rounded-md">' . $file_size . '</span>
                            <span class="text-[10px] font-mono text-zinc-600">' . $mod_date . '</span>
                        </div>
                    </div>
                    <div class="mt-5 pt-4 border-t border-white/5 flex gap-2 flex-wrap">' . $actions . '</div>
                </div>';
                $card_delay++;
            }
            ?>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div class="mt-14 pt-6 border-t border-white/5 flex justify-between items-center flex-wrap gap-3 text-[11px] text-zinc-600">
            <div class="font-mono">Local Server Hub v2.1 • Universal Preview — <span class="text-emerald-500/80">● Active</span></div>
            <a href="/phpmyadmin" target="_blank" class="bg-white/5 hover:bg-indigo-500/10 text-zinc-400 hover:text-indigo-300 px-4 py-2 rounded-xl border border-white/10 hover:border-indigo-500/30 transition-all font-semibold">🗄️ phpMyAdmin</a>
        </div>
    </main>

    <!-- ═══ UNIVERSAL PREVIEW MODAL ═══ -->
    <div id="previewModal" class="fixed inset-0 bg-black/92 backdrop-blur-lg hidden items-center justify-center z-50 p-4">
        <div id="previewBox" class="lb-anim bg-zinc-900 border border-white/10 rounded-3xl w-full max-w-5xl overflow-hidden shadow-2xl shadow-black/70 flex flex-col max-h-[92vh]">
            <!-- Header -->
            <div class="px-5 py-4 flex justify-between items-center border-b border-white/5 bg-gradient-to-r from-indigo-500/10 to-transparent shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <span id="previewIcon" class="text-base shrink-0">📄</span>
                    <div class="min-w-0">
                        <h4 id="previewTitle" class="text-xs font-bold text-zinc-200 truncate font-mono">preview</h4>
                        <p id="previewMeta" class="text-[10px] text-zinc-500 font-mono truncate">—</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="previewDownload" href="#" download class="text-zinc-400 hover:text-indigo-300 text-[11px] font-bold px-3 py-2 rounded-xl bg-white/5 border border-white/10 hover:border-indigo-500/40 transition-all">📥 Unduh</a>
                    <button onclick="closePreview()" class="text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 text-xs font-bold px-3 py-2 rounded-xl border border-white/5 hover:border-rose-500/30 transition-all">✕</button>
                </div>
            </div>
            <!-- Konten -->
            <div id="previewContent" class="flex-1 overflow-auto bg-black/50 flex items-center justify-center min-h-[300px]"></div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-[60] flex flex-col gap-2 items-end"></div>

    <script>
        /* ═══ JAM ═══ */
        function tickClock() { document.getElementById('serverClock').textContent = '⏱ ' + new Date().toLocaleTimeString('id-ID',{hour12:false}); }
        tickClock(); setInterval(tickClock, 1000);

        /* ═══ TOAST ═══ */
        function showToast(msg, type='success') {
            const c = { success:'border-emerald-500/30 bg-emerald-500/10 text-emerald-300', error:'border-rose-500/30 bg-rose-500/10 text-rose-300' };
            const el = document.createElement('div');
            el.className = `toast-anim backdrop-blur-xl border ${c[type]} text-xs font-semibold px-5 py-3.5 rounded-2xl shadow-2xl max-w-xs`;
            el.textContent = (type==='success'?'✅ ':'⚠️ ') + msg;
            document.getElementById('toastContainer').appendChild(el);
            setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),300); }, 3200);
        }

        /* ═══ DRAG & DROP ═══ */
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('hugeFileInput');
        ['dragenter','dragover'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault();dropZone.classList.add('dropzone-active');}));
        ['dragleave','drop'].forEach(ev=>dropZone.addEventListener(ev,e=>{e.preventDefault();dropZone.classList.remove('dropzone-active');}));
        dropZone.addEventListener('drop', e=>{ if(e.dataTransfer.files.length){ fileInput.files=e.dataTransfer.files; updateSelectionInfo(); } });
        fileInput.addEventListener('change', updateSelectionInfo);
        function updateSelectionInfo(){
            const fs = fileInput.files; if(!fs.length) return;
            let t=0; for(const f of fs) t+=f.size;
            document.getElementById('dropZoneTitle').textContent = `${fs.length} item terpilih — ${(t/1048576).toFixed(1)} MB siap ditransfer`;
        }

        /* ═══ PENCARIAN ═══ */
        function filterItems(){
            const q = document.getElementById('searchInput').value.toLowerCase().trim();
            document.querySelectorAll('[data-name]').forEach(c=>{ c.style.display = c.dataset.name.includes(q)?'':'none'; });
        }

        /* ═══════════ UNIVERSAL PREVIEW ═══════════ */
        const pm = document.getElementById('previewModal');
        const pBox = document.getElementById('previewBox');
        const pContent = document.getElementById('previewContent');
        let currentMedia = null;

        function openPreview(url, type, filename) {
            currentMedia = null;
            document.getElementById('previewTitle').textContent = decodeURIComponent(filename);
            document.getElementById('previewDownload').href = url;

            // Escape URL agar aman di src
            const safeUrl = url.replace(/'/g, '%27');

            if (type === 'image') {
                document.getElementById('previewIcon').textContent = '🖼️';
                pContent.innerHTML = `<img src="${safeUrl}" class="lb-anim max-w-full max-h-[75vh] object-contain cursor-zoom-in" onclick="this.classList.toggle('cursor-zoom-out'); this.style.maxWidth = this.style.maxWidth==='none'?'':'none'; this.style.width=this.style.width==='100vw'?'':'100vw';" style="transition:all .2s">`;
                pContent.className = 'flex-1 overflow-auto bg-black/50 flex items-center justify-center min-h-[300px] p-4';
            }
            else if (type === 'video') {
                document.getElementById('previewIcon').textContent = '🎬';
                pContent.innerHTML = `<video id="pvMedia" controls autoplay class="lb-anim w-full max-h-[75vh] bg-black"></video>`;
                pContent.className = 'flex-1 overflow-hidden bg-black flex items-center justify-center min-h-[300px]';
                currentMedia = 'video';
                const v = document.getElementById('pvMedia');
                v.src = safeUrl;
                v.play().catch(()=>{});
            }
            else if (type === 'audio') {
                document.getElementById('previewIcon').textContent = '🎵';
                pContent.innerHTML = `
                    <div class="lb-anim w-full max-w-md flex flex-col items-center gap-6 py-10">
                        <div class="w-28 h-28 rounded-3xl bg-gradient-to-br from-rose-500/30 to-pink-600/20 border border-rose-500/30 flex items-center justify-center text-5xl shadow-2xl shadow-rose-900/40">🎧</div>
                        <audio id="pvMedia" controls autoplay class="w-full"></audio>
                    </div>`;
                pContent.className = 'flex-1 overflow-auto bg-zinc-950/60 flex items-center justify-center min-h-[300px]';
                currentMedia = 'audio';
                document.getElementById('pvMedia').src = safeUrl;
            }
            else if (type === 'pdf') {
                document.getElementById('previewIcon').textContent = '📕';
                pContent.innerHTML = `<iframe src="${safeUrl}" class="lb-anim w-full h-full min-h-[70vh] bg-white" style="border:0"></iframe>`;
                pContent.className = 'flex-1 overflow-hidden bg-white/5 flex items-center justify-center min-h-[300px]';
            }
            else if (type === 'text') {
                document.getElementById('previewIcon').textContent = '📝';
                pContent.innerHTML = `<div class="lb-anim w-full p-0"><div class="flex items-center gap-2 px-5 py-2.5 border-b border-white/5 bg-zinc-900/80 text-[10px] font-mono text-zinc-500"><span class="w-2.5 h-2.5 rounded-full bg-rose-500/60"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-500/60"></span><span class="w-2.5 h-2.5 rounded-full bg-emerald-500/60"></span><span class="ml-2">source viewer</span></div><pre id="previewCode" class="text-[11px] leading-relaxed font-mono text-zinc-300 p-5 whitespace-pre-wrap break-words">Memuat...</pre></div>`;
                pContent.className = 'flex-1 overflow-auto bg-zinc-950/80 min-h-[300px]';
                fetch(url)
                    .then(r => r.text())
                    .then(txt => {
                        // Batasi tampilan 500KB agar browser ringan
                        if (txt.length > 500000) txt = txt.substring(0, 500000) + '\n\n... [file terlalu besar, tampil 500KB pertama. Unduh untuk melihat seluruhnya]';
                        document.getElementById('previewCode').textContent = txt;
                    })
                    .catch(() => { document.getElementById('previewCode').textContent = '⚠️ Gagal memuat isi file.'; });
            }

            pm.classList.remove('hidden');
            pm.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closePreview() {
            const v = document.getElementById('pvMedia');
            if (v) { v.pause(); v.removeAttribute('src'); v.load(); }
            pContent.innerHTML = '';
            pm.classList.add('hidden');
            pm.classList.remove('flex');
            document.body.style.overflow = '';
        }

        pm.addEventListener('click', e => { if (e.target === pm) closePreview(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !pm.classList.contains('hidden')) closePreview(); });

        /* ═══════════ CHUNK UPLOAD ═══════════ */
        async function startFolderUpload() {
            const filesList = Array.from(fileInput.files);
            if (!filesList.length) { showToast('Pilih file/folder dulu.', 'error'); return; }
            const totalFiles = filesList.length;
            const totalBytes = filesList.reduce((a,f)=>a+f.size,0);
            const bar = document.getElementById('progressBar');
            const status = document.getElementById('uploadStatus');
            const pctEl = document.getElementById('uploadPercentage');
            document.getElementById('progressContainer').classList.remove('hidden');
            document.getElementById('uploadButton').disabled = true;
            let uploaded = 0;

            for (let f = 0; f < totalFiles; f++) {
                const cf = filesList[f];
                const rp = cf.webkitRelativePath || cf.name;
                const CS = 10*1024*1024, TC = Math.max(1, Math.ceil(cf.size/CS));
                for (let ci = 0; ci < TC; ci++) {
                    const fd = new FormData();
                    fd.append('action','upload_chunk');
                    fd.append('file_chunk', cf.slice(ci*CS, Math.min((ci+1)*CS, cf.size)));
                    fd.append('fileName', cf.name);
                    fd.append('relativePath', rp);
                    fd.append('chunkIndex', ci);
                    fd.append('totalChunks', TC);
                    status.textContent = `[${f+1}/${totalFiles}] ${rp}`;
                    try {
                        const res = await fetch('index.php', { method:'POST', body:fd });
                        if ((await res.json()).status === 'error') throw new Error();
                    } catch(e) { showToast('Koneksi putus: '+cf.name, 'error'); document.getElementById('uploadButton').disabled=false; return; }
                    uploaded += Math.min((ci+1)*CS, cf.size) - ci*CS;
                    const p = totalBytes ? Math.round(uploaded/totalBytes*100) : 100;
                    bar.style.width = p+'%'; pctEl.textContent = p+'%';
                }
            }
            status.textContent = 'Sinkronisasi selesai...';
            showToast('Semua file berhasil ditransfer!');
            setTimeout(()=>window.location.reload(), 900);
        }
    </script>
</body>
</html>