<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$o = q_one("SELECT o.*, pe.first_name, pe.middle_name, pe.last_name, pe.suffix FROM officers o JOIN persons pe ON pe.id = o.person_id
            WHERE o.id = ? AND o.is_public_profile = 1 AND o.review_status = 'approved' AND o.archived_at IS NULL", [get_int('id')]);
if (!$o) {
    not_found();
}
$apps = q_all("SELECT ps.title, ou.name AS unit_name, oa.start_date FROM officer_appointments oa JOIN positions ps ON ps.id = oa.position_id LEFT JOIN organizational_units ou ON ou.id = oa.org_unit_id
               WHERE oa.officer_id = ? AND oa.appointment_status = 'current' AND oa.archived_at IS NULL ORDER BY ps.rank_order", [$o['id']]);
$awards = q_all("SELECT x.* FROM awards x WHERE x.officer_id = ? AND " . public_published_sql('x') . " ORDER BY x.award_date DESC", [$o['id'], now()]);
$name = full_name($o);
public_start($name, ['crumbs' => [['Leadership', url('leadership.php')], [$name, null]], 'sub' => e($o['public_title'] ?: implode(', ', array_column($apps, 'title')))]);
?>
<div class="wrap section split">
    <article class="article">
        <?php if ($o['bio']): ?><div class="prose"><?= $o['bio'] ?></div><?php else: ?><p class="muted">A biography has not been published.</p><?php endif; ?>
        <?php if ($awards): ?><h2 class="h3 mt">Awards and recognition</h2><ul class="objectives"><?php foreach ($awards as $a): ?><li><a href="<?= e(url('awards.php', ['slug' => $a['slug']])) ?>"><?= e($a['title']) ?></a>, <?= e($a['awarding_institution']) ?><?= $a['award_date'] ? ' (' . e(fmt_date($a['award_date'], 'Y')) . ')' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
    </article>
    <aside>
        <div class="card person-card"><span class="person-photo"><?php if ($o['photo_media_id']): ?><img src="<?= e(media_url((int) $o['photo_media_id'])) ?>" alt="Photo of <?= e($name) ?>"><?php else: ?><span class="person-initials" aria-hidden="true"><?= e(mb_substr($o['first_name'], 0, 1) . mb_substr($o['last_name'], 0, 1)) ?></span><?php endif; ?></span>
            <h2 class="h3"><?= e($name) ?></h2>
            <?php foreach ($apps as $a): ?><p><?= e($a['title']) ?><?= $a['unit_name'] ? ', ' . e($a['unit_name']) : '' ?></p><?php endforeach; ?>
        </div>
    </aside>
</div>
<?php public_end();
