<?php
$report=\App\FinanceReport::build($_GET);
$points=$report['points'];
$series=['income'=>'Income','expense'=>'Outcome (expenses)','profit'=>'Profit'];
$values=[0]; foreach($points as $point) foreach($series as $key=>$_) $values[]=$point[$key]/100;
$minimum=min($values); $maximum=max($values);
$range=max(1,$maximum-$minimum);
$power=10**floor(log10($range/5));
$fraction=$range/5/$power;
$step=($fraction<=1?1:($fraction<=2?2:($fraction<=5?5:10)))*$power;
$low=floor($minimum/$step)*$step;
$high=ceil($maximum/$step)*$step;
if ($low===$high) $high=$low+$step;
$left=80; $right=936; $top=20; $bottom=280;
$x=fn($index)=>$left+($right-$left)*$index/max(1,count($points)-1);
$y=fn($value)=>$bottom-($value-$low)/($high-$low)*($bottom-$top);
$compact=static function(float $value): string {
    foreach ([1000000000=>'B',1000000=>'M',1000=>'K'] as $divisor=>$suffix) if (abs($value)>=$divisor) return rtrim(rtrim(number_format($value/$divisor,2,'.',''),'0'),'.').$suffix;
    return number_format($value,abs($value-round($value))<0.000001?0:2);
};
?>
<section class="panel finance-report" aria-labelledby="finance-report-heading">
    <div class="panel-heading"><div><h2 id="finance-report-heading">Income, outcome &amp; profit</h2><p><?= $report['view']==='year'?'Monthly totals':'Daily totals' ?> for <?= e($report['title']) ?> · <?= e(school()['currency']) ?>. Profit = income − expenses. FOC adds no income.</p></div></div>
    <form method="get" action="<?= e(url('#finance-report-heading')) ?>" class="filters report-filters" data-report-filters>
        <label>View<select name="view"><option value="month" <?= $report['view']==='month'?'selected':'' ?>>In a month</option><option value="year" <?= $report['view']==='year'?'selected':'' ?>>In a year</option></select></label>
        <label data-report-month <?= $report['view']==='year'?'hidden':'' ?>>Month<input type="month" name="month" value="<?= e($report['month']) ?>" min="1000-01" max="9998-12"></label>
        <label data-report-year <?= $report['view']==='month'?'hidden':'' ?>>Year<input type="number" name="year" value="<?= (int)$report['year'] ?>" min="1000" max="9998" step="1"></label>
        <button class="button primary">Update chart</button>
        <a class="text-link" href="<?= e(url('#finance-report-heading')) ?>">Current month</a>
    </form>
    <div class="stats-grid three report-totals"><?php foreach($series as $key=>$label): ?><div class="stat-card"><span><?= e($label) ?></span><strong><?= money($report['totals'][$key]/100) ?></strong></div><?php endforeach ?></div>
    <div class="report-legend" aria-label="Chart legend"><?php foreach($series as $key=>$label): ?><span><i class="report-swatch report-<?= $key ?>" aria-hidden="true"></i><?= e($label) ?></span><?php endforeach ?></div>
    <?php if(!array_filter($points,fn($point)=>$point['income']!==0 || $point['expense']!==0)): ?><p class="report-empty">No income or expenses recorded for this period.</p><?php endif ?>
    <div class="report-chart-scroll">
        <svg class="report-chart" viewBox="0 0 960 320" role="group" aria-labelledby="report-chart-title report-chart-description">
            <title id="report-chart-title">Income, outcome and profit — <?= e($report['title']) ?></title>
            <desc id="report-chart-description"><?= $report['view']==='year'?'One point per month':'One point per day' ?>. Income includes enrollment payments and other income. Outcome includes recorded expenses. Profit can be negative. Focus or hover a point for exact amounts.</desc>
            <?php for($tick=$low,$i=0; $tick<=$high+$step/100 && $i<12; $tick+=$step,$i++): ?>
                <line class="report-grid <?= abs($tick)<$step/100?'report-zero':'' ?>" x1="<?= $left ?>" x2="<?= $right ?>" y1="<?= $y($tick) ?>" y2="<?= $y($tick) ?>" />
                <text class="report-axis" x="<?= $left-12 ?>" y="<?= $y($tick)+4 ?>" text-anchor="end"><?= e($compact($tick)) ?></text>
            <?php endfor ?>
            <?php foreach($series as $key=>$label): ?>
                <polyline class="report-line report-<?= $key ?>" points="<?= e(implode(' ',array_map(fn($index)=>round($x($index),2).','.round($y($points[$index][$key]/100),2),array_keys($points)))) ?>" />
            <?php endforeach ?>
            <?php foreach($points as $index=>$point): $half=($right-$left)/max(1,count($points)-1)/2; ?>
                <?php if($report['view']==='year' || $index%3===0 || $index===count($points)-1): ?><text class="report-axis" x="<?= $x($index) ?>" y="304" text-anchor="middle"><?= e($point['label']) ?></text><?php endif ?>
                <g class="report-point" tabindex="0" role="img" data-report-point="<?= $index ?>" aria-label="<?= e($point['description'].': Income '.strip_tags(money($point['income']/100)).', outcome '.strip_tags(money($point['expense']/100)).', profit '.strip_tags(money($point['profit']/100))) ?>">
                    <title><?= e($point['description']) ?>: Income <?= money($point['income']/100) ?>; outcome <?= money($point['expense']/100) ?>; profit <?= money($point['profit']/100) ?></title>
                    <rect x="<?= max($left,$x($index)-$half) ?>" y="<?= $top ?>" width="<?= min($right,$x($index)+$half)-max($left,$x($index)-$half) ?>" height="<?= $bottom-$top ?>" fill="transparent" />
                    <line class="report-guide" x1="<?= $x($index) ?>" x2="<?= $x($index) ?>" y1="<?= $top ?>" y2="<?= $bottom ?>" />
                    <?php foreach($series as $key=>$_): ?><circle class="report-dot report-<?= $key ?>" cx="<?= $x($index) ?>" cy="<?= $y($point[$key]/100) ?>" r="4" /><?php endforeach ?>
                </g>
            <?php endforeach ?>
        </svg>
    </div>
    <output class="report-readout" data-report-readout aria-live="polite">Hover or focus a point to see exact amounts.</output>
    <details class="report-data"><summary>View chart data</summary><div class="table-wrap"><table><thead><tr><th><?= $report['view']==='year'?'Month':'Day' ?></th><th>Income</th><th>Outcome (expenses)</th><th>Profit</th></tr></thead><tbody><?php foreach($points as $point): ?><tr><td><?= e($point['description']) ?></td><?php foreach($series as $key=>$_): ?><td><?= money($point[$key]/100) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div></details>
</section>
<script type="application/json" id="finance-report-data"><?= json_encode($report+['currency'=>school()['currency']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
