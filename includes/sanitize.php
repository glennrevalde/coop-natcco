<?php
/**
 * Server-side HTML sanitizer for rich text (allowlist based).
 * Scripts, styles, event handlers, iframes and unsafe URLs are removed.
 */
declare(strict_types=1);

const RICH_ALLOWED = [
    'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
    'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [],
    'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
    'figure' => [], 'figcaption' => [], 'sub' => [], 'sup' => [], 'code' => [], 'pre' => [],
];
const RICH_DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'frame', 'frameset', 'applet', 'head', 'title'];

function sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (mb_strlen($html) > 500000) {
        $html = mb_substr($html, 0, 500000);
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) {
        return e(strip_tags($html));
    }
    sanitize_node($root, $doc);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_node(DOMNode $node, DOMDocument $doc): void
{
    $children = [];
    foreach ($node->childNodes as $c) {
        $children[] = $c;
    }
    foreach ($children as $child) {
        if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction || $child instanceof DOMCdataSection) {
            $node->removeChild($child);
            continue;
        }
        if (!($child instanceof DOMElement)) {
            continue;
        }
        $tag = strtolower($child->tagName);
        if (in_array($tag, RICH_DROP_WITH_CONTENT, true)) {
            $node->removeChild($child);
            continue;
        }
        sanitize_node($child, $doc);
        if (!array_key_exists($tag, RICH_ALLOWED)) {
            // unwrap: keep the text/children, drop the element
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        $allowedAttrs = RICH_ALLOWED[$tag];
        $attrs = [];
        foreach ($child->attributes as $a) {
            $attrs[] = $a->name;
        }
        foreach ($attrs as $name) {
            $lname = strtolower($name);
            if (!in_array($lname, $allowedAttrs, true)) {
                $child->removeAttribute($name);
                continue;
            }
            $val = trim($child->getAttribute($name));
            if ($lname === 'href' && !safe_href($val)) {
                $child->removeAttribute($name);
            } elseif ($lname === 'src' && !safe_img_src($val)) {
                $child->removeAttribute($name);
            } elseif (in_array($lname, ['width', 'height', 'colspan', 'rowspan'], true) && !ctype_digit($val)) {
                $child->removeAttribute($name);
            } elseif ($lname === 'scope' && !in_array($val, ['row', 'col'], true)) {
                $child->removeAttribute($name);
            }
        }
        if ($tag === 'img' && !$child->hasAttribute('src')) {
            $node->removeChild($child);
            continue;
        }
        if ($tag === 'a' && $child->hasAttribute('href') && preg_match('#^https?://#i', $child->getAttribute('href'))) {
            $child->setAttribute('rel', 'noopener noreferrer');
        }
    }
}

function safe_href(string $v): bool
{
    $v = preg_replace('/[\x00-\x20]/', '', $v);
    if ($v === '' || $v[0] === '#' || $v[0] === '/') {
        return $v === '' ? false : strpos($v, '//') !== 0;
    }
    if (preg_match('#^(https?:|mailto:|tel:)#i', $v)) {
        return true;
    }
    // relative links without a scheme
    return !preg_match('#^[a-z][a-z0-9+.\-]*:#i', $v);
}

function safe_img_src(string $v): bool
{
    $base = base_path() . '/file.php?';
    return strpos($v, $base) === 0 || strpos($v, 'file.php?') === 0 || preg_match('#^https://#i', $v) === 1;
}

/** Plain multi-line text to safe HTML paragraphs. */
function nl2p(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $paras = preg_split('/\n\s*\n/', $text);
    return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', $paras));
}

/** Only allow known video hosts; returns an embeddable URL or null. */
function video_embed_url(?string $url): ?string
{
    $url = trim((string) $url);
    if (preg_match('#^https://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})#', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('#^https://(?:www\.)?vimeo\.com/(\d{6,12})#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}
