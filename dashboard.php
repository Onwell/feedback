<?php
require_once __DIR__ . '/config.php';

if (!isLoggedIn()) {
  redirect('login.php');
}

$pdo = getDB();

// Helper functions
function quarterFromDate($dateStr) {
    if (!$dateStr) return null;
    $month = intval(substr($dateStr, 5, 2));
    if ($month <= 3) return '1st';
    if ($month <= 6) return '2nd';
    if ($month <= 9) return '3rd';
    return '4th';
}

function fmtDate($iso) {
    if (!$iso) return '—';
    $d = new DateTime($iso);
    return $d->format('d M Y');
}

function escapeHtml($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function generateRef($type, $pdo) {
    $prefix = $type === 'compliment' ? 'CO' : 'CX';
    
    // Get current sequence value
    $stmt = $pdo->prepare("SELECT value FROM sequences WHERE type = ? FOR UPDATE");
    $stmt->execute([$type]);
    $row = $stmt->fetch();
    $seq = $row ? $row['value'] + 1 : 1;
    
    // Update sequence
    $stmt = $pdo->prepare("UPDATE sequences SET value = ? WHERE type = ?");
    $stmt->execute([$seq, $type]);
    
    return $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

  function nullableDate($value) {
    $value = trim((string) $value);
    return $value !== '' ? $value : null;
  }

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    // Compliment operations
    if ($action === 'add_compliment' || $action === 'edit_compliment') {
        $date = $_POST['date'] ?? '';
        $client = trim($_POST['client'] ?? '');
        $text = trim($_POST['text'] ?? '');
        
        if ($date && $client && $text) {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'edit_compliment') {
                    $id = $_POST['id'] ?? '';
                    $ref = $_POST['ref'] ?? '';
                    
                    $stmt = $pdo->prepare("UPDATE compliments SET 
                        date = ?, quarter = ?, client = ?, text = ?, 
                        source = ?, forwarded = ?, response = ?, response_date = ? 
                        WHERE id = ?");
                    $stmt->execute([
                        $date,
                        quarterFromDate($date),
                        $client,
                        $text,
                        $_POST['source'] ?? 'Facebook',
                        trim($_POST['forwarded'] ?? ''),
                        trim($_POST['response'] ?? ''),
                        nullableDate($_POST['response_date'] ?? ''),
                        $id
                    ]);
                    $response = ['success' => true, 'message' => 'Compliment updated successfully!'];
                } else {
                    $id = 'c_' . time() . '_' . rand(100, 999);
                    $ref = generateRef('compliment', $pdo);
                    
                    $stmt = $pdo->prepare("INSERT INTO compliments 
                        (id, ref, date, quarter, client, text, source, forwarded, response, response_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $id,
                        $ref,
                        $date,
                        quarterFromDate($date),
                        $client,
                        $text,
                        $_POST['source'] ?? 'Facebook',
                        trim($_POST['forwarded'] ?? ''),
                        trim($_POST['response'] ?? ''),
                        nullableDate($_POST['response_date'] ?? '')
                    ]);
                    $response = ['success' => true, 'message' => 'Compliment added successfully! Reference: ' . $ref];
                }
                
                $pdo->commit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Please fill in all required fields: Date, Client Name, and Compliment text.'];
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'delete_compliment') {
        $id = $_POST['id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM compliments WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true, 'message' => 'Compliment deleted successfully!'];
        } catch(PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
    
    // Complaint operations
    if ($action === 'add_complaint' || $action === 'edit_complaint') {
        $date = $_POST['date'] ?? '';
        $client = trim($_POST['client'] ?? '');
        $text = trim($_POST['text'] ?? '');
        
        if ($date && $client && $text) {
            try {
                $pdo->beginTransaction();
                
                if ($action === 'edit_complaint') {
                    $id = $_POST['id'] ?? '';
                    $ref = $_POST['ref'] ?? '';
                    
                    $stmt = $pdo->prepare("UPDATE complaints SET 
                        date = ?, quarter = ?, client = ?, text = ?, 
                        source = ?, assignee = ?, response = ?, action = ?, 
                        status = ?, response_date = ? 
                        WHERE id = ?");
                    $stmt->execute([
                        $date,
                        quarterFromDate($date),
                        $client,
                        $text,
                        $_POST['source'] ?? 'Facebook',
                        trim($_POST['assignee'] ?? ''),
                        trim($_POST['response'] ?? ''),
                        trim($_POST['action_item'] ?? ''),
                        $_POST['status'] ?? 'Pending',
                        nullableDate($_POST['response_date'] ?? ''),
                        $id
                    ]);
                    $response = ['success' => true, 'message' => 'Complaint updated successfully!'];
                } else {
                    $id = 'x_' . time() . '_' . rand(100, 999);
                    $ref = generateRef('complaint', $pdo);
                    
                    $stmt = $pdo->prepare("INSERT INTO complaints 
                        (id, ref, date, quarter, client, text, source, assignee, response, action, status, response_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $id,
                        $ref,
                        $date,
                        quarterFromDate($date),
                        $client,
                        $text,
                        $_POST['source'] ?? 'Facebook',
                        trim($_POST['assignee'] ?? ''),
                        trim($_POST['response'] ?? ''),
                        trim($_POST['action_item'] ?? ''),
                        $_POST['status'] ?? 'Pending',
                        nullableDate($_POST['response_date'] ?? '')
                    ]);
                    $response = ['success' => true, 'message' => 'Complaint added successfully! Reference: ' . $ref];
                }
                
                $pdo->commit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        } else {
            $response = ['success' => false, 'message' => 'Please fill in all required fields: Date, Client Name, and Issue description.'];
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'delete_complaint') {
        $id = $_POST['id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
            $stmt->execute([$id]);
            $response = ['success' => true, 'message' => 'Complaint deleted successfully!'];
        } catch(PDOException $e) {
            $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    }
}

// Load data from database
function loadCompliments($pdo) {
    $stmt = $pdo->query("SELECT * FROM compliments ORDER BY date DESC, created_at DESC");
    return $stmt->fetchAll();
}

function loadComplaints($pdo) {
    $stmt = $pdo->query("SELECT * FROM complaints ORDER BY date DESC, created_at DESC");
    return $stmt->fetchAll();
}

$compliments = loadCompliments($pdo);
$complaints = loadComplaints($pdo);

// Get counts for display
$complimentCount = count($compliments);
$complaintCount = count($complaints);

// Calculate stats
$responded = array_filter($compliments, function($c) { return isset($c['response']) && trim($c['response']); });
$responseRate = $complimentCount ? round((count($responded)/$complimentCount)*100) : 0;

$resolved = array_filter($complaints, function($c) { return isset($c['status']) && $c['status'] === 'Resolved'; });
$pending = array_filter($complaints, function($c) { return !isset($c['status']) || $c['status'] !== 'Resolved'; });

$currentQ = quarterFromDate(date('Y-m-d'));
$complimentsThisQ = array_filter($compliments, function($c) use ($currentQ) { return isset($c['quarter']) && $c['quarter'] === $currentQ; });
$complaintsThisQ = array_filter($complaints, function($c) use ($currentQ) { return isset($c['quarter']) && $c['quarter'] === $currentQ; });

$sources = array_unique(array_column($compliments, 'source'));
$sourceCount = count(array_filter($sources));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NCC Zimbabwe — Complaints &amp; Compliments Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,450;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --paper:#F6F3EA; --paper-raised:#FFFFFF; --ink:#1C231F; --ink-soft:#5B655D;
    --forest:#1F4D3A; --forest-deep:#153327; --brass:#AD8A4D; --sage:#E4EBE1;
    --hairline:#DCD5C2; --rule:1px solid var(--hairline);
    --fb:#3B5EA6; --li:#0F6E5C; --danger:#A23B2E;
    --success:#2E7D5E;
  }
  *{box-sizing:border-box;}
  body{margin:0; background:var(--paper); color:var(--ink); font-family:'IBM Plex Sans',sans-serif; -webkit-font-smoothing:antialiased;}
  .serif{font-family:'Fraunces',serif;}
  .mono{font-family:'IBM Plex Mono',monospace;}
  .wrap{max-width:1180px; margin:0 auto; padding:0 32px;}

  header.masthead{border-bottom:2px solid var(--forest); padding:26px 0 20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap;}
  .eyebrow{text-transform:uppercase; letter-spacing:.14em; font-size:11px; color:var(--forest); font-weight:600; margin:0 0 8px;}
  h1.title{font-family:'Fraunces',serif; font-size:clamp(26px,3.4vw,36px); line-height:1.05; margin:0 0 8px; font-weight:600; color:var(--forest-deep);}
  .subtitle{color:var(--ink-soft); font-size:14px; max-width:480px; line-height:1.5; margin:0;}
  .storage-note{font-size:11.5px; color:var(--ink-soft); display:flex; align-items:center; gap:6px; margin-top:10px;}
  .storage-note .dot{width:6px; height:6px; border-radius:50%; background:var(--forest);}

  .tabs{display:flex; gap:4px; padding:20px 0 0; border-bottom:var(--rule);}
  .tab{
    padding:12px 22px; font-size:14px; font-weight:600; color:var(--ink-soft); cursor:pointer;
    border-bottom:2px solid transparent; margin-bottom:-1px; font-family:'IBM Plex Sans',sans-serif; background:none; border-top:none; border-left:none; border-right:none;
  }
  .tab.active{color:var(--forest-deep); border-bottom-color:var(--forest);}
  .tab .count-badge{
    display:inline-block; margin-left:7px; font-family:'IBM Plex Mono',monospace; font-size:11px;
    background:var(--sage); color:var(--forest-deep); padding:1px 7px; border-radius:20px;
  }

  section.panel{padding:26px 0 60px;}
  .toolbar{display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:18px;}
  .filter-group{display:flex; gap:4px; padding:4px; background:var(--paper-raised); border:var(--rule); border-radius:8px;}
  .filter-btn{border:none; background:transparent; padding:7px 13px; font-size:12.5px; font-weight:500; color:var(--ink-soft); border-radius:5px; cursor:pointer; font-family:'IBM Plex Sans',sans-serif;}
  .filter-btn.active{background:var(--forest); color:#fff;}
  .search-input{border:var(--rule); background:var(--paper-raised); padding:9px 14px; border-radius:8px; font-size:13px; font-family:'IBM Plex Sans',sans-serif; color:var(--ink); min-width:200px;}
  .search-input:focus{outline:2px solid var(--forest); outline-offset:1px;}
  .spacer{flex:1;}
  .btn-primary{
    background:var(--forest); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-size:13px;
    font-weight:600; cursor:pointer; font-family:'IBM Plex Sans',sans-serif; display:inline-flex; align-items:center; gap:7px;
  }
  .btn-primary:hover{background:var(--forest-deep);}
  .btn-ghost{
    background:var(--paper-raised); color:var(--ink); border:var(--rule); padding:10px 16px; border-radius:8px; font-size:13px;
    font-weight:500; cursor:pointer; font-family:'IBM Plex Sans',sans-serif;
  }

  .stat-strip{display:grid; grid-template-columns:repeat(4,1fr); border:var(--rule); background:var(--paper-raised); margin-bottom:24px;}
  .stat-cell{padding:18px 20px; border-right:var(--rule);}
  .stat-cell:last-child{border-right:none;}
  .stat-num{font-family:'Fraunces',serif; font-size:28px; font-weight:600; color:var(--forest-deep);}
  .stat-label{font-size:10.5px; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-soft); margin-top:4px; font-weight:500;}

  .log-head{display:grid; grid-template-columns:90px 92px 1fr 130px 110px 90px; gap:16px; padding:0 6px 10px; border-bottom:2px solid var(--forest); font-size:10.5px; text-transform:uppercase; letter-spacing:.07em; color:var(--forest); font-weight:600;}
  .log{border-top:none;}
  .entry{display:grid; grid-template-columns:90px 92px 1fr 130px 110px 90px; gap:16px; align-items:start; padding:14px 6px; border-bottom:var(--rule);}
  .entry:hover{background:rgba(31,77,58,0.03);}
  .entry .ref{font-family:'IBM Plex Mono',monospace; font-size:11.5px; color:var(--brass); font-weight:500; padding-top:2px;}
  .entry .date{font-family:'IBM Plex Mono',monospace; font-size:11.5px; color:var(--ink-soft); padding-top:2px;}
  .entry .client{font-weight:600; font-size:13.5px; margin:0 0 3px;}
  .entry .quote{font-size:13px; color:var(--ink-soft); margin:0; line-height:1.5;}
  .source-badge{display:inline-flex; align-items:center; gap:6px; font-size:11.5px; color:var(--ink-soft); padding-top:3px;}
  .dot{width:7px; height:7px; border-radius:50%; flex:0 0 auto;}
  .dot.fb{background:var(--fb);} .dot.li{background:var(--li);} .dot.other{background:var(--brass);}
  .pill{font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; padding:4px 8px; border-radius:20px; white-space:nowrap; display:inline-block;}
  .pill.yes{background:var(--sage); color:var(--forest-deep);}
  .pill.no{background:#F3E4D6; color:#8A5A2B;}
  .pill.resolved{background:var(--sage); color:var(--forest-deep);}
  .pill.pending{background:#F3E4D6; color:#8A5A2B;}
  .pill.progress{background:#DDE6F3; color:#2F4C74;}
  .row-actions{display:flex; gap:6px; padding-top:1px;}
  .icon-btn{border:none; background:none; cursor:pointer; padding:4px; border-radius:5px; color:var(--ink-soft); font-size:14px;}
  .icon-btn:hover{background:var(--sage); color:var(--forest-deep);}
  .icon-btn.del:hover{background:#F3E0DC; color:var(--danger);}

  .empty-log{padding:60px 6px; text-align:center; color:var(--ink-soft);}
  .empty-log .big{font-family:'Fraunces',serif; font-size:18px; color:var(--forest-deep); margin-bottom:6px;}

  /* Modal */
  .modal-overlay{position:fixed; inset:0; background:rgba(21,51,39,0.35); display:none; align-items:flex-start; justify-content:center; z-index:100; padding:40px 20px; overflow-y:auto;}
  .modal-overlay.open{display:flex;}
  .modal{background:var(--paper-raised); border-radius:12px; max-width:620px; width:100%; padding:30px 32px; border:var(--rule);}
  .modal h3{font-family:'Fraunces',serif; font-size:20px; color:var(--forest-deep); margin:0 0 20px;}
  .form-grid{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
  .form-field{display:flex; flex-direction:column; gap:5px;}
  .form-field.full{grid-column:1/-1;}
  .form-field label{font-size:11.5px; font-weight:600; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.04em;}
  .form-field input, .form-field select, .form-field textarea{
    border:var(--rule); border-radius:7px; padding:9px 11px; font-size:13.5px; font-family:'IBM Plex Sans',sans-serif;
    background:var(--paper); color:var(--ink);
  }
  .form-field textarea{resize:vertical; min-height:60px; font-family:'IBM Plex Sans',sans-serif;}
  .form-field input:focus, .form-field select:focus, .form-field textarea:focus{outline:2px solid var(--forest); outline-offset:1px;}
  .modal-actions{display:flex; justify-content:flex-end; gap:10px; margin-top:24px;}
  .required-mark{color:var(--danger);}

  .toast{
    position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px); opacity:0;
    padding:12px 22px; border-radius:8px; font-size:13px;
    transition:opacity .25s, transform .25s; z-index:200; pointer-events:none;
    max-width:90%;
    text-align:center;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
  }
  .toast.success{
    background:var(--success); color:#fff;
  }
  .toast.error{
    background:var(--danger); color:#fff;
  }
  .toast.info{
    background:var(--forest-deep); color:#fff;
  }
  .toast.show{opacity:1; transform:translateX(-50%) translateY(0);}

  footer{border-top:2px solid var(--forest); padding:22px 0 40px; font-size:12px; color:var(--ink-soft);}

  @media (max-width:800px){
    .wrap{padding:0 16px;}
    .stat-strip{grid-template-columns:repeat(2,1fr);}
    .stat-cell:nth-child(2n){border-right:none;}
    .stat-cell{border-bottom:var(--rule);}
    .log-head{display:none;}
    .entry{grid-template-columns:1fr; gap:6px;}
    .row-actions{padding-top:6px;}
    .form-grid{grid-template-columns:1fr;}
    .search-input{width:100%;}
  }
  :focus-visible{outline:2px solid var(--forest); outline-offset:2px;}
</style>
</head>
<body>
<div class="wrap">
  <header class="masthead">
    <div>
      <p class="eyebrow">National Competitiveness Commission · Zimbabwe</p>
      <h1 class="title">Complaints &amp; Compliments Register</h1>
      <p class="subtitle">Log, track, and respond to stakeholder feedback — replacing the spreadsheet with a live system.</p>
      <p class="storage-note"><span class="dot"></span><span id="storageStatus">Connected to MySQL database — <?php echo $complimentCount + $complaintCount; ?> entries</span></p>
    </div>
  </header>

  <nav class="tabs" role="tablist">
    <button class="tab active" id="tabCompliments" role="tab">Compliments <span class="count-badge" id="countCompliments"><?php echo $complimentCount; ?></span></button>
    <button class="tab" id="tabComplaints" role="tab">Complaints <span class="count-badge" id="countComplaints"><?php echo $complaintCount; ?></span></button>
  </nav>

  <!-- COMPLIMENTS PANEL -->
  <section class="panel" id="panelCompliments">
    <div class="stat-strip">
      <div class="stat-cell"><div class="stat-num" id="cTotal"><?php echo $complimentCount; ?></div><div class="stat-label">Total Logged</div></div>
      <div class="stat-cell"><div class="stat-num" id="cRate"><?php echo $responseRate; ?>%</div><div class="stat-label">Response Rate</div></div>
      <div class="stat-cell"><div class="stat-num" id="cThisQ"><?php echo count($complimentsThisQ); ?></div><div class="stat-label">This Quarter</div></div>
      <div class="stat-cell"><div class="stat-num" id="cSources"><?php echo $sourceCount; ?></div><div class="stat-label">Channels Used</div></div>
    </div>
    <div class="toolbar">
      <div class="filter-group" id="cQuarterFilter">
        <button class="filter-btn active" data-q="all">All</button>
        <button class="filter-btn" data-q="1st">Q1</button>
        <button class="filter-btn" data-q="2nd">Q2</button>
        <button class="filter-btn" data-q="3rd">Q3</button>
        <button class="filter-btn" data-q="4th">Q4</button>
      </div>
      <div class="filter-group" id="cRespFilter">
        <button class="filter-btn active" data-r="all">All</button>
        <button class="filter-btn" data-r="yes">Responded</button>
        <button class="filter-btn" data-r="no">Pending</button>
      </div>
      <input type="text" class="search-input" id="cSearch" placeholder="Search client or keyword…">
      <div class="spacer"></div>
      <button class="btn-ghost" id="cExport">Export CSV</button>
      <button class="btn-primary" id="cAddBtn">+ New Compliment</button>
    </div>
    <div class="log-head">
      <span>Ref</span><span>Date</span><span>Client &amp; Compliment</span><span>Source</span><span>Response</span><span></span>
    </div>
    <div class="log" id="cLog"></div>
  </section>

  <!-- COMPLAINTS PANEL -->
  <section class="panel" id="panelComplaints" style="display:none;">
    <div class="stat-strip">
      <div class="stat-cell"><div class="stat-num" id="xTotal"><?php echo $complaintCount; ?></div><div class="stat-label">Total Logged</div></div>
      <div class="stat-cell"><div class="stat-num" id="xResolved"><?php echo count($resolved); ?></div><div class="stat-label">Resolved</div></div>
      <div class="stat-cell"><div class="stat-num" id="xPending"><?php echo count($pending); ?></div><div class="stat-label">Open / Pending</div></div>
      <div class="stat-cell"><div class="stat-num" id="xThisQ"><?php echo count($complaintsThisQ); ?></div><div class="stat-label">This Quarter</div></div>
    </div>
    <div class="toolbar">
      <div class="filter-group" id="xQuarterFilter">
        <button class="filter-btn active" data-q="all">All</button>
        <button class="filter-btn" data-q="1st">Q1</button>
        <button class="filter-btn" data-q="2nd">Q2</button>
        <button class="filter-btn" data-q="3rd">Q3</button>
        <button class="filter-btn" data-q="4th">Q4</button>
      </div>
      <div class="filter-group" id="xStatusFilter">
        <button class="filter-btn active" data-s="all">All</button>
        <button class="filter-btn" data-s="Resolved">Resolved</button>
        <button class="filter-btn" data-s="In Progress">In Progress</button>
        <button class="filter-btn" data-s="Pending">Pending</button>
      </div>
      <input type="text" class="search-input" id="xSearch" placeholder="Search client or keyword…">
      <div class="spacer"></div>
      <button class="btn-ghost" id="xExport">Export CSV</button>
      <button class="btn-primary" id="xAddBtn">+ New Complaint</button>
    </div>
    <div class="log-head" style="grid-template-columns:90px 92px 1fr 130px 110px 90px;">
      <span>Ref</span><span>Date</span><span>Client &amp; Issue</span><span>Assignee</span><span>Status</span><span></span>
    </div>
    <div class="log" id="xLog"></div>
  </section>

  <footer>
    <p>Stored in MySQL database &mdash; data persists automatically as you add, edit, or remove entries.</p>
  </footer>
</div>

<!-- Compliment Modal -->
<div class="modal-overlay" id="cModalOverlay">
  <div class="modal">
    <h3 id="cModalTitle">New Compliment</h3>
    <div class="form-grid">
      <div class="form-field"><label>Date <span class="required-mark">*</span></label><input type="date" id="f_c_date"></div>
      <div class="form-field"><label>Client Name <span class="required-mark">*</span></label><input type="text" id="f_c_client" placeholder="e.g. Jane Moyo"></div>
      <div class="form-field full"><label>Compliment <span class="required-mark">*</span></label><textarea id="f_c_text" placeholder="What did they say?"></textarea></div>
      <div class="form-field">
        <label>Source</label>
        <select id="f_c_source"><option>Facebook</option><option>LinkedIn</option><option>Email</option><option>Phone</option><option>In Person</option><option>Other</option></select>
      </div>
      <div class="form-field"><label>Forwarded To</label><input type="text" id="f_c_forwarded" placeholder="e.g. Communications"></div>
      <div class="form-field full"><label>Courtesy Response</label><textarea id="f_c_response" placeholder="Response sent, if any"></textarea></div>
      <div class="form-field"><label>Date of Response</label><input type="date" id="f_c_response_date"></div>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" id="cCancel">Cancel</button>
      <button class="btn-primary" id="cSave">Save Entry</button>
    </div>
  </div>
</div>

<!-- Complaint Modal -->
<div class="modal-overlay" id="xModalOverlay">
  <div class="modal">
    <h3 id="xModalTitle">New Complaint</h3>
    <div class="form-grid">
      <div class="form-field"><label>Date <span class="required-mark">*</span></label><input type="date" id="f_x_date"></div>
      <div class="form-field"><label>Client Name <span class="required-mark">*</span></label><input type="text" id="f_x_client" placeholder="e.g. John Chirwa"></div>
      <div class="form-field full"><label>Issue / Complaint <span class="required-mark">*</span></label><textarea id="f_x_text" placeholder="Describe the complaint"></textarea></div>
      <div class="form-field">
        <label>Source</label>
        <select id="f_x_source"><option>Facebook</option><option>LinkedIn</option><option>Email</option><option>Phone</option><option>In Person</option><option>Other</option></select>
      </div>
      <div class="form-field"><label>Assignee</label><input type="text" id="f_x_assignee" placeholder="e.g. Communications & M&E"></div>
      <div class="form-field full"><label>Initial Response</label><textarea id="f_x_response" placeholder="Initial response given"></textarea></div>
      <div class="form-field full"><label>Corrective Action</label><textarea id="f_x_action" placeholder="What was done to resolve it"></textarea></div>
      <div class="form-field">
        <label>Status</label>
        <select id="f_x_status"><option>Pending</option><option>In Progress</option><option>Resolved</option></select>
      </div>
      <div class="form-field"><label>Date Resolved / Updated</label><input type="date" id="f_x_response_date"></div>
    </div>
    <div class="modal-actions">
      <button class="btn-ghost" id="xCancel">Cancel</button>
      <button class="btn-primary" id="xSave">Save Entry</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Pass PHP data to JavaScript
const complimentsData = <?php echo json_encode($compliments); ?>;
const complaintsData = <?php echo json_encode($complaints); ?>;

let compliments = complimentsData;
let complaints = complaintsData;
let editingId = null;
let editingXId = null;
let cFilters = { q: 'all', r: 'all', search: '' };
let xFilters = { q: 'all', s: 'all', search: '' };

function showToast(msg, type = 'info'){
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast ' + type;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 3000);
}

function quarterFromDate(dateStr){
  if(!dateStr) return null;
  const month = parseInt(dateStr.slice(5,7),10);
  if(month<=3) return '1st';
  if(month<=6) return '2nd';
  if(month<=9) return '3rd';
  return '4th';
}

function fmtDate(iso){
  if(!iso) return '—';
  const d = new Date(iso+'T00:00:00');
  return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
}

function escapeHtml(s){
  const div = document.createElement('div');
  div.textContent = s || '';
  return div.innerHTML;
}

// ---------- COMPLIMENTS RENDER ----------
function renderCompliments(){
  document.getElementById('countCompliments').textContent = compliments.length;
  const total = compliments.length;
  const responded = compliments.filter(d=>d.response && d.response.trim()).length;
  const rate = total ? Math.round((responded/total)*100) : 0;
  const sources = new Set(compliments.map(d=>d.source).filter(Boolean));
  const currentQ = quarterFromDate(new Date().toISOString().slice(0,10));
  const thisQCount = compliments.filter(d=>d.quarter===currentQ).length;

  document.getElementById('cTotal').textContent = total;
  document.getElementById('cRate').textContent = rate + '%';
  document.getElementById('cThisQ').textContent = thisQCount;
  document.getElementById('cSources').textContent = sources.size;

  const filtered = compliments.filter(d=>{
    if(cFilters.q!=='all' && d.quarter!==cFilters.q) return false;
    if(cFilters.r==='yes' && !(d.response && d.response.trim())) return false;
    if(cFilters.r==='no' && (d.response && d.response.trim())) return false;
    if(cFilters.search){
      const hay = ((d.client||'')+' '+(d.text||'')).toLowerCase();
      if(!hay.includes(cFilters.search)) return false;
    }
    return true;
  }).sort((a,b)=> (b.date||'').localeCompare(a.date||''));

  const logEl = document.getElementById('cLog');
  logEl.innerHTML = '';
  if(filtered.length===0){
    logEl.innerHTML = `<div class="empty-log"><div class="big">${total===0 ? 'No compliments logged yet' : 'No entries match these filters'}</div>${total===0 ? 'Click "New Compliment" to add your first entry.' : 'Try clearing a filter or the search box.'}</div>`;
    return;
  }
  filtered.forEach(d=>{
    const dotClass = d.source==='Facebook' ? 'fb' : (d.source==='LinkedIn' ? 'li' : 'other');
    const hasResp = d.response && d.response.trim();
    const row = document.createElement('div');
    row.className = 'entry';
    row.innerHTML = `
      <span class="ref">${escapeHtml(d.ref)}</span>
      <span class="date">${fmtDate(d.date)}</span>
      <div><p class="client">${escapeHtml(d.client)}</p><p class="quote">${escapeHtml((d.text||'').slice(0,160))}${(d.text||'').length>160?'…':''}</p></div>
      <span class="source-badge"><span class="dot ${dotClass}"></span>${escapeHtml(d.source||'—')}</span>
      <span class="pill ${hasResp?'yes':'no'}">${hasResp?'Responded':'Pending'}</span>
      <span class="row-actions">
        <button class="icon-btn edit" title="Edit" data-id="${escapeHtml(d.id)}">✎</button>
        <button class="icon-btn del" title="Delete" data-id="${escapeHtml(d.id)}">🗑</button>
      </span>
    `;
    logEl.appendChild(row);
  });
  logEl.querySelectorAll('.icon-btn.edit').forEach(b=>b.addEventListener('click', ()=>openComplimentModal(b.dataset.id)));
  logEl.querySelectorAll('.icon-btn.del').forEach(b=>b.addEventListener('click', ()=>deleteCompliment(b.dataset.id)));
}

// filters
document.getElementById('cQuarterFilter').addEventListener('click', e=>{
  const b = e.target.closest('.filter-btn'); if(!b) return;
  document.querySelectorAll('#cQuarterFilter .filter-btn').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); cFilters.q = b.dataset.q; renderCompliments();
});
document.getElementById('cRespFilter').addEventListener('click', e=>{
  const b = e.target.closest('.filter-btn'); if(!b) return;
  document.querySelectorAll('#cRespFilter .filter-btn').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); cFilters.r = b.dataset.r; renderCompliments();
});
document.getElementById('cSearch').addEventListener('input', e=>{
  cFilters.search = e.target.value.toLowerCase(); renderCompliments();
});

// modal
function openComplimentModal(id){
  editingId = id || null;
  const modal = document.getElementById('cModalOverlay');
  if(id){
    const d = compliments.find(x=>x.id===id);
    if (d) {
      document.getElementById('cModalTitle').textContent = 'Edit Compliment';
      document.getElementById('f_c_date').value = d.date || '';
      document.getElementById('f_c_client').value = d.client || '';
      document.getElementById('f_c_text').value = d.text || '';
      document.getElementById('f_c_source').value = d.source || 'Facebook';
      document.getElementById('f_c_forwarded').value = d.forwarded || '';
      document.getElementById('f_c_response').value = d.response || '';
      document.getElementById('f_c_response_date').value = d.response_date || '';
    }
  }else{
    document.getElementById('cModalTitle').textContent = 'New Compliment';
    ['f_c_date','f_c_client','f_c_text','f_c_forwarded','f_c_response','f_c_response_date'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('f_c_source').value = 'Facebook';
    document.getElementById('f_c_date').value = new Date().toISOString().slice(0,10);
  }
  modal.classList.add('open');
}
document.getElementById('cAddBtn').addEventListener('click', ()=>openComplimentModal(null));
document.getElementById('cCancel').addEventListener('click', ()=>document.getElementById('cModalOverlay').classList.remove('open'));
document.getElementById('cModalOverlay').addEventListener('click', e=>{ if(e.target.id==='cModalOverlay') e.target.classList.remove('open'); });

document.getElementById('cSave').addEventListener('click', async ()=>{
  const date = document.getElementById('f_c_date').value;
  const client = document.getElementById('f_c_client').value.trim();
  const text = document.getElementById('f_c_text').value.trim();
  if(!date || !client || !text){ 
    showToast('Please fill in all required fields: Date, Client Name, and Compliment text.', 'error');
    return; 
  }
  
  const formData = new FormData();
  if(editingId) {
    formData.append('action', 'edit_compliment');
    formData.append('id', editingId);
    const existing = compliments.find(x=>x.id===editingId);
    if (existing) formData.append('ref', existing.ref);
  } else {
    formData.append('action', 'add_compliment');
  }
  formData.append('date', date);
  formData.append('client', client);
  formData.append('text', text);
  formData.append('source', document.getElementById('f_c_source').value);
  formData.append('forwarded', document.getElementById('f_c_forwarded').value.trim());
  formData.append('response', document.getElementById('f_c_response').value.trim());
  formData.append('response_date', document.getElementById('f_c_response_date').value);

  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if(result.success) {
      document.getElementById('cModalOverlay').classList.remove('open');
      showToast(result.message, 'success');
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showToast(result.message || 'Error saving entry', 'error');
    }
  } catch(e) {
    showToast('Error saving entry. Please try again.', 'error');
  }
});

async function deleteCompliment(id){
  if(!confirm('Delete this compliment entry? This cannot be undone.')) return;
  
  const formData = new FormData();
  formData.append('action', 'delete_compliment');
  formData.append('id', id);

  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if(result.success) {
      showToast(result.message, 'success');
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showToast(result.message || 'Error deleting entry', 'error');
    }
  } catch(e) {
    showToast('Error deleting entry. Please try again.', 'error');
  }
}

document.getElementById('cExport').addEventListener('click', ()=>exportCsv(compliments,
  ['ref','date','quarter','client','text','source','forwarded','response','response_date'],
  ['Ref','Date','Quarter','Client','Compliment','Source','Forwarded To','Response','Response Date'],
  'compliments.csv'));

// ---------- COMPLAINTS RENDER ----------
function renderComplaints(){
  document.getElementById('countComplaints').textContent = complaints.length;
  const total = complaints.length;
  const resolved = complaints.filter(d=>d.status==='Resolved').length;
  const pending = complaints.filter(d=>d.status!=='Resolved').length;
  const currentQ = quarterFromDate(new Date().toISOString().slice(0,10));
  const thisQCount = complaints.filter(d=>d.quarter===currentQ).length;

  document.getElementById('xTotal').textContent = total;
  document.getElementById('xResolved').textContent = resolved;
  document.getElementById('xPending').textContent = pending;
  document.getElementById('xThisQ').textContent = thisQCount;

  const filtered = complaints.filter(d=>{
    if(xFilters.q!=='all' && d.quarter!==xFilters.q) return false;
    if(xFilters.s!=='all' && d.status!==xFilters.s) return false;
    if(xFilters.search){
      const hay = ((d.client||'')+' '+(d.text||'')).toLowerCase();
      if(!hay.includes(xFilters.search)) return false;
    }
    return true;
  }).sort((a,b)=> (b.date||'').localeCompare(a.date||''));

  const logEl = document.getElementById('xLog');
  logEl.innerHTML = '';
  if(filtered.length===0){
    logEl.innerHTML = `<div class="empty-log"><div class="big">${total===0 ? 'No complaints logged yet' : 'No entries match these filters'}</div>${total===0 ? 'Click "New Complaint" to add your first entry.' : 'Try clearing a filter or the search box.'}</div>`;
    return;
  }
  filtered.forEach(d=>{
    const statusClass = d.status==='Resolved' ? 'resolved' : (d.status==='In Progress' ? 'progress' : 'pending');
    const row = document.createElement('div');
    row.className = 'entry';
    row.style.gridTemplateColumns = '90px 92px 1fr 130px 110px 90px';
    row.innerHTML = `
      <span class="ref">${escapeHtml(d.ref)}</span>
      <span class="date">${fmtDate(d.date)}</span>
      <div><p class="client">${escapeHtml(d.client)}</p><p class="quote">${escapeHtml((d.text||'').slice(0,160))}${(d.text||'').length>160?'…':''}</p></div>
      <span class="source-badge">${escapeHtml(d.assignee || '—')}</span>
      <span class="pill ${statusClass}">${escapeHtml(d.status)}</span>
      <span class="row-actions">
        <button class="icon-btn edit" title="Edit" data-id="${escapeHtml(d.id)}">✎</button>
        <button class="icon-btn del" title="Delete" data-id="${escapeHtml(d.id)}">🗑</button>
      </span>
    `;
    logEl.appendChild(row);
  });
  logEl.querySelectorAll('.icon-btn.edit').forEach(b=>b.addEventListener('click', ()=>openComplaintModal(b.dataset.id)));
  logEl.querySelectorAll('.icon-btn.del').forEach(b=>b.addEventListener('click', ()=>deleteComplaint(b.dataset.id)));
}

document.getElementById('xQuarterFilter').addEventListener('click', e=>{
  const b = e.target.closest('.filter-btn'); if(!b) return;
  document.querySelectorAll('#xQuarterFilter .filter-btn').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); xFilters.q = b.dataset.q; renderComplaints();
});
document.getElementById('xStatusFilter').addEventListener('click', e=>{
  const b = e.target.closest('.filter-btn'); if(!b) return;
  document.querySelectorAll('#xStatusFilter .filter-btn').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); xFilters.s = b.dataset.s; renderComplaints();
});
document.getElementById('xSearch').addEventListener('input', e=>{
  xFilters.search = e.target.value.toLowerCase(); renderComplaints();
});

function openComplaintModal(id){
  editingXId = id || null;
  const modal = document.getElementById('xModalOverlay');
  if(id){
    const d = complaints.find(x=>x.id===id);
    if (d) {
      document.getElementById('xModalTitle').textContent = 'Edit Complaint';
      document.getElementById('f_x_date').value = d.date || '';
      document.getElementById('f_x_client').value = d.client || '';
      document.getElementById('f_x_text').value = d.text || '';
      document.getElementById('f_x_source').value = d.source || 'Facebook';
      document.getElementById('f_x_assignee').value = d.assignee || '';
      document.getElementById('f_x_response').value = d.response || '';
      document.getElementById('f_x_action').value = d.action || '';
      document.getElementById('f_x_status').value = d.status || 'Pending';
      document.getElementById('f_x_response_date').value = d.response_date || '';
    }
  }else{
    document.getElementById('xModalTitle').textContent = 'New Complaint';
    ['f_x_date','f_x_client','f_x_text','f_x_assignee','f_x_response','f_x_action','f_x_response_date'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('f_x_source').value = 'Facebook';
    document.getElementById('f_x_status').value = 'Pending';
    document.getElementById('f_x_date').value = new Date().toISOString().slice(0,10);
  }
  modal.classList.add('open');
}
document.getElementById('xAddBtn').addEventListener('click', ()=>openComplaintModal(null));
document.getElementById('xCancel').addEventListener('click', ()=>document.getElementById('xModalOverlay').classList.remove('open'));
document.getElementById('xModalOverlay').addEventListener('click', e=>{ if(e.target.id==='xModalOverlay') e.target.classList.remove('open'); });

document.getElementById('xSave').addEventListener('click', async ()=>{
  const date = document.getElementById('f_x_date').value;
  const client = document.getElementById('f_x_client').value.trim();
  const text = document.getElementById('f_x_text').value.trim();
  if(!date || !client || !text){ 
    showToast('Please fill in all required fields: Date, Client Name, and Issue description.', 'error');
    return; 
  }
  
  const formData = new FormData();
  if(editingXId) {
    formData.append('action', 'edit_complaint');
    formData.append('id', editingXId);
    const existing = complaints.find(x=>x.id===editingXId);
    if (existing) formData.append('ref', existing.ref);
  } else {
    formData.append('action', 'add_complaint');
  }
  formData.append('date', date);
  formData.append('client', client);
  formData.append('text', text);
  formData.append('source', document.getElementById('f_x_source').value);
  formData.append('assignee', document.getElementById('f_x_assignee').value.trim());
  formData.append('response', document.getElementById('f_x_response').value.trim());
  formData.append('action_item', document.getElementById('f_x_action').value.trim());
  formData.append('status', document.getElementById('f_x_status').value);
  formData.append('response_date', document.getElementById('f_x_response_date').value);

  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if(result.success) {
      document.getElementById('xModalOverlay').classList.remove('open');
      showToast(result.message, 'success');
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showToast(result.message || 'Error saving entry', 'error');
    }
  } catch(e) {
    showToast('Error saving entry. Please try again.', 'error');
  }
});

async function deleteComplaint(id){
  if(!confirm('Delete this complaint entry? This cannot be undone.')) return;
  
  const formData = new FormData();
  formData.append('action', 'delete_complaint');
  formData.append('id', id);

  try {
    const response = await fetch(window.location.href, {
      method: 'POST',
      body: formData
    });
    const result = await response.json();
    if(result.success) {
      showToast(result.message, 'success');
      setTimeout(() => window.location.reload(), 1500);
    } else {
      showToast(result.message || 'Error deleting entry', 'error');
    }
  } catch(e) {
    showToast('Error deleting entry. Please try again.', 'error');
  }
}

document.getElementById('xExport').addEventListener('click', ()=>exportCsv(complaints,
  ['ref','date','quarter','client','text','source','assignee','response','action','status','response_date'],
  ['Ref','Date','Quarter','Client','Issue','Source','Assignee','Initial Response','Corrective Action','Status','Date Resolved'],
  'complaints.csv'));

function exportCsv(rows, fields, headers, filename){
  if(rows.length===0){ showToast('Nothing to export yet', 'info'); return; }
  const csvRows = [headers.join(',')];
  rows.forEach(r=>{
    const line = fields.map(f=>{
      let v = (r[f] ?? '').toString().replace(/"/g,'""');
      return `"${v}"`;
    }).join(',');
    csvRows.push(line);
  });
  const blob = new Blob([csvRows.join('\n')], {type:'text/csv'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = filename;
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  URL.revokeObjectURL(url);
  showToast('CSV downloaded successfully!', 'success');
}

// ---------- TABS ----------
document.getElementById('tabCompliments').addEventListener('click', ()=>{
  document.getElementById('tabCompliments').classList.add('active');
  document.getElementById('tabComplaints').classList.remove('active');
  document.getElementById('panelCompliments').style.display = '';
  document.getElementById('panelComplaints').style.display = 'none';
});
document.getElementById('tabComplaints').addEventListener('click', ()=>{
  document.getElementById('tabComplaints').classList.add('active');
  document.getElementById('tabCompliments').classList.remove('active');
  document.getElementById('panelComplaints').style.display = '';
  document.getElementById('panelCompliments').style.display = 'none';
});

// Initial render
renderCompliments();
renderComplaints();
</script>
</body>
</html>