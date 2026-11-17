<?php
/**
 * Form field rendering shared by the CRUD engine and custom pages.
 */
declare(strict_types=1);

function field_id(string $name): string
{
    return 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
}

function field_options(array $f): array
{
    if (isset($f['options'])) {
        return $f['options'];
    }
    if (isset($f['options_sql'])) {
        static $cache = [];
        $k = $f['options_sql'];
        if (!isset($cache[$k])) {
            $cache[$k] = q_pairs($f['options_sql'], $f['options_params'] ?? []);
        }
        return $cache[$k];
    }
    return [];
}

function regions_list(): array
{
    static $r = null;
    return $r ??= q_pairs('SELECT id, name FROM regions ORDER BY sort_order');
}

function provinces_for(?int $regionId): array
{
    if (!$regionId) {
        return [];
    }
    return q_pairs('SELECT id, name FROM provinces WHERE region_id = ? ORDER BY name', [$regionId]);
}

function cities_for(?int $regionId, ?int $provinceId): array
{
    if ($provinceId) {
        return q_pairs('SELECT id, name FROM cities_municipalities WHERE province_id = ? ORDER BY name', [$provinceId]);
    }
    if ($regionId) {
        return q_pairs("SELECT id, CONCAT(name, IF(province_id IS NULL, ' (independent)', '')) FROM cities_municipalities WHERE region_id = ? ORDER BY name", [$regionId]);
    }
    return [];
}

function lookup_label(string $type, $id): string
{
    $id = (int) $id;
    if (!$id) {
        return '';
    }
    switch ($type) {
        case 'coop':
            $r = q_one('SELECT name, reference_no FROM cooperatives WHERE id = ?', [$id]);
            return $r ? $r['name'] . ' (' . $r['reference_no'] . ')' : '';
        case 'person':
            $r = q_one('SELECT first_name, middle_name, last_name, suffix FROM persons WHERE id = ?', [$id]);
            return $r ? full_name($r) : '';
        case 'user':
            $r = q_one('SELECT full_name, username FROM users WHERE id = ?', [$id]);
            return $r ? $r['full_name'] . ' (' . $r['username'] . ')' : '';
    }
    return '';
}

function select_html(string $name, array $options, $value, array $attrs = [], ?string $placeholder = '— Select —'): string
{
    $a = '';
    foreach ($attrs as $k => $v) {
        if ($v === true) {
            $a .= ' ' . $k;
        } elseif ($v !== false && $v !== null) {
            $a .= ' ' . $k . '="' . e($v) . '"';
        }
    }
    $h = '<select name="' . e($name) . '"' . $a . '>';
    if ($placeholder !== null) {
        $h .= '<option value="">' . e($placeholder) . '</option>';
    }
    foreach ($options as $k => $label) {
        $h .= '<option value="' . e($k) . '"' . ((string) $value === (string) $k && $value !== null && $value !== '' ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h . '</select>';
}

/**
 * Render one form field.
 * $ctx: ['values' => all current values, 'mode' => create|edit]
 */
function form_field(string $name, array $f, $value, array $errors = [], array $ctx = []): string
{
    $type = $f['type'] ?? 'text';
    $id = field_id($name);
    $label = $f['label'] ?? label_from_key($name);
    $req = !empty($f['required']);
    $err = $errors[$name] ?? null;
    $help = $f['help'] ?? '';
    $width = $f['width'] ?? (in_array($type, ['textarea', 'richtext', 'person'], true) ? 'full' : 'half');
    $describedBy = trim(($help ? $id . '_help ' : '') . ($err ? $id . '_err' : ''));
    $common = ['id' => $id, 'aria-describedby' => $describedBy ?: null, 'aria-invalid' => $err ? 'true' : null, 'required' => $req && $type !== 'media' ? true : null];
    $attr = function (array $extra = []) use ($common): string {
        $out = '';
        foreach ($common + $extra as $k => $v) {
            if ($v === true) {
                $out .= ' ' . $k;
            } elseif ($v !== null && $v !== false) {
                $out .= ' ' . $k . '="' . e($v) . '"';
            }
        }
        return $out;
    };
    $values = $ctx['values'] ?? [];
    $input = '';
    switch ($type) {
        case 'textarea':
            $input = '<textarea name="' . e($name) . '" rows="' . (int) ($f['rows'] ?? 4) . '"' . $attr(['maxlength' => $f['max'] ?? null]) . '>' . e($value) . '</textarea>';
            break;
        case 'richtext':
            $input = '<textarea name="' . e($name) . '" rows="10" data-rich="1"' . $attr() . '>' . e($value) . '</textarea>';
            break;
        case 'select':
        case 'lookup_sql':
            $input = select_html($name, field_options($f), $value, ['id' => $id, 'required' => $req, 'aria-describedby' => $describedBy ?: null], $f['placeholder'] ?? '— Select —');
            break;
        case 'bool':
            return '<div class="field field-' . e($width) . ' field-check' . ($err ? ' has-error' : '') . '"><label class="check"><input type="hidden" name="' . e($name) . '" value="0">'
                . '<input type="checkbox" name="' . e($name) . '" value="1" id="' . e($id) . '"' . ((string) $value === '1' ? ' checked' : '') . '> <span>' . e($label) . '</span></label>'
                . ($help ? '<p class="help" id="' . e($id) . '_help">' . e($help) . '</p>' : '') . ($err ? '<p class="err" id="' . e($id) . '_err">' . e($err) . '</p>' : '') . '</div>';
        case 'date':
            $input = '<input type="date" name="' . e($name) . '" value="' . e($value) . '"' . $attr() . '>';
            break;
        case 'datetime':
            $v = $value ? str_replace(' ', 'T', substr((string) $value, 0, 16)) : '';
            $input = '<input type="datetime-local" name="' . e($name) . '" value="' . e($v) . '"' . $attr() . '>';
            break;
        case 'number':
            $input = '<input type="number" inputmode="numeric" name="' . e($name) . '" value="' . e($value) . '"' . $attr(['min' => $f['min'] ?? null, 'max' => $f['max_value'] ?? null, 'step' => $f['step'] ?? '1']) . '>';
            break;
        case 'region':
            $input = select_html($name, regions_list(), $value, ['id' => $id, 'required' => $req, 'data-geo' => 'region', 'data-geo-group' => $f['geo_group'] ?? 'g', 'aria-describedby' => $describedBy ?: null], '— Select region —');
            break;
        case 'province':
            $g = $f['geo_group'] ?? 'g';
            $regionVal = $values[$f['region_field'] ?? 'region_id'] ?? null;
            $input = select_html($name, provinces_for($regionVal ? (int) $regionVal : null), $value, ['id' => $id, 'required' => $req, 'data-geo' => 'province', 'data-geo-group' => $g, 'aria-describedby' => $describedBy ?: null], '— None / independent city —');
            break;
        case 'city':
            $g = $f['geo_group'] ?? 'g';
            $regionVal = $values[$f['region_field'] ?? 'region_id'] ?? null;
            $provVal = $values[$f['province_field'] ?? 'province_id'] ?? null;
            $input = select_html($name, cities_for($regionVal ? (int) $regionVal : null, $provVal ? (int) $provVal : null), $value, ['id' => $id, 'required' => $req, 'data-geo' => 'city', 'data-geo-group' => $g, 'aria-describedby' => $describedBy ?: null], '— Select city or municipality —');
            break;
        case 'coop':
        case 'user':
            $lt = $type;
            $input = '<div class="lookup" data-lookup="' . $lt . '"><input type="hidden" name="' . e($name) . '" value="' . e($value) . '" class="lookup-value">'
                . '<input type="search" class="lookup-input" autocomplete="off" placeholder="Type to search…" value="' . e(lookup_label($lt, $value)) . '"' . $attr(['required' => null, 'id' => $id, 'aria-autocomplete' => 'list', 'role' => 'combobox', 'aria-expanded' => 'false']) . '>'
                . '<ul class="lookup-list" role="listbox" hidden></ul></div>';
            break;
        case 'person':
            $mode = $values['person_mode'] ?? 'existing';
            $input = '<div class="person-field" data-person-field>'
                . '<div class="seg" role="radiogroup" aria-label="Person record">'
                . '<label><input type="radio" name="person_mode" value="existing"' . ($mode !== 'new' ? ' checked' : '') . '> Existing person</label>'
                . '<label><input type="radio" name="person_mode" value="new"' . ($mode === 'new' ? ' checked' : '') . '> New person</label></div>'
                . '<div class="person-existing"' . ($mode === 'new' ? ' hidden' : '') . '><div class="lookup" data-lookup="person"><input type="hidden" name="' . e($name) . '" value="' . e($value) . '" class="lookup-value">'
                . '<input type="search" class="lookup-input" id="' . e($id) . '" autocomplete="off" placeholder="Search people by name…" value="' . e(lookup_label('person', $value)) . '" role="combobox" aria-expanded="false">'
                . '<ul class="lookup-list" role="listbox" hidden></ul></div></div>'
                . '<div class="person-new grid"' . ($mode === 'new' ? '' : ' hidden') . '>';
            foreach (['np_first_name' => 'First name', 'np_middle_name' => 'Middle name', 'np_last_name' => 'Last name', 'np_suffix' => 'Suffix', 'np_email' => 'Email', 'np_phone' => 'Phone'] as $k => $l) {
                $t = $k === 'np_email' ? 'email' : ($k === 'np_phone' ? 'tel' : 'text');
                $input .= '<div class="field field-third' . (isset($errors[$k]) ? ' has-error' : '') . '"><label for="' . $k . '">' . e($l) . (in_array($k, ['np_first_name', 'np_last_name'], true) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>'
                    . '<input type="' . $t . '" id="' . $k . '" name="' . $k . '" value="' . e($values[$k] ?? '') . '" maxlength="' . ($k === 'np_email' ? 191 : 80) . '">'
                    . (isset($errors[$k]) ? '<p class="err">' . e($errors[$k]) . '</p>' : '') . '</div>';
            }
            $input .= '<div class="field field-full"><label class="check"><input type="checkbox" name="np_confirm_different" value="1"' . (!empty($values['np_confirm_different']) ? ' checked' : '') . '> <span>I checked the people directory and this is a different person from any similar name</span></label></div>';
            $input .= '</div></div>';
            break;
        case 'media':
            $cur = $value ? media_row((int) $value) : null;
            $input = '';
            if ($cur) {
                $isImg = strpos($cur['mime_type'], 'image/') === 0;
                $input .= '<div class="media-current">' . ($isImg ? '<img src="' . e(media_url((int) $cur['id'], true)) . '" alt="" loading="lazy">' : '')
                    . '<a href="' . e(media_url((int) $cur['id'])) . '" target="_blank" rel="noopener">' . e($cur['original_name']) . '</a> <span class="muted">' . e(human_size((int) $cur['file_size'])) . '</span>'
                    . '<label class="check"><input type="checkbox" name="' . e($name) . '__remove" value="1"> <span>Remove</span></label></div>';
            }
            $input .= '<input type="file" name="' . e($name) . '__file" id="' . e($id) . '" accept="' . e(media_accept_attr($f['kind'] ?? 'image')) . '"' . ($describedBy ? ' aria-describedby="' . e($describedBy) . '"' : '') . '>';
            $help = $help ?: ('Allowed: ' . strtoupper(implode(', ', array_keys(media_allowed($f['kind'] ?? 'image')))) . '. Maximum ' . setting_int('upload_max_mb', 10) . ' MB.');
            break;
        case 'static':
            $input = '<div class="static">' . ($f['render'] ?? fn($v) => e($v))($value) . '</div>';
            break;
        default:
            $t = in_array($type, ['email', 'tel', 'url', 'password'], true) ? $type : 'text';
            $input = '<input type="' . $t . '" name="' . e($name) . '" value="' . e($type === 'password' ? '' : $value) . '"' . $attr(['maxlength' => $f['max'] ?? 191, 'autocomplete' => $f['autocomplete'] ?? null, 'placeholder' => $f['placeholder'] ?? null, 'inputmode' => $f['inputmode'] ?? null]) . '>';
    }
    return '<div class="field field-' . e($width) . ($err ? ' has-error' : '') . '">'
        . '<label for="' . e($id) . '">' . e($label) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>'
        . $input
        . ($help ? '<p class="help" id="' . e($id) . '_help">' . e($help) . '</p>' : '')
        . ($err ? '<p class="err" id="' . e($id) . '_err">' . e($err) . '</p>' : '')
        . '</div>';
}

/** Error summary at the top of a form (links to fields). */
function form_error_summary(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $h = '<div class="notice notice-error" role="alert" tabindex="-1" data-focus><strong>Please correct the following:</strong><ul>';
    foreach ($errors as $k => $msg) {
        $h .= '<li><a href="#' . e(field_id((string) $k)) . '">' . e($msg) . '</a></li>';
    }
    return $h . '</ul></div>';
}
