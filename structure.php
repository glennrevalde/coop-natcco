<?php
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/public.php';

$units = q_all("SELECT ou.*, r.name AS region_name FROM organizational_units ou LEFT JOIN regions r ON r.id = ou.region_id WHERE ou.is_public = 1 AND ou.unit_status = 'active' AND ou.archived_at IS NULL ORDER BY ou.sort_order, ou.name");
$people = q_all("SELECT oa.org_unit_id, o.id AS officer_id, o.public_title, pe.first_name, pe.middle_name, pe.last_name, pe.suffix, ps.title AS position_title FROM officer_appointments oa
                 JOIN officers o ON o.id = oa.officer_id JOIN persons pe ON pe.id = o.person_id JOIN positions ps ON ps.id = oa.position_id
                 WHERE oa.appointment_status = 'current' AND oa.archived_at IS NULL AND o.is_public_profile = 1 AND o.review_status = 'approved' AND o.archived_at IS NULL ORDER BY ps.rank_order, pe.last_name");
$byUnit = [];
foreach ($people as $p) {
    $byUnit[(int) $p['org_unit_id']][] = $p;
}
$children = [];
foreach ($units as $u) {
    $children[(int) $u['parent_id']][] = $u;
}
$types = ['national' => 'National leadership', 'department' => 'Department', 'committee' => 'Committee', 'office' => 'Office', 'regional' => 'Regional structure', 'provincial' => 'Provincial structure', 'local' => 'Local structure'];
function render_units(array $children, int $parent, array $byUnit, array $types, int $depth = 0): string
{
    if (empty($children[$parent]) || $depth > 8) {
        return '';
    }
    $h = '<ul' . ($depth === 0 ? ' class="org-tree"' : '') . '>';
    foreach ($children[$parent] as $u) {
        $h .= '<li><div class="org-unit"><h3>' . e($u['name']) . '</h3><p class="ou-type">' . e($types[$u['unit_type']]) . ($u['region_name'] ? ', ' . e($u['region_name']) : '') . '</p>'
            . ($u['description'] ? '<p class="small">' . e($u['description']) . '</p>' : '');
        if (!empty($byUnit[(int) $u['id']])) {
            $h .= '<ul class="org-people">';
            foreach ($byUnit[(int) $u['id']] as $p) {
                $h .= '<li><strong><a href="' . e(url('officer.php', ['id' => $p['officer_id']])) . '">' . e(full_name($p)) . '</a></strong><span>' . e($p['position_title']) . '</span></li>';
            }
            $h .= '</ul>';
        }
        $h .= '</div>' . render_units($children, (int) $u['id'], $byUnit, $types, $depth + 1) . '</li>';
    }
    return $h . '</ul>';
}
public_start('Organizational structure', ['crumbs' => [['About', url('about.php')], ['Organizational structure', null]], 'sub' => 'National leadership, departments, committees and regional structures, with current officers.']);
echo '<div class="wrap section">';
echo $units ? render_units($children, 0, $byUnit, $types) : empty_state('The organizational chart is being prepared', 'The official structure will be published here.');
echo '<p class="muted small mt">Only current appointments of officers with an approved public profile are shown. Past appointments are kept in the organization\'s records.</p></div>';
public_end();
