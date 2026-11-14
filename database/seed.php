<?php
declare(strict_types=1);

use Mini\Database as DB;

// Stable identifiers make reruns safe. Existing records are never overwritten.
$created=['students'=>0,'classes'=>0,'batches'=>0];
$today=date('Y-m-d');
$endsOn=date('Y-m-d',strtotime('+8 weeks'));
DB::transaction(function() use(&$created,$today,$endsOn) {
    $names=['Aye Chan','May Thazin','Htet Aung','Su Myat','Kyaw Zin','Thiri Mon','Min Khant','Ei Phyu','Zaw Lin','Nandar Hlaing'];
    $origins=['Facebook','TikTok','Telegram','Phone','Other'];
    foreach ($names as $index=>$name) {
        $number=str_pad((string)($index+1),2,'0',STR_PAD_LEFT);
        $admission='ST-DEMO-'.$number;
        if (DB::fetchValue('SELECT id FROM students WHERE admission_no=?',[$admission])) continue;
        $origin=$origins[$index%count($origins)];
        $phone='09-0000-00'.$number;
        DB::insert('students',[
            'admission_no'=>$admission,'name'=>$name,'phone'=>$phone,
            'address'=>'Sample address '.($index+1).', Yangon',
            'origin'=>$origin,'account'=>$origin==='Phone'?$phone:'https://example.com/student-'.$number,
            'status'=>'Active','enrolled_on'=>$today,
        ]);
        $created['students']++;
    }
    $courses=[
        'Demo English'=>'100000.00',
        'Demo Mathematics'=>'120000.00',
        'Demo Computer Basics'=>'150000.00',
        'Demo Graphic Design'=>'180000.00',
        'Demo Digital Marketing'=>'200000.00',
    ];
    foreach ($courses as $name=>$fee) {
        $classId=(int)DB::fetchValue('SELECT id FROM classes WHERE name=?',[$name]);
        if (!$classId) {
            $classId=DB::insert('classes',['name'=>$name,'capacity'=>20,'fee'=>$fee]);
            $created['classes']++;
        }
        foreach (['Demo Morning','Demo Evening'] as $batchName) {
            if (DB::fetchValue('SELECT id FROM batches WHERE class_id=? AND name=?',[$classId,$batchName])) continue;
            DB::insert('batches',[
                'class_id'=>$classId,'name'=>$batchName,'capacity'=>20,
                'starts_on'=>$today,'ends_on'=>$endsOn,'status'=>'Ongoing',
            ]);
            $created['batches']++;
        }
    }
});
echo 'Created '.$created['students'].' students, '.$created['classes'].' classes, and '.$created['batches']." ongoing batches.\n";
echo "Demo data is ready. Rerunning db:seed skips existing demo records.\n";
