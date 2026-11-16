<?php $query=\App\Receipts::queryString($filters); ?>
<div class="page-heading"><div><div class="eyebrow">SCHOOL WORKSPACE</div><h1>Enrollment receipts<span class="green">.</span></h1><p>Deposits, installments, paid and FOC enrollments, with totals matching your filters.</p></div><?php if($filters['errors']): ?><button class="button" disabled>Export CSV</button><?php else: ?><a class="button" download href="<?= e(url('export/fees'.($query?'?'.$query:''))) ?>"><?= icon('download') ?> Export CSV</a><?php endif ?></div>
<div class="stats-grid three"><div class="stat-card"><span>Receipts</span><strong><?= $stats['receipts'] ?></strong></div><div class="stat-card"><span>FOC enrollments</span><strong><?= $stats['foc'] ?></strong></div><div class="stat-card"><span>Income received</span><strong><?= money($stats['income']/100) ?></strong></div></div>
<section class="panel">
    <div class="panel-heading"><div><h2>Enrollment receipts</h2><p>Filter by enrollment date. Earlier receipts without an enrollment use their due date.</p></div><span class="badge neutral"><?= count($invoices) ?> receipts</span></div>
    <form method="get" class="receipt-filters" aria-label="Filter enrollment receipts" data-receipt-filters>
        <div class="receipt-filter-scroll"><div class="receipt-filter-fields">
            <label class="receipt-filter-search">Search<input name="q" maxlength="150" placeholder="Student, phone, invoice, or class" value="<?= e($filters['q']) ?>"></label>
            <label>Class<select name="class" data-searchable data-receipt-class><option value="">All classes</option><?php foreach($classes as $class): ?><option value="<?= $class['id'] ?>" <?= $filters['class']==$class['id']?'selected':'' ?>><?= e($class['name']) ?></option><?php endforeach ?></select></label>
            <label>Batch<select name="batch" data-searchable data-receipt-batch><option value="">All batches</option><?php foreach($batches as $batch): ?><option value="<?= $batch['id'] ?>" data-class="<?= $batch['class_id'] ?>" <?= $filters['batch']==$batch['id']?'selected':'' ?> <?= $filters['class'] && $filters['class']!=$batch['class_id']?'disabled hidden':'' ?>><?= e($batch['class_name'].' / '.$batch['name']) ?></option><?php endforeach ?></select></label>
            <label>Payment status<select name="status"><option value="">All statuses</option><?php foreach(['Paid','FOC','Unpaid','Partial','Overdue'] as $status): ?><option <?= $filters['status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach ?></select></label>
            <label class="receipt-filter-date">From date<input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
            <label class="receipt-filter-date">To date<input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
        </div></div>
        <div class="receipt-filter-actions"><button class="button primary">Apply filters</button><a class="button" href="<?= e(url('fees')) ?>">Reset</a></div>
    </form>
    <?php if($filters['errors']): ?><div class="receipt-filter-error" role="alert"><?= e(implode(' ',$filters['errors'])) ?></div><?php endif ?>
    <div class="table-wrap"><table><thead><tr><th>Student / receipt</th><th>Class / batch</th><th>Enrollment date</th><th>Fee / FOC</th><th>Paid</th><th>Balance</th><th>Status</th><th>Receipt</th></tr></thead><tbody>
        <?php foreach($invoices as $invoice): ?><tr>
            <td><a class="text-link" href="<?= e(url('students/'.$invoice['student_id'])) ?>"><?= e($invoice['name']) ?></a><small><?= e($invoice['phone']) ?> · INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?></small></td>
            <td><?= e($invoice['class_name']) ?><?php if($invoice['description']!==$invoice['class_name']): ?><small><?= e($invoice['description']) ?></small><?php endif ?></td>
            <td><?= e($invoice['receipt_date']) ?></td><td><?= $invoice['is_foc']?'FOC':money($invoice['amount']) ?></td><td><?= money($invoice['paid']) ?></td><td><?= money($invoice['balance']) ?></td>
            <td><span class="badge <?= $invoice['is_foc']?'neutral':($invoice['payment_status']==='Paid'?'success':($invoice['payment_status']==='Overdue'?'danger':'warning')) ?>"><?= e($invoice['payment_status']) ?></span></td>
            <td><a class="button small" href="<?= e(url('invoices/'.$invoice['id'].'/print')) ?>" target="_blank" rel="noopener">Print A4</a> <a class="button small" href="<?= e(url('invoices/'.$invoice['id'].'/payments')) ?>">Payments</a></td>
        </tr><?php endforeach ?>
        <?php if(!$invoices): ?><tr><td colspan="8" class="empty"><?= $filters['errors']?'Correct the date filters to view receipts.':($query?'No receipts match these filters. Reset the filters or choose another date range.':'No receipts yet. Enroll a student from a class batch to get started.') ?></td></tr><?php endif ?>
    </tbody></table></div>
</section>
