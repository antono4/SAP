<?php
// ============================================================
// Sales & Distribution (SD) - Order-to-Cash
// Aksi: create_so, create_delivery, issue, receipt, pay, cancel
// ============================================================
$pdo = db();
$msg = '';
$err = '';

if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    try {
        $id = (int)$_GET['id'];
        $st = $pdo->prepare("UPDATE sales_orders SET status='CANCELLED' WHERE id=?");
        $st->execute([$id]);
        $msg = 'Sales order dibatalkan.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// Dukung aksi issue/pay lewat GET (link tombol)
$viaGet = isset($_GET['action']) && in_array($_GET['action'], ['issue', 'pay']);
if ($viaGet) {
    $_POST['action'] = $_GET['action'];
    $_POST['id'] = (int)$_GET['id'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        $pdo->beginTransaction();

        if ($action === 'create_so') {
            $cust = (int)$_POST['customer_id'];
            $date = $_POST['order_date'];
            if ($cust <= 0) { throw new Exception('Pilih pelanggan.'); }
            if (empty($_POST['items'])) { throw new Exception('Tambahkan minimal satu item.'); }
            $items = $_POST['items'];
            $soNo = doc_number('SO');
            $total = 0;
            $st = $pdo->prepare("INSERT INTO sales_orders (so_number,customer_id,order_date,status,total_amount) VALUES (?,?,?,?,?)");
            $st->execute([$soNo,$cust,$date,'CONFIRMED',0]);
            $soId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sales_order_items (so_id,material_id,quantity,price,subtotal) VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                $mid = isset($it['material_id']) ? (int)$it['material_id'] : 0;
                $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
                $price = isset($it['price']) ? (float)$it['price'] : 0;
                if ($mid > 0 && $qty > 0) {
                    $sub = $qty * $price;
                    $total = $total + $sub;
                    $ins->execute([$soId,$mid,$qty,$price,$sub]);
                }
            }
            if ($total <= 0) { throw new Exception('Total tidak boleh nol.'); }
            $st = $pdo->prepare("UPDATE sales_orders SET total_amount=? WHERE id=?");
            $st->execute([$total,$soId]);
            $msg = 'Sales Order ' . $soNo . ' dibuat (status CONFIRMED.';
        }

        if ($action === 'create_delivery') {
            $soId = (int)$_POST['so_id'];
            if ($soId <= 0) { throw new Exception('SO tidak valid.'); }
            $st = $pdo->prepare("SELECT status FROM sales_orders WHERE id=?");
            $st->execute([$soId]);
            $so = $st->fetch();
            if (!$so) { throw new Exception('SO tidak ditemukan.'); }
            if ($so['status'] === 'CANCELLED') { throw new Exception('SO sudah dibatalkan.'); }
            $dlvNo = doc_number('DLV');
            $st = $pdo->prepare("INSERT INTO deliveries (so_id,delivery_number,delivery_date,status) VALUES (?,?,?,?)");
            $st->execute([$soId,$dlvNo,today(),'OPEN']);
            $st = $pdo->prepare("UPDATE sales_orders SET status='DELIVERED' WHERE id=?");
            $st->execute([$soId]);
            $msg = 'Delivery ' . $dlvNo . ' dibuat.';
        }

        if ($action === 'issue') {
            $deliveryId = (int)$_POST['id'];
            $st = $pdo->prepare("SELECT d.id,d.so_id,d.delivery_number FROM deliveries d WHERE d.id=?");
            $st->execute([$deliveryId]);
            $dlv = $st->fetch();
            if (!$dlv) { throw new Exception('Delivery tidak ditemukan.'); }
            $dlvNo = $dlv['delivery_number'];
            $dlvSoId = (int)$dlv['so_id'];
            $st = $pdo->prepare("SELECT i.material_id,i.quantity,i.price FROM sales_order_items i WHERE i.so_id=?");
            $st->execute([$dlvSoId]);
            $items = $st->fetchAll();
            $dec = $pdo->prepare("UPDATE materials SET stock=stock-? WHERE id=? AND stock>=?");
            $mv = $pdo->prepare("INSERT INTO goods_movements (material_id,movement_type,quantity,reference) VALUES (?,?,?,?)");
            $hppTotal = 0;
            foreach ($items as $it) {
                $mid = (int)$it['material_id'];
                $qty = (int)$it['quantity'];
                $dec->execute([$qty,$mid,$qty]);
                if ($dec->rowCount() === 0) { throw new Exception('Stok tidak cukup.'); }
                $mv->execute([$mid,'201',$qty,$dlvNo]);
                $hppTotal = $hppTotal + ((float)$it['price'] * $qty);
            }
            $hppId = (int)$pdo->query("SELECT id FROM accounts WHERE code='410000'")->fetchColumn();
            $invAssetId = (int)$pdo->query("SELECT id FROM accounts WHERE code='120000'")->fetchColumn();
            $je = $pdo->prepare("INSERT INTO journal_entries (entry_number,account_id,debit,credit,description,post_date) VALUES (?,?,?,?,?,?)");
            $je->execute([doc_number('JE'),$hppId,$hppTotal,0,'HPP '.$dlvNo,today()]);
            $je->execute([doc_number('JE'),$invAssetId,0,$hppTotal,'Persediaan keluar '.$dlvNo,today()]);
            $st = $pdo->prepare("UPDATE deliveries SET status='GOODS_ISSUED' WHERE id=?");
            $st->execute([$deliveryId]);
            $st = $pdo->prepare("UPDATE sales_orders SET status='INVOICED' WHERE id=?");
            $st->execute([$dlvSoId]);
            $msg = 'Goods Issue diposting. Stok & jurnal HPP ter-update.';
        }

        if ($action === 'receipt') {
            $soId = (int)$_POST['so_id'];
            if ($soId <= 0) { throw new Exception('SO tidak valid.'); }
            $st = $pdo->prepare("SELECT s.id,s.total_amount,s.status FROM sales_orders s WHERE s.id=?");
            $st->execute([$soId]);
            $so = $st->fetch();
            if (!$so) { throw new Exception('SO tidak ditemukan.'); }
            if ($so['status'] === 'CANCELLED') { throw new Exception('SO dibatalkan.'); }
            $st = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE so_id=?");
            $st->execute([$soId]);
            if ((int)$st->fetchColumn() > 0) { throw new Exception('Invoice sudah ada.'); }
            $st = $pdo->prepare("SELECT id FROM deliveries WHERE so_id=?");
            $st->execute([$soId]);
            if (!$st->fetch()) {
                $dlvNo = doc_number('DLV');
                $st = $pdo->prepare("INSERT INTO deliveries (so_id,delivery_number,delivery_date,status) VALUES (?,?,?,?)");
                $st->execute([$soId,$dlvNo,today(),'GOODS_ISSUED']);
                $st = $pdo->prepare("SELECT material_id,quantity FROM sales_order_items WHERE so_id=?");
                $st->execute([$soId]);
                $items = $st->fetchAll();
                $dec = $pdo->prepare("UPDATE materials SET stock=stock-? WHERE id=?");
                $mv = $pdo->prepare("INSERT INTO goods_movements (material_id,movement_type,quantity,reference) VALUES (?,?,?,?)");
                foreach ($items as $it) {
                    $mid = (int)$it['material_id'];
                    $qty = (int)$it['quantity'];
                    $dec->execute([$qty,$mid]);
                    $mv->execute([$mid,'201',$qty,$dlvNo]);
                }
            }
            $invNo = doc_number('INV');
            $amt = (float)$so['total_amount'];
            $st = $pdo->prepare("INSERT INTO invoices (so_id,invoice_number,invoice_date,amount,status) VALUES (?,?,?,?,?)");
            $st->execute([$soId,$invNo,today(),$amt,'UNPAID']);
            $arId = (int)$pdo->query("SELECT id FROM accounts WHERE code='110000'")->fetchColumn();
            $revId = (int)$pdo->query("SELECT id FROM accounts WHERE code='400000'")->fetchColumn();
            $je = $pdo->prepare("INSERT INTO journal_entries (entry_number,account_id,debit,credit,description,post_date) VALUES (?,?,?,?,?,?)");
            $je->execute([doc_number('JE'),$arId,$amt,0,'Invoice '.$invNo,today()]);
            $je->execute([doc_number('JE'),$revId,0,$amt,'Pendapatan '.$invNo,today()]);
            $st = $pdo->prepare("UPDATE sales_orders SET status='INVOICED' WHERE id=?");
            $st->execute([$soId]);
            $msg = 'Invoice ' . $invNo . ' dibuat & jurnal diposting.';
        }

        if ($action === 'pay') {
            $invId = (int)$_POST['id'];
            $st = $pdo->prepare("SELECT i.id,i.invoice_number,i.amount,s.id AS so_id FROM invoices i JOIN sales_orders s ON s.id=i.so_id WHERE i.id=?");
            $st->execute([$invId]);
            $inv = $st->fetch();
            if (!$inv) { throw new Exception('Invoice tidak ditemukan.'); }
            $invNo = $inv['invoice_number'];
            $amt = (float)$inv['amount'];
            $soId = (int)$inv['so_id'];
            $st = $pdo->prepare("UPDATE invoices SET status='PAID',paid_date=? WHERE id=?");
            $st->execute([today(),$invId]);
            $st = $pdo->prepare("UPDATE sales_orders SET status='PAID' WHERE id=?");
            $st->execute([$soId]);
            $cashId = (int)$pdo->query("SELECT id FROM accounts WHERE code='100000'")->fetchColumn();
            $arId = (int)$pdo->query("SELECT id FROM accounts WHERE code='110000'")->fetchColumn();
            $je = $pdo->prepare("INSERT INTO journal_entries (entry_number,account_id,debit,credit,description,post_date) VALUES (?,?,?,?,?,?)");
            $je->execute([doc_number('JE'),$cashId,$amt,0,'Penerimaan '.$invNo,today()]);
            $je->execute([doc_number('JE'),$arId,0,$amt,'Lunas '.$invNo,today()]);
            $msg = 'Pembayaran diterima.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $err = $e->getMessage();
    }
}

// ---------- Data ----------
$soList = $pdo->query("SELECT s.*,c.name AS customer_name,c.email AS customer_email FROM sales_orders s JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC")->fetchAll();
$customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
$materials = $pdo->query("SELECT * FROM materials ORDER BY name")->fetchAll();
$deliveries = $pdo->query("SELECT d.*,s.so_number,c.name AS customer_name FROM deliveries d JOIN sales_orders s ON s.id=d.so_id JOIN customers c ON c.id=s.customer_id ORDER BY d.id DESC")->fetchAll();
$invoices = $pdo->query("SELECT i.*,s.so_number,c.name AS customer_name FROM invoices i JOIN sales_orders s ON s.id=i.so_id JOIN customers c ON c.id=s.customer_id ORDER BY i.id DESC")->fetchAll();
?>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss><i class="bi bi-check-circle me-2"></i><?= esc($msg) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= esc($err) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- ============ Sales Orders ============ -->
<div class="card mb-4">
  <div class="card-header d-flex align-items-center">
    <i class="bi bi-cart3 me-2 text-primary"></i> Sales Order (SD)
    <button class="btn btn-erp btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#modalSO"><i class="bi bi-plus-lg me-1"></i>Buat SO</button>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>No. SO</th><th>Customer</th><th>Tgl. Order</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
      <tbody>
      <?php if (!$soList): ?>
        <tr><td colspan="6"><div class="erp-empty"><i class="bi bi-inbox"></i>Belum ada sales order</div></td></tr>
      <?php else: foreach ($soList as $so): ?>
        <tr>
          <td class="text-mono"><?= esc($so['so_number']) ?></td>
          <td><div class="fw-semibold"><?= esc($so['customer_name']) ?></div><small class="text-muted"><?= esc($so['customer_email']) ?></small></td>
          <td><?= date('d M Y',strtotime($so['order_date'])) ?></td>
          <td class="text-end fw-semibold"><?= nf($so['total_amount']) ?></td>
          <td><?= badge($so['status']) ?></td>
          <td class="text-end">
          <?php if ($so['status'] === 'CONFIRMED'): ?>
            <form method="post" action="index.php?page=sales" class="d-inline">
              <input type="hidden" name="action" value="create_delivery">
              <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
              <button class="btn btn-outline-erp btn-action"><i class="bi bi-truck me-1"></i>Delivery</button>
            </form>
            <form method="post" action="index.php?page=sales" class="d-inline">
              <input type="hidden" name="action" value="receipt">
              <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
              <button class="btn btn-outline-erp btn-action"><i class="bi bi-receipt me-1"></i>Billing</button>
            </form>
            <a href="index.php?page=sales&action=cancel&id=<?= $so['id'] ?>" class="btn btn-outline-erp btn-action text-danger" onclick="return confirm('Batalkan SO ini?')"><i class="bi bi-x-lg me-1"></i>Cancel</a>
          <?php elseif ($so['status'] === 'DELIVERED'): ?>
            <?php foreach ($deliveries as $d): if ($d['so_id'] === $so['id'] && $d['status'] === 'OPEN'): ?>
              <a href="index.php?page=sales&action=issue&id=<?= $d['id'] ?>" class="btn btn-outline-erp btn-action text-success" onclick="return confirm('Post Goods Issue?')"><i class="bi bi-box-arrow-down me-1"></i>Goods Issue</a>
            <?php endif; endforeach; ?>
            <form method="post" action="index.php?page=sales" class="d-inline">
              <input type="hidden" name="action" value="receipt">
              <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
              <button class="btn btn-outline-erp btn-action"><i class="bi bi-receipt me-1"></i>Billing</button>
            </form>
          <?php elseif ($so['status'] === 'INVOICED'): ?>
            <?php foreach ($invoices as $inv): if ($inv['so_id'] === $so['id'] && $inv['status'] === 'UNPAID'): ?>
              <a href="index.php?page=sales&action=pay&id=<?= $inv['id'] ?>" class="btn btn-success btn-action" onclick="return confirm('Terima pembayaran?')"><i class="bi bi-cash-coin me-1"></i>Receive Payment</a>
            <?php endif; endforeach; ?>
          <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3">
  <!-- Delivery -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-truck me-2 text-primary"></i> Delivery (Outbound)</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>No. Delivery</th><th>SO</th><th>Customer</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php if (!$deliveries): ?>
            <tr><td colspan="5"><div class="erp-empty"><i class="bi bi-truck"></i>Belum ada delivery</div></td></tr>
          <?php else: foreach ($deliveries as $d): ?>
            <tr>
              <td class="text-mono"><?= esc($d['delivery_number']) ?></td>
              <td class="text-mono"><?= esc($d['so_number']) ?></td>
              <td><?= esc($d['customer_name']) ?></td>
              <td><?= badge($d['status']) ?></td>
              <td class="text-end">
                <?php if ($d['status'] === 'OPEN'): ?>
                  <a href="index.php?page=sales&action=issue&id=<?= $d['id'] ?>" class="btn btn-outline-erp btn-action text-success" onclick="return confirm('Post Goods Issue?')"><i class="bi bi-box-arrow-down me-1"></i>Goods Issue</a>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Invoices -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-receipt me-2 text-primary"></i> Billing & Invoices (FI-AR)</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>No. Invoice</th><th>SO</th><th>Customer</th><th class="text-end">Amount</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php if (!$invoices): ?>
            <tr><td colspan="6"><div class="erp-empty"><i class="bi bi-receipt"></i>Belum ada invoice</div></td></tr>
          <?php else: foreach ($invoices as $inv): ?>
            <tr>
              <td class="text-mono"><?= esc($inv['invoice_number']) ?></td>
              <td class="text-mono"><?= esc($inv['so_number']) ?></td>
              <td><?= esc($inv['customer_name']) ?></td>
              <td class="text-end fw-semibold"><?= nf($inv['amount']) ?></td>
              <td><?= badge($inv['status']) ?></td>
              <td class="text-end">
                <?php if ($inv['status'] === 'UNPAID'): ?>
                  <a href="index.php?page=sales&action=pay&id=<?= $inv['id'] ?>" class="btn btn-success btn-action" onclick="return confirm('Terima pembayaran?')"><i class="bi bi-cash-coin me-1"></i>Receive Payment</a>
                <?php else: ?>
                  <span class="text-success small"><i class="bi bi-check2 me-1"></i>Paid</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============ Modal Buat SO ============ -->
<div class="modal fade" id="modalSO" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="index.php?page=sales">
        <input type="hidden" name="action" value="create_so">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-cart-plus me-2 text-primary"></i>Buat Sales Order Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Pelanggan</label>
              <select name="customer_id" class="form-select" required>
                <option value="">— Pilih —</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tanggal Order</label>
              <input type="date" name="order_date" class="form-control" value="<?= today() ?>" required>
            </div>
          </div>
          <hr>
          <div class="d-flex align-items-center mb-2">
            <label class="form-label mb-0">Item Pesanan</label>
            <button type="button" class="btn btn-sm btn-outline-erp ms-auto" onclick="addSOItem()"><i class="bi bi-plus-lg me-1"></i>Tambah Item</button>
          </div>
          <div id="so-items">
            <div class="row g-2 so-item-row">
              <div class="col-md-5">
                <select name="items[0][material_id]" class="form-select so-material">
                  <?php foreach ($materials as $m): ?>
                    <option value="<?= $m['id'] ?>" data-price="<?= $m['price'] ?>"><?= esc($m['code']) ?> — <?= esc($m['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2"><input type="number" name="items[0][quantity]" class="form-control so-qty" value="1" min="1" required></div>
              <div class="col-md-3">
                <div class="input-group"><span class="input-group-text">Rp</span><input type="text" name="items[0][price]" class="form-control so-price" required></div>
              </div>
              <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-action w-100" onclick="this.closest('.so-item-row').remove()"><i class="bi bi-trash"></i></button></div>
            </div>
          </div>
          <div class="alert alert-light border mt-3 mb-0 small"><i class="bi bi-info-circle me-1"></i>Harga item otomatis terisi dari master material.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-erp"><i class="bi bi-check-lg me-1"></i>Simpan SO</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var soCount =  1;
function addSOItem() {
  var holder = document.getElementById('so-items');
  var row = document.createElement('div');
  row.className = 'row g-2 so-item-row';
  row.innerHTML = ''
    + '<div class="col-md-5"><select name="items[' + soCount + '][material_id]" class="form-select so-material">'
    <?php foreach ($materials as $m): ?>
    + '<option value="<?= $m['id'] ?>" data-price="<?= $m['price'] ?>"><?= esc($m['code']) ?> — <?= esc($m['name']) ?></option>'
    <?php endforeach; ?>
    + '</select></div>'
    + '<div class="col-md-2"><input type="number" name="items[' + soCount + '][quantity]" class="form-control so-qty" value="1" min="1" required></div>'
    + '<div class="col-md-3"><div class="input-group"><span class="input-group-text">Rp</span><input type="text" name="items[' + soCount + '][price]" class="form-control so-price" required></div></div>'
    + '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-action w-100" onclick="this.parentElement.parentElement.remove()"><i class="bi bi-trash"></i></button></div>'
    + '</div>';
  holder.appendChild(row);
  bindPrice();
  soCount = soCount +  1;
}
function bindPrice() {
  var sels = document.querySelectorAll('.so-material');
  for (var i =  0; i < sels.length; i = i +  1) {
    sels[i].onchange = function() {
      var price = this.options[this.selectedIndex].getAttribute('data-price');
      var row = this.parentElement.parentElement.parentElement;
      var inputs = row.querySelectorAll('.so-price');
      inputs[0].value = price;
    };
  }
}
document.addEventListener('DOMContentLoaded', function() {
  bindPrice();
  var first = document.querySelector('.so-material');
  if (first) {
    var price = first.options[first.selectedIndex].getAttribute('data-price');
    document.querySelector('.so-price').value = price;
  }
});
</script>