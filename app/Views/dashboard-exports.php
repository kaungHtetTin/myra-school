<section class="section-gap" id="dashboard-exports" aria-labelledby="dashboard-exports-heading">
    <div class="panel-heading"><h2 id="dashboard-exports-heading">Exports</h2></div>
    <div class="cards-grid reports-grid">
        <?php foreach([
            ['students','students','Student directory','Student contact details, origins, notes, and class enrollments.'],
            ['fees','wallet','Enrollment receipts','All receipts, including paid amounts, FOC enrollments, and earlier balances.'],
        ] as [$key,$ico,$label,$description]): ?>
            <article class="panel report-card"><span class="stat-icon mint"><?= icon($ico) ?></span><h2><?= e($label) ?></h2><p><?= e($description) ?></p><a class="button" download href="<?= e(url('export/'.$key)) ?>"><?= icon('download') ?> Download CSV</a></article>
        <?php endforeach ?>
    </div>
    <div class="info-note"><?= icon('book') ?><p>Dashboard exports include all saved records, regardless of the chart period. Dates use YYYY-MM-DD. Currency: <?= e(school()['currency']) ?>.</p></div>
</section>
