<?php
// Verify an upgrade with real legacy records in a disposable database.
declare(strict_types=1);
$testDb='myra_test_'.bin2hex(random_bytes(5));
putenv('DB_DATABASE='.$testDb);
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
$checks=0;
function verify(bool $ok,string $label): void { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; echo "PASS $label\n"; }
try {
    DB::connection(true)->exec("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach(glob(BASE_PATH.'/migrations/2026_10_*.php') as $file) foreach((require $file)['up'] as $sql) DB::connection()->exec($sql);
    $class=DB::insert('classes',['name'=>'Legacy class','capacity'=>1]);
    $active=DB::insert('students',['name'=>'Legacy active','admission_no'=>'OLD-001','gender'=>'Female','dob'=>'2000-01-01','class_id'=>$class,'guardian'=>'Existing guardian','phone'=>'091111','status'=>'Active','enrolled_on'=>'2026-01-01']);
    $inactive=DB::insert('students',['name'=>'Legacy inactive','admission_no'=>'OLD-002','gender'=>'Male','dob'=>'2000-01-01','class_id'=>$class,'guardian'=>'Existing guardian','phone'=>'092222','status'=>'Inactive','enrolled_on'=>'2026-02-01']);
    $invoice=DB::insert('invoices',['student_id'=>$active,'description'=>'Legacy tuition','amount'=>'50000.50','due_date'=>'2026-01-01']);
    DB::insert('payments',['invoice_id'=>$invoice,'amount'=>'10000.25','paid_on'=>'2026-01-01','method'=>'Cash']);
    $upgrade=require BASE_PATH.'/migrations/2026_11_13_000003_batches_finance.php';
    foreach($upgrade['up'] as $sql) DB::connection()->exec($sql);
    verify(DB::fetchValue('SELECT COUNT(*) FROM batches')==1 && DB::fetchValue('SELECT capacity FROM batches')==2,'Legacy class becomes a batch with sufficient capacity');
    verify(DB::fetchValue('SELECT COUNT(*) FROM enrollments')==2,'All legacy students gain enrollments');
    verify(DB::fetchValue('SELECT status FROM enrollments WHERE student_id=?',[$active])==='Active' && DB::fetchValue('SELECT status FROM enrollments WHERE student_id=?',[$inactive])==='Completed','Legacy active and archived enrollment states preserved');
    verify(DB::fetchValue('SELECT enrolled_on FROM enrollments WHERE student_id=?',[$active])==='2026-01-01','Legacy enrollment dates preserved');
    verify(DB::fetchValue('SELECT COUNT(*) FROM invoices')==1 && DB::fetchValue('SELECT enrollment_id FROM invoices WHERE id=?',[$invoice])>0,'Existing invoice linked without generating extra charges');
    verify(DB::fetchValue('SELECT amount FROM invoices WHERE id=?',[$invoice])==='50000.50' && DB::fetchValue('SELECT amount FROM payments WHERE invoice_id=?',[$invoice])==='10000.25','Legacy fees and payments preserved precisely');
    verify(DB::fetchValue('SELECT guardian FROM students WHERE id=?',[$active])==='Existing guardian' && DB::fetchValue('SELECT admission_no FROM students WHERE id=?',[$active])==='OLD-001','Legacy profile and internal student ID preserved');
    $focMigration=require BASE_PATH.'/migrations/2026_11_13_000004_enrollment_foc.php';
    foreach($focMigration['up'] as $sql) DB::connection()->exec($sql);
    verify(DB::fetchValue('SELECT COUNT(*) FROM enrollments WHERE is_foc=0')==2,'FOC migration preserves existing enrollment classifications');
    verify(DB::fetchValue('SELECT amount FROM payments')==='10000.25' && DB::fetchValue('SELECT COUNT(*) FROM payments')==1,'FOC migration does not backfill or duplicate historical payments');
    foreach($focMigration['down'] as $sql) DB::connection()->exec($sql);
    foreach($upgrade['down'] as $sql) DB::connection()->exec($sql);
    verify(DB::fetchValue('SELECT COUNT(*) FROM students')==2 && DB::fetchValue('SELECT amount FROM payments')==='10000.25','Rollback preserves legacy records');
    echo "\n$checks migration checks passed.\n";
} finally {
    if (preg_match('/^myra_test_[a-f0-9]{10}$/D',$testDb)) DB::connection(true)->exec("DROP DATABASE IF EXISTS `$testDb`");
}
