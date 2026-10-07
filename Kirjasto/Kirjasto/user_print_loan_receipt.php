<?php
session_start();
$current_page = basename($_SERVER["PHP_SELF"]);
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'book';

$data = null;

if ($type == 'receipt') {
    // Get receipt from kuitit table
    $query = "SELECT k.*, j.etunimi, j.sukunimi, j.jasennumero, j.email 
              FROM kuitit k
              JOIN jasenet j ON k.jasen_id = j.id
              WHERE k.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $title = "MAKSUKUITTI";
    }
} elseif ($type == 'book') {
    // Get book loan details
    $query = "SELECT l.*, k.nimi as kirja_nimi, k.tekija, j.etunimi, j.sukunimi, j.jasennumero
              FROM lainat l
              JOIN kirjat k ON l.kirja_id = k.id
              JOIN jasenet j ON l.jasen_id = j.id
              WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $title = "LAINAKUITTI";
    }
} elseif ($type == 'device') {
    // Get device loan details
    $query = "SELECT l.*, d.merkki, d.malli, j.etunimi, j.sukunimi, j.jasennumero
              FROM Laitelainat l
              JOIN Laitteet d ON l.laite_id = d.id
              JOIN jasenet j ON l.jasen_id = j.id
              WHERE l.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $title = "LAINAKUITTI";
    }
}

if (!$data) {
    die("Tietoja ei löytynyt");
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Kirjasto</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
        }
        body {
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .receipt {
            max-width: 450px;
            background: white;
            padding: 25px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .receipt-header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #eee;
        }
        .total-row {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #333;
            font-weight: bold;
        }
        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 10px;
        }
        .print-btn {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
        }
        @media print {
            .print-btn { display: none; }
            body { background: white; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt-header">
            <h1>📄 <?php echo $title; ?></h1>
            <p>#<?php echo $id; ?></p>
            <p><?php echo date('d.m.Y H:i'); ?></p>
        </div>
        
        <div class="row">
            <span>Jäsen:</span>
            <span><?php echo htmlspecialchars($data['etunimi'] . ' ' . $data['sukunimi']); ?></span>
        </div>
        <div class="row">
            <span>Jäsennumero:</span>
            <span><?php echo $data['jasennumero']; ?></span>
        </div>
        
        <?php if ($type == 'receipt'): ?>
        <div class="row">
            <span>Kuvaus:</span>
            <span><?php echo htmlspecialchars($data['kuvaus']); ?></span>
        </div>
        <div class="row">
            <span>Summa:</span>
            <span><?php echo number_format($data['summa'], 2); ?> €</span>
        </div>
        <div class="row">
            <span>Maksupäivä:</span>
            <span><?php echo date('d.m.Y', strtotime($data['maksupaiva'])); ?></span>
        </div>
        <?php elseif ($type == 'book'): ?>
        <div class="row">
            <span>Kirja:</span>
            <span><?php echo htmlspecialchars($data['kirja_nimi']); ?></span>
        </div>
        <div class="row">
            <span>Tekijä:</span>
            <span><?php echo htmlspecialchars($data['tekija']); ?></span>
        </div>
        <div class="row">
            <span>Lainattu:</span>
            <span><?php echo date('d.m.Y', strtotime($data['lainauspaiva'])); ?></span>
        </div>
        <div class="row">
            <span>Palautettava:</span>
            <span><?php echo date('d.m.Y', strtotime($data['erapaiva'])); ?></span>
        </div>
        <?php elseif ($type == 'device'): ?>
        <div class="row">
            <span>Laite:</span>
            <span><?php echo htmlspecialchars($data['merkki'] . ' ' . $data['malli']); ?></span>
        </div>
        <div class="row">
            <span>Lainattu:</span>
            <span><?php echo date('d.m.Y', strtotime($data['lainaus_pvm'])); ?></span>
        </div>
        <div class="row">
            <span>Palautettava:</span>
            <span><?php echo date('d.m.Y', strtotime($data['erapaiva'])); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="total-row row">
            <span>STATUS:</span>
            <span>VOIMASSA</span>
        </div>
        
        <div class="receipt-footer">
            <p>Kiitos asioinnista!</p>
            <p>Kirjasto - Palvelemme ilolla</p>
        </div>
        
        <button class="print-btn" onclick="window.print()">🖨️ Tulosta</button>
    </div>
    <script>
        setTimeout(function() { window.print(); }, 500);
    </script>
</body>
</html>
