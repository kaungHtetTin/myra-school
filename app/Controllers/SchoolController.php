<?php
declare(strict_types=1);
namespace App\Controllers;
use Mini\Database as DB;
use Mini\Controller;
final class SchoolController extends Controller
{
    public function login(): void {
        if (isset($_SESSION['user'])) redirect('');
        view('auth', ['setup' => !(int) DB::fetchValue('SELECT COUNT(*) FROM users')], null);
    }
    public function setup(): void {
        $d=$this->validate(['name'=>'required','email'=>'required|email','password'=>'required']);
        if (strlen($d['password'])<10) redirect('login','Use at least 10 characters for your password.','error');
        if (!(int) DB::fetchValue("SELECT GET_LOCK('myra_school_setup', 5)")) redirect('login','Setup is busy. Try again.','error');
        try {
            if ((int) DB::fetchValue('SELECT COUNT(*) FROM users')) redirect('login');
            $id=DB::insert('users',['name'=>$d['name'],'email'=>$d['email'],'password'=>password_hash($d['password'],PASSWORD_DEFAULT)]);
            session_regenerate_id(true); $_SESSION['user']=['id'=>$id,'name'=>$d['name'],'email'=>$d['email']];
        } finally { DB::fetchValue("SELECT RELEASE_LOCK('myra_school_setup')"); }
        redirect('','Welcome! Add teachers, classes, and students to get started.');
    }
    public function authenticate(): void {
        $d=$this->validate(['email'=>'required|email','password'=>'required']);
        if (($_SESSION['login_after']??0)>time()) redirect('login','Please wait a minute before trying again.','error');
        $u=DB::fetch('SELECT * FROM users WHERE email=?',[$d['email']]);
        if (!$u || !password_verify($d['password'],$u['password'])) {
            $_SESSION['login_failures']=($_SESSION['login_failures']??0)+1;
            if ($_SESSION['login_failures']>=5) { $_SESSION['login_after']=time()+60; $_SESSION['login_failures']=0; }
            redirect('login','Email or password is incorrect.','error');
        }
        session_regenerate_id(true); unset($u['password'],$_SESSION['login_failures'],$_SESSION['login_after']); $_SESSION['user']=$u; redirect('');
    }
    public function logout(): void { $_SESSION=[]; session_regenerate_id(true); redirect('login'); }
    public function password(): void {
        $d=$this->validate(['current_password'=>'required','password'=>'required','password_confirmation'=>'required']);
        $hash=DB::fetchValue('SELECT password FROM users WHERE id=?',[$_SESSION['user']['id']]);
        if (!password_verify($d['current_password'],$hash)) redirect('settings','Current password is incorrect.','error');
        if (strlen($d['password'])<10 || $d['password']!==$d['password_confirmation']) redirect('settings','New passwords must match and contain at least 10 characters.','error');
        DB::update('users',(int)$_SESSION['user']['id'],['password'=>password_hash($d['password'],PASSWORD_DEFAULT)]);
        session_regenerate_id(true); redirect('settings','Password changed successfully.');
    }
    public function dashboard(): void {
        $stats=['students'=>DB::fetchValue("SELECT COUNT(*) FROM students WHERE status='Active'"),'classes'=>DB::fetchValue('SELECT COUNT(*) FROM classes'),'teachers'=>DB::fetchValue('SELECT COUNT(*) FROM teachers'),'attendance'=>DB::fetch("SELECT COUNT(*) total,SUM(a.status IN ('Present','Late')) present FROM attendance a JOIN students s ON s.id=a.student_id WHERE a.day=? AND s.status='Active'",[date('Y-m-d')]),'outstanding'=>DB::fetchValue('SELECT COALESCE(SUM(amount),0)-(SELECT COALESCE(SUM(amount),0) FROM payments) FROM invoices')];
        $recent=DB::fetchAll('SELECT s.*,c.name class_name FROM students s JOIN classes c ON c.id=s.class_id ORDER BY s.id DESC LIMIT 5');
        $breakdown=DB::fetchAll("SELECT c.name,COUNT(s.id) total,c.capacity FROM classes c LEFT JOIN students s ON s.class_id=c.id AND s.status='Active' GROUP BY c.id ORDER BY c.name");
        $due=DB::fetchAll('SELECT i.*,s.name,COALESCE(p.paid,0) paid FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id WHERE i.amount>COALESCE(p.paid,0) ORDER BY i.due_date LIMIT 4');
        view('dashboard',compact('stats','recent','breakdown','due')+['page'=>'dashboard','title'=>'Overview']);
    }
    public function page(string $page): void {
        $classes=DB::fetchAll('SELECT * FROM classes ORDER BY name'); $teachers=DB::fetchAll('SELECT * FROM teachers ORDER BY name');
        $students=DB::fetchAll('SELECT s.*,c.name class_name FROM students s JOIN classes c ON c.id=s.class_id ORDER BY s.name');
        $invoices=DB::fetchAll('SELECT i.*,s.name,s.class_id,c.name class_name,COALESCE(p.paid,0) paid FROM invoices i JOIN students s ON s.id=i.student_id JOIN classes c ON c.id=s.class_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id ORDER BY i.due_date DESC,i.id DESC');
        $q=trim(is_string($_GET['q']??null)?$_GET['q']:''); $classId=(int)($_GET['class']??0); $status=is_string($_GET['status']??null)?$_GET['status']:'';
        $filtered=array_values(array_filter($students,fn($s)=>(!$classId || $s['class_id']==$classId) && (!$status || $s['status']===$status) && (!$q || mb_stripos($s['name'].' '.$s['admission_no'].' '.$s['guardian'].' '.$s['phone'],$q)!==false)));
        $day=$this->validDate($_GET['day']??date('Y-m-d'))?($_GET['day']??date('Y-m-d')):date('Y-m-d');
        $marks=DB::fetchPairs('SELECT student_id,status FROM attendance WHERE day=?',[$day]);
        $results=DB::fetchAll('SELECT r.*,s.name,c.name class_name FROM results r JOIN students s ON s.id=r.student_id JOIN classes c ON c.id=s.class_id ORDER BY r.id DESC');
        $payments=DB::fetchAll('SELECT p.*,s.name,i.description FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN students s ON s.id=i.student_id ORDER BY p.id DESC LIMIT 30');
        $edit=null;
        if (in_array($page,['students','teachers','classes']) && isset($_GET['edit'])) $edit=DB::fetch("SELECT * FROM `$page` WHERE id=?",[(int)$_GET['edit']]);
        view('manage',compact('page','classes','teachers','students','invoices','q','classId','status','filtered','day','marks','results','payments','edit')+['title'=>ucfirst($page)]);
    }
    public function printInvoice(string $id): void {
        header('Cache-Control: private, no-store');
        $invoice = preg_match('/^[1-9][0-9]{0,9}$/D', $id)
            ? DB::fetch('SELECT i.*,s.name,s.admission_no,s.guardian,s.phone,c.name class_name FROM invoices i JOIN students s ON s.id=i.student_id JOIN classes c ON c.id=s.class_id WHERE i.id=?', [$id])
            : null;
        if (!$invoice) {
            http_response_code(404);
            view('invoice-not-found', [], null);
            return;
        }
        $payments = DB::fetchAll('SELECT * FROM payments WHERE invoice_id=? ORDER BY paid_on,id', [$invoice['id']]);
        $paidCents = array_sum(array_map(fn($payment) => (int)round((float)$payment['amount'] * 100), $payments));
        $balanceCents = (int)round((float)$invoice['amount'] * 100) - $paidCents;
        $paid = $paidCents / 100;
        $balance = $balanceCents / 100;
        $status = $balanceCents <= 0 ? 'Paid' : ($invoice['due_date'] < date('Y-m-d') ? 'Overdue' : ($paidCents > 0 ? 'Partial' : 'Unpaid'));
        $number = 'INV-'.str_pad((string)$invoice['id'], 4, '0', STR_PAD_LEFT);
        view('invoice', compact('invoice','payments','paid','balance','status','number'), null);
    }
    private function validDate(mixed $v): bool { if (!is_string($v)) return false; $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }
    private function fail(string $p,string $m): never {
        $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v)&&!in_array($k,['_token','password']),ARRAY_FILTER_USE_BOTH);
        if ((int)($_POST['id']??0) && in_array($p,['students','teachers','classes'])) $p.='?edit='.(int)$_POST['id'];
        elseif ($p==='fees' && isset($_POST['invoice_id'])) $p.='?payment=1';
        redirect($p,$m,'error');
    }
    public function save(string $entity): void {
        $id=(int)($_POST['id']??0); $path=['student'=>'students','teacher'=>'teachers','class'=>'classes','attendance'=>'attendance','invoice'=>'fees','payment'=>'fees','result'=>'results','settings'=>'settings'][$entity]??'';
        if (!$path) { http_response_code(404); return; }
        try {
            if ($entity==='student') {
                $d=$this->validate(['name'=>'required','admission_no'=>'required','gender'=>'required','dob'=>'required','class_id'=>'required|integer','guardian'=>'required','phone'=>'required','address'=>'','status'=>'required','enrolled_on'=>'required']);
                if (!$this->validDate($d['dob']) || $d['dob']>date('Y-m-d') || !$this->validDate($d['enrolled_on'])) $this->fail($path,'Enter a valid birth date and enrollment date.');
                if (!in_array($d['gender'],['Female','Male','Other']) || !in_array($d['status'],['Active','Inactive'])) $this->fail($path,'Choose a valid gender and status.');
                DB::transaction(function() use($d,$id,$path) {
                    $c=DB::fetch('SELECT * FROM classes WHERE id=? FOR UPDATE',[$d['class_id']]);
                    if (!$c) $this->fail($path,'Choose an existing class.');
                    $count=DB::fetchValue("SELECT COUNT(*) FROM students WHERE class_id=? AND status='Active' AND id<>?",[$c['id'],$id]);
                    if ($d['status']==='Active' && $count >= $c['capacity']) $this->fail($path,'This class is full. Choose another class or increase its capacity.');
                    if ($id) DB::update('students',$id,$d); else DB::insert('students',$d);
                });
            } elseif ($entity==='teacher') {
                $d=$this->validate(['name'=>'required','email'=>'email','phone'=>'','subject'=>'required']);
                if ($id) DB::update('teachers',$id,$d); else DB::insert('teachers',$d);
            } elseif ($entity==='class') {
                $d=$this->validate(['name'=>'required','teacher_id'=>'integer','capacity'=>'required|integer']);
                if ($d['capacity']<1 || $d['capacity']>500) $this->fail($path,'Capacity must be between 1 and 500.');
                if ($id && $d['capacity']<DB::fetchValue("SELECT COUNT(*) FROM students WHERE class_id=? AND status='Active'",[$id])) $this->fail($path,'Capacity cannot be below current enrollment.');
                if ($id) DB::update('classes',$id,$d); else DB::insert('classes',$d);
            } elseif ($entity==='attendance') {
                $day=$_POST['day']??'';
                if (!$this->validDate($day) || $day>date('Y-m-d')) $this->fail($path,'Attendance requires today or an earlier date.');
                $rows=$_POST['marks']??[]; if (!is_array($rows)) $this->fail($path,'Invalid attendance.');
                DB::transaction(function() use($rows,$day) { foreach ($rows as $student=>$status) {
                    if (!in_array($status,['Present','Absent','Late','Excused'],true)) continue;
                    if (!DB::fetchValue("SELECT id FROM students WHERE id=? AND status='Active'",[(int)$student])) continue;
                    DB::statement('INSERT INTO attendance (student_id,day,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)',[(int)$student,$day,$status]);
                }});
                $path.='?day='.urlencode($day).'&class='.(int)($_POST['class']??0);
            } elseif ($entity==='invoice') {
                $d=$this->validate(['student_id'=>'required|integer','description'=>'required','amount'=>'required|numeric','due_date'=>'required']);
                if ($d['amount']<=0 || $d['amount']>9999999999 || round((float)$d['amount'],2)!=(float)$d['amount'] || !$this->validDate($d['due_date'])) $this->fail($path,'Enter a positive amount with at most two decimal places and a valid due date.');
                DB::insert('invoices',$d);
            } elseif ($entity==='payment') {
                $d=$this->validate(['invoice_id'=>'required|integer','amount'=>'required|numeric','paid_on'=>'required','method'=>'required']);
                if (!$this->validDate($d['paid_on']) || $d['paid_on']>date('Y-m-d') || !in_array($d['method'],['Cash','Bank transfer','Mobile payment'])) $this->fail($path,'Choose a valid payment date and method.');
                DB::transaction(function() use($d,$path) {
                    $i=DB::fetch('SELECT * FROM invoices WHERE id=? FOR UPDATE',[$d['invoice_id']]);
                    $paid=DB::fetchValue('SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=?',[$d['invoice_id']]);
                    if (!$i || $d['amount']<=0 || round((float)$d['amount'],2)!=(float)$d['amount'] || (int)round((float)$d['amount']*100)>(int)round(((float)$i['amount']-(float)$paid)*100)) $this->fail($path,'Payment must be positive and cannot exceed the balance.');
                    DB::insert('payments',$d);
                });
            } elseif ($entity==='result') {
                $d=$this->validate(['student_id'=>'required|integer','exam'=>'required','subject'=>'required','score'=>'required|numeric','maximum'=>'required|numeric']);
                if ($d['maximum']<=0 || $d['maximum']>9999 || $d['score']<0 || $d['score']>$d['maximum']) $this->fail($path,'Score must be between zero and the maximum (up to 9,999).');
                DB::statement('INSERT INTO results (student_id,exam,subject,score,maximum) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),maximum=VALUES(maximum)',array_values($d));
            } elseif ($entity==='settings') {
                $d=$this->validate(['school_name'=>'required','school_year'=>'required','currency'=>'required']);
                try {
                    \App\Branding::save($d, $_FILES, ['logo'=>$_POST['remove_logo']??false,'favicon'=>$_POST['remove_favicon']??false]);
                } catch (\InvalidArgumentException $error) { $this->fail('settings', $error->getMessage()); }
            }
        } catch (\PDOException $e) {
            if ($e->getCode()==='23000') $this->fail($path,'This record already exists or a selected related record is invalid.');
            if (in_array($e->getCode(),['22001','22003'])) $this->fail($path,'A value is too long or too large. Please shorten it.');
            throw $e;
        }
        unset($_SESSION['old']); redirect($path,'Changes saved successfully.');
    }
    public function archive(string $id): void { DB::statement("UPDATE students SET status='Inactive' WHERE id=?",[(int)$id]); redirect('students','Student archived. Their records have been kept.'); }
    public function export(string $entity): void {
        $queries=[
            'students'=>'SELECT s.admission_no,s.name,s.gender,s.dob,c.name class,s.guardian,s.phone,s.address,s.status,s.enrolled_on FROM students s JOIN classes c ON c.id=s.class_id ORDER BY s.name',
            'attendance'=>'SELECT a.day,s.admission_no,s.name,c.name class,a.status FROM attendance a JOIN students s ON s.id=a.student_id JOIN classes c ON c.id=s.class_id ORDER BY a.day DESC,s.name',
            'fees'=>'SELECT i.id invoice,s.admission_no,s.name,i.description,i.amount,i.due_date,COALESCE(p.paid,0) paid,i.amount-COALESCE(p.paid,0) balance FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id ORDER BY i.id',
            'results'=>'SELECT s.admission_no,s.name,r.exam,r.subject,r.score,r.maximum FROM results r JOIN students s ON s.id=r.student_id ORDER BY s.name'];
        if (!isset($queries[$entity])) { http_response_code(404); return; }
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="myra-'.$entity.'-'.date('Y-m-d').'.csv"');
        $f=fopen('php://output','w'); fwrite($f,"\xEF\xBB\xBF"); $stmt=DB::statement($queries[$entity]); $first=true;
        while ($row=$stmt->fetch()) { if ($first) { fputcsv($f,array_keys($row)); $first=false; } fputcsv($f,array_map(fn($v)=>preg_match('/^[\s]*[=+@\-]/u',(string)$v)?"'".$v:$v,$row)); }
        if ($first) fputcsv($f,['No records']); fclose($f);
    }
}
