<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($number) ?> · <?= e($invoice['name']) ?> · <?= e(school()['school_name']) ?></title>
    <link rel="icon" href="<?= e(school_favicon()) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/invoice.css')) ?>">
    <script src="<?= e(asset('js/invoice.js')) ?>" defer></script>
</head>
<body>
<nav class="print-toolbar" aria-label="Invoice actions">
    <a href="<?= e(url('fees')) ?>">← Fees & payments</a>
    <span>A4 portrait · Print or save as PDF</span>
    <button type="button" data-print>Print invoice</button>
</nav>
<main class="invoice-sheet">
    <header class="invoice-header">
        <div class="school-identity"><span class="school-mark"><?= school_logo() ?></span><div><h1><?= e(school()['school_name']) ?></h1><p>Academic year <?= e(school()['school_year']) ?></p></div></div>
        <div class="invoice-title"><p>SCHOOL FEE INVOICE</p><h2><?= e($number) ?></h2><span class="invoice-status"><?= e($status) ?></span></div>
    </header>
    <section class="invoice-details" aria-label="Student and invoice details">
        <div><h3>Student details</h3><strong><?= e($invoice['name']) ?></strong><dl><dt>Student ID</dt><dd><?= e($invoice['admission_no']) ?></dd><dt>Class</dt><dd><?= e($invoice['class_name']) ?></dd><dt>Guardian</dt><dd><?= e($invoice['guardian']) ?></dd><dt>Contact</dt><dd><?= e($invoice['phone']) ?></dd></dl></div>
        <div><h3>Invoice details</h3><dl><dt>Invoice number</dt><dd><?= e($number) ?></dd><dt>Due date</dt><dd><?= e(date('d M Y', strtotime($invoice['due_date']))) ?></dd><dt>Printed on</dt><dd><?= e(date('d M Y, H:i')) ?></dd><dt>Currency</dt><dd><?= e(school()['currency']) ?></dd></dl></div>
    </section>
    <table class="invoice-items"><thead><tr><th scope="col">Description</th><th scope="col" class="amount">Amount</th></tr></thead><tbody><tr><td><?= e($invoice['description']) ?></td><td class="amount"><?= money($invoice['amount']) ?></td></tr></tbody></table>
    <section class="invoice-totals" aria-label="Invoice totals"><dl><dt>Invoice total</dt><dd><?= money($invoice['amount']) ?></dd><dt>Total paid</dt><dd><?= money($paid) ?></dd><dt class="balance">Balance due</dt><dd class="balance"><?= money($balance) ?></dd></dl></section>
    <section class="payment-history" aria-label="Payment history"><h3>Payment history</h3><table><thead><tr><th scope="col">Receipt</th><th scope="col">Payment date</th><th scope="col">Method</th><th scope="col" class="amount">Amount paid</th></tr></thead><tbody><?php foreach($payments as $payment): ?><tr><td>REC-<?= str_pad((string)$payment['id'], 4, '0', STR_PAD_LEFT) ?></td><td><?= e(date('d M Y', strtotime($payment['paid_on']))) ?></td><td><?= e($payment['method']) ?></td><td class="amount"><?= money($payment['amount']) ?></td></tr><?php endforeach ?><?php if (!$payments): ?><tr><td colspan="4" class="no-payments">No payments recorded for this invoice.</td></tr><?php endif ?></tbody></table></section>
    <footer class="invoice-footer"><p>Amounts reflect payments recorded at the time of printing.<br>Please quote <?= e($number) ?> when making a payment.</p><div class="signature">Authorized signature / school stamp</div><p class="thank-you">Thank you for supporting your child’s education.</p></footer>
</main>
</body>
</html>
