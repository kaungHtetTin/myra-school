<?php
declare(strict_types=1);
namespace App;

use Mini\Database as DB;

final class Receipts
{
    public static function filters(array $query): array {
        $filters=['q'=>is_string($query['q']??null)?mb_substr(trim($query['q']),0,150):'',
            'class'=>max(0,(int)filter_var($query['class']??0,FILTER_VALIDATE_INT)),
            'batch'=>max(0,(int)filter_var($query['batch']??0,FILTER_VALIDATE_INT)),
            'status'=>in_array($query['status']??'',['Paid','FOC','Unpaid','Partial','Overdue'],true)?$query['status']:'',
            'from'=>'','to'=>'','errors'=>[]];
        foreach(['from','to'] as $key) {
            $value=$query[$key]??'';
            if ($value==='') continue;
            if (!is_string($value) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value) || !checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))) $filters['errors'][]='Choose a valid '.($key==='from'?'start':'end').' date.';
            else $filters[$key]=$value;
        }
        if ($filters['from'] && $filters['to'] && $filters['from']>$filters['to']) $filters['errors'][]='From date must be on or before To date.';
        if ($filters['class'] && $filters['batch'] && !DB::fetchValue('SELECT id FROM batches WHERE id=? AND class_id=?',[$filters['batch'],$filters['class']])) $filters['batch']=0;
        return $filters;
    }
    public static function queryString(array $filters): string {
        return http_build_query(array_filter(array_intersect_key($filters,array_flip(['q','class','batch','status','from','to'])),fn($value)=>$value!=='' && $value!==0));
    }
    public static function statement(array $filters,bool $export=false): \PDOStatement {
        $base="SELECT i.*,s.name,s.phone,b.class_id,b.id batch_id,COALESCE(en.is_foc,0) is_foc,COALESCE(CONCAT(c.name,' / ',b.name),'General fee') class_name,COALESCE(en.enrolled_on,i.due_date) receipt_date,COALESCE(p.paid,0) paid,i.amount-COALESCE(p.paid,0) balance,CASE WHEN en.is_foc=1 THEN 'FOC' WHEN i.amount<=COALESCE(p.paid,0) THEN 'Paid' WHEN i.due_date<? THEN 'Overdue' WHEN COALESCE(p.paid,0)>0 THEN 'Partial' ELSE 'Unpaid' END payment_status FROM invoices i JOIN students s ON s.id=i.student_id LEFT JOIN enrollments en ON en.id=i.enrollment_id LEFT JOIN batches b ON b.id=en.batch_id LEFT JOIN classes c ON c.id=b.class_id LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM payments GROUP BY invoice_id)p ON p.invoice_id=i.id";
        $where=[]; $bindings=[date('Y-m-d')];
        if ($filters['errors']) $where[]='1=0';
        if ($filters['q']!=='') {
            foreach(preg_split('/\s+/u',$filters['q'],-1,PREG_SPLIT_NO_EMPTY) as $term) {
                $pattern='%'.str_replace(['=','%','_'],['==','=%','=_'],$term).'%';
                $parts=[];
                foreach(['name','phone','class_name','description',"CONCAT('INV-',IF(id<10000,LPAD(id,4,'0'),CAST(id AS CHAR)))",'CAST(id AS CHAR)'] as $column) { $parts[]=$column." LIKE ? ESCAPE '='"; $bindings[]=$pattern; }
                $where[]='('.implode(' OR ',$parts).')';
            }
        }
        foreach(['class'=>'class_id','batch'=>'batch_id','status'=>'payment_status'] as $key=>$column) if ($filters[$key]!=='' && $filters[$key]!==0) { $where[]=$column.'=?'; $bindings[]=$filters[$key]; }
        if ($filters['from']) { $where[]='receipt_date>=?'; $bindings[]=$filters['from']; }
        if ($filters['to']) { $where[]='receipt_date<=?'; $bindings[]=$filters['to']; }
        $columns=$export?'id invoice,name,phone,class_name,description,receipt_date,amount,due_date,is_foc,paid,balance,payment_status':'*';
        return DB::statement('SELECT '.$columns.' FROM ('.$base.') receipts'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY receipt_date DESC,id DESC',$bindings);
    }
    public static function page(): void {
        $filters=self::filters($_GET);
        $invoices=self::statement($filters)->fetchAll();
        $classes=DB::fetchAll('SELECT id,name FROM classes ORDER BY name');
        $batches=DB::fetchAll('SELECT b.id,b.name,b.class_id,c.name class_name FROM batches b JOIN classes c ON c.id=b.class_id ORDER BY c.name,b.name');
        $foc=[]; $income=0;
        foreach($invoices as $invoice) {
            if ($invoice['is_foc']) $foc[$invoice['enrollment_id']??('invoice-'.$invoice['id'])]=true;
            $income+=(int)round((float)$invoice['paid']*100);
        }
        $stats=['receipts'=>count($invoices),'foc'=>count($foc),'income'=>$income];
        view('receipts',compact('filters','invoices','classes','batches','stats')+['page'=>'fees','title'=>'Enrollment receipts']);
    }
}
