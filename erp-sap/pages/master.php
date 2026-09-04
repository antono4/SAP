<?php
// ================================================================
// Master Data - Customers, Vendors, Materials, Employees
// ================================================================
$pdo = db();
$msg='';
$err='';

$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    try {
        if ($action === 'add_customer') {
            $name=$_POST['name'];
            $email=$_POST['email'];
            $phone=$_POST['phone'];
            $addr=$_POST['address'];
            $st=$pdo->prepare("INSERT INTO customers (name,email,phone,address) VALUES (?,?,?,?)");
            $st->execute([$name,$email,$phone,$addr]);
            $msg='Customer ditambahkan.';
        }
        if ($action === 'add_vendor') {
            $name=$_POST['name'];
            $email=$_POST['email'];
            $phone=$_POST['phone'];
            $terms=(int)$_POST['payment_terms'];
            $st=$pdo->prepare("INSERT INTO vendors (name,email,phone,payment_terms) VALUES (?,?,?,?)");
            $st->execute([$name,$email,$phone,$terms]);
            $msg='Vendor ditambahkan.';
        }
        if ($action === 'add_material') {
            $code=$_POST['code'];
            $name=$_POST['name'];
            $type=$_POST['type'];
            $unit=$_POST['unit'];
            $price=(float)$_POST['price'];
            $stock=(int)$_POST['stock'];
            $reorder=(int)$_POST['reorder_level'];
            $st=$pdo->prepare("INSERT INTO materials (code,name,type,unit,price,stock,reorder_level) VALUES (?,?,?,?,?,?,?)");
            $st->execute([$code,$name,$type,$unit,$price,$stock,$reorder]);
            $msg='Material ditambahkan.';
        }
        if ($action === 'add_employee') {
            $empNo=$_POST['emp_no'];
            $name=$_POST['name'];
            $pos=$_POST['position'];
            $dept=$_POST['department'];
            $salary=(float)$_POST['base_salary'];
            $st=$pdo->prepare("INSERT INTO employees (emp_no,name,position,department,base_salary) VALUES (?,?,?,?,?)");
            $st->execute([$empNo,$name,$pos,$dept,$salary]);
            $msg='Karyawan ditambahkan.';
        }
    } catch (Throwable $e) {
        $err=$e->getMessage();
    }
}

$customers=$pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
$vendors=$pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
$materials=$pdo->query("SELECT * FROM materials ORDER BY code")->fetchAll();
$employees=$pdo->query("SELECT * FROM employees ORDER BY emp_no")->fetchAll();
?>


<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" data-auto-dismiss><i class="bi bi-check-circle me-2"></i><?= esc($msg) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= esc($err) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Customers -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-people me-2 text-primary"></i> Pelanggan
        <button class="btn btn-sm btn-outline-erp ms-auto" data-bs-toggle="modal" data-bs-target="#modalCust"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nama</th><th>Kontak</th><th class="text-end">Limit Kredit</th></tr></thead>
          <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><?= esc($c['name']) ?></td>
              <td><small class="text-muted"><?= esc($c['email']) ?><br><?= esc($c['phone']) ?></small></td>
              <td class="text-end"><?= nf($c['credit_limit']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Vendors -->
  <div class="col-12 col-xl-6">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-building me-2 text-success"></i> Vendor
        <button class="btn btn-sm btn-outline-erp ms-auto" data-bs-toggle="modal" data-bs-target="#modalVend"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nama</th><th>Kontak</th><th class="text-end">Termin</th></tr></thead>
          <tbody>
          <?php foreach ($vendors as $v): ?>
            <tr>
              <td><?= esc($v['name']) ?></td>
              <td><small class="text-muted"><?= esc($v['email']) ?><br><?= esc($v['phone']) ?></small></td>
              <td class="text-end"><?= (int)$v['payment_terms'] ?> hari</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Materials -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-box-seam me-2 text-primary"></i> Material Master
        <button class="btn btn-sm btn-outline-erp ms-auto" data-bs-toggle="modal" data-bs-target="#modalMat"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Stok</th><th class="text-end">Min. Stok</th></tr></thead>
          <tbody>
          <?php foreach ($materials as $m): ?>
            <tr>
              <td class="text-mono"><?= esc($m['code']) ?></td>
              <td><?= esc($m['name']) ?></td>
              <td><span class="badge bg-light border text-dark"><?= esc($m['type']) ?></span></td>
              <td><?= esc($m['unit']) ?></td>
              <td class="text-end"><?= nf($m['price']) ?></td>
              <td class="text-end"><?= (int)$m['stock'] ?></td>
              <td class="text-end"><?= (int)$m['reorder_level'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Employees -->
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-person-badge me-2 text-primary"></i> Karyawan (HCM
        <button class="btn btn-sm btn-outline-erp ms-auto" data-bs-toggle="modal" data-bs-target="#modalEmp"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>No. Induk</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th class="text-end">Gaji Pokok</th></tr></thead>
          <tbody>
          <?php foreach ($employees as $e): ?>
            <tr>
              <td class="text-mono"><?= esc($e['emp_no']) ?></td>
              <td><?= esc($e['name']) ?></td>
              <td><?= esc($e['position']) ?></td>
              <td><?= esc($e['department']) ?></td>
              <td class="text-end"><?= nf($e['base_salary']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Customer -->
<div class="modal fade" id="modalCust" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="index.php?page=master">
      <input type="hidden" name="action" value="add_customer">
      <div class="modal-header"><h5 class="modal-title">Tambah Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-erp">Simpan</button></div>
    </form>
  </div></div>
</div>

<!-- Modal Vendor -->
<div class="modal fade" id="modalVend" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="index.php?page=master">
      <input type="hidden" name="action" value="add_vendor">
      <div class="modal-header"><h5 class="modal-title">Tambah Vendor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
        <div class="mb-2"><label class="form-label">Termin Pembayaran (hari)</label><input type="number" name="payment_terms" class="form-control" value="30"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-erp">Simpan</button></div>
    </form>
  </div></div>
</div>

<!-- Modal Material -->
<div class="modal fade" id="modalMat" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="index.php?page=master">
      <input type="hidden" name="action" value="add_material">
      <div class="modal-header"><h5 class="modal-title">Tambah Material</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row">
          <div class="col-6 mb-2"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required></div>
          <div class="col-6 mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
        </div>
        <div class="row">
          <div class="col-6 mb-2">
            <label class="form-label">Tipe</label>
            <select name="type" class="form-select">
              <option value="FERT">FERT (Barang Jadi)</option>
              <option value="HALB">HALB (Setengah Jadi)</option>
              <option value="ROH">ROH (Bahan Baku)</option>
            </select>
          </div>
          <div class="col-6 mb-2"><label class="form-label">Satuan</label><input type="text" name="unit" class="form-control" value="UNIT"></div>
        </div>
        <div class="row">
          <div class="col-4 mb-2"><label class="form-label">Harga</label><input type="number" name="price" class="form-control" value="0" step="0.01"></div>
          <div class="col-4 mb-2"><label class="form-label">Stok Awal</label><input type="number" name="stock" class="form-control" value="0"></div>
          <div class="col-4 mb-2"><label class="form-label">Min. Stok</label><input type="number" name="reorder_level" class="form-control" value="10"></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-erp">Simpan</button></div>
    </form>
  </div></div>
</div>

<!-- Modal Karyawan -->
<div class="modal fade" id="modalEmp" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="index.php?page=master">
      <input type="hidden" name="action" value="add_employee">
      <div class="modal-header"><h5 class="modal-title">Tambah Karyawan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row">
          <div class="col-6 mb-2"><label class="form-label">No. Induk</label><input type="text" name="emp_no" class="form-control" required></div>
          <div class="col-6 mb-2"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
        </div>
        <div class="row">
          <div class="col-6 mb-2"><label class="form-label">Jabatan</label><input type="text" name="position" class="form-control"></div>
          <div class="col-6 mb-2"><label class="form-label">Departemen</label><input type="text" name="department" class="form-control"></div>
        </div>
        <div class="mb-2"><label class="form-label">Gaji Pokok</label><input type="number" name="base_salary" class="form-control" value="0"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-erp">Simpan</button></div>
    </form>
  </div></div>
</div>