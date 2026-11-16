<section class="panel">
    <div class="panel-heading">
        <div><h2>Batch students &amp; receipts</h2><p>Student details, deposits, remaining balances, and receipts in one place.</p></div>
        <a class="text-link" href="<?= e(url('students?new=1&batch='.$batch['id'])) ?>">Create student profile</a>
    </div>
    <div class="table-wrap batch-students-wrap"><table>
        <colgroup><col style="width:13%"><col style="width:9%"><col style="width:10%"><col style="width:10%"><col style="width:10%"><col style="width:8%"><col style="width:18%"><col style="width:9%"><col style="width:13%"></colgroup>
        <thead><tr><th>Student / phone</th><th>Enrolled</th><th>Fee / FOC</th><th>Paid</th><th>Balance</th><th>Payment</th><th>Enrollment status</th><th>Due date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($enrollments as $en): $receipt=$receipts[$en['id']]??null; $settled=$receipt && $receipt['paid']>=$receipt['total']; ?>
            <tr>
                <td><a class="text-link student-detail-link" href="<?= e(url('students/'.$en['student_id'])) ?>" title="View student details"><?= e($en['name']) ?></a><small><?= e($en['phone']) ?></small><?php if($en['student_status']!=='Active'): ?><small>Archived student</small><?php endif ?></td>
                <td><?= e($en['enrolled_on']) ?></td>
                <td><?= $en['is_foc']?'FOC':($receipt?money($receipt['total']):'Not recorded') ?></td>
                <td><?= $receipt?money($receipt['paid']):'—' ?></td>
                <td><?= $receipt?money($receipt['total']-$receipt['paid']):'—' ?></td>
                <td><div class="batch-payment"><span class="badge <?= $en['is_foc']?'neutral':($settled?'success':'warning') ?>"><?= $en['is_foc']?'FOC':($settled?'Paid':($receipt?($receipt['paid']>0?'Partial':'Unpaid'):'No receipt')) ?></span>

                </div></td>
                <td><form method="post" class="enrollment-status-form" data-enrollment-status action="<?= e(url('learning/enrollment-status')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $en['id'] ?>"><input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">
                    <select name="status" data-current-status="<?= e($en['status']) ?>" aria-label="Enrollment status for <?= e($en['name']) ?>"><?php foreach(['Active','Completed','Withdrawn'] as $v): ?><option <?= $en['status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach ?></select>
                    <div class="enrollment-status-actions"><button type="submit" class="button small primary" title="Save status" aria-label="Save enrollment status for <?= e($en['name']) ?>"><?= icon('check') ?></button><button type="reset" class="button small" aria-label="Cancel status change for <?= e($en['name']) ?>" title="Cancel status change" hidden><span aria-hidden="true">&times;</span></button></div>
                </form></td>
                <td><?php foreach($receipt['invoices']??[] as $invoice): ?><div class="batch-invoice-due"><span><?= e($invoice['due_date']) ?></span><?php if(count($receipt['invoices'])>1): ?><small>INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?></small><?php endif ?></div><?php endforeach ?><?php if(!$receipt): ?><span class="muted">&mdash;</span><?php endif ?></td>
                <td><?php foreach($receipt['invoices']??[] as $invoice): ?>
                    <div class="batch-receipt-link"><div class="batch-receipt-actions">
                        <?php if(!$en['is_foc']): $hasBalance=(float)$invoice['amount']>(float)$invoice['paid']; ?><a class="button small <?= $hasBalance?'primary':'' ?>" href="<?= e(url('invoices/'.$invoice['id'].'/payments')) ?>" aria-label="<?= $hasBalance?'Collect payment':'View payments' ?> for <?= e($en['name']) ?>, invoice <?= (int)$invoice['id'] ?>" title="<?= $hasBalance?'Collect payment':'View payment history' ?>"><?= icon($hasBalance?'plus':'wallet') ?><?= $hasBalance?'Collect':'History' ?></a><?php endif ?>
                        <a class="button small" href="<?= e(url('invoices/'.$invoice['id'].'/print')) ?>" target="_blank" rel="noopener" aria-label="Print invoice INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?> for <?= e($en['name']) ?> (opens a new tab)" title="Print A4">Print</a>
                    </div><?php if(count($receipt['invoices'])>1): ?><small>INV-<?= str_pad((string)$invoice['id'],4,'0',STR_PAD_LEFT) ?></small><?php endif ?></div>
                <?php endforeach ?><?php if(!$receipt): ?><span class="muted">No receipt</span><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        <?php if(!$enrollments): ?><tr><td colspan="9" class="empty">No students enrolled yet.</td></tr><?php endif ?>
        </tbody>
    </table></div>
</section>
