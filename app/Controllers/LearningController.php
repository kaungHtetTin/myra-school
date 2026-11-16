<?php
declare(strict_types=1);
namespace App\Controllers;

use Mini\Controller;
use Mini\Database as DB;

final class LearningController extends Controller
{
    public static function studentList(): array {
        return DB::fetchAll("SELECT s.*,COALESCE((SELECT GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') FROM enrollments e JOIN batches b ON b.id=e.batch_id JOIN classes c ON c.id=b.class_id WHERE e.student_id=s.id),'No enrollments') class_name FROM students s ORDER BY s.name");
    }
    public function students(): void {
        $students=self::studentList();
        $classes=DB::fetchAll('SELECT * FROM classes ORDER BY name');
        $q=is_string($_GET['q']??null)?trim($_GET['q']):'';
        $status=is_string($_GET['status']??null)?$_GET['status']:'';
        $classId=(int)($_GET['class']??0);
        $members=$classId?DB::fetchPairs('SELECT DISTINCT e.student_id,1 FROM enrollments e JOIN batches b ON b.id=e.batch_id WHERE b.class_id=?',[$classId]):[];
        $filtered=array_filter($students,fn($s)=>(!$q || mb_stripos($s['name'].' '.$s['phone'].' '.$s['address'].' '.$s['account'],$q)!==false) && (!$status || $s['status']===$status) && (!$classId || isset($members[$s['id']])));
        $edit=isset($_GET['edit'])?DB::fetch('SELECT * FROM students WHERE id=?',[(int)$_GET['edit']]):null;
        $returnBatch=(int)old('return_batch',$_GET['batch']??0);
        if ($returnBatch && !DB::fetchValue('SELECT id FROM batches WHERE id=?',[$returnBatch])) $returnBatch=0;
        view('students',compact('students','classes','q','status','classId','filtered','edit','returnBatch')+['page'=>'students','title'=>'Students']);
    }
    public function student(string $id): void {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { $this->missing(); return; }
        $student=DB::fetch('SELECT * FROM students WHERE id=?',[(int)$id]);
        if (!$student) { $this->missing(); return; }
        $enrollments=DB::fetchAll('SELECT e.*,b.name batch_name,b.status batch_status,c.name class_name,COALESCE(f.total,0) total,COALESCE(f.paid,0) paid FROM enrollments e JOIN batches b ON b.id=e.batch_id JOIN classes c ON c.id=b.class_id LEFT JOIN (SELECT i.enrollment_id,SUM(i.amount) total,SUM(COALESCE(p.paid,0)) paid FROM invoices i LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id GROUP BY i.enrollment_id)f ON f.enrollment_id=e.id WHERE e.student_id=? ORDER BY e.enrolled_on DESC,e.id DESC',[(int)$id]);
        $invoices=DB::fetchAll('SELECT i.*,COALESCE(en.is_foc,0) is_foc,COALESCE(p.paid,0) paid FROM invoices i LEFT JOIN enrollments en ON en.id=i.enrollment_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id WHERE i.student_id=? ORDER BY i.id DESC',[(int)$id]);
        view('student-detail',compact('student','enrollments','invoices')+['page'=>'students','title'=>$student['name']]);
    }
    public function batches(string $id): void {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { $this->missing(); return; }
        $class=DB::fetch('SELECT * FROM classes WHERE id=?',[(int)$id]);
        if (!$class) { $this->missing(); return; }
        $batches=DB::fetchAll("SELECT b.*,(SELECT COUNT(*) FROM enrollments e WHERE e.batch_id=b.id AND e.status='Active') students FROM batches b WHERE b.class_id=? ORDER BY b.starts_on DESC,b.id DESC",[(int)$id]);
        $edit=isset($_GET['edit'])?DB::fetch('SELECT * FROM batches WHERE id=? AND class_id=?',[(int)$_GET['edit'],(int)$id]):null;
        view('batches',compact('class','batches','edit')+['page'=>'classes','title'=>$class['name'].' / Batches']);
    }
    public function batch(string $id): void {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { $this->missing(); return; }
        $batch=DB::fetch('SELECT b.*,c.name class_name,c.fee FROM batches b JOIN classes c ON c.id=b.class_id WHERE b.id=?',[(int)$id]);
        if (!$batch) { $this->missing(); return; }
        $enrollments=DB::fetchAll('SELECT e.*,s.name,s.phone,s.status student_status FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.batch_id=? ORDER BY s.name',[(int)$id]);
        $available=DB::fetchAll("SELECT s.* FROM students s WHERE s.status='Active' AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id=s.id AND e.batch_id=?) ORDER BY s.name",[(int)$id]);
        $invoices=DB::fetchAll('SELECT i.*,s.name,s.phone,e.is_foc,COALESCE(p.paid,0) paid FROM invoices i JOIN enrollments e ON e.id=i.enrollment_id JOIN students s ON s.id=i.student_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id WHERE e.batch_id=? ORDER BY i.id DESC',[(int)$id]);
        view('batch-detail',compact('batch','enrollments','available','invoices')+['page'=>'classes','title'=>$batch['class_name'].' / '.$batch['name']]);
    }
    public function payments(string $id): void {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { $this->missing(); return; }
        $invoice=DB::fetch('SELECT i.*,s.name,s.phone,COALESCE(e.is_foc,0) is_foc,e.enrolled_on FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN enrollments e ON e.id=i.enrollment_id WHERE i.id=?',[(int)$id]);
        if (!$invoice) { $this->missing(); return; }
        $payments=DB::fetchAll('SELECT * FROM payments WHERE invoice_id=? ORDER BY paid_on,id',[(int)$id]);
        $paidCents=array_sum(array_map(fn($p)=>(int)round((float)$p['amount']*100),$payments));
        $balanceCents=(int)round((float)$invoice['amount']*100)-$paidCents;
        $paymentToken=bin2hex(random_bytes(24));
        $_SESSION['payment_tokens'][$paymentToken]=(int)$id;
        $_SESSION['payment_tokens']=array_slice($_SESSION['payment_tokens'],-50,null,true);
        view('payments',compact('invoice','payments','paidCents','balanceCents','paymentToken')+['page'=>'fees','title'=>'Fee payments']);
    }
    public function finance(): void {
        $from=is_string($_GET['from']??null)?$_GET['from']:'';
        $to=is_string($_GET['to']??null)?$_GET['to']:'';
        $where=[]; $bindings=[];
        if ($from && $this->date($from)) { $where[]='occurred_on>=?'; $bindings[]=$from; } else $from='';
        if ($to && $this->date($to)) { $where[]='occurred_on<=?'; $bindings[]=$to; } else $to='';
        $entries=DB::fetchAll("SELECT * FROM (SELECT CONCAT('PAY-',p.id) reference,'Income' type,CONCAT(s.name,' / ',i.description) description,p.amount,p.paid_on occurred_on,'Student fees' category,p.invoice_id FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN students s ON s.id=i.student_id UNION ALL SELECT CONCAT('BOOK-',id),type,description,amount,occurred_on,category,NULL FROM finance_entries)book".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY occurred_on DESC,reference DESC',$bindings);
        $income=0; $expenses=0;
        foreach ($entries as $entry) { if ($entry['type']==='Income') $income+=(int)round((float)$entry['amount']*100); else $expenses+=(int)round((float)$entry['amount']*100); }
        view('finance',compact('entries','from','to','income','expenses')+['page'=>'finance','title'=>'Mini Finance Book']);
    }
    private function missing(): void { http_response_code(404); view('missing',['page'=>'classes','title'=>'Record not found']); }
    private function date(mixed $value): bool { if (!is_string($value)) return false; $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value); return $date && $date->format('Y-m-d')===$value; }
    private function amount(mixed $value,bool $zero=false): bool { return is_scalar($value) && preg_match('/^\d{1,10}(\.\d{1,2})?$/D',(string)$value) && ($zero || (float)$value>0); }
    public function save(string $entity): void {
        $path=match($entity) { 'batch'=>'classes/'.(int)($_POST['class_id']??0).'/batches', 'enrollment','enrollment-status'=>'batches/'.(int)($_POST['batch_id']??0), 'payment'=>'invoices/'.(int)($_POST['invoice_id']??0).'/payments', 'finance'=>'finance', default=>'' };
        if (!$path) { $this->missing(); return; }
        try {
            if ($entity==='batch') {
                $d=$this->validate(['class_id'=>'required|integer','name'=>'required','capacity'=>'required|integer','starts_on'=>'required','ends_on'=>'','status'=>'required']);
                if (!$this->date($d['starts_on']) || ($d['ends_on'] && (!$this->date($d['ends_on']) || $d['ends_on']<$d['starts_on'])) || $d['capacity']<1 || $d['capacity']>500 || !in_array($d['status'],['Open','Ongoing','Completed'])) throw new \InvalidArgumentException('Check batch dates, capacity (1–500), and status.');
                $id=(int)($_POST['id']??0);
                DB::transaction(function() use($d,$id) {
                    if (!DB::fetch('SELECT id FROM classes WHERE id=? FOR UPDATE',[$d['class_id']])) throw new \InvalidArgumentException('Class not found.');
                    if ($id) {
                        if (!DB::fetch('SELECT id FROM batches WHERE id=? AND class_id=? FOR UPDATE',[$id,$d['class_id']])) throw new \InvalidArgumentException('Batch not found.');
                        if ($d['capacity']<DB::fetchValue("SELECT COUNT(*) FROM enrollments WHERE batch_id=? AND status='Active'",[$id])) throw new \InvalidArgumentException('Capacity cannot be below active enrollment.');
                        DB::update('batches',$id,$d);
                    } else DB::insert('batches',$d);
                });
            } elseif ($entity==='enrollment') {
                $d=$this->validate(['batch_id'=>'required|integer','student_id'=>'required|integer','enrolled_on'=>'required']);
                $foc=$_POST['is_foc']??'0';
                if (!is_scalar($foc) || !in_array((string)$foc,['0','1'],true)) throw new \InvalidArgumentException('Choose a valid FOC option.');
                if (!$this->date($d['enrolled_on']) || $d['enrolled_on']>date('Y-m-d')) throw new \InvalidArgumentException('Enrollment and payment date must be today or earlier.');
                DB::transaction(function() use($d,$foc) {
                    $b=DB::fetch('SELECT b.*,c.name class_name,c.fee FROM batches b JOIN classes c ON c.id=b.class_id WHERE b.id=? FOR UPDATE',[$d['batch_id']]);
                    $s=DB::fetch('SELECT * FROM students WHERE id=? FOR UPDATE',[$d['student_id']]);
                    if (!$b || $b['status']==='Completed' || !$s || $s['status']!=='Active') throw new \InvalidArgumentException('Choose an active student and an open or ongoing batch.');
                    if (DB::fetchValue("SELECT COUNT(*) FROM enrollments WHERE batch_id=? AND status='Active'",[$b['id']]) >= $b['capacity']) throw new \InvalidArgumentException('This batch is full.');
                    // FOC overrides the posted fee on the server, even if the browser sends a nonzero amount.
                    $amount=(string)$foc==='1'?'0':($_POST['amount']??$b['fee']);
                    if (!$this->amount($amount,true)) throw new \InvalidArgumentException('Enter a non-negative total fee with at most two decimal places.');
                    $isFoc=(int)round((float)$amount*100)===0;
                    $deposit=$isFoc?'0':($_POST['deposit']??$amount);
                    if (!$this->amount($deposit,true) || (int)round((float)$deposit*100)>(int)round((float)$amount*100)) throw new \InvalidArgumentException('Deposit must be between zero and the total fee, with at most two decimal places.');
                    $dueDate=$_POST['due_date']??$d['enrolled_on'];
                    if (!$this->date($dueDate) || $dueDate<$d['enrolled_on']) throw new \InvalidArgumentException('Payment due date must be on or after enrollment.');
                    $method=$_POST['method']??'Cash';
                    if ((float)$deposit>0 && (!is_string($method) || !in_array($method,['Cash','Bank transfer','Mobile payment'],true))) throw new \InvalidArgumentException('Choose a valid payment method.');
                    $eid=DB::insert('enrollments',['batch_id'=>$b['id'],'student_id'=>$s['id'],'enrolled_on'=>$d['enrolled_on'],'status'=>'Active','is_foc'=>(int)$isFoc]);
                    $invoice=DB::insert('invoices',['student_id'=>$s['id'],'enrollment_id'=>$eid,'description'=>$b['class_name'].' / '.$b['name'],'amount'=>$amount,'due_date'=>$dueDate]);
                    if ((float)$deposit>0) DB::insert('payments',['invoice_id'=>$invoice,'amount'=>$deposit,'paid_on'=>$d['enrolled_on'],'method'=>$method]);
                });
            } elseif ($entity==='payment') {
                $d=$this->validate(['invoice_id'=>'required|integer','amount'=>'required','paid_on'=>'required','method'=>'required','payment_token'=>'required']);
                if (($_SESSION['payment_tokens'][$d['payment_token']]??null)!==(int)$d['invoice_id']) throw new \InvalidArgumentException('This payment form has expired or was already submitted. Review the payment history before trying again.');
                if (!$this->amount($d['amount']) || !$this->date($d['paid_on']) || $d['paid_on']>date('Y-m-d') || !in_array($d['method'],['Cash','Bank transfer','Mobile payment'],true)) throw new \InvalidArgumentException('Enter a positive payment, a valid method, and today or an earlier date.');
                DB::transaction(function() use($d) {
                    // Serialize installments against this invoice so simultaneous payments cannot overpay it.
                    $invoice=DB::fetch('SELECT i.*,e.enrolled_on,COALESCE(e.is_foc,0) is_foc FROM invoices i LEFT JOIN enrollments e ON e.id=i.enrollment_id WHERE i.id=? FOR UPDATE',[$d['invoice_id']]);
                    if (!$invoice || $invoice['is_foc']) throw new \InvalidArgumentException('Choose an existing, non-FOC invoice.');
                    if ($invoice['enrolled_on'] && $d['paid_on']<$invoice['enrolled_on']) throw new \InvalidArgumentException('Payment date cannot be before enrollment.');
                    $paid=DB::fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?',[$invoice['id']]);
                    $balance=(int)round((float)$invoice['amount']*100)-(int)round((float)$paid*100);
                    if ((int)round((float)$d['amount']*100)>$balance) throw new \InvalidArgumentException('Payment cannot exceed the remaining balance. Review the latest payment history.');
                    DB::insert('payments',array_intersect_key($d,array_flip(['invoice_id','amount','paid_on','method'])));
                });
                unset($_SESSION['payment_tokens'][$d['payment_token']]);
            } elseif ($entity==='enrollment-status') {
                $d=$this->validate(['id'=>'required|integer','batch_id'=>'required|integer','status'=>'required']);
                if (!in_array($d['status'],['Active','Completed','Withdrawn'])) throw new \InvalidArgumentException('Choose a valid enrollment status.');
                DB::transaction(function() use($d) {
                    $b=DB::fetch('SELECT * FROM batches WHERE id=? FOR UPDATE',[$d['batch_id']]);
                    $e=DB::fetch('SELECT e.*,s.status student_status FROM enrollments e JOIN students s ON s.id=e.student_id WHERE e.id=? AND e.batch_id=? FOR UPDATE',[$d['id'],$d['batch_id']]);
                    if (!$b || !$e) throw new \InvalidArgumentException('Enrollment not found.');
                    if ($d['status']==='Active' && $e['status']!=='Active' && ($b['status']==='Completed' || $e['student_status']!=='Active' || DB::fetchValue("SELECT COUNT(*) FROM enrollments WHERE batch_id=? AND status='Active'",[$b['id']]) >= $b['capacity'])) throw new \InvalidArgumentException('Cannot reactivate in a full or completed batch, or for an archived student.');
                    DB::update('enrollments',(int)$e['id'],['status'=>$d['status']]);
                });
            } else {
                $d=$this->validate(['type'=>'required','description'=>'required','amount'=>'required|numeric','occurred_on'=>'required','category'=>'required']);
                if (!in_array($d['type'],['Income','Expense']) || !$this->amount($d['amount']) || !$this->date($d['occurred_on']) || $d['occurred_on']>date('Y-m-d')) throw new \InvalidArgumentException('Enter a positive amount, valid type, and today or an earlier date.');
                DB::insert('finance_entries',$d);
            }
        } catch (\InvalidArgumentException $e) {
            if (in_array($entity,['enrollment','payment'],true)) {
                $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v) && !in_array($k,['_token','payment_token'],true),ARRAY_FILTER_USE_BOTH);
                if ($entity==='enrollment') $_SESSION['old']['is_foc']=$_POST['is_foc']??'0';
            }
            redirect($path,$e->getMessage(),'error');
        }
        catch (\PDOException $e) { if (in_array($e->getCode(),['23000','22001','22003'])) redirect($path,'Duplicate enrollment or batch, invalid selection, or a value is too long.','error'); throw $e; }
        unset($_SESSION['old']); redirect($path,'Changes saved successfully.');
    }
}
