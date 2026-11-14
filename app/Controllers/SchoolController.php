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
        $stats=['students'=>DB::fetchValue("SELECT COUNT(*) FROM students WHERE status='Active'"),'classes'=>DB::fetchValue('SELECT COUNT(*) FROM classes'),'teachers'=>DB::fetchValue('SELECT COUNT(*) FROM teachers'),'outstanding'=>DB::fetchValue('SELECT COALESCE(SUM(amount),0)-(SELECT COALESCE(SUM(amount),0) FROM payments) FROM invoices')];
        $recent=DB::fetchAll("SELECT s.*,COALESCE((SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') FROM enrollments e JOIN batches b ON b.id=e.batch_id JOIN classes c ON c.id=b.class_id WHERE e.student_id=s.id),'No enrollments') class_name FROM students s ORDER BY s.id DESC LIMIT 5");
        $breakdown=DB::fetchAll("SELECT c.name,(SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN batches b ON b.id=e.batch_id JOIN students s ON s.id=e.student_id WHERE b.class_id=c.id AND e.status='Active' AND s.status='Active' AND b.status<>'Completed') total,COALESCE((SELECT SUM(b.capacity) FROM batches b WHERE b.class_id=c.id AND b.status<>'Completed'),0) capacity FROM classes c ORDER BY c.name");
        $due=DB::fetchAll('SELECT i.*,s.name,COALESCE(p.paid,0) paid FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id WHERE i.amount>COALESCE(p.paid,0) ORDER BY i.due_date LIMIT 4');
        $origins=array_fill_keys(['Facebook','TikTok','Telegram','Phone','Other'],0);
        foreach(DB::fetchAll("SELECT origin,COUNT(*) total FROM students WHERE status='Active' GROUP BY origin") as $row) {
            $match='Other';
            foreach(array_keys($origins) as $name) if (strcasecmp(trim((string)$row['origin']),$name)===0) { $match=$name; break; }
            $origins[$match]+=(int)$row['total'];
        }
        view('dashboard',compact('stats','recent','breakdown','due','origins')+['page'=>'dashboard','title'=>'Overview']);
    }
    public function reportsRedirect(): void {
        $filters=array_filter(array_intersect_key($_GET,array_flip(['view','month','year'])),'is_scalar');
        $query=http_build_query($filters);
        redirect(($query?'?'.$query:'').'#finance-report-heading');
    }
    public function page(string $page): void {
        if ($page==='fees') { \App\Receipts::page(); return; }
        $classes=DB::fetchAll('SELECT * FROM classes ORDER BY name');
        $teachers=DB::fetchAll('SELECT * FROM teachers ORDER BY name');
        $edit=null;
        if (in_array($page,['teachers','classes']) && isset($_GET['edit'])) $edit=DB::fetch("SELECT * FROM `$page` WHERE id=?",[(int)$_GET['edit']]);
        view('manage',compact('page','classes','teachers','edit')+['title'=>ucfirst($page)]);
    }
    public function printInvoice(string $id): void {
        header('Cache-Control: private, no-store');
        $invoice = preg_match('/^[1-9][0-9]{0,9}$/D', $id)
            ? DB::fetch("SELECT i.*,COALESCE(en.is_foc,0) is_foc,s.name,s.admission_no,s.guardian,s.phone,COALESCE(CONCAT(c.name,' / ',b.name),'General fee') class_name FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN enrollments en ON en.id=i.enrollment_id LEFT JOIN batches b ON b.id=en.batch_id LEFT JOIN classes c ON c.id=b.class_id WHERE i.id=?", [$id])
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
        $status = $balanceCents <= 0 ? ($invoice['is_foc'] ? 'FOC' : 'Paid') : ($invoice['due_date'] < date('Y-m-d') ? 'Overdue' : ($paidCents > 0 ? 'Partial' : 'Unpaid'));
        $number = 'INV-'.str_pad((string)$invoice['id'], 4, '0', STR_PAD_LEFT);
        view('invoice', compact('invoice','payments','paid','balance','status','number'), null);
    }
    private function validDate(mixed $v): bool { if (!is_string($v)) return false; $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }
    private function fail(string $p,string $m): never {
        $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v)&&!in_array($k,['_token','password']),ARRAY_FILTER_USE_BOTH);
        if ((int)($_POST['id']??0) && in_array($p,['students','teachers','classes'])) $p.='?edit='.(int)$_POST['id'];
        redirect($p,$m,'error');
    }
    public function save(string $entity): void {
        $id=(int)($_POST['id']??0); $path=['student'=>'students','teacher'=>'teachers','class'=>'classes','settings'=>'settings'][$entity]??'';
        if (!$path) { http_response_code(404); return; }
        try {
            if ($entity==='student') {
                $d=$this->validate(['name'=>'required','phone'=>'required','address'=>'','origin'=>'required','account'=>'','status'=>'required','notes'=>'']);
                if ($d['notes']!==null && mb_strlen($d['notes'])>5000) $this->fail($path,'Student notes must contain at most 5,000 characters.');
                if (!in_array($d['origin'],['Facebook','TikTok','Telegram','Phone','Other']) || !in_array($d['status'],['Active','Inactive'])) $this->fail($path,'Choose a valid origin and status.');
                if ($id) {
                    if (!DB::fetchValue('SELECT id FROM students WHERE id=?',[$id])) $this->fail($path,'Student not found.');
                    DB::update('students',$id,$d);
                } else {
                    $d['admission_no']='ST-'.bin2hex(random_bytes(12));
                    $d['enrolled_on']=date('Y-m-d');
                    $id=DB::insert('students',$d);
                }
                $returnBatch=(int)($_POST['return_batch']??0);
                if ($returnBatch && DB::fetchValue('SELECT id FROM batches WHERE id=?',[$returnBatch])) $path='batches/'.$returnBatch.'?enroll=1&student='.$id;
            } elseif ($entity==='teacher') {
                $d=$this->validate(['name'=>'required','email'=>'email','phone'=>'','subject'=>'required']);
                if ($id) DB::update('teachers',$id,$d); else DB::insert('teachers',$d);
            } elseif ($entity==='class') {
                $d=$this->validate(['name'=>'required','teacher_id'=>'integer','capacity'=>'required|integer']);
                $d['fee']=$_POST['fee']??'0';
                if (!is_scalar($d['fee']) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D',(string)$d['fee'])) $this->fail($path,'Enter a non-negative class fee with at most two decimal places.');
                if ($d['capacity']<1 || $d['capacity']>500) $this->fail($path,'Capacity must be between 1 and 500.');
                if ($id) DB::update('classes',$id,$d); else DB::insert('classes',$d);
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
            'students'=>"SELECT s.name,s.phone,s.address,s.origin,s.account,s.notes,s.status,s.enrolled_on,(SELECT GROUP_CONCAT(CONCAT(c.name,' / ',b.name) SEPARATOR '; ') FROM enrollments e JOIN batches b ON b.id=e.batch_id JOIN classes c ON c.id=b.class_id WHERE e.student_id=s.id) classes FROM students s ORDER BY s.name",
            'fees'=>'',
        ];
        if (!isset($queries[$entity])) { http_response_code(404); return; }
        // Build the file before sending download headers so an SQL error cannot become a broken CSV.
        if ($entity==='fees') {
            $filters=\App\Receipts::filters($_GET);
            if ($filters['errors']) { http_response_code(422); header('Content-Type: text/plain; charset=utf-8'); echo implode(' ',$filters['errors']); return; }
            $stmt=\App\Receipts::statement($filters,true);
        } else $stmt=DB::statement($queries[$entity]);
        $f=fopen('php://temp','w+');
        if (!$f) throw new \RuntimeException('Cannot prepare the CSV download.');
        try {
            fwrite($f,"\xEF\xBB\xBF");
            $columns=[];
            for ($index=0;$index<$stmt->columnCount();$index++) $columns[]=$stmt->getColumnMeta($index)['name'];
            // Explicit escape and CRLF prevent PHP 8.4+ warnings and produce standard spreadsheet CSV.
            fputcsv($f,$columns,',','"','',"\r\n");
            while ($row=$stmt->fetch()) {
                fputcsv($f,array_map(fn($value)=>preg_match('/^[\s]*[=+@\-]/u',(string)$value)?"'".$value:($value??''),$row),',','"','',"\r\n");
            }
            $length=ftell($f);
            rewind($f);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="myra-'.$entity.'-'.date('Y-m-d').'.csv"');
            header('Content-Length: '.$length);
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            fpassthru($f);
        } finally { fclose($f); }
    }
}
