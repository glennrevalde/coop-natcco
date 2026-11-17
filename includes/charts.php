<?php
/**
 * Server-rendered SVG charts (print friendly, no inline script or style).
 * Every chart is paired with a data table for screen readers.
 */
declare(strict_types=1);

/** Horizontal bar chart. $data: label => value. */
function chart_hbar(array $data, string $title, array $opt = []): string
{
    if (!$data) {
        return '<p class="muted">No data for this chart yet.</p>';
    }
    $fmt = $opt['format'] ?? fn($v) => fmt_num($v);
    $labelW = $opt['label_width'] ?? 170;
    $w = 720;
    $rowH = 30;
    $h = count($data) * $rowH + 10;
    $max = max(1, max(array_map('floatval', $data)));
    $barMax = $w - $labelW - 90;
    $cls = $opt['class'] ?? 'bar';
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '"><title>' . e($title) . '</title>';
    $i = 0;
    foreach ($data as $label => $val) {
        $y = $i * $rowH + 6;
        $bw = max(2, (int) round(((float) $val / $max) * $barMax));
        $lab = mb_strlen((string) $label) > 26 ? mb_substr((string) $label, 0, 25) . '…' : (string) $label;
        $svg .= '<text class="lbl" x="' . ($labelW - 10) . '" y="' . ($y + 15) . '" text-anchor="end">' . e($lab) . '</text>';
        $svg .= '<rect class="bar-track" x="' . $labelW . '" y="' . $y . '" width="' . $barMax . '" height="20" rx="4"/>';
        $svg .= '<rect class="' . e($cls) . '" x="' . $labelW . '" y="' . $y . '" width="' . $bw . '" height="20" rx="4"><title>' . e($label . ': ' . $fmt($val)) . '</title></rect>';
        $svg .= '<text class="val" x="' . ($labelW + $bw + 8) . '" y="' . ($y + 15) . '">' . e($fmt($val)) . '</text>';
        $i++;
    }
    return $svg . '</svg>' . chart_table($data, $title, $fmt);
}

/** Vertical column chart for time series. $data: label => value. */
function chart_columns(array $data, string $title, array $opt = []): string
{
    if (!$data) {
        return '<p class="muted">No data for this chart yet.</p>';
    }
    $fmt = $opt['format'] ?? fn($v) => fmt_num($v);
    $w = 720;
    $h = 240;
    $padL = 70;
    $padB = 34;
    $n = count($data);
    $max = max(1, max(array_map('floatval', $data)));
    $nice = chart_nice_max($max);
    $plotW = $w - $padL - 10;
    $plotH = $h - $padB - 14;
    $slot = $plotW / $n;
    $bw = max(6, min(46, $slot * .62));
    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '"><title>' . e($title) . '</title>';
    for ($g = 0; $g <= 4; $g++) {
        $gy = 14 + $plotH - ($plotH * $g / 4);
        $svg .= '<line class="grid-line" x1="' . $padL . '" x2="' . ($w - 10) . '" y1="' . $gy . '" y2="' . $gy . '"/>';
        $svg .= '<text class="lbl" x="' . ($padL - 6) . '" y="' . ($gy + 4) . '" text-anchor="end">' . e(fmt_num($nice * $g / 4)) . '</text>';
    }
    $i = 0;
    $every = (int) max(1, ceil($n / 12));
    foreach ($data as $label => $val) {
        $bh = (float) $val / $nice * $plotH;
        $x = $padL + $slot * $i + ($slot - $bw) / 2;
        $y = 14 + $plotH - $bh;
        $svg .= '<rect class="' . e($opt['class'] ?? 'bar') . '" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max(0, $bh), 1) . '" rx="3"><title>' . e($label . ': ' . $fmt($val)) . '</title></rect>';
        if ($i % $every === 0) {
            $svg .= '<text class="lbl" x="' . round($x + $bw / 2, 1) . '" y="' . ($h - 12) . '" text-anchor="middle">' . e((string) $label) . '</text>';
        }
        $i++;
    }
    $svg .= '<line class="axis" x1="' . $padL . '" x2="' . ($w - 10) . '" y1="' . (14 + $plotH) . '" y2="' . (14 + $plotH) . '"/>';
    return $svg . '</svg>' . chart_table($data, $title, $fmt);
}

/** Two-series horizontal comparison (e.g. two election years). */
function chart_compare(array $labels, array $a, array $b, string $nameA, string $nameB, string $title): string
{
    if (!$labels) {
        return '<p class="muted">No data for this comparison yet.</p>';
    }
    $w = 720;
    $labelW = 170;
    $rowH = 44;
    $h = count($labels) * $rowH + 10;
    $max = max(1, max(array_merge(array_map('floatval', $a), array_map('floatval', $b), [1])));
    $barMax = $w - $labelW - 100;
    $svg = '<div class="chart-legend"><span><i class="lg-blue"></i>' . e($nameA) . '</span><span><i class="lg-yellow"></i>' . e($nameB) . '</span></div>';
    $svg .= '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '"><title>' . e($title) . '</title>';
    $i = 0;
    foreach ($labels as $k => $label) {
        $y = $i * $rowH + 4;
        $va = (float) ($a[$k] ?? 0);
        $vb = (float) ($b[$k] ?? 0);
        $wa = max($va > 0 ? 2 : 0, (int) round($va / $max * $barMax));
        $wb = max($vb > 0 ? 2 : 0, (int) round($vb / $max * $barMax));
        $lab = mb_strlen($label) > 26 ? mb_substr($label, 0, 25) . '…' : $label;
        $svg .= '<text class="lbl" x="' . ($labelW - 10) . '" y="' . ($y + 22) . '" text-anchor="end">' . e($lab) . '</text>';
        $svg .= '<rect class="bar" x="' . $labelW . '" y="' . $y . '" width="' . $wa . '" height="16" rx="3"><title>' . e("{$label}, {$nameA}: " . fmt_num($va)) . '</title></rect>';
        $svg .= '<text class="val" x="' . ($labelW + $wa + 6) . '" y="' . ($y + 13) . '">' . e(isset($a[$k]) ? fmt_num($va) : 'n/a') . '</text>';
        $svg .= '<rect class="bar-alt" x="' . $labelW . '" y="' . ($y + 19) . '" width="' . $wb . '" height="16" rx="3"><title>' . e("{$label}, {$nameB}: " . fmt_num($vb)) . '</title></rect>';
        $svg .= '<text class="val" x="' . ($labelW + $wb + 6) . '" y="' . ($y + 32) . '">' . e(isset($b[$k]) ? fmt_num($vb) : 'n/a') . '</text>';
        $i++;
    }
    return $svg . '</svg>';
}

function chart_nice_max(float $max): float
{
    $exp = pow(10, floor(log10(max(1, $max))));
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($m * $exp >= $max) {
            return $m * $exp;
        }
    }
    return 10 * $exp;
}

function chart_table(array $data, string $title, callable $fmt): string
{
    $h = '<details class="chart-data"><summary class="small muted">Show data table</summary><table class="table"><caption class="sr-only">' . e($title) . '</caption><thead><tr><th scope="col">Item</th><th scope="col" class="num">Value</th></tr></thead><tbody>';
    foreach ($data as $k => $v) {
        $h .= '<tr><td>' . e((string) $k) . '</td><td class="num">' . e($fmt($v)) . '</td></tr>';
    }
    return $h . '</tbody></table></details>';
}
