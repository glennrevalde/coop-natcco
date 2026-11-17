<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$rows = q_all("SELECT o.id, o.public_title, o.photo_media_id, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, MIN(ps.rank_order) rnk,
               GROUP_CONCAT(ps.title ORDER BY ps.rank_order SEPARATOR ', ') positions FROM officer_appointments oa JOIN positions ps ON ps.id = oa.position_id
               JOIN officers o ON o.id = oa.officer_id JOIN persons pe ON pe.id = o.person_id
               WHERE oa.appointment_status = 'current' AND oa.archived_at IS NULL AND ps.is_leadership = 1 AND o.is_public_profile = 1 AND o.review_status = 'approved' AND o.archived_at IS NULL
               GROUP BY o.id, o.public_title, o.photo_media_id, pe.first_name, pe.middle_name, pe.last_name, pe.suffix ORDER BY rnk, pe.last_name");
public_start('Leadership', ['crumbs' => [['About', url('about.php')], ['Leadership', null]], 'sub' => 'Current national leadership of COOP NATCCO Partylist.']);
echo '<div class="wrap section">';
if (!$rows) {
    echo empty_state('Leadership profiles are being prepared', 'Official profiles are published here once approved.');
} else {
    echo '<div class="people-grid">';
    foreach ($rows as $r) {
        $initials = mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1);
        echo '<article class="card person-card"><a class="card-link" href="' . e(url('officer.php', ['id' => $r['id']])) . '"><span class="person-photo">'
            . ($r['photo_media_id'] ? '<img src="' . e(media_url((int) $r['photo_media_id'])) . '" alt="Photo of ' . e(full_name($r)) . '" loading="lazy">' : '<span class="person-initials" aria-hidden="true">' . e($initials) . '</span>')
            . '</span><h3>' . e(full_name($r)) . '</h3><p>' . e($r['public_title'] ?: $r['positions']) . '</p></a></article>';
    }
    echo '</div>';
}
echo '<p class="mt"><a class="more" href="' . e(url('structure.php')) . '">See the full organizational structure' . icon('arrow') . '</a></p></div>';
public_end();
