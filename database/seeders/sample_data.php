<?php
/**
 * Sample (demonstration) data generator - DEVELOPMENT USE ONLY.
 *
 * Run on a fresh clean database:   php database/seeders/sample_data.php
 * (images and PDFs come from database/seeders/sample-assets unless another folder is given)
 * Every name, figure and document created here is fictional sample data for testing.
 * Never run this against a production database.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
define('CNPL_TESTING', true);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/modules.php';

if (is_production()) {
    fwrite(STDERR, "Refusing to load sample data in production.\n");
    exit(1);
}
$assets = rtrim($argv[1] ?? (__DIR__ . '/sample-assets'), '/\\');
if (!is_dir($assets)) {
    fwrite(STDERR, "Usage: php database/seeders/sample_data.php [sample-assets folder]\n");
    exit(1);
}
mt_srand(2026);
$GLOBALS['CNPL_USER'] = q_one('SELECT * FROM users WHERE id = 1');
$NOW = time();
function ago(int $days, int $hour = 9): string
{
    return date('Y-m-d H:i:s', strtotime("-{$days} days") - (int) date('G') * 3600 + $hour * 3600 + mt_rand(0, 3000));
}
function ahead(int $days, int $hour = 9): string
{
    return date('Y-m-d', strtotime("+{$days} days")) . sprintf(' %02d:00:00', $hour);
}
function pick(array $a)
{
    return $a[mt_rand(0, count($a) - 1)];
}
function file_media(string $path, string $kind, string $vis, string $title): int
{
    $tmp = tempnam(sys_get_temp_dir(), 'cnpl');
    copy($path, $tmp);
    $info = media_validate_upload(['name' => basename($path), 'type' => '', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)], $kind);
    $id = media_store($info, $vis, $title);
    @unlink($tmp);
    return $id;
}
$first = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Antonio', 'Carmen', 'Ramon', 'Teresa', 'Manuel', 'Elena', 'Ricardo', 'Luz', 'Fernando', 'Gloria', 'Eduardo', 'Cristina', 'Roberto', 'Lourdes',
    'Mark', 'Joy', 'Paolo', 'Grace', 'Arnel', 'Liza', 'Dennis', 'Marites', 'Rodel', 'Jocelyn', 'Noel', 'Rowena', 'Edgar', 'Maricel', 'Jun', 'Analyn', 'Ronaldo', 'Sheila', 'Allan', 'Rachelle'];
$last = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos', 'Aquino', 'Castillo', 'Rivera', 'Flores', 'Gonzales', 'Torres', 'Navarro', 'Domingo', 'Mercado',
    'Pascual', 'Salazar', 'Valdez', 'Aguilar', 'Manalo', 'Fernandez', 'Lopez', 'Cruz', 'Del Rosario', 'Soriano', 'Tolentino', 'Magbanua', 'Ocampo', 'Panganiban', 'Lacson', 'Dizon', 'Ignacio', 'Javier'];
$middle = ['Abad', 'Bernal', 'Cortez', 'Dimaculangan', 'Espino', 'Galang', 'Hernandez', 'Lim', 'Morales', 'Quiambao', 'Robles', 'Tan', 'Uy', 'Velasco', 'Yap', ''];
$coopWords = ['Bayanihan', 'Masagana', 'Pag-asa', 'Kaunlaran', 'Tagumpay', 'Liwanag', 'Pagkakaisa', 'Kabalikat', 'Sama-Sama', 'Damayan', 'Malasakit', 'Kapatiran', 'Bigkis', 'Sinag', 'Matatag', 'Ugnayan', 'Ginhawa', 'Pagsulong'];
$suffix = ['Multi-Purpose Cooperative' => 6, 'Credit Cooperative' => 1, 'Consumers Cooperative' => 2, 'Producers Cooperative' => 3, 'Agrarian Reform Cooperative' => 8, 'Fishermen Cooperative' => 14, 'Transport Cooperative' => 18, 'Workers Cooperative' => 20, 'Marketing Cooperative' => 4];

function make_person(array $first, array $middle, array $last, ?string $emailDomain = 'example.org'): int
{
    static $used = [];
    do {
        $f = pick($first);
        $m = pick($middle);
        $l = pick($last);
        $key = name_key($f, $l, $m);
    } while (isset($used[$key]));
    $used[$key] = 1;
    $email = strtolower(preg_replace('/[^a-z]/i', '', $f) . '.' . preg_replace('/[^a-z]/i', '', $l) . mt_rand(10, 999)) . '@' . $emailDomain;
    return q_insert('persons', ['first_name' => $f, 'middle_name' => $m ?: null, 'last_name' => $l, 'name_key' => $key, 'email_enc' => encrypt_value($email), 'email_bidx' => blind_index($email),
        'phone_enc' => encrypt_value('09' . mt_rand(10, 99) . ' ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999)), 'created_by' => 1, 'created_at' => ago(mt_rand(200, 700))]);
}

echo "Settings...\n";
$S = [
    'demo_notice' => 'Demonstration copy: names, figures and content are sample data for testing, not official COOP NATCCO Partylist information.',
    'site_tagline' => 'Official Website and National Database',
    'site_description' => 'Official website of COOP NATCCO Partylist: news, legislative updates, events, help desk and resources for primary cooperatives.',
    'contact_address' => "Sample Office Address\nQuezon City, Metro Manila",
    'contact_phone' => '(02) 8000 0000', 'contact_email' => 'info@example.org', 'office_hours' => 'Monday to Friday, 8:00 AM to 5:00 PM',
    'home_intro' => 'COOP NATCCO Partylist works for cooperatives and their members through legislation, representation and services that help primary cooperatives grow.',
    'home_mission' => 'Sample mission statement: to advance the welfare of cooperatives and their members through responsive legislation, representation and service.',
    'home_vision' => 'Sample vision statement: a strong and inclusive cooperative sector that improves the lives of Filipino families.',
    'home_objectives' => "Champion laws and policies that strengthen cooperatives\nProvide timely assistance to primary cooperatives\nPromote transparency and good governance\nBuild partnerships for cooperative development",
    'footer_about' => 'The cooperative sector party-list. This demonstration copy contains sample content only.',
    'social_facebook' => 'https://www.facebook.com/', 'social_youtube' => 'https://www.youtube.com/',
    'dpo_name' => 'Data Protection Officer (sample)', 'dpo_email' => 'dpo@example.org', 'mfa_required_privileged' => '0',
];
foreach ($S as $k => $v) {
    setting_set($k, $v, 1);
}
q("UPDATE pages SET status = 'published', published_at = ?, body = ?, summary = ? WHERE slug = 'about-us'", [ago(300), '<p>COOP NATCCO Partylist represents the cooperative sector. This sample profile shows how the About page presents the organization, its purpose and its work with primary cooperatives across the country.</p><h2>What we do</h2><ul><li>Legislative work for the cooperative sector</li><li>Assistance to primary cooperatives through the help desk</li><li>Partnerships, programs and capacity building</li></ul><p>Replace this text with the approved official profile.</p>', 'Who we are and what we do (sample content).']);
q("UPDATE pages SET status = 'published', published_at = ?, body = ?, summary = ? WHERE slug = 'history'", [ago(300), '<p>This sample introduction shows where the verified history of COOP NATCCO Partylist will appear. The timeline below lists milestones, each with its source.</p>', 'Establishment, milestones and achievements (sample content).']);

echo "Media...\n";
$covers = [];
for ($i = 1; $i <= 10; $i++) {
    $covers[] = file_media("{$assets}/cover-{$i}.jpg", 'image', 'public', 'Cover image ' . $i);
}
$photos = [];
for ($i = 1; $i <= 8; $i++) {
    $photos[] = file_media("{$assets}/gallery-{$i}.jpg", 'image', 'public', 'Activity photo ' . $i);
}
$pdf = [];
foreach (['newsletter-q1', 'newsletter-q2', 'policy-helpdesk', 'policy-records', 'form-update', 'publication-guide'] as $n) {
    $pdf[$n] = file_media("{$assets}/{$n}.pdf", 'document', 'public', $n);
}
$pdf['election-source'] = file_media("{$assets}/election-source.pdf", 'document', 'private', 'Sample election source');

echo "Organizational structure...\n";
$units = [];
$units['board'] = q_insert('organizational_units', ['name' => 'National Board', 'unit_type' => 'national', 'sort_order' => 10, 'description' => 'Sample national leadership body.', 'created_at' => ago(400)]);
$units['secgen'] = q_insert('organizational_units', ['parent_id' => $units['board'], 'name' => 'Office of the Secretary General', 'unit_type' => 'office', 'sort_order' => 20, 'created_at' => ago(400)]);
$units['leg'] = q_insert('organizational_units', ['parent_id' => $units['secgen'], 'name' => 'Legislative Affairs Department', 'unit_type' => 'department', 'sort_order' => 30, 'created_at' => ago(400)]);
$units['dev'] = q_insert('organizational_units', ['parent_id' => $units['secgen'], 'name' => 'Cooperative Development Department', 'unit_type' => 'department', 'sort_order' => 40, 'created_at' => ago(400)]);
$units['fin'] = q_insert('organizational_units', ['parent_id' => $units['board'], 'name' => 'Finance and Audit Committee', 'unit_type' => 'committee', 'sort_order' => 50, 'created_at' => ago(400)]);
$units['mem'] = q_insert('organizational_units', ['parent_id' => $units['board'], 'name' => 'Membership and Partnerships Committee', 'unit_type' => 'committee', 'sort_order' => 60, 'created_at' => ago(400)]);
foreach (['Luzon' => 3, 'Visayas' => 11, 'Mindanao' => 15] as $n => $rid) {
    $units['reg_' . $n] = q_insert('organizational_units', ['parent_id' => $units['secgen'], 'name' => $n . ' Regional Coordination Office', 'unit_type' => 'regional', 'region_id' => $rid, 'sort_order' => 70, 'created_at' => ago(400)]);
}
$pos = [];
foreach ([['Chairperson', 'board', 1, 1], ['Vice Chairperson', 'board', 2, 1], ['Secretary General', 'secgen', 3, 1], ['Treasurer', 'fin', 4, 1], ['Auditor', 'fin', 5, 1], ['Board Member', 'board', 10, 1],
    ['Department Head', 'leg', 20, 0], ['Committee Chair', 'mem', 25, 0], ['Regional Coordinator', 'reg_Visayas', 30, 0]] as [$t, $u, $rank, $lead]) {
    $pos[$t] = q_insert('positions', ['title' => $t, 'org_unit_id' => $units[$u], 'rank_order' => $rank, 'is_leadership' => $lead, 'created_at' => ago(400)]);
}

echo "Officers...\n";
$officerDefs = [['Chairperson', 'board', null], ['Vice Chairperson', 'board', null], ['Secretary General', 'secgen', null], ['Treasurer', 'fin', null], ['Auditor', 'fin', null],
    ['Board Member', 'board', null], ['Board Member', 'board', null], ['Department Head', 'leg', null], ['Department Head', 'dev', null], ['Committee Chair', 'mem', null],
    ['Regional Coordinator', 'reg_Luzon', 3], ['Regional Coordinator', 'reg_Visayas', 11], ['Regional Coordinator', 'reg_Mindanao', 15]];
$officers = [];
foreach ($officerDefs as $i => [$ptitle, $unit, $reg]) {
    $pid = make_person($first, $middle, $last);
    $oid = q_insert('officers', ['person_id' => $pid, 'officer_code' => 'TMP' . $i, 'public_title' => $ptitle, 'bio' => '<p>Sample biography for demonstration. Replace with the approved official profile of the officer.</p>',
        'is_public_profile' => $i < 10 ? 1 : 0, 'region_id' => $reg, 'office_assignment' => $reg ? 'Regional coordination office' : 'National office', 'responsibilities' => 'Sample responsibilities for the ' . strtolower($ptitle) . ' role.',
        'officer_status' => 'active', 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(380)]);
    q('UPDATE officers SET officer_code = ? WHERE id = ?', ['CNPL-OFF-' . str_pad((string) $oid, 5, '0', STR_PAD_LEFT), $oid]);
    q_insert('officer_appointments', ['officer_id' => $oid, 'position_id' => $pos[$ptitle], 'org_unit_id' => $units[$unit], 'region_id' => $reg, 'start_date' => date('Y-m-d', strtotime('-' . mt_rand(200, 600) . ' days')),
        'appointment_status' => 'current', 'appointment_reference' => 'Sample Board Resolution No. ' . (100 + $i), 'created_by' => 1, 'created_at' => ago(370)]);
    $officers[] = $oid;
}
// historical appointment preserved
q_insert('officer_appointments', ['officer_id' => $officers[1], 'position_id' => $pos['Board Member'], 'org_unit_id' => $units['board'], 'start_date' => date('Y-m-d', strtotime('-1400 days')), 'end_date' => date('Y-m-d', strtotime('-610 days')),
    'appointment_status' => 'ended', 'appointment_reference' => 'Sample Board Resolution No. 12', 'created_by' => 1, 'created_at' => ago(1400)]);

echo "Cooperatives...\n";
$regionCount = q_pairs('SELECT id, short_name FROM regions');
$weights = [1 => 14, 2 => 5, 3 => 7, 4 => 6, 5 => 12, 6 => 13, 7 => 5, 8 => 7, 9 => 7, 10 => 5, 11 => 12, 12 => 5, 13 => 4, 14 => 6, 15 => 7, 16 => 5, 17 => 4, 18 => 3];
$coops = [];
$consolacion = (int) q_val("SELECT c.id FROM cities_municipalities c JOIN provinces p ON p.id = c.province_id WHERE c.name = 'Consolacion' AND p.name = 'Cebu'");
$cebuProv = (int) q_val("SELECT id FROM provinces WHERE name = 'Cebu'");
$n = 0;
foreach ($weights as $rid => $count) {
    $cities = q_all('SELECT id, name, province_id FROM cities_municipalities WHERE region_id = ? ORDER BY RAND(' . $rid . ') LIMIT 10', [$rid]);
    for ($i = 0; $i < $count; $i++) {
        $city = $cities[$i % count($cities)];
        if ($rid === 11 && $i < 3) {
            $city = q_one('SELECT id, name, province_id FROM cities_municipalities WHERE id = ?', [$consolacion]);
        }
        $suf = array_keys($suffix)[mt_rand(0, count($suffix) - 1)];
        $place = preg_replace('/^City of /', '', $city['name']);
        $name = pick($coopWords) . ' ' . $place . ' ' . $suf;
        if (q_val('SELECT id FROM cooperatives WHERE name_key = ?', [coop_name_key($name)])) {
            $name = pick($coopWords) . ' ' . pick($coopWords) . ' ' . $place . ' ' . $suf;
        }
        $n++;
        $verified = mt_rand(1, 100) <= 72;
        $review = $n % 23 === 0 ? 'pending' : 'approved';
        $created = ago(mt_rand(5, 520));
        $cid = q_insert('cooperatives', ['reference_no' => 'TMP' . $n, 'name' => $name, 'name_key' => coop_name_key($name), 'cda_registration_no' => mt_rand(1, 10) > 1 ? 'DEMO-9520-' . str_pad((string) (1000 + $n), 6, '0', STR_PAD_LEFT) : null,
            'cooperative_type_id' => $suffix[$suf], 'category' => 'primary', 'registered_address' => 'Sample Street, ' . $place, 'region_id' => $rid, 'province_id' => $city['province_id'], 'city_id' => $city['id'],
            'contact_person' => pick($first) . ' ' . pick($last), 'contact_email' => mt_rand(1, 6) > 1 ? 'coop' . $n . '@example.org' : null, 'contact_phone' => mt_rand(1, 5) > 1 ? '(0' . mt_rand(32, 88) . ') ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999) : null,
            'date_registered' => date('Y-m-d', strtotime('-' . mt_rand(800, 9000) . ' days')), 'reported_member_count' => mt_rand(40, 4500), 'org_status' => mt_rand(1, 20) === 1 ? 'inactive' : 'active',
            'verification_status' => $verified ? 'verified' : (mt_rand(0, 3) ? 'unverified' : 'needs_update'), 'verified_by' => $verified ? 1 : null, 'verified_at' => $verified ? $created : null,
            'is_public_listing' => $verified && mt_rand(1, 10) > 2 ? 1 : 0, 'review_status' => $review, 'created_by' => 1, 'created_at' => $created]);
        q('UPDATE cooperatives SET reference_no = ? WHERE id = ?', ['CNPL-COOP-' . str_pad((string) $cid, 6, '0', STR_PAD_LEFT), $cid]);
        if ($review === 'pending') {
            q_insert('approval_requests', ['module' => 'cooperatives', 'record_id' => $cid, 'request_type' => 'create', 'summary' => 'New cooperative: ' . $name, 'request_status' => 'pending', 'requested_by' => 1, 'created_at' => $created]);
        }
        revision_log('cooperatives', $cid, 'created', null);
        $coops[] = ['id' => $cid, 'region' => $rid, 'province' => $city['province_id'], 'city' => $city['id']];
    }
}
echo count($coops) . " cooperatives\n";

echo "Personnel, point persons, members...\n";
$titles = [['officer', 'Chairperson', 'Board of Directors'], ['officer', 'Vice Chairperson', 'Board of Directors'], ['officer', 'Secretary', 'Board of Directors'], ['officer', 'Treasurer', 'Board of Directors'],
    ['staff', 'General Manager', 'Management'], ['staff', 'Bookkeeper', 'Finance'], ['staff', 'Loans Officer', 'Credit'], ['staff', 'Membership Officer', 'Membership']];
$pps = [];
foreach ($coops as $ci => $c) {
    $k = mt_rand(2, 4);
    $used = [];
    for ($j = 0; $j < $k; $j++) {
        $t = $titles[mt_rand(0, count($titles) - 1)];
        if (isset($used[$t[1]])) {
            continue;
        }
        $used[$t[1]] = 1;
        q_insert('cooperative_personnel', ['cooperative_id' => $c['id'], 'person_id' => make_person($first, $middle, $last), 'personnel_type' => $t[0], 'position_title' => $t[1], 'department' => $t[2],
            'employment_status' => 'active', 'start_date' => date('Y-m-d', strtotime('-' . mt_rand(60, 1500) . ' days')), 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(mt_rand(5, 400))]);
    }
    if ($ci % 9 === 0) {
        q_insert('cooperative_personnel', ['cooperative_id' => $c['id'], 'person_id' => make_person($first, $middle, $last), 'personnel_type' => 'officer', 'position_title' => 'Chairperson', 'department' => 'Board of Directors',
            'employment_status' => 'ended', 'start_date' => date('Y-m-d', strtotime('-2000 days')), 'end_date' => date('Y-m-d', strtotime('-500 days')), 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(900)]);
    }
    if (mt_rand(1, 100) <= 70) {
        $pps[$c['id']] = q_insert('cnpl_point_persons', ['person_id' => make_person($first, $middle, $last), 'cooperative_id' => $c['id'], 'role_title' => 'CNPL Point Person', 'coverage_region_id' => $c['region'],
            'coverage_province_id' => $c['province'], 'coverage_city_id' => $c['city'], 'start_date' => date('Y-m-d', strtotime('-' . mt_rand(30, 500) . ' days')), 'assignment_status' => 'active',
            'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(mt_rand(5, 400))]);
    }
}
// members for 30 cooperatives (including the Consolacion cooperatives)
$memberCoops = array_slice($coops, 0, 6);
foreach (array_rand($coops, 24) as $idx) {
    $memberCoops[] = $coops[$idx];
}
$mcount = 0;
foreach ($memberCoops as $c) {
    $k = mt_rand(12, 30);
    for ($j = 1; $j <= $k; $j++) {
        $f = pick($first);
        $l = pick($last);
        $m = pick($middle);
        $status = mt_rand(1, 12) === 1 ? pick(['inactive', 'withdrawn']) : 'active';
        $adm = date('Y-m-d', strtotime('-' . mt_rand(60, 4000) . ' days'));
        $mid = q_insert('members', ['member_ref' => 'TMP' . (++$mcount), 'cooperative_id' => $c['id'], 'coop_member_no' => 'M-' . str_pad((string) $j, 5, '0', STR_PAD_LEFT), 'first_name' => $f, 'middle_name' => $m ?: null, 'last_name' => $l,
            'name_key' => name_key($f, $l, $m), 'email_enc' => mt_rand(0, 2) ? encrypt_value(strtolower($f . '.' . str_replace(' ', '', $l) . $mcount) . '@example.org') : null,
            'phone_enc' => encrypt_value('09' . mt_rand(10, 99) . ' ' . mt_rand(100, 999) . ' ' . mt_rand(1000, 9999)), 'membership_type' => 'regular', 'membership_status' => $status, 'date_admitted' => $adm,
            'date_ended' => $status !== 'active' ? date('Y-m-d', strtotime('-' . mt_rand(10, 300) . ' days')) : null, 'lawful_basis' => 'contract', 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(mt_rand(5, 300))]);
        q('UPDATE members SET member_ref = ? WHERE id = ?', ['CNPL-MEM-' . str_pad((string) $mid, 7, '0', STR_PAD_LEFT), $mid]);
        q_insert('cooperative_memberships', ['member_id' => $mid, 'cooperative_id' => $c['id'], 'coop_member_no' => 'M-' . str_pad((string) $j, 5, '0', STR_PAD_LEFT), 'membership_status' => $status, 'start_date' => $adm, 'change_reason' => 'Initial record', 'recorded_by' => 1, 'created_at' => ago(5)]);
    }
}
echo "{$mcount} members\n";

echo "Ambassadors and focal persons...\n";
$amb = [];
for ($i = 0; $i < 26; $i++) {
    $c = pick($coops);
    $type = $i % 3 === 0 ? 'focal_person' : 'ambassador';
    $amb[] = q_insert('ambassadors_focal_persons', ['person_id' => make_person($first, $middle, $last), 'designation_type' => $type, 'designation_title' => $type === 'ambassador' ? 'CNPL Ambassador' : 'CNPL Focal Person',
        'cooperative_id' => mt_rand(0, 1) ? $c['id'] : null, 'region_id' => $c['region'], 'province_id' => $i % 2 ? $c['province'] : null, 'appointment_date' => date('Y-m-d', strtotime('-' . mt_rand(30, 700) . ' days')),
        'designation_status' => mt_rand(1, 10) > 1 ? 'active' : 'inactive', 'responsibilities' => 'Sample responsibilities: advocacy, coordination and reporting within the assigned area.', 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(mt_rand(20, 600))]);
}

echo "Demo user accounts...\n";
$pw = password_hash('CnplDemo#2026', PASSWORD_BCRYPT, ['cost' => 10]);
$roleId = q_pairs('SELECT slug, id FROM roles');
$r7 = 11;
$cebuCoop = $coops[array_search(11, array_column($coops, 'region'))]['id'];
$consCoop = (int) q_val('SELECT id FROM cooperatives WHERE city_id = ? ORDER BY id LIMIT 1', [$consolacion]);
$accounts = [
    ['nationaladmin.demo', 'National Administrator (Demo)', 'national_admin', ['national']],
    ['regionaladmin.demo', 'Regional Administrator, Region VII (Demo)', 'regional_admin', ['region', $r7]],
    ['provadmin.demo', 'Provincial Administrator, Cebu (Demo)', 'provincial_admin', ['province', $cebuProv]],
    ['cityadmin.demo', 'City/Municipal Administrator, Consolacion (Demo)', 'city_admin', ['city', $consolacion]],
    ['coopadmin.demo', 'Cooperative Administrator (Demo)', 'coop_admin', ['cooperative', $consCoop]],
    ['coopstaff.demo', 'Cooperative Officer/Staff (Demo)', 'coop_staff', ['cooperative', $consCoop]],
    ['pointperson.demo', 'CNPL Point Person (Demo)', 'point_person', []],
    ['ambassador.demo', 'CNPL Ambassador (Demo)', 'ambassador', []],
    ['focal.demo', 'CNPL Focal Person (Demo)', 'focal_person', []],
    ['content.demo', 'Content Manager (Demo)', 'content_manager', ['national']],
    ['helpdesk.demo', 'Help Desk Officer (Demo)', 'helpdesk_officer', ['national']],
    ['encoder.demo', 'Data Encoder, Region VII (Demo)', 'data_encoder', ['region', $r7]],
    ['auditor.demo', 'Auditor (Demo)', 'auditor', ['national']],
];
$uids = [];
foreach ($accounts as [$un, $name, $role, $scope]) {
    $id = q_insert('users', ['username' => $un, 'email' => $un . '@example.org', 'password_hash' => $pw, 'full_name' => $name, 'account_status' => 'active', 'must_change_password' => 0,
        'password_changed_at' => ago(30), 'created_by' => 1, 'created_at' => ago(60)]);
    q_insert('user_roles', ['user_id' => $id, 'role_id' => $roleId[$role], 'assignment_status' => 'active', 'assigned_by' => 1, 'approved_by' => 1, 'approved_at' => ago(60), 'created_at' => ago(60)]);
    if ($scope) {
        $row = ['user_id' => $id, 'scope_type' => $scope[0], 'created_by' => 1, 'created_at' => ago(60)];
        if (isset($scope[1])) {
            $row[['region' => 'region_id', 'province' => 'province_id', 'city' => 'city_id', 'cooperative' => 'cooperative_id'][$scope[0]]] = $scope[1];
        }
        q_insert('user_scope_assignments', $row);
    }
    $uids[$role] = $id;
}
// superadmin: demo password, no forced change, for testing
q('UPDATE users SET password_hash = ?, must_change_password = 0, email = ? WHERE id = 1', [$pw, 'superadmin@example.org']);
// link assignment-based accounts
$ppForCons = q_val('SELECT id FROM cnpl_point_persons WHERE cooperative_id = ? LIMIT 1', [$consCoop]);
if (!$ppForCons) {
    $ppForCons = q_insert('cnpl_point_persons', ['person_id' => make_person($first, $middle, $last), 'cooperative_id' => $consCoop, 'role_title' => 'CNPL Point Person', 'coverage_region_id' => $r7, 'coverage_province_id' => $cebuProv,
        'coverage_city_id' => $consolacion, 'start_date' => date('Y-m-d', strtotime('-200 days')), 'assignment_status' => 'active', 'review_status' => 'approved', 'created_by' => 1, 'created_at' => ago(200)]);
}
q('UPDATE cnpl_point_persons SET user_id = ? WHERE id = ?', [$uids['point_person'], $ppForCons]);
q('UPDATE ambassadors_focal_persons SET user_id = ?, region_id = ?, province_id = ?, city_id = NULL, designation_status = ? WHERE id = ?', [$uids['ambassador'], $r7, $cebuProv, 'active', $amb[1]]);
q('UPDATE ambassadors_focal_persons SET user_id = ?, region_id = ?, province_id = NULL, city_id = NULL, designation_status = ? WHERE id = ?', [$uids['focal_person'], $r7, 'active', $amb[0]]);
q('UPDATE helpdesk_categories SET default_assignee_id = ? WHERE id IN (1, 7)', [$uids['helpdesk_officer']]);

echo "Activities and tasks...\n";
$atypes = ['meeting', 'training', 'outreach', 'assembly', 'consultation', 'coordination', 'field_report'];
$atitles = ['Pre-membership education seminar', 'Financial literacy training', 'Coordination meeting with cooperative leaders', 'General assembly support visit', 'Consultation on cooperative concerns',
    'Bookkeeping workshop', 'Community outreach day', 'Governance and management training', 'Field visit and records review'];
for ($i = 0; $i < 70; $i++) {
    $c = pick($coops);
    $d = date('Y-m-d', strtotime('-' . mt_rand(1, 360) . ' days'));
    q_insert('activities', ['title' => pick($atitles), 'activity_type' => pick($atypes), 'activity_date' => $d, 'cooperative_id' => mt_rand(0, 4) ? $c['id'] : null, 'ambassador_id' => mt_rand(0, 5) ? null : pick($amb),
        'point_person_id' => isset($pps[$c['id']]) && mt_rand(0, 2) === 0 ? $pps[$c['id']] : null, 'region_id' => $c['region'], 'province_id' => $c['province'], 'city_id' => $c['city'], 'venue' => 'Cooperative office',
        'participants_count' => mt_rand(8, 160), 'description' => 'Sample activity report for demonstration.', 'outcome' => 'Sample outcomes and agreed next steps.', 'review_status' => $i % 17 === 0 ? 'pending' : 'approved', 'created_by' => 1, 'created_at' => $d . ' 16:00:00']);
}
$ttitles = ['Submit updated list of cooperative officers', 'Follow up on the information update form', 'Coordinate venue for the regional consultation', 'Send acknowledgment letter to the cooperative', 'Collect attendance sheets from the training'];
foreach (array_slice($pps, 0, 18, true) as $cid => $ppid) {
    q_insert('point_person_tasks', ['point_person_id' => $ppid, 'title' => pick($ttitles), 'details' => 'Sample task details.', 'task_type' => mt_rand(0, 3) ? 'task' : 'correspondence',
        'due_date' => date('Y-m-d', strtotime(mt_rand(-20, 40) . ' days')), 'task_status' => pick(['open', 'open', 'in_progress', 'done']), 'created_by' => 1, 'created_at' => ago(mt_rand(1, 60))]);
}
foreach (['Submit the cooperative officers list for the quarter', 'Confirm attendance for the regional consultation'] as $t) {
    q_insert('point_person_tasks', ['point_person_id' => $ppForCons, 'title' => $t, 'task_type' => 'task', 'due_date' => date('Y-m-d', strtotime('+10 days')), 'task_status' => 'open', 'created_by' => 1, 'created_at' => ago(3)]);
}

echo "Elections...\n";
$src = q_insert('election_sources', ['title' => 'Sample dataset for testing (not official COMELEC data)', 'source_type' => 'other', 'issuing_body' => 'System test data', 'reference_no' => 'SAMPLE-ER-001',
    'date_issued' => date('Y-m-d', strtotime('-30 days')), 'document_media_id' => $pdf['election-source'], 'notes' => 'Fictional figures used only to demonstrate election performance reports.', 'created_by' => 1, 'created_at' => ago(30)]);
$years = [];
foreach ([2016 => '2016-05-09', 2019 => '2019-05-13', 2022 => '2022-05-09', 2025 => '2025-05-12'] as $y => $d) {
    $years[$y] = q_insert('election_years', ['election_year' => $y, 'election_date' => $d, 'title' => $y . ' National and Local Elections', 'created_by' => 1, 'created_at' => ago(30)]);
}
$regions = q_all('SELECT id FROM regions ORDER BY sort_order');
$base = [];
foreach ($regions as $r) {
    $base[$r['id']] = mt_rand(8000, 52000);
}
foreach ($years as $y => $yid) {
    $growth = ['2016' => 0.82, '2019' => 0.95, '2022' => 1.0, '2025' => 1.12][$y];
    $natVotes = 0;
    $natTotal = 0;
    foreach ($regions as $r) {
        $v = (int) round($base[$r['id']] * $growth * (0.85 + mt_rand(0, 30) / 100));
        $tot = $v * mt_rand(55, 90);
        $natVotes += $v;
        $natTotal += $tot;
        $status = ($y === 2025 && $r['id'] % 5 === 0) ? 'pending' : 'verified';
        $rid = q_insert('election_results', ['election_year_id' => $yid, 'geo_level' => 'region', 'geo_key' => 'R' . $r['id'], 'region_id' => $r['id'], 'votes_obtained' => $v, 'total_votes_cast' => $tot,
            'source_id' => $src, 'verification_status' => $status, 'verified_by' => $status === 'verified' ? 1 : null, 'verified_at' => $status === 'verified' ? ago(20) : null, 'created_by' => 1, 'created_at' => ago(25)]);
        q_insert('election_result_verifications', ['result_id' => $rid, 'action' => 'submitted', 'user_id' => 1, 'created_at' => ago(25)]);
        if ($status === 'verified') {
            q_insert('election_result_verifications', ['result_id' => $rid, 'action' => 'verified', 'user_id' => 1, 'created_at' => ago(20)]);
        }
    }
    q_insert('election_results', ['election_year_id' => $yid, 'geo_level' => 'national', 'geo_key' => 'N', 'votes_obtained' => $natVotes, 'total_votes_cast' => $natTotal, 'rank_position' => mt_rand(8, 30),
        'source_id' => $src, 'verification_status' => 'verified', 'verified_by' => 1, 'verified_at' => ago(20), 'created_by' => 1, 'created_at' => ago(25)]);
    // provinces and cities of Region VII
    foreach (q_all('SELECT id FROM provinces WHERE region_id = ?', [$r7]) as $p) {
        $v = (int) round(mt_rand(3000, 16000) * $growth);
        q_insert('election_results', ['election_year_id' => $yid, 'geo_level' => 'province', 'geo_key' => 'P' . $p['id'], 'region_id' => $r7, 'province_id' => $p['id'], 'votes_obtained' => $v, 'total_votes_cast' => $v * mt_rand(50, 85),
            'source_id' => $src, 'verification_status' => 'verified', 'verified_by' => 1, 'verified_at' => ago(20), 'created_by' => 1, 'created_at' => ago(25)]);
    }
    foreach (q_all('SELECT id, province_id FROM cities_municipalities WHERE province_id = ? ORDER BY name LIMIT 14', [$cebuProv]) as $c) {
        $v = (int) round(mt_rand(300, 2600) * $growth);
        q_insert('election_results', ['election_year_id' => $yid, 'geo_level' => 'city', 'geo_key' => 'C' . $c['id'], 'region_id' => $r7, 'province_id' => $c['province_id'], 'city_id' => $c['id'], 'votes_obtained' => $v,
            'total_votes_cast' => $v * mt_rand(45, 80), 'source_id' => $src, 'verification_status' => 'verified', 'verified_by' => 1, 'verified_at' => ago(20), 'created_by' => 1, 'created_at' => ago(25)]);
    }
}

echo "Website content...\n";
$cat = q_pairs("SELECT CONCAT(scope, ':', slug), id FROM content_categories");
$posts = [
    ['news', 'Cooperative leaders gather for a regional consultation on financing programs', 'Leaders of primary cooperatives shared their experiences with access to financing and identified issues for legislative follow-up.', 'cooperative-development', 1],
    ['news', 'Help desk for primary cooperatives now accepts online concerns', 'Primary cooperatives can now submit concerns online, receive a ticket number and follow up without visiting an office.', 'general', 1],
    ['announcement', 'Schedule of the regional consultations this quarter', 'The schedule of regional consultations is now available. Registration is open on each event page.', 'general', 0],
    ['announcement', 'Call for updated cooperative information', 'Cooperatives are invited to review and update their registration details through the help desk.', 'cooperative-development', 0],
    ['news', 'Legislative briefing held with cooperative federations', 'A briefing covered pending measures relevant to cooperatives and gathered recommendations from the sector.', 'legislative-work', 0],
    ['achievement', 'Training program reaches cooperatives in every region', 'The capacity-building program completed sessions in all regions during the year (sample achievement).', 'cooperative-development', 1],
    ['achievement', 'Partnership signed to expand financial literacy sessions', 'A partnership expands financial literacy sessions for cooperative members (sample achievement).', 'partnerships', 0],
    ['achievement', 'Help desk resolves its first thousand concerns', 'The help desk reached a milestone in resolving concerns from primary cooperatives (sample achievement).', 'general', 0],
    ['program', 'Cooperative Governance and Management Program', 'Training and coaching for cooperative boards and managers on governance, compliance and planning.', 'cooperative-development', 1],
    ['program', 'Legislative Assistance Desk', 'Assistance to cooperatives on laws, regulations and pending measures that affect their operations.', 'legislative-work', 0],
    ['activity', 'Financial literacy day with fishermen cooperatives', 'Members of fishermen cooperatives joined a day of sessions on savings, credit and record keeping.', 'community-programs', 1],
    ['activity', 'Bookkeeping clinic for small cooperatives', 'Volunteer accountants helped small cooperatives organize their books of accounts.', 'community-programs', 0],
    ['newsletter', 'Cooperative Bulletin, First Quarter', 'The first-quarter bulletin with legislative updates, cooperative stories and upcoming events.', 'general', 0],
    ['newsletter', 'Cooperative Bulletin, Second Quarter', 'The second-quarter bulletin with highlights from the regional consultations.', 'general', 0],
    ['publication', 'Guide to the Help Desk for Primary Cooperatives', 'How to submit a concern, track it and send follow-up information.', 'general', 0],
];
$body = '<p>This is sample content written to demonstrate the publishing workflow. It shows how news, announcements and other updates appear on the public website.</p><h2>Highlights</h2><ul><li>Participants from several primary cooperatives</li><li>Discussion of common concerns and proposals</li><li>Agreed follow-up actions with timelines</li></ul><p>Replace this sample with official, approved content before going live.</p>';
foreach ($posts as $i => [$type, $title, $summary, $catSlug, $feat]) {
    $d = $i < 3 ? ago($i * 3 + 1) : ago(mt_rand(10, 300));
    $attach = $type === 'newsletter' ? ($i === 12 ? $pdf['newsletter-q1'] : $pdf['newsletter-q2']) : ($type === 'publication' ? $pdf['publication-guide'] : null);
    q_insert('posts', ['post_type' => $type, 'category_id' => $cat['post:' . $catSlug] ?? null, 'slug' => slugify($title, 150), 'title' => $title, 'summary' => $summary, 'body' => $body,
        'cover_media_id' => $covers[$i % 10], 'attachment_media_id' => $attach, 'video_url' => $i === 4 ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null, 'author_name' => 'Communications Office', 'is_featured' => $feat,
        'status' => 'published', 'published_at' => $d, 'reviewed_by' => $uids['content_manager'], 'view_count' => mt_rand(20, 900), 'created_by' => $uids['content_manager'], 'created_at' => $d]);
}
q_insert('posts', ['post_type' => 'news', 'slug' => 'draft-update-on-cooperative-week', 'title' => 'Draft: update on cooperative month activities', 'summary' => 'Waiting for review.', 'body' => '<p>Draft content waiting for review.</p>',
    'status' => 'in_review', 'created_by' => $uids['content_manager'], 'created_at' => ago(1)]);
q_insert('posts', ['post_type' => 'announcement', 'slug' => 'scheduled-announcement-holiday-office-hours', 'title' => 'Holiday office hours', 'summary' => 'Office hours during the holidays.', 'body' => '<p>Scheduled announcement.</p>',
    'status' => 'published', 'publish_at' => ahead(5), 'published_at' => ahead(5), 'created_by' => $uids['content_manager'], 'created_at' => ago(1)]);

$events = [
    ['Luzon Regional Consultation on Cooperative Financing', 'consultation', 12, 'Sample Convention Center, Quezon City', 3, 'public', 1, 150],
    ['Visayas Regional Consultation on Cooperative Financing', 'consultation', 26, 'Sample Hall, Cebu City', 11, 'public', 1, 120],
    ['Webinar: Using the Help Desk for Primary Cooperatives', 'webinar', 8, 'Online', null, 'public', 1, 300],
    ['Governance Training for Cooperative Boards', 'training', 40, 'Sample Training Center, Davao City', 15, 'public', 1, 2],
    ['Internal Planning Session of the National Board', 'internal-meeting', 18, 'National Office', null, 'private', 0, null],
    ['Mindanao Cooperative Assembly', 'general-assembly', -45, 'Sample Gymnasium, Cagayan de Oro', 14, 'public', 0, null],
    ['Financial Literacy Day', 'outreach', -90, 'Sample Plaza, Iloilo City', 9, 'public', 0, null],
];
$eventIds = [];
foreach ($events as $i => [$t, $c, $days, $loc, $rid, $vis, $reg, $limit]) {
    $starts = $days >= 0 ? ahead($days, 9) : date('Y-m-d 09:00:00', strtotime("{$days} days"));
    $eventIds[] = q_insert('events', ['slug' => slugify($t), 'title' => $t, 'category_id' => $cat['event:' . $c] ?? null, 'summary' => 'Sample event for demonstration. ' . $t . '.',
        'description' => '<p>Sample event description. The program includes presentations, open forum and planning workshops.</p><h3>Who should attend</h3><p>Officers and staff of primary cooperatives, CNPL point persons and partners.</p>',
        'location_name' => $loc, 'region_id' => $rid, 'starts_at' => $starts, 'ends_at' => date('Y-m-d 16:00:00', strtotime($starts)), 'organizer' => 'COOP NATCCO Partylist (sample)', 'contact_email' => 'events@example.org',
        'cover_media_id' => $covers[($i + 3) % 10], 'visibility' => $vis, 'registration_enabled' => $reg, 'registration_limit' => $limit, 'registration_deadline' => $reg ? date('Y-m-d 17:00:00', strtotime($starts) - 86400) : null,
        'event_status' => $days < 0 ? 'completed' : 'scheduled', 'is_featured' => $i < 2 ? 1 : 0, 'status' => 'published', 'published_at' => ago(20), 'created_by' => $uids['content_manager'], 'created_at' => ago(20)]);
}
foreach ([0 => 24, 1 => 15, 2 => 40, 3 => 2] as $ei => $nreg) {
    for ($j = 0; $j < $nreg; $j++) {
        $f = pick($first);
        $l = pick($last);
        $email = strtolower($f . '.' . str_replace(' ', '', $l) . $ei . $j) . '@example.org';
        q_insert('event_registrations', ['event_id' => $eventIds[$ei], 'reference_code' => 'EV' . random_code(8), 'full_name' => "$f $l", 'email_enc' => encrypt_value($email), 'email_bidx' => blind_index($email),
            'organization' => pick($coopWords) . ' Cooperative', 'registration_status' => 'registered', 'consent_given' => 1, 'created_at' => ago(mt_rand(1, 10))]);
    }
}
q_insert('event_registrations', ['event_id' => $eventIds[3], 'reference_code' => 'EV' . random_code(8), 'full_name' => 'Waiting Registrant', 'email_enc' => encrypt_value('waiting@example.org'), 'email_bidx' => blind_index('waiting@example.org'),
    'registration_status' => 'waitlisted', 'consent_given' => 1, 'created_at' => ago(1)]);
foreach ([5 => 60, 6 => 45] as $ei => $nreg) {
    for ($j = 0; $j < $nreg; $j++) {
        $email = "past{$ei}{$j}@example.org";
        q_insert('event_registrations', ['event_id' => $eventIds[$ei], 'reference_code' => 'EV' . random_code(8), 'full_name' => pick($first) . ' ' . pick($last), 'email_enc' => encrypt_value($email), 'email_bidx' => blind_index($email),
            'registration_status' => mt_rand(1, 10) > 2 ? 'attended' : 'no_show', 'consent_given' => 1, 'created_at' => ago(100)]);
    }
}
q_insert('posts', ['post_type' => 'announcement', 'event_id' => $eventIds[0], 'slug' => 'venue-update-luzon-consultation', 'title' => 'Venue update for the Luzon regional consultation', 'summary' => 'Registration desk opens at 8:00 AM.',
    'body' => '<p>Sample event update. Registration opens at 8:00 AM at the main lobby.</p>', 'status' => 'published', 'published_at' => ago(2), 'created_by' => $uids['content_manager'], 'created_at' => ago(2)]);

$legs = [
    ['house_bill', 'HB 0101 (sample)', 'An act strengthening access of cooperatives to development financing (sample measure)', 'pending_committee', 220, null, 'finance-and-credit'],
    ['house_bill', 'HB 0102 (sample)', 'An act providing tax incentives for agrarian reform cooperatives (sample measure)', 'second_reading', 300, null, 'agriculture-and-fisheries'],
    ['house_resolution', 'HR 0031 (sample)', 'Resolution urging an inquiry into cooperative registration backlogs (sample measure)', 'adopted', 400, 330, 'governance'],
    ['house_bill', 'HB 0103 (sample)', 'An act creating a cooperative insurance support program (sample measure)', 'filed', 60, null, 'social-services'],
    ['senate_bill', 'SB 0201 (sample)', 'An act modernizing the regulation of cooperative banks (sample measure)', 'third_reading', 500, null, 'finance-and-credit'],
    ['republic_act', 'RA 0001 (sample)', 'Sample enacted measure shown for demonstration of the archive', 'enacted', 900, 760, 'cooperative-sector'],
];
foreach ($legs as $i => [$type, $no, $title, $st, $filed, $appr, $catSlug]) {
    q_insert('legislative_updates', ['slug' => slugify($no . ' ' . $title, 150), 'title' => $title, 'measure_type' => $type, 'measure_number' => $no, 'congress' => '20th Congress (sample)', 'legislative_status' => $st,
        'filing_date' => date('Y-m-d', strtotime("-{$filed} days")), 'approval_date' => $appr ? date('Y-m-d', strtotime("-{$appr} days")) : null, 'sponsors' => 'Sample author names (to be verified)',
        'official_summary' => '<p>Sample summary. In a live system, this text is copied from the official record, such as the explanatory note or the official digest.</p>',
        'is_officially_verified' => 0, 'commentary' => $i % 2 === 0 ? '<p>Sample commentary from COOP NATCCO Partylist explaining why the measure matters to cooperatives.</p>' : null,
        'reference_links' => "https://www.congress.gov.ph/\nhttps://legacy.senate.gov.ph/", 'category_id' => $cat['legislative:' . $catSlug] ?? null, 'status' => 'published', 'published_at' => ago($filed > 200 ? 100 : 5),
        'created_by' => $uids['content_manager'], 'created_at' => ago(150)]);
}

$awardRows = [
    ['Outstanding Cooperative Leadership Award (sample)', 'officer', null, $officers[0], 'Sample Cooperative Federation', 'leadership', 200],
    ['Excellence in Cooperative Governance (sample)', 'cooperative', $coops[3]['id'], null, 'Sample Regional Cooperative Council', 'cooperative-excellence', 120],
    ['Community Service Recognition (sample)', 'cooperative', $coops[10]['id'], null, 'Sample Local Government', 'community-service', 420],
    ['Innovation in Member Services (sample)', 'cooperative', $coops[25]['id'], null, 'Sample Development Foundation', 'innovation', 600],
    ['Partner of the Year (sample)', 'organization', null, null, 'Sample Cooperative Network', 'community-service', 700],
];
foreach ($awardRows as $i => [$t, $rt, $cid, $oid, $inst, $cs, $days]) {
    $rname = $rt === 'officer' ? lookup_label('person', q_val('SELECT person_id FROM officers WHERE id = ?', [$oid])) : ($cid ? q_val('SELECT name FROM cooperatives WHERE id = ?', [$cid]) : 'Sample Partner Organization');
    q_insert('awards', ['slug' => slugify($t), 'title' => $t, 'recipient_type' => $rt, 'recipient_name' => $rname, 'officer_id' => $oid, 'cooperative_id' => $cid, 'awarding_institution' => $inst, 'category_id' => $cat['award:' . $cs] ?? null,
        'award_date' => date('Y-m-d', strtotime("-{$days} days")), 'description' => '<p>Sample description of the recognition and why it was given.</p>', 'cover_media_id' => $photos[$i % 8], 'status' => 'published', 'published_at' => ago(30), 'created_by' => $uids['content_manager'], 'created_at' => ago(30)]);
}

$pols = [
    ['Help Desk Service Standards', 'policy', 'policies', 'POL-2026-01 (sample)', 'public', $pdf['policy-helpdesk']],
    ['Records Management Guideline', 'guideline', 'procedures', 'GL-2026-02 (sample)', 'public', $pdf['policy-records']],
    ['Cooperative Information Update Form', 'form', 'forms', 'FORM-01 (sample)', 'public', $pdf['form-update']],
    ['Data Privacy Manual', 'manual', 'manuals', 'MAN-01 (sample)', 'internal', null],
    ['Memorandum on Regional Consultations', 'memorandum', 'circulars-and-memoranda', 'MEMO-2026-07 (sample)', 'public', null],
];
foreach ($pols as $i => [$t, $type, $cs, $ref, $vis, $doc]) {
    $pid = q_insert('policies', ['slug' => slugify($t), 'title' => $t, 'doc_type' => $type, 'category_id' => $cat['document:' . $cs] ?? null, 'reference_no' => $ref, 'summary' => 'Sample summary of the ' . strtolower($t) . '.',
        'visibility' => $vis, 'status' => 'published', 'published_at' => ago(60), 'created_by' => $uids['content_manager'], 'created_at' => ago(90)]);
    if ($i === 1) {
        q_insert('policy_versions', ['policy_id' => $pid, 'version_label' => 'v1', 'effective_date' => date('Y-m-d', strtotime('-300 days')), 'change_notes' => 'First issue', 'body' => '<p>Version 1 sample text.</p>',
            'created_by' => 1, 'created_at' => ago(300), 'superseded_at' => ago(40)]);
    }
    $vid = q_insert('policy_versions', ['policy_id' => $pid, 'version_label' => $i === 1 ? 'v2' : 'v1', 'effective_date' => date('Y-m-d', strtotime('-40 days')), 'change_notes' => $i === 1 ? 'Updated retention periods' : 'First issue',
        'body' => '<p>Sample document text. Official documents can be published as text, as an uploaded file, or both.</p><ol><li>Purpose</li><li>Coverage</li><li>Procedures</li></ol>', 'document_media_id' => $doc, 'created_by' => 1, 'created_at' => ago(40)]);
    q('UPDATE policies SET current_version_id = ?, effective_date = ? WHERE id = ?', [$vid, date('Y-m-d', strtotime('-40 days')), $pid]);
}

foreach ([['2009', '2009-01-01', 'Founding (sample milestone)'], ['2010', '2010-05-10', 'First participation in party-list elections (sample)'], ['2013', '2013-06-30', 'Legislative agenda for cooperatives adopted (sample)'],
    ['2019', '2019-07-01', 'Regional coordination offices organized (sample)'], ['2026', '2026-01-15', 'Launch of the national database system (sample)']] as $i => [$label, $date, $title]) {
    q_insert('history_milestones', ['milestone_date' => $date, 'date_label' => $label, 'title' => $title, 'description' => 'Sample description of this milestone. Publish only details confirmed by approved records.',
        'media_id' => $i % 2 ? $photos[$i] : null, 'source_reference' => 'Sample source reference for demonstration', 'sort_order' => $i, 'status' => 'published', 'published_at' => ago(60), 'created_by' => $uids['content_manager'], 'created_at' => ago(60)]);
}

$g1 = q_insert('galleries', ['slug' => 'regional-consultation-photos', 'title' => 'Regional consultation photos (sample)', 'description' => 'Sample photo gallery.', 'cover_media_id' => $photos[0], 'category_id' => $cat['gallery:events'] ?? null,
    'event_id' => $eventIds[5], 'status' => 'published', 'published_at' => ago(40), 'created_by' => $uids['content_manager'], 'created_at' => ago(40)]);
$g2 = q_insert('galleries', ['slug' => 'archival-photographs', 'title' => 'Archival photographs (sample)', 'description' => 'Sample archival collection for the History page.', 'cover_media_id' => $photos[4], 'category_id' => $cat['gallery:archival'] ?? null,
    'is_archival' => 1, 'status' => 'published', 'published_at' => ago(40), 'created_by' => $uids['content_manager'], 'created_at' => ago(40)]);
foreach ($photos as $i => $m) {
    q_insert('gallery_items', ['gallery_id' => $i < 5 ? $g1 : $g2, 'media_id' => $m, 'caption' => 'Sample photo ' . ($i + 1), 'sort_order' => $i, 'created_by' => 1, 'created_at' => ago(40)]);
}

$faqs = [
    ['How do I submit a concern to the help desk?', 'Open the Help Desk page, fill in the form and submit it. You will see a ticket number and an access key. Save both to follow up on your concern.', 'help-desk'],
    ['I lost my access key. What should I do?', 'Submit a new concern and mention your earlier ticket number in the details. The help desk will link the two tickets.', 'help-desk'],
    ['Who can see a confidential concern?', 'Only help desk officers who are specifically authorized to handle confidential concerns, and the officer assigned to the ticket.', 'help-desk'],
    ['How can our cooperative appear in the cooperative directory?', 'Cooperatives are listed after their registration details are verified and they agree to be listed. Send a request through the help desk under "Registration and Records Update".', 'general'],
    ['How do I register for an event?', 'Open the event page and use the registration form. When an event is full, you can join the waiting list.', 'general'],
    ['Is cooperative membership treated as political support?', 'No. Membership in a cooperative is never treated as political affiliation or support, and no voter profiles are created.', 'privacy-and-data'],
    ['How do I ask for a copy or correction of my personal data?', 'Use the Data Privacy Request form linked from the Privacy Notice. You will receive a reference number.', 'privacy-and-data'],
];
foreach ($faqs as $i => [$qq, $a, $cs]) {
    q_insert('faqs', ['question' => $qq, 'answer' => $a, 'category_id' => $cat['faq:' . $cs] ?? null, 'sort_order' => $i, 'status' => 'published', 'published_at' => ago(30), 'created_by' => $uids['content_manager'], 'created_at' => ago(30)]);
}

echo "Help desk...\n";
require_once APP_ROOT . '/includes/helpdesk.php';
$subjects = [['Request to update our list of officers', 1], ['Question about the membership records', 2], ['Request for financial literacy training', 3], ['Clarification on a new regulation', 4],
    ['Inquiry about financing programs for small cooperatives', 5], ['Assistance with a community project proposal', 6], ['Cannot sign in to the account', 7], ['Request for a copy of the registration certificate', 1],
    ['Concern about delayed processing', 4], ['Invitation for a resource speaker', 3], ['Question on the cooperative directory listing', 1], ['Report of incorrect cooperative address', 1]];
$statuses = ['open', 'assigned', 'in_progress', 'awaiting_response', 'resolved', 'closed'];
foreach ($subjects as $i => [$subj, $catId]) {
    $created = ago(mt_rand(1, 120));
    $st = $statuses[$i % 6];
    $email = 'requester' . $i . '@example.org';
    $c = pick($coops);
    $tid = q_insert('helpdesk_tickets', ['ticket_no' => 'TMP' . $i, 'access_key_hash' => token_hash('DEMOKEY' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)), 'category_id' => $catId, 'subject' => $subj,
        'description' => "Good day. This is a sample concern for testing the help desk.\n\nWe would appreciate guidance on this matter.", 'priority' => pick(['normal', 'normal', 'high', 'low']), 'ticket_status' => $st,
        'is_confidential' => $i === 3 ? 1 : 0, 'requester_name' => pick($first) . ' ' . pick($last), 'requester_email_enc' => encrypt_value($email), 'requester_email_bidx' => blind_index($email),
        'cooperative_id' => $c['id'], 'cooperative_name' => q_val('SELECT name FROM cooperatives WHERE id = ?', [$c['id']]), 'region_id' => $c['region'], 'assigned_to' => $st === 'open' ? null : $uids['helpdesk_officer'],
        'first_response_at' => in_array($st, ['in_progress', 'awaiting_response', 'resolved', 'closed'], true) ? date('Y-m-d H:i:s', strtotime($created) + mt_rand(3600, 86400)) : null,
        'resolved_at' => in_array($st, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s', strtotime($created) + mt_rand(86400, 86400 * 6)) : null,
        'closed_at' => $st === 'closed' ? date('Y-m-d H:i:s', strtotime($created) + 86400 * 7) : null, 'created_at' => $created, 'updated_at' => $created]);
    $no = 'CNPL-HD-' . date('Y', strtotime($created)) . '-' . str_pad((string) $tid, 6, '0', STR_PAD_LEFT);
    q('UPDATE helpdesk_tickets SET ticket_no = ? WHERE id = ?', [$no, $tid]);
    q_insert('ticket_replies', ['ticket_id' => $tid, 'author_type' => 'system', 'body' => 'Ticket submitted.', 'created_at' => $created]);
    if ($st !== 'open') {
        q_insert('ticket_replies', ['ticket_id' => $tid, 'user_id' => $uids['helpdesk_officer'], 'author_type' => 'staff', 'body' => 'Thank you for reaching out. We are reviewing your concern and will update you soon.', 'created_at' => date('Y-m-d H:i:s', strtotime($created) + 7200)]);
        q_insert('ticket_replies', ['ticket_id' => $tid, 'user_id' => $uids['helpdesk_officer'], 'author_type' => 'staff', 'body' => 'Internal note: checked the cooperative record; contact person confirmed.', 'is_internal' => 1, 'created_at' => date('Y-m-d H:i:s', strtotime($created) + 9000)]);
    }
    if (in_array($st, ['awaiting_response', 'resolved', 'closed'], true)) {
        q_insert('ticket_replies', ['ticket_id' => $tid, 'author_type' => 'requester', 'body' => 'Thank you. Here is the additional information you asked for.', 'created_at' => date('Y-m-d H:i:s', strtotime($created) + 90000)]);
    }
}
$sampleTicket = q_one('SELECT ticket_no FROM helpdesk_tickets ORDER BY id LIMIT 1');

q_insert('contact_messages', ['reference_code' => 'CM' . random_code(8), 'full_name' => 'Sample Visitor', 'email_enc' => encrypt_value('visitor@example.org'), 'subject' => 'Partnership inquiry', 'message' => 'Sample message from the contact form.', 'created_at' => ago(2)]);
q_insert('contact_messages', ['reference_code' => 'CM' . random_code(8), 'full_name' => 'Sample Student', 'email_enc' => encrypt_value('student@example.org'), 'subject' => 'Request for an interview', 'message' => 'Sample message asking for an interview for a school project.', 'message_status' => 'read', 'created_at' => ago(6)]);
q_insert('privacy_requests', ['reference_code' => 'PR-2026-' . random_code(6), 'requester_name' => 'Sample Member', 'requester_email_enc' => encrypt_value('member@example.org'), 'request_type' => 'correction',
    'details' => 'Sample request to correct the spelling of a name in the member record.', 'member_id' => q_val('SELECT id FROM members ORDER BY id LIMIT 1'), 'request_status' => 'in_review', 'due_date' => date('Y-m-d', strtotime('+20 days')), 'created_at' => ago(10)]);
q_insert('security_incidents', ['title' => 'Sample: phishing email reported by staff', 'severity' => 'low', 'detected_at' => ago(15), 'description' => 'Sample incident record for demonstration. A staff member reported a suspicious email asking for their password.',
    'affected_systems' => 'Email', 'is_personal_data_breach' => 0, 'actions_taken' => 'Email blocked; staff reminded never to share passwords.', 'incident_status' => 'closed', 'created_by' => 1, 'created_at' => ago(15)]);

// a few authentication and security events for the monitoring screens
foreach (range(1, 6) as $i) {
    q_insert('authentication_events', ['user_id' => null, 'identifier' => 'admin', 'event_type' => 'login_failed', 'ip_address' => '203.0.113.' . (10 + $i), 'user_agent' => 'Sample', 'details' => 'unknown_account', 'created_at' => ago(1, 3)]);
}
security_event('bruteforce_suspected', 'medium', 'Sample alert: repeated failed sign-ins from 203.0.113.0/24');

$counts = [];
foreach (['cooperatives', 'cooperative_personnel', 'members', 'cnpl_point_persons', 'ambassadors_focal_persons', 'officers', 'activities', 'election_results', 'posts', 'events', 'event_registrations', 'helpdesk_tickets', 'users'] as $t) {
    $counts[] = $t . '=' . q_val('SELECT COUNT(*) FROM ' . ident($t));
}
audit_log('sample_data', 'system', null, 'Loaded sample demonstration data');
echo "Done. " . implode(', ', $counts) . "\nSample ticket for tracking: {$sampleTicket['ticket_no']} / requester0@example.org / DEMOKEY000\n";
