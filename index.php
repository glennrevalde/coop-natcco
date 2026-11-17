<?php
/**
 * Home page. All dynamic content comes from the database and is managed in the CMS.
 */
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$now = now();
$newsCount = max(2, min(12, setting_int('home_news_count', 4)));
$featured = q_all("SELECT * FROM posts x WHERE " . public_published_sql('x') . " AND x.post_type IN ('news','announcement') ORDER BY x.is_featured DESC, COALESCE(x.publish_at, x.published_at) DESC LIMIT {$newsCount}", [$now]);
$events = q_all("SELECT * FROM events x WHERE " . public_published_sql('x') . " AND x.visibility = 'public' AND x.starts_at >= ? AND x.event_status <> 'cancelled' ORDER BY x.starts_at LIMIT 4", [$now, $now]);
$achievements = setting('home_show_achievements', '1') === '1' ? q_all("SELECT * FROM posts x WHERE " . public_published_sql('x') . " AND x.post_type = 'achievement' ORDER BY x.is_featured DESC, COALESCE(x.publish_at, x.published_at) DESC LIMIT 3", [$now]) : [];
$legislation = setting('home_show_legislation', '1') === '1' ? q_all("SELECT * FROM legislative_updates x WHERE " . public_published_sql('x') . " ORDER BY COALESCE(x.approval_date, x.filing_date, x.published_at) DESC LIMIT 4", [$now]) : [];
$activities = setting('home_show_activities', '1') === '1' ? q_all("SELECT * FROM posts x WHERE " . public_published_sql('x') . " AND x.post_type IN ('activity','program') ORDER BY x.is_featured DESC, COALESCE(x.publish_at, x.published_at) DESC LIMIT 3", [$now]) : [];
$publications = setting('home_show_publications', '1') === '1' ? q_all("SELECT * FROM posts x WHERE " . public_published_sql('x') . " AND x.post_type IN ('publication','newsletter') AND x.attachment_media_id IS NOT NULL ORDER BY COALESCE(x.publish_at, x.published_at) DESC LIMIT 4", [$now]) : [];
$measures = measure_type_options();
$lstatus = legislative_status_options();

public_start(site_name(), ['no_bar' => true]);
$name = site_name();
$parts = preg_split('/\s+(?=Partylist|Party-List|Party List)/i', $name, 2);
?>
<div class="page-bar home-bar"><div class="wrap home-bar-in">
    <div>
        <h1><?= count($parts) === 2 ? e($parts[0]) . ' <span>' . e($parts[1]) . '</span>' : e($name) ?></h1>
        <p class="pb-sub"><?= e(setting('home_intro') !== '' ? excerpt(setting('home_intro'), 240) : setting('site_tagline', 'Official website')) ?></p>
    </div>
    <div class="home-actions">
        <a class="btn btn-accent" href="<?= e(url('helpdesk.php')) ?>">Submit a cooperative concern</a>
        <a class="btn btn-secondary" href="<?= e(url('legislation.php')) ?>">Legislative updates</a>
    </div>
</div></div>

<section class="section" aria-labelledby="h-news"><div class="wrap split">
    <div>
        <?= section_head('Latest news and announcements', url('news.php'), 'All news', 'h-news') ?>
        <?php if (!$featured): ?>
            <?= empty_state('No news yet', 'Official news and announcements will appear here once published.') ?>
        <?php else: ?>
            <div class="news-feature">
                <?= post_card($featured[0], true) ?>
                <div class="news-side"><?php foreach (array_slice($featured, 1, 3) as $p) { echo post_card($p); } ?></div>
            </div>
        <?php endif; ?>
    </div>
    <aside aria-label="Events and help desk">
        <?php if (setting('home_show_events', '1') === '1'): ?>
        <?= section_head('Upcoming events', url('events.php'), 'Calendar') ?>
        <?php if ($events): ?><ul class="event-list"><?php foreach ($events as $ev) { echo event_item($ev); } ?></ul>
        <?php else: ?><p class="muted">No upcoming public events.</p><?php endif; ?>
        <?php endif; ?>
        <div class="box box-blue mt">
            <h3>Help desk for primary cooperatives</h3>
            <p>Send a concern to the COOP NATCCO Partylist team and follow its progress with your ticket number.</p>
            <div class="btn-row"><a class="btn btn-accent" href="<?= e(url('helpdesk.php')) ?>">Submit a concern</a><a class="btn btn-secondary" href="<?= e(url('helpdesk-track.php')) ?>">Track a ticket</a></div>
        </div>
    </aside>
</div></section>

<?php if (setting('home_mission') !== '' || setting('home_vision') !== '' || setting('home_objectives') !== '' || setting('home_intro') !== ''): ?>
<section class="section section-alt" aria-labelledby="h-about"><div class="wrap">
    <?= section_head('Who we are', url('about.php'), 'About us', 'h-about') ?>
    <?php if (setting('home_intro') !== ''): ?><p class="lead"><?= e(setting('home_intro')) ?></p><?php endif; ?>
    <div class="mv-grid">
        <?php if (setting('home_mission') !== ''): ?><div class="box"><h3>Mission</h3><?= nl2p(setting('home_mission')) ?></div><?php endif; ?>
        <?php if (setting('home_vision') !== ''): ?><div class="box"><h3>Vision</h3><?= nl2p(setting('home_vision')) ?></div><?php endif; ?>
        <?php if (setting('home_objectives') !== ''): ?><div class="box"><h3>Objectives</h3><ul class="objectives"><?php foreach (array_filter(array_map('trim', preg_split('/\R/', setting('home_objectives')))) as $o): ?><li><?= e($o) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>
</div></section>
<?php endif; ?>

<?php if ($legislation): ?>
<section class="section" aria-labelledby="h-leg"><div class="wrap">
    <?= section_head('Recent legislative updates', url('legislation.php'), 'Legislative portal', 'h-leg') ?>
    <ul class="item-list">
    <?php foreach ($legislation as $l): ?>
        <li><span class="measure-no"><?= e(trim(($measures[$l['measure_type']] ?? '') . ' ' . $l['measure_number'])) ?></span><br>
            <a class="il-title" href="<?= e(url('legislation.php', ['slug' => $l['slug']])) ?>"><?= e($l['title']) ?></a>
            <div class="il-meta"><span>Status: <strong><?= e($lstatus[$l['legislative_status']] ?? '') ?></strong></span><?php if ($l['filing_date']): ?><span>Filed <?= e(fmt_date($l['filing_date'])) ?></span><?php endif; ?>
                <span><?= $l['is_officially_verified'] ? status_text('verified', 'Verified against the official record') : status_text('pending', 'Official details being verified') ?></span></div></li>
    <?php endforeach; ?>
    </ul>
</div></section>
<?php endif; ?>

<?php if ($achievements): ?>
<section class="section section-alt" aria-labelledby="h-ach"><div class="wrap">
    <?= section_head('Featured achievements', url('achievements.php'), 'All achievements', 'h-ach') ?>
    <div class="grid-3"><?php foreach ($achievements as $p) { echo post_card($p); } ?></div>
</div></section>
<?php endif; ?>

<?php if ($activities): ?>
<section class="section" aria-labelledby="h-act"><div class="wrap">
    <?= section_head('Cooperative programs and activities', url('programs.php'), 'All programs and activities', 'h-act') ?>
    <div class="grid-3"><?php foreach ($activities as $p) { echo post_card($p); } ?></div>
</div></section>
<?php endif; ?>

<section class="section section-alt" aria-labelledby="h-pub"><div class="wrap grid-2">
    <div>
        <?= section_head('Official publications', url('publications.php'), 'Publications and downloads', 'h-pub') ?>
        <?php if ($publications): ?><ul class="item-list"><?php foreach ($publications as $p): $m = media_row((int) $p['attachment_media_id']); ?>
            <li><a class="il-title" href="<?= e(url('post.php', ['slug' => $p['slug']])) ?>"><?= e($p['title']) ?></a>
                <div class="il-meta"><span><?= e(post_type_labels()[$p['post_type']]) ?></span><span><?= e(fmt_date($p['publish_at'] ?: $p['published_at'])) ?></span>
                <?php if ($m): ?><a class="file-dl" href="<?= e(media_url((int) $m['id'])) ?>"><?= icon('download') ?>Download <?= e(strtoupper($m['extension'])) ?>, <?= e(human_size((int) $m['file_size'])) ?></a><?php endif; ?></div></li>
        <?php endforeach; ?></ul>
        <?php else: ?><p class="muted">Official publications will be listed here.</p><?php endif; ?>
    </div>
    <div>
        <?= section_head('Contact us', url('contact.php'), 'Contact page') ?>
        <div class="box">
            <ul class="contact-list">
                <?php if (setting('contact_address') !== ''): ?><li><?= icon('pin') ?><span><?= nl2br(e(setting('contact_address'))) ?></span></li><?php endif; ?>
                <?php if (setting('contact_phone') !== ''): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a></li><?php endif; ?>
                <?php if (setting('contact_email') !== ''): ?><li><?= icon('mail') ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></li><?php endif; ?>
                <?php if (setting('office_hours') !== ''): ?><li><?= icon('clock') ?><span><?= e(setting('office_hours')) ?></span></li><?php endif; ?>
                <li><?= icon('help') ?><span>For cooperative concerns, use the <a href="<?= e(url('helpdesk.php')) ?>">help desk</a> so your request can be tracked.</span></li>
            </ul>
        </div>
    </div>
</div></section>
<?php public_end();
