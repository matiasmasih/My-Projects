<?php
session_start();
$current_page = basename($_SERVER["PHP_SELF"]);
require_once 'connection.php';

$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'print';

if ($receipt_id == 0) {
    die("Kuittia ei löytynyt");
}

$receipt_query = "SELECT k.*,
                    j.etunimi, j.sukunimi, j.jasennumero, j.email, j.puhelin,
                    CASE
                        WHEN k.sakko_id IS NOT NULL THEN 'Sakko'
                        WHEN k.laina_id IS NOT NULL THEN 'Kirjalaina'
                        WHEN k.laitelaina_id IS NOT NULL THEN 'Laitelaina'
                        ELSE 'Maksu'
                    END as tyyppi
                    FROM kuitit k
                    LEFT JOIN jasenet j ON k.jasen_id = j.id
                    WHERE k.id = ?";

$stmt = $conn->prepare($receipt_query);
$stmt->bind_param("i", $receipt_id);
$stmt->execute();
$result = $stmt->get_result();
$receipt = $result->fetch_assoc();

if (!$receipt) {
    die("Kuittia ei löytynyt");
}

// Different title for client copy
$title = ($mode == 'client') ? 'ASIASKASTALLENNE' : 'KIRJASTON KUITTI';
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitti #<?php echo $receipt['id']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Courier New', monospace;
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
            font-size: 22px;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .receipt-header p {
            font-size: 12px;
            color: #666;
        }

        .receipt-details {
            margin-bottom: 20px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #eee;
        }

        .row .label {
            font-weight: bold;
            color: #333;
        }

        .row .value {
            color: #555;
        }

        .total-row {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 18px;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 10px;
            color: #888;
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
            font-size: 14px;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }

        .print-btn:hover {
            background: #2980b9;
        }

        .client-note {
            background: #fef3c7;
            padding: 10px;
            margin-top: 15px;
            text-align: center;
            font-size: 11px;
            border-radius: 5px;
            color: #d97706;
        }

        @media print {
            .print-btn {
                display: none;
            }
            body {
                background: white;
                padding: 0;
            }
            .receipt {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="receipt-header">
            <h1>📄 <?php echo $title; ?></h1>
            <p>Kuitti #<?php echo $receipt['id']; ?></p>
            <p><?php echo date('d.m.Y H:i', strtotime($receipt['maksupaiva'])); ?></p>
        </div>

        <div class="receipt-details">
            <div class="row">
                <span class="label">Jäsen:</span>
                <span class="value"><?php echo htmlspecialchars($receipt['etunimi'] . ' ' . $receipt['sukunimi']); ?></span>
            </div>
            <div class="row">
                <span class="label">Jäsennumero:</span>
                <span class="value"><?php echo $receipt['jasennumero']; ?></span>
            </div>
            <div class="row">
                <span class="label">Sähköposti:</span>
                <span class="value"><?php echo $receipt['email']; ?></span>
            </div>
            <?php if ($mode == 'client' && !empty($receipt['puhelin'])): ?>
            <div class="row">
                <span class="label">Puhelin:</span>
                <span class="value"><?php echo $receipt['puhelin']; ?></span>
            </div>
            <?php endif; ?>
            <div class="row">
                <span class="label">Tyyppi:</span>
                <span class="value"><?php echo $receipt['tyyppi']; ?></span>
            </div>
            <div class="row">
                <span class="label">Kuvaus:</span>
                <span class="value"><?php echo htmlspecialchars($receipt['kuvaus']); ?></span>
            </div>
        </div>

        <div class="total-row row">
            <span class="label">MAKSETTU YHTEENSÄ:</span>
            <span class="value"><?php echo number_format($receipt['summa'], 2); ?> €</span>
        </div>

        <div class="receipt-footer">
            <p>Kiitos asioinnista! Tämä on sähköinen kuitti.</p>
            <p>Kirjasto - Palvelemme ilolla</p>
        </div>

        <?php if ($mode == 'client'): ?>
        <div class="client-note">
            <i class="fas fa-save"></i> Säilytä tämä kuitti mahdollisia takuita varten.
        </div>
        <?php endif; ?>

        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Tulosta kuitti
        </button>
    </div>

    <script>
        // Auto print when page loads (only for print mode)
        <?php if ($mode == 'print'): ?>
        setTimeout(function() {
            window.print();
        }, 500);
        <?php endif; ?>
    </script>
</body>
</html>
