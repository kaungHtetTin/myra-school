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
    $headers=[];
    $ch=curl_init('http://127.0.0.1:8019/'.$path);
    curl_setopt($ch,CURLOPT_HEADERFUNCTION,function($handle,$line) use (&$headers) { if (str_contains($line,':')) { [$key,$value]=explode(':',$line,2); $headers[strtolower(trim($key))]=trim($value); } return strlen($line); });
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>10]);
    if ($data!==null) { if ($token!==null) $data['_token']=$token; $multipart=(bool)array_filter($data,fn($v)=>$v instanceof CURLFile); curl_setopt($ch,CURLOPT_POSTFIELDS,$multipart?$data:http_build_query($data)); }
    $body=curl_exec($ch); if ($body===false) throw new RuntimeException(curl_error($ch));
    $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); return [$status,$body,$headers];
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
    function originData(string $html): array {
        if (!preg_match('/<script type="application\/json" id="student-origin-data">(.*?)<\/script>/s',$html,$match)) throw new RuntimeException('Missing student origin chart');
        return json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
    }
    $emptyOrigins=originData($html);
    check($emptyOrigins['total']===0 && count($emptyOrigins['items'])===5 && array_sum(array_column($emptyOrigins['items'],'count'))===0,'Empty dashboard origin chart has five zero categories');
    preg_match('/name="_token" value="([^"]+)"/',$html,$matches); $token=$matches[1];
    foreach(['students','fees'] as $entity) {
        [$code,$csv,$headers]=request('export/'.$entity);
        check($code===200 && str_starts_with($csv,"\xEF\xBB\xBF") && !str_contains($csv,'Deprecated:') && substr_count($csv,"\r\n")===1,'Empty CSV export still contains clean column headers: '.$entity);
    }
    [$code]=request('save/teacher',['name'=>'Daw May','subject'=>'Mathematics','phone'=>'0912345678','email'=>'may@example.test'],'invalid'); check($code===419,'CSRF rejects invalid token');
    request('save/teacher',['name'=>'Daw May','subject'=>'Mathematics','phone'=>'0912345678','email'=>'may@example.test'],$token);
    $teacher=(int)DB::fetchValue('SELECT id FROM teachers LIMIT 1'); check($teacher>0,'Teacher saved');
    request('save/class',['name'=>'Grade 1 · A','capacity'=>1,'teacher_id'=>$teacher],$token);
    $class=(int)DB::fetchValue('SELECT id FROM classes LIMIT 1'); check($class>0,'Class saved with assigned teacher');
    request('learning/batch',['class_id'=>$class,'name'=>'October tuition','capacity'=>1,'starts_on'=>date('Y-m-d'),'status'=>'Open'],$token);
    $batch=(int)DB::fetchValue('SELECT id FROM batches LIMIT 1'); check($batch>0,'Batch created under class');
    $student=['name'=>'Aye Chan','phone'=>'0999999999','address'=>'Yangon','origin'=>'Facebook','account'=>'https://facebook.com/student','status'=>'Active','notes'=>"Prefers evening classes.\nContact after 5pm."];
    request('save/student',$student,$token); $sid=(int)DB::fetchValue('SELECT id FROM students LIMIT 1'); check($sid>0,'Student created without a class or visible student ID');
    check(DB::fetchValue('SELECT origin FROM students WHERE id=?',[$sid])==='Facebook' && str_starts_with(DB::fetchValue('SELECT admission_no FROM students WHERE id=?',[$sid]),'ST-'),'Origin saved and internal ID generated');
    check(DB::fetchValue('SELECT notes FROM students WHERE id=?',[$sid])===$student['notes'],'Multiline student notes saved on creation');
    [$code,$html]=request(''); $singleOrigin=originData($html);
    check($code===200 && $singleOrigin['total']===1 && $singleOrigin['items'][0]['count']===1 && $singleOrigin['items'][0]['percentage']===100 && str_contains($html,'Student origins pie chart'),'Single-origin dashboard displays a valid full pie and 100 percent share');
    $enrollment=['batch_id'=>$batch,'student_id'=>$sid,'enrolled_on'=>date('Y-m-d'),'amount'=>'50000.50','method'=>'Cash'];
    request('learning/enrollment',$enrollment,$token); check(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==1,'Student enrolled and receipt created');
    check(DB::fetchValue('SELECT SUM(amount) FROM payments')==='50000.50','Enrollment automatically records the full payment');
    check(DB::fetchValue('SELECT COUNT(*) FROM finance_entries')==0,'Automatic income uses payments without duplicate finance entries');
    $enrollmentInvoice=(int)DB::fetchValue('SELECT id FROM invoices LIMIT 1');
    [$code,$html]=request('invoices/'.$enrollmentInvoice.'/print'); check($code===200 && str_contains($html,'>Paid</span>') && str_contains($html,'0 MMK'),'Enrollment receipt is fully paid immediately');
    request('save/student',array_replace($student,['name'=>'Second Student','phone'=>'0988888888']),$token);
    $second=(int)DB::fetchValue('SELECT MAX(id) FROM students');
    request('learning/enrollment',array_replace($enrollment,['student_id'=>$second]),$token); check(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==1,'Batch capacity prevents over-enrollment');
    request('learning/enrollment',$enrollment,$token); check(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==1 && DB::fetchValue('SELECT COUNT(*) FROM invoices')==1 && DB::fetchValue('SELECT COUNT(*) FROM payments')==1,'Duplicate enrollment cannot duplicate fee invoice or income');
    request('save/student',array_replace($student,['id'=>$sid,'name'=>'Aye Chan Updated']),$token); check(DB::fetchValue('SELECT name FROM students WHERE id=?',[$sid])==='Aye Chan Updated','Student editing persists');
    $updatedNotes="Needs a follow-up.\n<script>alert('note')</script>";
    request('save/student',array_replace($student,['id'=>$sid,'name'=>'Aye Chan Updated','notes'=>$updatedNotes]),$token);
    check(DB::fetchValue('SELECT notes FROM students WHERE id=?',[$sid])===$updatedNotes,'Student notes can be edited');
    [$code,$html]=request('students/'.$sid);
    check($code===200 && str_contains($html,e($updatedNotes)) && !str_contains($html,"<script>alert('note')</script>"),'Student detail displays escaped multiline notes');
    [$code,$html]=request('students?edit='.$sid);
    check($code===200 && str_contains($html,'name="notes"') && str_contains($html,e($updatedNotes)),'Student edit form loads existing notes');
    request('save/student',array_replace($student,['id'=>$sid,'name'=>'Aye Chan Updated','notes'=>str_repeat('x',5001)]),$token);
    check(DB::fetchValue('SELECT notes FROM students WHERE id=?',[$sid])===$updatedNotes,'Oversized notes are rejected without changing saved data');
    request('students');
    request('save/student',array_replace($student,['id'=>$sid,'name'=>'Aye Chan Updated','notes'=>'']),$token);
    check(DB::fetchValue('SELECT notes FROM students WHERE id=?',[$sid])===null,'Optional notes can be cleared');
    request('students');
    DB::insert('attendance',['student_id'=>$sid,'day'=>date('Y-m-d'),'status'=>'Present']);
    [$code]=request('attendance'); check($code===404,'Removed attendance page returns 404');
    [$code]=request('save/attendance',['day'=>date('Y-m-d'),'marks'=>[$sid=>'Absent']],$token);
    check($code===404 && DB::fetchValue('SELECT status FROM attendance')==='Present','Removed recording endpoint cannot change historical attendance');
    [$code]=request('export/attendance'); check($code===404,'Removed attendance export returns 404');
    $invoice=DB::insert('invoices',['student_id'=>$sid,'description'=>'October tuition (legacy)','amount'=>'50000.50','due_date'=>date('Y-m-d')]); check($invoice>0,'Legacy invoice fixture created');
    [$code,$html]=request('invoices/'.$invoice.'/print');
    check($code===200 && str_contains($html,'October tuition') && str_contains($html,'Aye Chan Updated') && !str_contains($html,'Student ID'),'Print invoice includes enrollment and student details with hidden student ID');
    check(str_contains($html,'No payments recorded') && str_contains($html,'50,000.50 MMK') && str_contains($html,'Unpaid'),'Unpaid print invoice shows full balance and no payment history');
    [$code]=request('invoices/9999999999/print'); check($code===404,'Missing invoice returns 404');
    [$code]=request('invoices/not-a-number/print'); check($code===404,'Malformed invoice ID returns 404');
    [$code,$printCss]=request('assets/css/invoice.css'); check($code===200 && str_contains($printCss,'size: A4 portrait') && str_contains($printCss,'.print-toolbar { display: none !important; }'),'A4 print stylesheet loads and hides controls');
    $payment=['invoice_id'=>$invoice,'amount'=>'20000.25','paid_on'=>date('Y-m-d'),'method'=>'Cash'];
    DB::insert('payments',$payment); check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$invoice])=='20000.25','Historical partial payment remains readable');
    [$code,$html]=request('invoices/'.$invoice.'/print');
    check($code===200 && str_contains($html,'Partial') && str_contains($html,'20,000.25 MMK') && str_contains($html,'30,000.25 MMK') && str_contains($html,'REC-'),'Print invoice shows partial payment, receipt and precise remaining balance');
    DB::insert('invoices',['student_id'=>$sid,'description'=>'Separate activity fee','amount'=>'1234.50','due_date'=>date('Y-m-d')]);
    $otherInvoice=(int)DB::fetchValue('SELECT MAX(id) FROM invoices');
    DB::insert('payments',['invoice_id'=>$otherInvoice,'amount'=>'1234.50','paid_on'=>date('Y-m-d'),'method'=>'Bank transfer']);
    [$code,$html]=request('invoices/'.$invoice.'/print'); check(!str_contains($html,'Separate activity fee') && !str_contains($html,'Bank transfer'),'Print invoice excludes other invoices and their payments');
    // Remove only the isolated fixture so original suite totals remain unchanged.
    DB::statement('DELETE FROM payments WHERE invoice_id=?',[$otherInvoice]); DB::statement('DELETE FROM invoices WHERE id=?',[$otherInvoice]);
    [$code]=request('save/payment',array_replace($payment,['amount'=>'30000.26']),$token); check($code===404,'Legacy standalone payment endpoint remains disabled');
    [$code]=request('save/invoice',['student_id'=>$sid,'description'=>'Unwanted fee','amount'=>'1','due_date'=>date('Y-m-d')],$token); check($code===404,'Separate invoice creation is disabled');
    DB::insert('payments',array_replace($payment,['amount'=>'30000.25']));
    [$code,$html]=request('invoices/'.$invoice.'/print'); check(str_contains($html,'>Paid</span>') && str_contains($html,'0 MMK'),'Fully paid print invoice shows Paid and zero balance');
    [$code,$html]=request('fees'); check(substr_count($html,'invoices/'.$invoice.'/print')===1 && substr_count($html,'<table>')===1 && !str_contains($html,'Recent payments') && str_contains($html,'Print A4'),'Enrollment receipts uses one table with one print link per record');
    $historicalResult=['student_id'=>$sid,'exam'=>'Midterm','subject'=>'Mathematics','score'=>'82.5','maximum'=>100];
    DB::insert('results',$historicalResult);
    [$code]=request('results'); check($code===404,'Removed exam results page returns 404');
    [$code]=request('save/result',array_replace($historicalResult,['score'=>90]),$token);
    check($code===404 && DB::fetchValue('SELECT score FROM results')==='82.50' && DB::fetchValue('SELECT COUNT(*) FROM results')==1,'Removed result endpoint cannot create or change historical results');
    [$code]=request('export/results'); check($code===404,'Removed exam results export returns 404');
    // Multi-class enrollment, fee snapshots, batch actions, and finance reconciliation.
    request('save/class',['name'=>'English','capacity'=>20,'fee'=>'75000.25'],$token);
    $english=(int)DB::fetchValue('SELECT MAX(id) FROM classes');
    check(DB::fetchValue('SELECT fee FROM classes WHERE id=?',[$english])==='75000.25','Class fee saved precisely');
    request('learning/batch',['class_id'=>$english,'name'=>'Evening','capacity'=>2,'starts_on'=>date('Y-m-d'),'status'=>'Ongoing'],$token);
    $evening=(int)DB::fetchValue('SELECT MAX(id) FROM batches');
    request('learning/enrollment',array_replace($enrollment,['batch_id'=>$evening,'amount'=>'75000.25']),$token);
    $englishInvoice=(int)DB::fetchValue('SELECT MAX(id) FROM invoices');
    check(DB::fetchValue('SELECT COUNT(*) FROM enrollments WHERE student_id=?',[$sid])==2,'One student can enroll in multiple classes');
    request('save/class',['id'=>$english,'name'=>'English','capacity'=>20,'fee'=>'90000'],$token);
    check(DB::fetchValue('SELECT amount FROM invoices WHERE id=?',[$englishInvoice])==='75000.25','Changing class fee preserves previous enrollment fees');

    check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$englishInvoice])==='75000.25','Second class enrollment also records full payment automatically');
    $paymentCount=DB::fetchValue('SELECT COUNT(*) FROM payments');
    request('save/payment',['batch_id'=>$batch,'invoice_id'=>$englishInvoice,'amount'=>'1','paid_on'=>date('Y-m-d'),'method'=>'Cash'],$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM payments')==$paymentCount,'Legacy payment endpoint cannot add extra income'); request('batches/'.$batch);
    [$code,$html]=request('students/'.$sid); check($code===200 && str_contains($html,'October tuition') && str_contains($html,'Evening') && str_contains($html,'75,000.25 MMK'),'Student detail shows all classes and their paid amounts');
    [$code,$html]=request('students?class='.$english); check($code===200 && str_contains($html,'Aye Chan Updated') && !str_contains($html,'Second Student'),'Student class filter uses multi-class enrollments');
    [$code,$html]=request('finance'); check($code===200 && str_contains($html,'175,001.25 MMK') && substr_count($html,'PAY-')==4,'All recorded fee payments counted exactly once as income');
    $manual=['type'=>'Expense','description'=>'Rent','category'=>'Operations','amount'=>'20000.25','occurred_on'=>date('Y-m-d')];
    request('learning/finance',$manual,$token);
    request('learning/finance',array_replace($manual,['type'=>'Income','description'=>'Book sales','category'=>'Books','amount'=>'5000.50']),$token);
    [$code,$html]=request('finance'); check($code===200 && str_contains($html,'180,001.75 MMK') && str_contains($html,'20,000.25 MMK') && str_contains($html,'160,001.50 MMK'),'Finance calculates collected income, expenses, and profit precisely');
    [$code,$html]=request('finance?from=2000-01-01&to=2000-01-31'); check($code===200 && str_contains($html,'No finance entries for these dates.') && str_contains($html,'0 MMK'),'Finance date filter applies to manual and student income');
    request('learning/finance',array_replace($manual,['amount'=>'-1']),$token); check(DB::fetchValue('SELECT COUNT(*) FROM finance_entries')==2,'Negative finance amounts rejected');
    request('learning/finance',array_replace($manual,['occurred_on'=>date('Y-m-d',strtotime('+1 day'))]),$token); check(DB::fetchValue('SELECT COUNT(*) FROM finance_entries')==2,'Future finance entries rejected');
    $eid=(int)DB::fetchValue('SELECT id FROM enrollments WHERE batch_id=? AND student_id=?',[$evening,$sid]);
    request('learning/enrollment-status',['id'=>$eid,'batch_id'=>$evening,'status'=>'Completed'],$token);
    check(DB::fetchValue('SELECT status FROM enrollments WHERE id=?',[$eid])==='Completed' && DB::fetchValue('SELECT amount FROM invoices WHERE id=?',[$englishInvoice])==='75000.25','Completing enrollment preserves fees and payment history');
    request('learning/batch',['id'=>$evening,'class_id'=>$english,'name'=>'Evening','capacity'=>2,'starts_on'=>date('Y-m-d'),'status'=>'Completed'],$token);
    request('learning/enrollment',array_replace($enrollment,['batch_id'=>$evening,'student_id'=>$second,'amount'=>'0']),$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM enrollments WHERE batch_id=?',[$evening])==1,'Completed batch rejects new enrollment');
    request('learning/enrollment-status',['id'=>$eid,'batch_id'=>$evening,'status'=>'Active'],$token);
    check(DB::fetchValue('SELECT status FROM enrollments WHERE id=?',[$eid])==='Completed','Completed batch rejects reactivation');
    request('learning/batch',['class_id'=>$english,'name'=>'Free workshop','capacity'=>10,'starts_on'=>date('Y-m-d'),'status'=>'Open'],$token);
    $freeBatch=(int)DB::fetchValue('SELECT MAX(id) FROM batches');
    request('learning/enrollment',array_replace($enrollment,['batch_id'=>$freeBatch,'student_id'=>$second,'amount'=>'75000','is_foc'=>'1']),$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM invoices WHERE amount=0')==1,'FOC overrides posted fee and creates a zero-value receipt');
    $focEnrollment=DB::fetch('SELECT * FROM enrollments WHERE batch_id=? AND student_id=?',[$freeBatch,$second]);
    check((int)$focEnrollment['is_foc']===1 && DB::fetchValue('SELECT COUNT(*) FROM payments p JOIN invoices i ON i.id=p.invoice_id WHERE i.enrollment_id=?',[$focEnrollment['id']])==0,'FOC enrollment records no payment');
    [$code,$html]=request('finance'); check($code===200 && substr_count($html,'PAY-')==4 && str_contains($html,'180,001.75 MMK'),'FOC adds no finance income');
    [$code,$html]=request('batches/'.$freeBatch); check($code===200 && str_contains($html,'FOC') && !str_contains($html,'Record payment'),'Batch shows FOC without an inline payment form');
    [$code,$html]=request('students/'.$second); check($code===200 && str_contains($html,'FOC'),'Student history clearly identifies FOC');
    $freeInvoice=(int)DB::fetchValue('SELECT id FROM invoices WHERE enrollment_id=?',[$focEnrollment['id']]);
    [$code,$html]=request('invoices/'.$freeInvoice.'/print'); check($code===200 && str_contains($html,'>FOC</span>'),'Printed receipt identifies FOC');
    [$code,$html]=request('fees'); check($code===200 && !str_contains($html,'Record payment') && !str_contains($html,'Add invoice'),'Receipts link to payments without inline invoice/payment forms');
    $before=DB::fetchValue('SELECT COUNT(*) FROM enrollments');
    request('learning/enrollment',array_replace($enrollment,['batch_id'=>$freeBatch,'enrolled_on'=>date('Y-m-d',strtotime('+1 day'))]),$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==$before,'Future paid enrollment is rejected without income');
    request('learning/enrollment',array_replace($enrollment,['batch_id'=>$freeBatch,'amount'=>'-1']),$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==$before,'Invalid paid amount creates no enrollment or income');
    request('save/student',array_replace($student,['name'=>'FOC Student','phone'=>'0977777777']),$token);
    $third=(int)DB::fetchValue('SELECT MAX(id) FROM students');
    request('learning/enrollment',['batch_id'=>$freeBatch,'student_id'=>$third,'enrolled_on'=>date('Y-m-d'),'is_foc'=>'1'],$token);
    check(DB::fetchValue('SELECT is_foc FROM enrollments WHERE batch_id=? AND student_id=?',[$freeBatch,$third])==1 && DB::fetchValue('SELECT COUNT(*) FROM payments')==4,'FOC enrollment needs no amount or payment method');
    request('learning/enrollment',['batch_id'=>$freeBatch,'student_id'=>$third,'enrolled_on'=>date('Y-m-d'),'is_foc'=>'1'],$token);
    check(DB::fetchValue('SELECT COUNT(*) FROM enrollments WHERE batch_id=? AND student_id=?',[$freeBatch,$third])==1 && DB::fetchValue('SELECT COUNT(*) FROM payments')==4,'Duplicate FOC submission creates no extra enrollment or income');
    request('learning/enrollment',['batch_id'=>$freeBatch,'student_id'=>$sid,'enrolled_on'=>date('Y-m-d'),'method'=>'Bank transfer'],$token);
    $defaultFeeInvoice=DB::fetch('SELECT i.* FROM invoices i JOIN enrollments e ON e.id=i.enrollment_id WHERE e.batch_id=? AND e.student_id=?',[$freeBatch,$sid]);
    check($defaultFeeInvoice && $defaultFeeInvoice['amount']==='90000.00' && DB::fetchValue('SELECT amount FROM payments WHERE invoice_id=?',[$defaultFeeInvoice['id']])==='90000.00','Omitted amount uses the current class fee and records payment automatically');
    foreach(['batches/'.$batch,'students/'.$sid] as $page) {
        [$code,$html]=request($page);
        check($code===200 && str_contains($html,str_starts_with($page,'batches/')?'Batch students &amp; receipts':'Enrollment receipts') && !str_contains($html,'<h2>Payment history</h2>'),'Enrollment receipts replace separate payment history on '.$page);
    }
    [$code,$html]=request('batches/'.$freeBatch);
    check($code===200 && substr_count($html,'<table>')===1 && str_contains($html,'Enrollment status') && str_contains($html,'Print A4') && str_contains($html,'FOC') && str_contains($html,'Paid'),'Batch merges students, enrollment controls, FOC, and receipts into one table');
    check(str_contains($html,'<span>Enrolled students</span><strong>3</strong>') && str_contains($html,'<span>FOC students</span><strong>2</strong>') && str_contains($html,'<span>Income received</span><strong>90,000 MMK</strong>'),'Batch cards show student counts and actual income with FOC excluded');
    $originalEnrollment=(int)DB::fetchValue('SELECT id FROM enrollments WHERE batch_id=? AND student_id=?',[$batch,$sid]);
    $extraReceipt=DB::insert('invoices',['student_id'=>$sid,'enrollment_id'=>$originalEnrollment,'description'=>'Historical extra fee','amount'=>'1234.50','due_date'=>date('Y-m-d')]);
    [$code,$html]=request('batches/'.$batch);
    check($code===200 && substr_count($html,'<table>')===1 && substr_count($html,'invoices/'.$enrollmentInvoice.'/print')===1 && substr_count($html,'invoices/'.$extraReceipt.'/print')===1 && str_contains($html,'51,235 MMK') && str_contains($html,'1,234.50 MMK'),'Merged row aggregates multiple historical invoices and retains every print link');
    function receiptRows(array $query): array {
        [$code,$csv]=request('export/fees?'.http_build_query($query));
        check($code===200 && str_starts_with($csv,"\xEF\xBB\xBF"),'Filtered receipt CSV downloads: '.http_build_query($query));
        $stream=fopen('php://temp','w+'); fwrite($stream,substr($csv,3)); rewind($stream);
        $columns=fgetcsv($stream,0,',','"',''); $rows=[];
        while(($row=fgetcsv($stream,0,',','"',''))!==false) $rows[]=array_combine($columns,$row);
        fclose($stream); return $rows;
    }
    [$code,$html]=request('fees?batch='.$freeBatch);
    check($code===200 && str_contains($html,'<span>Receipts</span><strong>3</strong>') && str_contains($html,'<span>FOC enrollments</span><strong>2</strong>') && str_contains($html,'<span>Income received</span><strong>90,000 MMK</strong>'),'Receipt stats follow batch filters and exclude FOC from income');
    check(str_contains($html,'export/fees?batch='.$freeBatch) && substr_count($html,'>Print A4</a>')===3,'Receipt export link preserves applied filters and displayed rows');
    $filteredReceipts=receiptRows(['batch'=>$freeBatch]);
    check(count($filteredReceipts)===3 && array_sum(array_column($filteredReceipts,'is_foc'))==2 && array_sum(array_column($filteredReceipts,'paid'))==90000,'Filtered export matches the receipt cards');
    $focReceipts=receiptRows(['status'=>'FOC']);
    check(count($focReceipts)===2 && array_sum(array_column($focReceipts,'paid'))==0,'FOC filter exports only complimentary enrollments');
    $paidReceipts=receiptRows(['status'=>'Paid']); check(count($paidReceipts)===4 && !array_sum(array_column($paidReceipts,'is_foc')),'Paid filter excludes FOC and unpaid receipts');
    $classReceipts=receiptRows(['class'=>$english]); check(count($classReceipts)===4,'Class filter uses each invoice enrollment rather than a student primary class');
    $phoneReceipts=receiptRows(['q'=>'0988888888']); check(count($phoneReceipts)===1 && $phoneReceipts[0]['name']==='Second Student','Receipt search matches phone numbers');
    $numberReceipts=receiptRows(['q'=>'INV-'.str_pad((string)$englishInvoice,4,'0',STR_PAD_LEFT)]); check(count($numberReceipts)===1 && (int)$numberReceipts[0]['invoice']===$englishInvoice,'Receipt search matches formatted invoice numbers');
    check(count(receiptRows(['q'=>'%']))===0,'Receipt search treats SQL wildcard characters literally');
    check(count(receiptRows(['class'=>$class,'status'=>'FOC']))===0,'Combined receipt filters safely return no matches');
    DB::update('invoices',$enrollmentInvoice,['due_date'=>date('Y-m-d',strtotime('+30 days'))]);
    $todayReceipts=receiptRows(['batch'=>$batch,'from'=>date('Y-m-d'),'to'=>date('Y-m-d')]);
    check(count($todayReceipts)===2,'Receipt date range includes enrollment dates even when invoice due date differs');
    [$code,$html]=request('fees?from=2026-12-31&to=2026-01-01');
    check($code===200 && str_contains($html,'From date must be on or before To date.') && str_contains($html,'disabled>Export CSV'),'Inverted dates show a clear error and disable export');
    [$code,$body,$headers]=request('export/fees?from=2026-12-31&to=2026-01-01');
    check($code===422 && !isset($headers['content-disposition']),'Invalid date range cannot accidentally export unfiltered records');
    [$code]=request('export/fees?from=2026-02-30'); check($code===422,'Invalid calendar date is rejected for receipt exports');
    $overdueReceipt=DB::insert('invoices',['student_id'=>$third,'description'=>'Historical overdue','amount'=>'100','due_date'=>date('Y-m-d',strtotime('-1 day'))]);
    $partialReceipt=DB::insert('invoices',['student_id'=>$third,'description'=>'Historical partial','amount'=>'200','due_date'=>date('Y-m-d')]);
    DB::insert('payments',['invoice_id'=>$partialReceipt,'amount'=>'50','paid_on'=>date('Y-m-d'),'method'=>'Cash']);
    $overdueRows=receiptRows(['status'=>'Overdue']); check(count($overdueRows)===1 && (int)$overdueRows[0]['invoice']===$overdueReceipt,'Overdue receipt filter keeps historical balances visible');
    $partialRows=receiptRows(['status'=>'Partial']); check(count($partialRows)===1 && (int)$partialRows[0]['invoice']===$partialReceipt && $partialRows[0]['balance']==='150.00','Partial filter uses actual recorded payments and balances');
    foreach(['finance','classes/'.$class.'/batches','batches/'.$batch,'batches/'.$evening,'students/'.$sid] as $newPage) {
        [$code,$html]=request($newPage); check($code===200 && !str_contains($html,'Application error') && !str_contains($html,'Warning:') && !str_contains($html,'Student ID'),'New page renders with hidden student ID: '.$newPage);
    }
    foreach(['students/99999999','classes/99999999/batches','batches/99999999','students/1bad','batches/not-a-number'] as $missing) { [$code]=request($missing); check($code===404,'Missing record returns 404: '.$missing); }
    request('save/settings',['school_name'=>'Myra Test School','school_year'=>'2026–2027','currency'=>'MMK'],$token); check(DB::fetchValue('SELECT school_name FROM settings')==='Myra Test School','School settings saved');
    $png=tempnam(sys_get_temp_dir(),'myra_png_'); $fixtures[]=$png;
    $image=imagecreatetruecolor(120,80); imagefill($image,0,0,imagecolorallocate($image,33,108,82)); imagepng($image,$png);
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
    foreach(['','students','teachers','classes','fees','settings','students?edit='.$sid,'students?q=no-match'] as $page) { [$code,$html]=request($page); check($code===200 && !str_contains($html,'Application error') && !str_contains($html,'Warning:'),'Page renders: '.($page?:'overview')); }
    $csvNote="Sample, quoted \"note\"\n"."\u{1019}\u{103c}\u{1014}\u{103a}\u{1019}\u{102c}";
    DB::update('students',$second,['notes'=>$csvNote,'account'=>'=1+1']);
    foreach(['students','fees'] as $entity) {
        [$code,$csv,$headers]=request('export/'.$entity);
        check($code===200 && str_contains($csv,'Aye Chan Updated'),'CSV export: '.$entity);
        check((int)($headers['content-length']??0)===strlen($csv),'CSV download length matches its file contents: '.$entity);
        check(!str_contains($csv,'Deprecated:') && !str_contains($csv,'<br') && !str_contains($csv,'Warning:'),'CSV contains no PHP warnings or HTML: '.$entity);
        check(str_starts_with($csv,"\xEF\xBB\xBF") && ($headers['content-type']??'')==='text/csv; charset=utf-8' && str_contains($headers['content-disposition']??'','attachment; filename="myra-'.$entity.'-'),'CSV response has UTF-8 encoding and download headers: '.$entity);
        $stream=fopen('php://temp','w+'); fwrite($stream,substr($csv,3)); rewind($stream);
        $columns=fgetcsv($stream,0,',','"',''); $rowCount=0;
        while(($row=fgetcsv($stream,0,',','"',''))!==false) {
            check(count($row)===count($columns),'CSV row has the correct number of columns: '.$entity);
            $record=array_combine($columns,$row);
            if ($entity==='students' && $record['name']==='Second Student') check($record['notes']===$csvNote && $record['account']==="'=1+1",'CSV preserves commas, quotes, newlines and Myanmar text while escaping formulas');
            $rowCount++;
        }
        fclose($stream);
        check($rowCount==(int)DB::fetchValue('SELECT COUNT(*) FROM '.($entity==='students'?'students':'invoices')),'CSV includes all saved records: '.$entity);
    }
    foreach(['','students/'.$sid] as $page) {
        [$code,$html]=request($page); check($code===200 && stripos($html,'attendance')===false,'Attendance controls removed from '.($page?:'dashboard'));
    }
    foreach(['','students/'.$sid] as $page) {
        [$code,$html]=request($page); check($code===200 && !str_contains($html,'Exam results') && !str_contains($html,'save/result') && !str_contains($html,'export/results'),'Exam result controls removed from '.($page?:'dashboard'));
    }
    [$code,$html]=request('');
    check($code===200 && str_contains($html,'finance-report-data') && str_contains($html,'student-origin-data') && str_contains($html,'id="dashboard-exports"') && str_contains($html,'export/students') && str_contains($html,'export/fees'),'Dashboard combines financial charts, student origins, and both exports');
    check(!str_contains($html,'Reports & exports') && !str_contains($html,'href="'.e(url('reports')).'"'),'Separate Reports navigation and dashboard links are removed');
    [$code,$body,$headers]=request('reports?view=year&year=2024');
    check($code===302 && str_ends_with($headers['location']??'','/?view=year&year=2024#finance-report-heading'),'Old reports URL redirects to dashboard and preserves selected chart period');
    [$code,$body,$headers]=request('reports');
    check($code===302 && str_ends_with($headers['location']??'','/#finance-report-heading'),'Old Reports page redirects to the dashboard chart');
    // Isolated historical chart fixtures: aggregation by day/month and date boundaries.
    $chartInvoice=DB::insert('invoices',['student_id'=>$sid,'description'=>'Historical chart fixture','amount'=>'123.45','due_date'=>'2024-01-05']);
    DB::insert('payments',['invoice_id'=>$chartInvoice,'amount'=>'123.45','paid_on'=>'2024-01-05','method'=>'Cash']);
    foreach([
        ['Income','26.55','2024-01-05'],['Expense','200.00','2024-01-05'],
        ['Income','50.00','2024-01-31'],['Expense','20.00','2024-02-29'],
        ['Income','40.00','2024-06-15'],['Expense','10.00','2024-06-15'],
        ['Income','9999.00','2023-12-31'],['Expense','9999.00','2025-01-01'],
    ] as [$type,$amount,$date]) DB::insert('finance_entries',['type'=>$type,'amount'=>$amount,'occurred_on'=>$date,'description'=>'Historical chart fixture','category'=>'Test']);
    function reportData(string $path): array {
        [$code,$html]=request($path);
        check($code===200 && str_contains($html,'report-chart'),'Financial report chart renders: '.$path);
        if (!preg_match('/<script type="application\/json" id="finance-report-data">(.*?)<\/script>/s',$html,$match)) throw new RuntimeException('Missing financial chart data');
        return json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
    }
    $monthly=reportData('?view=month&month=2024-01');
    check(count($monthly['points'])===31 && $monthly['totals']===['income'=>20000,'expense'=>20000,'profit'=>0],'Monthly chart combines collected fees and manual entries precisely');
    check($monthly['points'][4]['income']===15000 && $monthly['points'][4]['profit']===-5000 && $monthly['points'][0]['income']===0,'Daily chart supports negative profit and fills inactive days with zero');
    $february=reportData('?view=month&month=2024-02');
    check(count($february['points'])===29 && $february['points'][28]['expense']===2000 && $february['totals']['profit']===-2000,'Monthly chart handles leap years and expenses without income');
    $annual=reportData('?view=year&year=2024');
    check(count($annual['points'])===12 && $annual['totals']===['income'=>24000,'expense'=>23000,'profit'=>1000],'Yearly chart aggregates 12 months and excludes adjacent years');
    check($annual['points'][0]['income']===20000 && $annual['points'][1]['expense']===2000 && $annual['points'][2]['profit']===0,'Yearly chart buckets entries by month and fills inactive months');
    $empty=reportData('?view=month&month=2020-04');
    check(count($empty['points'])===30 && $empty['totals']===['income'=>0,'expense'=>0,'profit'=>0],'Empty reporting period produces a valid zero chart');
    $fallback=reportData('?view=invalid&month=2024-13&year=bad');
    check($fallback['view']==='month' && $fallback['month']===date('Y-m') && $fallback['year']===(int)date('Y'),'Invalid report filters fall back to the current month and year');
    DB::update('students',$second,['origin'=>'TikTok']);
    DB::update('students',$third,['origin'=>'Telegram']);
    foreach(['Phone','Other'] as $origin) DB::insert('students',['admission_no'=>'ORIGIN-'.$origin,'name'=>'Origin '.$origin,'phone'=>'090000','origin'=>$origin,'status'=>'Active','enrolled_on'=>date('Y-m-d')]);
    DB::insert('students',['admission_no'=>'ORIGIN-ARCHIVED','name'=>'Archived origin fixture','phone'=>'090001','origin'=>'Facebook','status'=>'Inactive','enrolled_on'=>date('Y-m-d')]);
    [$code,$html]=request(''); $originChart=originData($html);
    check($code===200 && $originChart['total']===5 && array_column($originChart['items'],'count','name')===['Facebook'=>1,'TikTok'=>1,'Telegram'=>1,'Phone'=>1,'Other'=>1],'Origin pie counts active students once despite multiple class enrollments');
    check(array_sum(array_column($originChart['items'],'percentage'))===100 && substr_count($html,'class="student-origin-slice ' )===5 && !str_contains($html,'NaN'),'Origin pie renders all five slices and correct percentages');
    request('archive/'.$sid,[],$token); check(DB::fetchValue('SELECT status FROM students')==='Inactive' && DB::fetchValue('SELECT COUNT(*) FROM attendance')==1 && DB::fetchValue('SELECT COUNT(*) FROM results')==1,'Archiving preserves historical records');
    [$code,$html]=request(''); $archivedOrigins=originData($html);
    check($archivedOrigins['total']===4 && $archivedOrigins['items'][0]['count']===0,'Archiving a student updates the origin chart and excludes that student');
    request('logout',[],$token); [$code]=request('students'); check($code===302,'Logout protects school records');
    [$code]=request('invoices/'.$invoice.'/print'); check($code===302,'Printing requires administrator authentication');
    [, $html]=request('login'); preg_match('/name="_token" value="([^"]+)"/',$html,$matches); $token=$matches[1];
    check(str_contains($html,'Myra Branded School') && str_contains($html,'assets/favicon.svg'),'Sign-in uses school name and default favicon');
    request('login',['email'=>'admin@example.test','password'=>'wrong'],$token); [$code]=request(''); check($code===302,'Invalid credentials rejected');
    request('login'); request('login',['email'=>'admin@example.test','password'=>'Test-school-password-42'],$token); [$code]=request(''); check($code===200,'Administrator can sign back in');
    request('setup',['name'=>'Intruder','email'=>'new@example.test','password'=>'another-password'],$token); check(DB::fetchValue('SELECT COUNT(*) FROM users')==1,'Setup closes after first administrator');
    request('password',['current_password'=>'incorrect','password'=>'New-test-password-42','password_confirmation'=>'New-test-password-42'],$token); check(password_verify('Test-school-password-42',DB::fetchValue('SELECT password FROM users')),'Password change requires current password');
    request('password',['current_password'=>'Test-school-password-42','password'=>'New-test-password-42','password_confirmation'=>'New-test-password-42'],$token); check(password_verify('New-test-password-42',DB::fetchValue('SELECT password FROM users')),'Administrator password change persists');
    // Deposit and installment workflow. Keep these fixtures after earlier reporting totals.
    request('learning/batch',['class_id'=>$english,'name'=>'Installment course','capacity'=>10,'starts_on'=>date('Y-m-d'),'status'=>'Open'],$token);
    $installmentBatch=(int)DB::fetchValue('SELECT MAX(id) FROM batches');
    $yesterday=date('Y-m-d',strtotime('-1 day'));
    $installmentEnrollment=['batch_id'=>$installmentBatch,'student_id'=>$second,'enrolled_on'=>$yesterday,'amount'=>'100000.75','deposit'=>'20000.25','due_date'=>date('Y-m-d',strtotime('+30 days')),'method'=>'Bank transfer'];
    $beforeEnrollments=(int)DB::fetchValue('SELECT COUNT(*) FROM enrollments');
    foreach(['100000.76','-1','1.001','invalid'] as $badDeposit) {
        request('learning/enrollment',array_replace($installmentEnrollment,['deposit'=>$badDeposit]),$token);
        check((int)DB::fetchValue('SELECT COUNT(*) FROM enrollments')===$beforeEnrollments,'Invalid deposit rolls back enrollment: '.$badDeposit);
    }
    $incomeBefore=(float)DB::fetchValue('SELECT SUM(amount) FROM payments');
    request('learning/enrollment',$installmentEnrollment,$token);
    $installmentInvoice=(int)DB::fetchValue('SELECT MAX(id) FROM invoices');
    check(DB::fetchValue('SELECT amount FROM invoices WHERE id=?',[$installmentInvoice])==='100000.75','Deposit enrollment preserves the total course fee');
    check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$installmentInvoice])==='20000.25','Only the deposit is recorded as initial income');
    check(DB::fetchValue('SELECT is_foc FROM enrollments WHERE batch_id=? AND student_id=?',[$installmentBatch,$second])==0,'Deposit enrollment is chargeable');
    [$code,$html]=request('batches/'.$installmentBatch);
    check($code===200 && str_contains($html,'80,000.50 MMK') && str_contains($html,'Partial') && str_contains($html,'invoices/'.$installmentInvoice.'/payments'),'Batch shows the deposit, balance and installment action');
    [$code,$html]=request('invoices/'.$installmentInvoice.'/print');
    check($code===200 && str_contains($html,'80,000.50 MMK') && str_contains($html,'Partial'),'Printed invoice shows deposit and remaining balance');
    function paymentFormToken(int $invoice): string {
        [$code,$html]=request('invoices/'.$invoice.'/payments');
        check($code===200 && str_contains($html,'Payment history'),'Invoice payment page renders');
        if (!preg_match('/name="payment_token" value="([^"]+)"/',$html,$match)) throw new RuntimeException('Missing installment token');
        return $match[1];
    }
    $installmentToken=paymentFormToken($installmentInvoice);
    $staleToken=paymentFormToken($installmentInvoice);
    $installment=['invoice_id'=>$installmentInvoice,'amount'=>'30000.25','paid_on'=>date('Y-m-d'),'method'=>'Mobile payment','payment_token'=>$installmentToken];
    $beforePayments=(int)DB::fetchValue('SELECT COUNT(*) FROM payments');
    [$code]=request('learning/payment',$installment,'invalid'); check($code===419,'Installments require CSRF protection');
    foreach([
        ['amount'=>'80000.51'],['amount'=>'0'],['amount'=>'-1'],['amount'=>'1.001'],['amount'=>['1']],
        ['paid_on'=>date('Y-m-d',strtotime('+1 day'))],['paid_on'=>date('Y-m-d',strtotime('-2 days'))],
        ['paid_on'=>'2026-02-30'],['method'=>'Unknown'],['payment_token'=>'invalid'],['invoice_id'=>$freeInvoice],
    ] as $invalid) {
        request('learning/payment',array_replace($installment,$invalid),$token);
        check((int)DB::fetchValue('SELECT COUNT(*) FROM payments')===$beforePayments,'Invalid installment records no income: '.json_encode($invalid));
    }
    request('learning/payment',$installment,$token);
    check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$installmentInvoice])==='50000.50','Second installment adds precisely to the deposit');
    request('learning/payment',$installment,$token);
    check((int)DB::fetchValue('SELECT COUNT(*) FROM payments')===$beforePayments+1,'Repeated payment submission does not duplicate income');
    request('learning/payment',array_replace($installment,['payment_token'=>$staleToken,'amount'=>'80000.50']),$token);
    check((int)DB::fetchValue('SELECT COUNT(*) FROM payments')===$beforePayments+1,'Stale payment form cannot overpay the latest balance');
    request('learning/payment',array_replace($installment,['payment_token'=>$staleToken,'amount'=>'50000.25','method'=>'Cash']),$token);
    check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$installmentInvoice])==='100000.75','Final installment settles the exact course fee');
    [$code,$html]=request('invoices/'.$installmentInvoice.'/payments');
    check($code===200 && str_contains($html,'fully paid') && !str_contains($html,'>Save payment<') && substr_count($html,'REC-')===3,'Settled payment page retains all three receipts and hides collection form');
    [$code,$html]=request('invoices/'.$installmentInvoice.'/print');
    check($code===200 && str_contains($html,'>Paid</span>') && substr_count($html,'REC-')===3,'Printed invoice includes every installment and Paid status');
    check(abs((float)DB::fetchValue('SELECT SUM(amount) FROM payments')-$incomeBefore-100000.75)<0.001,'Installment income is counted once without duplicating the course fee');
    request('learning/enrollment',array_replace($installmentEnrollment,['student_id'=>$third,'deposit'=>'0']),$token);
    $unpaidInvoice=(int)DB::fetchValue('SELECT MAX(id) FROM invoices');
    check(DB::fetchValue('SELECT is_foc FROM enrollments WHERE batch_id=? AND student_id=?',[$installmentBatch,$third])==0 && DB::fetchValue('SELECT COUNT(*) FROM payments WHERE invoice_id=?',[$unpaidInvoice])==0,'Zero deposit keeps a chargeable unpaid enrollment without income');
    $unpaidToken=paymentFormToken($unpaidInvoice);
    request('learning/payment',array_replace($installment,['invoice_id'=>$unpaidInvoice,'payment_token'=>$unpaidToken,'amount'=>'1.25']),$token);
    check(DB::fetchValue('SELECT SUM(amount) FROM payments WHERE invoice_id=?',[$unpaidInvoice])==='1.25','Unpaid enrollment accepts its first installment later');
    [$code,$html]=request('invoices/'.$freeInvoice.'/payments');
    check($code===200 && str_contains($html,'FOC enrollment') && !str_contains($html,'>Save payment<'),'FOC invoice cannot collect installments');
    foreach(['not-a-number','9999999999'] as $missing) { [$code]=request('invoices/'.$missing.'/payments'); check($code===404,'Invalid installment invoice returns 404: '.$missing); }
    request('logout',[],$token);
    [$code]=request('invoices/'.$installmentInvoice.'/payments'); check($code===302,'Payment history requires administrator sign-in');
    [$code]=request('learning/payment',$installment,$token); check($code===302,'Recording installments requires administrator sign-in');
    echo "\n$checks integration checks passed.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach($brandingFiles as $path) if (is_string($path) && preg_match('#^uploads/branding/(logo|favicon)-[a-f0-9]{32}\.png$#D',$path)) @unlink(BASE_PATH.'/public/'.$path);
    foreach($fixtures as $file) @unlink($file);
    // This database was created by this test with a random name, never the school DB.
    if (preg_match('/^myra_test_[a-f0-9]{10}$/',$testDb)) DB::connection(true)->exec("DROP DATABASE IF EXISTS `$testDb`");
    @unlink($cookie); @unlink($log);
}
