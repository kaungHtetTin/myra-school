<?php
$originTotal=array_sum($origins);
$originItems=[];
foreach($origins as $name=>$count) $originItems[]=['name'=>$name,'count'=>$count,'percentage'=>$originTotal?round(100*$count/$originTotal,1):0];
$angle=-M_PI/2;
$coordinate=static fn(float $value)=>number_format($value,4,'.','');
?>
<section class="panel student-origin-panel" aria-labelledby="student-origin-heading">
    <div class="panel-heading"><div><h2 id="student-origin-heading">Student origins</h2><p>Where active students first heard about your school. Each student is counted once.</p></div><span class="badge neutral"><?= $originTotal ?> active students</span></div>
    <div class="student-origin-body">
        <svg class="student-origin-pie" viewBox="0 0 220 220" role="group" aria-labelledby="student-origin-title student-origin-desc">
            <title id="student-origin-title">Student origins pie chart</title>
            <desc id="student-origin-desc">Active students by Facebook, TikTok, Telegram, Phone, and Other. Counts and percentages are also listed alongside the chart.</desc>
            <?php if(!$originTotal): ?><circle class="student-origin-empty" cx="110" cy="110" r="90" /><text class="student-origin-empty-total" x="110" y="108" text-anchor="middle">0</text><text class="student-origin-empty-label" x="110" y="132" text-anchor="middle">Active students</text><?php endif ?>
            <?php foreach($originItems as $index=>$item): if (!$item['count']) continue; $nextAngle=$angle+2*M_PI*$item['count']/$originTotal; $description=$item['name'].': '.$item['count'].' students ('.$item['percentage'].'%)'; ?>
                <g class="student-origin-slice origin-color-<?= $index ?>" tabindex="0" role="img" aria-label="<?= e($description) ?>" data-origin-index="<?= $index ?>">
                    <title><?= e($description) ?></title>
                    <?php if($item['count']===$originTotal): ?><circle cx="110" cy="110" r="90" /><?php else: ?>
                        <path d="M 110 110 L <?= $coordinate(110+90*cos($angle)) ?> <?= $coordinate(110+90*sin($angle)) ?> A 90 90 0 <?= $nextAngle-$angle>M_PI?'1':'0' ?> 1 <?= $coordinate(110+90*cos($nextAngle)) ?> <?= $coordinate(110+90*sin($nextAngle)) ?> Z" />
                    <?php endif ?>
                </g>
            <?php $angle=$nextAngle; endforeach ?>
        </svg>
        <div class="student-origin-legend" aria-label="Student origin counts and percentages">
            <div class="student-origin-legend-heading"><span>Origin</span><span>Students</span><span>Share</span></div>
            <?php foreach($originItems as $index=>$item): ?><button type="button" class="student-origin-row" data-origin-index="<?= $index ?>" aria-label="<?= e($item['name'].': '.$item['count'].' students, '.$item['percentage'].' percent') ?>"><span><i class="origin-color-<?= $index ?>" aria-hidden="true"></i><?= e($item['name']) ?></span><strong><?= $item['count'] ?></strong><span><?= e($item['percentage']) ?>%</span></button><?php endforeach ?>
        </div>
    </div>
    <output class="student-origin-readout" data-origin-readout aria-live="polite"><?= $originTotal?'Hover or focus an origin to see its share.':'No active students yet. Add a student to see the origin breakdown.' ?></output>
</section>
<script type="application/json" id="student-origin-data"><?= json_encode(['total'=>$originTotal,'items'=>$originItems],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
