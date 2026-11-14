<section class="panel">
    <div class="panel-heading">
        <div><h2>Batch students &amp; receipts</h2><p>Student details, paid or FOC enrollment, and receipts in one place.</p></div>
        <a class="text-link" href="<?= e(url('students?new=1&batch='.$batch['id'])) ?>">Create student profile</a>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Student / phone</th><th>Enrolled</th><th>Fee / FOC</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Enrollment status</th><th>Receipt</th></tr></thead>
        <tbody>
        <?php foreach($enrollments as $en): $receipt=$receipts[$en['id']]??null; $settled=$receipt && $receipt['paid']>=$receipt['total']; ?>
            <tr>
                <td><a class="text-link" href="<?= e(url('students/'.$en['student_id'])) ?>"><?= e($en['name']) ?></a><small><?= e($en['phone']) ?></small><?php if($en['student_status']!=='Active'): ?><small>Archived student</small><?php endif ?></td>
                <td><?= e($en['enrolled_on']) ?></td>
                <td><?= $en['is_foc']?'FOC':($receipt?money($receipt['total']):'Not recorded') ?></td>
                <td><?= $receipt?money($receipt['paid']):'—' ?></td>
                <td><?= $receipt?money($receipt['total']-$receipt['paid']):'—' ?></td>
                <td><span class="badge <?= $en['is_foc']?'neutral':($settled?'success':'warning') ?>"><?= $en['is_foc']?'FOC':($settled?'Paid':($receipt?'Earlier balance':'No receipt')) ?></span></td>
                <td><form method="post" class="row-actions" action="<?= e(url('learning/enrollment-status')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $en['id'] ?>"><input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                    <select name="status" aria-label="Enrollment status for <?= e($en['name']) ?>"><?php foreach(['Active','Completed','Withdrawn'] as $v): ?><option <?= $en['status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach ?></select>
                    <button class="button small">Save</button>
                </form></td>
                <td><?php foreach($receipt['invoices']??[] as $invoice): ?>
                    <div class="batch-receipt-link"><a class="text-link" href="<?= e(url('invoices/'.$invoice['id'].'/print')) ?>" target="_blank" rel="noopener" aria-label="Print invoice INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?> for <?= e($en['name']) ?> (opens a new tab)">Print A4<?php if(count($receipt['invoices'])>1): ?> · INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?><?php endif ?></a><small>Due <?= e($invoice['due_date']) ?></small></div>
                <?php endforeach ?><?php if(!$receipt): ?><span class="muted">No receipt</span><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        <?php if(!$enrollments): ?><tr><td colspan="8" class="empty">No students enrolled yet.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</section>
