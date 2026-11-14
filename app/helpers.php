<?php
function school(): array { static $s; return $s ??= \Mini\Database::fetch('SELECT * FROM settings WHERE id = 1'); }
function school_logo(): string {
    return !empty(school()['logo_path'])
        ? '<img class="school-logo" src="'.e(url(school()['logo_path'])).'" alt="'.e(school()['school_name']).' logo">'
        : icon('school');
}
function school_favicon(): string { return !empty(school()['favicon_path']) ? url(school()['favicon_path']) : asset('favicon.svg'); }
function money(mixed $n): string { return number_format((float)$n, (float)$n == round((float)$n) ? 0 : 2) . ' ' . e(school()['currency']); }
function icon(string $name): string {
    $paths = [
        'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'students'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'book'=>'M4 3h6a3 3 0 0 1 3 3v15a4 4 0 0 0-4-2H3V3z M13 6a3 3 0 0 1 3-3h5v16h-5a4 4 0 0 0-3 2',
        'calendar'=>'M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2 M8 15l3 3 5-5',
        'wallet'=>'M3 5h16v4 M3 5v14h18V9H3 M16 13h5 M3 5l13-3v3',
        'chart'=>'M4 3v18h18 M8 16v-5 M13 16V7 M18 16v-8',
        'settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M10 2h4l1 3 3 1 3 2-2 4 2 4-3 2-3 1-1 3h-4l-1-3-3-1-3-2 2-4-2-4 3-2 3-1z',
        'arrow'=>'M5 12h14 M14 7l5 5-5 5', 'plus'=>'M12 5v14 M5 12h14',
        'search'=>'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
        'download'=>'M12 3v12 M7 10l5 5 5-5 M4 16v5h16v-5',
        'logout'=>'M9 3H3v18h6 M10 12h12 M17 7l5 5-5 5',
        'school'=>'M3 10l9-7 9 7v11H3z M9 21v-7h6v7 M10 8h4',
        'check'=>'M5 12l4 4L19 6', 'clock'=>'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    ];
    return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name] ?? $paths['book']).'"/></svg>';
}
function initials(string $name): string { $p = explode(' ', trim($name)); return mb_strtoupper(mb_substr($p[0],0,1).mb_substr(end($p),0,1)); }
function input(string $label, string $name, string $type = 'text', mixed $value = '', bool $required = true): void {
    echo '<label>'.e($label).'<input type="'.e($type).'" name="'.e($name).'" value="'.e(old($name,$value)).'" '.($required?'required':'').' '.($type==='text'?'maxlength="150"':'').'></label>';
}
function choose(string $label, string $name, array $options, mixed $value = ''): void {
    echo '<label>'.e($label).'<select name="'.e($name).'" '.(in_array($name,['student_id','invoice_id','teacher_id'],true)?'data-searchable ':'').'required>';
    foreach ($options as $key=>$text) echo '<option value="'.e($key).'" '.((string)old($name,$value)===(string)$key?'selected':'').'>'.e($text).'</option>';
    echo '</select></label>';
}
