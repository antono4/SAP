<?php
// ============================================================
// Finance & Accounting (FI/CO) - General Ledger
// ============================================================
$pdo = db();
$akun = $pdo->query("SELECT * FROM accounts ORDER BY code")->fetchAll();
$jurnal = $pdo->query("SELECT j.*,a.code AS account_code,a.name AS account_name FROM journal_entries j JOIN accounts a ON a.id=j.account_id ORDER BY j.id DESC LIMIT 50")->fetchAll();

$jurnal = $pdo->query("SELECT j.*,a.code AS account_code,a.name AS account_name FROM journal_entries j JOIN accounts a ON a.id=j.account_id ORDER BY j.id DESC LIMIT 50")->fetchAll();
$jurnal = $pdo->query("SELECT j.*,a.code AS account_code,a.name AS account_name FROM journal_entries j JOIN accounts a ON a.id=j.account_id ORDER BY j.id DESC LIMIT 50")->fetchAll();
$piutang =(float)$pdo->query("SELECT COALESCE(SUM(i.amount),0) FROM invoices i WHERE i.status='UNPAID'")->fetchColumn();
$utang =(float)$pdo->query("SELECT COALESCE(SUM(p.total_amount),0) FROM purchase_orders p WHERE p.status='RECEIVED' OR p.status='INVOICED'")->fetchColumn();
?>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-blue">
      <i class="bi bi-receipt stat-icon"></i>
      <div class="stat-label">Total Debit</div>
      <div class="stat-value"><?= nf($totalDebit) ?></div>
      <div class="stat-sub">Seluruh jurnal</div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-teal">
      <i class="bi bi-receipt-cutoff stat-icon"></i>
      <div class="stat-label">Total Kredit</div>
      <div class="stat-value"><?= nf($totalKredit) ?></div>
      <div class="stat-sub">Seluruh jurnal</div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-orange">
      <i class="bi bi-arrow-left-right stat-icon"></i>
      <div class="stat-label">Piutang Terbuka</div>
      <div class="stat-value"><?= nf($piutang) ?></div>
      <div class="stat-sub">AR belum lunas</div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-xl-3">
    <div class="stat-card bg-erp-purple">
      <i class="bi bi-arrow-left-right stat-icon"></i>
      <div class="stat-label">Utang Terbuka</div>
      <div class="stat-value"><?= nf($utang) ?></div>
      <div class="stat-sub">AP belum dibayar</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-book me-2 text-primary"></i> Chart of Accounts (FI</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th></tr></thead>
          <tbody>
          <?php foreach ($akun as $a): ?>
            <tr>
              <td class="text-mono"><?= esc($a['code']) ?></td>
              <td><?= esc($a['name']) ?></td>
              <td><span class="badge bg-light border text-dark"><?= esc($a['type']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-journal-text me-2 text-primary"></i> Jurnal Umum (FI-GL</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>No. Jurnal</th><th>Akun</th><th class="text-end">Debit</th><th class="text-end">Kredit</th><th>Keterangan</th><th>Tanggal</th></tr></thead>
          <tbody>
          <?php if (!$jurnal): ?>
            <tr><td colspan="6"><div class="erp-empty"><i class="bi bi-journal-text"></i>Belum ada jurnal</div></td></tr>
          <?php else: foreach ($jurnal as $j): ?>
            <tr>
              <td class="text-mono"><?= esc($j['entry_number']) ?></td>
              <td><span class="text-mono"><?= esc($j['account_code']) ?></span> — <?= esc($j['account_name']) ?></td>
              <td class="text-end"><?= $j['debit'] > 0 ? nf($j['debit']) : '—' ?></td>
              <td class="text-end"><?= $j['credit'] > 0 ? nf($j['credit']) : '—' ?></td>
              <td><?= esc($j['description']) ?></td>
              <td><?= esc($j['post_date']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>