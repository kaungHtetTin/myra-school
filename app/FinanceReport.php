<?php
declare(strict_types=1);
namespace App;

use DateTimeImmutable;
use Mini\Database as DB;

final class FinanceReport
{
    public static function build(array $query): array {
        $view=($query['view']??'month')==='year'?'year':'month';
        $month=is_string($query['month']??null)?$query['month']:date('Y-m');
        if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D',$month)) $month=date('Y-m');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$month.'-01');
        if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D',$month) || !$date || $date->format('Y-m')!==$month || (int)$date->format('Y')>9998) $month=date('Y-m');
        $year=filter_var($query['year']??substr($month,0,4),FILTER_VALIDATE_INT,['options'=>['min_range'=>1000,'max_range'=>9998]]);
        if ($year===false) $year=(int)date('Y');
        $start=new DateTimeImmutable(($view==='year'?$year.'-01':$month).'-01');
        $end=$start->modify($view==='year'?'+1 year':'+1 month');
        $points=[];
        for ($day=$start; $day<$end; $day=$day->modify($view==='year'?'+1 month':'+1 day')) {
            $key=$day->format($view==='year'?'Y-m':'Y-m-d');
            $points[$key]=['date'=>$key,'label'=>$day->format($view==='year'?'M':'j'),'description'=>$day->format($view==='year'?'F Y':'j F Y'),'income'=>0,'expense'=>0,'profit'=>0];
        }
        // Payments are collected enrollment income. FOC has no payment and contributes nothing.
        $range=[$start->format('Y-m-d'),$end->format('Y-m-d')];
        $rows=DB::fetchAll("SELECT paid_on day,'Income' type,SUM(amount) amount FROM payments WHERE paid_on>=? AND paid_on<? GROUP BY paid_on UNION ALL SELECT occurred_on day,type,SUM(amount) amount FROM finance_entries WHERE occurred_on>=? AND occurred_on<? GROUP BY occurred_on,type",[...$range,...$range]);
        foreach ($rows as $row) {
            $key=$view==='year'?substr($row['day'],0,7):$row['day'];
            if (!isset($points[$key])) continue;
            $type=$row['type']==='Income'?'income':'expense';
            $points[$key][$type]+=(int)round((float)$row['amount']*100);
        }
        $totals=['income'=>0,'expense'=>0,'profit'=>0];
        foreach ($points as &$point) {
            $point['profit']=$point['income']-$point['expense'];
            foreach ($totals as $key=>$_) $totals[$key]+=$point[$key];
        }
        unset($point);
        return ['view'=>$view,'month'=>$month,'year'=>$year,'title'=>$start->format($view==='year'?'Y':'F Y'),'start'=>$range[0],'end'=>$range[1],'points'=>array_values($points),'totals'=>$totals];
    }
}
