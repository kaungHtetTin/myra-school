<?php
// Real HTTP + MySQL tests. Uses only a uniquely named disposable database.
declare(strict_types=1);
$testDb='myra_test_'.bin2hex(random_bytes(5));
putenv('DB_DATABASE='.$testDb);
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
$server=null; $cookie=tempnam(sys_get_temp_dir(),'myra_cookie_'); $log=tempnam(sys_get_temp_dir(),'myra_http_'); $checks=0;
$brandingFiles=[]; $fixtures=[];
function check(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; echo "PASS $label\n"; }
function request(string $path,array|null $data=null,?string $token=null): array {
    global $cookie;
    $ch=curl_init('http://127.0.0.1:8019/'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>10]);
    if ($data!==null) { if ($token!==null) $data['_token']=$token; $multipart=(bool)array_filter($data,fn($v)=>$v instanceof CURLFile); curl_setopt($ch,CURLOPT_POSTFIELDS,$multipart?$data:http_build_query($data)); }
    $body=curl_exec($ch); if ($body===false) throw new RuntimeException(curl_error($ch));
    $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); return [$status,$body];
}
try {
    DB::connection(true)->exec("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach(glob(BASE_PATH.'/migrations/*.php') as $file) { $migration=require $file; foreach($migration['up'] as $sql) DB::connection()->exec($sql); }
    $server=proc_open([PHP_BINARY,'-S','127.0.0.1:8019','-t','public','public/router.php'],[['pipe','r'],['file',$log,'a'],['file',$log,'a']],$pipes,BASE_PATH);
    if (!is_resource($server)) throw new RuntimeException('Cannot start test server');
    for ($i=0;$i<30;$i++) { $sock=@fsockopen('127.0.0.1',8019); if ($sock) { fclose($sock); break; } usleep(100000); }
    [$code,$html]=request(''); check($code===302,'Guest dashboard redirects to sign-in');
    [$code,$html]=request('login'); check($code===200 && str_contains($html,'Create school account'),'First-run setup renders');
    preg_match('/name="_token" value="([^"]+)"/',$html,$matches); $token=$matches[1];
    [$code]=request('setup',['name'=>'Test Administrator','email'=>'admin@example.test','password'=>'Test-school-password-42'],$token);
    check($code===302 && DB::fetchValue('SELECT COUNT(*) FROM users')==1,'Administrator created with MySQL persistence');
    check(password_verify('Test-school-password-42',DB::fetchValue('SELECT password FROM users')),'Password stored as a verified hash');
    [$code,$html]=request(''); check($code===200 && str_contains($html,'Small steps. Bright futures.'),'Dashboard renders after signup');
    preg_match('/name="_token" value="([^"]+)"/',$html,$matches); $token=$matches[1];
    [$code]=request('save/teacher',['name'=>'Daw May','subject'=>'Mathematics','phone'=>'0912345678','email'=>'may@example.test'],'invalid'); check($code===419,'CSRF rejects invalid token');
    request('save/teacher',['name'=>'Daw May','subject'=>'Mathematics','phone'=>'0912345678','email'=>'may@example.test'],$token);
    $teacher=(int)DB::fetchValue('SELECT id FROM teachers LIMIT 1'); check($teacher>0,'Teacher saved');
    request('save/class',['name'=>'Grade 1 · A','capacity'=>1,'teacher_id'=>$teacher],$token);
    $class=(int)DB::fetchValue('SELECT id FROM classes LIMIT 1'); check($class>0,'Class saved with assigned teacher');
    $student=['name'=>'Aye Chan','admission_no'=>'MYR-001','gender'=>'Female','dob'=>'2018-05-16','class_id'=>$class,'guardian'=>'U Chan','phone'=>'0999999999','address'=>'Yangon','status'=>'Active','enrolled_on'=>date('Y-m-d')];
    request('save/student',$student,$token); $sid=(int)DB::fetchValue('SELECT id FROM students LIMIT 1'); check($sid>0,'Student enrolled');
    request('save/student',array_replace($student,['admission_no'=>'MYR-002']),$token); check(DB::fetchValue('SELECT COUNT(*) FROM students')==1,'Class capacity prevents over-enrollment');
    request('students');
    request('save/class',['id'=>$class,'name'=>'Grade 1 · A','capacity'=>30,'teacher_id'=>$teacher],$token);
    request('save/student',array_replace($student,['id'=>$sid,'name'=>'Aye Chan Updated']),$token); check(DB::fetchValue('SELECT name FROM students WHERE id=?',[$sid])==='Aye Chan Updated','Student editing persists');
    request('save/student',$student,$token); check(DB::fetchValue('SELECT COUNT(*) FROM students')==1,'Duplicate admission IDs rejected'); request('students');
    request('save/attendance',['day'=>date('Y-m-d'),'marks'=>[$sid=>'Present']],$token); check(DB::fetchValue('SELECT status FROM attendance')==='Present','Attendance saved');
    request('save/attendance',['day'=>date('Y-m-d'),'marks'=>[$sid=>'Late']],$token); check(DB::fetchValue('SELECT COUNT(*) FROM attendance')==1 && DB::fetchValue('SELECT status FROM attendance')==='Late','Attendance updates without duplicate rows');
    request('save/attendance',['day'=>date('Y-m-d',strtotime('+1 day')),'marks'=>[$sid=>'Absent']],$token); check(DB::fetchValue('SELECT COUNT(*) FROM attendance')==1,'Future attendance rejected'); request('attendance');
    request('save/invoice',['student_id'=>$sid,'description'=>'October tuition','amount'=>'50000.50','due_date'=>date('Y-m-d')],$token);
    $invoice=(int)DB::fetchValue('SELECT id FROM invoices LIMIT 1'); check($invoice>0,'Invoice saved');
    [$code,$html]=request('invoices/'.$invoice.'/print');
    check($code===200 && str_contains($html,'October tuition') && str_contains($html,'Aye Chan Updated') && str_contains($html,'MYR-001') && str_contains($html,'U Chan'),'Print invoice includes invoice and student details');
    check(str_contains($html,'No payments recorded') && str_contains($html,'50,000.50 MMK') && str_contains($html,'Unpaid'),'Unpaid print invoice shows full balance and no payment history');
    [$code]=request('invoices/9999999999/print'); check($code===404,'Missing invoice returns 404');
    [$code]=request('invoices/not-a-number/print'); check($code===404,'Malformed invoice ID returns 404');
    [$code,$printCss]=request('assets/css/invoice.css'); check($code===200 && str_contains($printCss,'size: A4 portrait') && str_contains($printCss,'.print-toolbar { display: none !important; }'),'A4 print stylesheet loads and hides controls');
    $payment=['invoice_id'=>$invoice,'amount'=>'20000.25','paid_on'=>date('Y-m-d'),'method'=>'Cash'];
    request('save/payment',$payment,$token); check(DB::fetchValue('SELECT SUM(amount) FROM payments')=='20000.25','Partial payment saved precisely');
    [$code,$html]=request('invoices/'.$invoice.'/print');
    check($code===200 && str_contains($html,'Partial') && str_contains($html,'20,000.25 MMK') && str_contains($html,'30,000.25 MMK') && str_contains($html,'REC-0001'),'Print invoice shows partial payment, receipt and precise remaining balance');
    request('save/invoice',['student_id'=>$sid,'description'=>'Separate activity fee','amount'=>'1234.50','due_date'=>date('Y-m-d')],$token);
    $otherInvoice=(int)DB::fetchValue('SELECT MAX(id) FROM invoices');
    request('save/payment',['invoice_id'=>$otherInvoice,'amount'=>'1234.50','paid_on'=>date('Y-m-d'),'method'=>'Bank transfer'],$token);
    [$code,$html]=request('invoices/'.$invoice.'/print'); check(!str_contains($html,'Separate activity fee') && !str_contains($html,'Bank transfer'),'Print invoice excludes other invoices and their payments');
    // Remove only the isolated fixture so original suite totals remain unchanged.
    DB::statement('DELETE FROM payments WHERE invoice_id=?',[$otherInvoice]); DB::statement('DELETE FROM invoices WHERE id=?',[$otherInvoice]);
    request('save/payment',array_replace($payment,['amount'=>'30000.26']),$token); check(DB::fetchValue('SELECT COUNT(*) FROM payments')==1,'Overpayment rejected'); request('fees');
    request('save/payment',array_replace($payment,['amount'=>'30000.25']),$token); check(DB::fetchValue('SELECT SUM(amount) FROM payments')=='50000.50','Exact remaining balance accepted');
    [$code,$html]=request('invoices/'.$invoice.'/print'); check(str_contains($html,'>Paid</span>') && str_contains($html,'0 MMK'),'Fully paid print invoice shows Paid and zero balance');
    [$code,$html]=request('fees'); check(substr_count($html,'invoices/'.$invoice.'/print')===3 && str_contains($html,'Print A4'),'Invoice and recent payment rows link to the A4 invoice');
    $result=['student_id'=>$sid,'exam'=>'Midterm','subject'=>'Mathematics','score'=>'82.5','maximum'=>100];
    request('save/result',$result,$token); check(DB::fetchValue('SELECT score FROM results')=='82.50','Exam score saved');
    request('save/result',array_replace($result,['score'=>90]),$token); check(DB::fetchValue('SELECT COUNT(*) FROM results')==1 && DB::fetchValue('SELECT score FROM results')==90,'Result correction updates existing result');
    request('save/result',array_replace($result,['score'=>101]),$token); check(DB::fetchValue('SELECT score FROM results')==90,'Score above maximum rejected'); request('results');
    request('save/settings',['school_name'=>'Myra Test School','school_year'=>'2026–2027','currency'=>'MMK'],$token); check(DB::fetchValue('SELECT school_name FROM settings')==='Myra Test School','School settings saved');
    $png=tempnam(sys_get_temp_dir(),'myra_png_'); $fixtures[]=$png;
    $image=imagecreatetruecolor(120,80); imagefill($image,0,0,imagecolorallocate($image,33,108,82)); imagepng($image,$png); imagedestroy($image);
    $identity=['school_name'=>'Myra Branded School','school_year'=>'2026–2027','currency'=>'MMK'];
    request('save/settings',$identity+['logo'=>new CURLFile($png,'image/png','school.png'),'favicon'=>new CURLFile($png,'image/png','icon.png')],$token);
    $branding=DB::fetch('SELECT * FROM settings'); $brandingFiles[]=$branding['logo_path']; $brandingFiles[]=$branding['favicon_path'];
    check((bool)$branding['logo_path'] && (bool)$branding['favicon_path'],'Logo and favicon uploads persist');
    [$code,$html]=request('invoices/'.$invoice.'/print'); check(str_contains($html,'Myra Branded School') && str_contains($html,$branding['logo_path']),'Print invoice uses current school name and logo');
    check(getimagesize(BASE_PATH.'/public/'.$branding['logo_path'])[2]===IMAGETYPE_PNG,'Logo saved as a decoded and re-encoded PNG');
    $faviconInfo=getimagesize(BASE_PATH.'/public/'.$branding['favicon_path']); check($faviconInfo[0]===64 && $faviconInfo[1]===64,'Favicon converted to a square 64-pixel PNG');
    [$code,$html]=request(''); check(str_contains($html,$branding['logo_path']) && str_contains($html,$branding['favicon_path']) && str_contains($html,'Myra Branded School'),'Dashboard uses the saved school branding');
    [$code]=request($branding['logo_path']); check($code===200,'Uploaded logo is served over HTTP');
    request('save/settings',$identity,$token); check(DB::fetchValue('SELECT logo_path FROM settings')===$branding['logo_path'],'Saving without uploads preserves existing logo');
    $invalid=tempnam(sys_get_temp_dir(),'myra_invalid_'); $fixtures[]=$invalid; file_put_contents($invalid,'<?php echo "not an image";');
    request('save/settings',array_replace($identity,['school_name'=>'Must not save','logo'=>new CURLFile($png,'image/png','valid.png'),'favicon'=>new CURLFile($invalid,'image/png','invalid.png')]),$token);
    check(DB::fetchValue('SELECT school_name FROM settings')==='Myra Branded School' && DB::fetchValue('SELECT logo_path FROM settings')===$branding['logo_path'],'Invalid image prevents partial branding updates'); request('settings');
    request('save/settings',$identity+['logo'=>new CURLFile($png,'image/png','replacement.png')],$token);
    $newLogo=DB::fetchValue('SELECT logo_path FROM settings'); $brandingFiles[]=$newLogo;
    check($newLogo!==$branding['logo_path'] && !is_file(BASE_PATH.'/public/'.$branding['logo_path']),'Logo replacement uses a fresh URL and removes the old image');
    request('save/settings',$identity+['remove_logo'=>1,'remove_favicon'=>1],$token);
    check(DB::fetchValue('SELECT logo_path FROM settings')===null && DB::fetchValue('SELECT favicon_path FROM settings')===null && !is_file(BASE_PATH.'/public/'.$newLogo) && !is_file(BASE_PATH.'/public/'.$branding['favicon_path']),'Removing branding restores defaults and cleans up image files');
    foreach(['','students','teachers','classes','attendance','fees','results','reports','settings','students?edit='.$sid,'students?q=no-match'] as $page) { [$code,$html]=request($page); check($code===200 && !str_contains($html,'Application error') && !str_contains($html,'Warning:'),'Page renders: '.($page?:'overview')); }
    foreach(['students','attendance','fees','results'] as $entity) { [$code,$csv]=request('export/'.$entity); check($code===200 && str_contains($csv,'Aye Chan Updated'),'CSV export: '.$entity); }
    request('archive/'.$sid,[],$token); check(DB::fetchValue('SELECT status FROM students')==='Inactive' && DB::fetchValue('SELECT COUNT(*) FROM attendance')==1,'Archiving preserves historical records');
    request('logout',[],$token); [$code]=request('students'); check($code===302,'Logout protects school records');
    [$code]=request('invoices/'.$invoice.'/print'); check($code===302,'Printing requires administrator authentication');
    [, $html]=request('login'); preg_match('/name="_token" value="([^"]+)"/',$html,$matches); $token=$matches[1];
    check(str_contains($html,'Myra Branded School') && str_contains($html,'assets/favicon.svg'),'Sign-in uses school name and default favicon');
    request('login',['email'=>'admin@example.test','password'=>'wrong'],$token); [$code]=request(''); check($code===302,'Invalid credentials rejected');
    request('login'); request('login',['email'=>'admin@example.test','password'=>'Test-school-password-42'],$token); [$code]=request(''); check($code===200,'Administrator can sign back in');
    request('setup',['name'=>'Intruder','email'=>'new@example.test','password'=>'another-password'],$token); check(DB::fetchValue('SELECT COUNT(*) FROM users')==1,'Setup closes after first administrator');
    request('password',['current_password'=>'incorrect','password'=>'New-test-password-42','password_confirmation'=>'New-test-password-42'],$token); check(password_verify('Test-school-password-42',DB::fetchValue('SELECT password FROM users')),'Password change requires current password');
    request('password',['current_password'=>'Test-school-password-42','password'=>'New-test-password-42','password_confirmation'=>'New-test-password-42'],$token); check(password_verify('New-test-password-42',DB::fetchValue('SELECT password FROM users')),'Administrator password change persists');
    echo "\n$checks integration checks passed.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach($brandingFiles as $path) if (is_string($path) && preg_match('#^uploads/branding/(logo|favicon)-[a-f0-9]{32}\.png$#D',$path)) @unlink(BASE_PATH.'/public/'.$path);
    foreach($fixtures as $file) @unlink($file);
    // This database was created by this test with a random name, never the school DB.
    if (preg_match('/^myra_test_[a-f0-9]{10}$/',$testDb)) DB::connection(true)->exec("DROP DATABASE IF EXISTS `$testDb`");
    @unlink($cookie); @unlink($log);
}
