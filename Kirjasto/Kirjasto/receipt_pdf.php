<?php
// receipt_pdf.php - Modern minimal kuitti (Dompdf compatible)
require_once 'dompdf/autoload.inc.php';
require_once 'connection.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) {
    header("Location: user_kuitit.php");
    exit();
}

$kuitin_id = (int)$_GET['id'];

$query = "SELECT k.*, j.etunimi, j.sukunimi, j.email, j.jasennumero 
          FROM kuitit k 
          JOIN jasenet j ON k.jasen_id = j.id 
          WHERE k.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $kuitin_id);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();

if (!$receipt) {
    die("Kuittia ei löytynyt");
}

$is_payment     = $receipt['summa'] > 0;
$receipt_type   = $is_payment ? 'MAKSOKUITTI' : 'LAINAUSKUITTI';
$receipt_number = str_pad($receipt['id'], 8, '0', STR_PAD_LEFT);
$formatted_date = date('d.m.Y', strtotime($receipt['luotu']));
$formatted_time = date('H:i',   strtotime($receipt['luotu']));

// Open book icon — flat outline, black, transparent background (base64 PNG)
$book_icon = 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAYAAAA5ZDbSAAACM0lEQVR4nO3dQW7CMBBA0aTiBD5wFz1wrpBurREpJtjJ+Pe/NUSYL4M1Qe267/sirq+7X4DGMjCcgeEMDGdgOAPDGRjOwHAGhjMwnIHhDAxnYDgDwxkYzsBwBoYzMJyB4R6tDyyl+OOtZLZtW189xh0MZ2A4A8MZGK75kPVMy5f8Xc4cCmnrWRZ3MJ6B4QwMZ2A4A8MZGM7AcAaG+2jQUSul/PS61lnbtn33uhZlPe5gOAPDra1/hOXZLJQ2u51tPd7wl4HpDAxnYDgDwxkYzsBwBoZzFn2Ash53MJyB4ZxFV2Zbj7NoGZjOwHAGhjMwnIHhDAxnYDhn0Qco63EHwxkYzll0Zbb1OIuWgekMDGdgOAPDGRjOwHAGhnMWfYCyHncwnIHhnEVXZluPs2gZmM7AcAaGMzCcgeEMDGdgOGfRByjrcQfDGRjOWXRltvU4i5aB6QwMZ2A4A8N9NOg4+1+ps6KtZ1ncwXgGhjMwnIHhut1NOpJl/Jd51DrycNcU+JMXUD83yxuaQY+opZT91XvaFDhe5OyLi8/7T8F77dJ337NTH9EGf+2uoFGX72CD5wkaDTlk1S+y1/d3vO7deh6MRq7r8lP0rMFnCRoNDxyNCj7iTcv6sfuOywNHmb6/CUGj5t9k3WWGOzyZgkbpA0cZgmcOGk0XuHZl7Jmi1qYOHM160h0JFTg6usGQ+cZDb7efokf664RODRqhd7C84Y9nYDgDwxkYzsBwBoYzMJyB4X4BfnUckzGAVIQAAAAASUVORK5CYII=';

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kuitti #' . $receipt_number . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            background: #ffffff;
            color: #111111;
            padding: 56px 52px;
        }

        /* Header */
        .brand-row {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
            margin-bottom: 3px;
        }
        .brand-icon {
            width: 13px;
            height: 13px;
            vertical-align: middle;
            margin-right: 5px;
        }
        .brand-sub {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
            padding-left: 18px;
        }
        .badge {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            border: 1px solid #111;
            padding: 5px 13px;
            border-radius: 20px;
            color: #111;
            float: right;
            margin-top: -20px;
        }
        .clearfix { clear: both; }

        /* Receipt number */
        .num-section {
            margin: 40px 0 36px;
        }
        .meta-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
            margin-bottom: 6px;
        }
        .num-value {
            font-size: 22px;
            font-weight: bold;
            color: #111;
        }

        /* Dividers */
        .line {
            width: 100%;
            border: none;
            border-top: 1px solid #dddddd;
            margin: 20px 0;
        }
        .line-bold {
            width: 100%;
            border: none;
            border-top: 1px solid #111111;
            margin: 24px 0;
        }

        /* Section labels */
        .section-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
            margin-bottom: 10px;
        }

        /* Info table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 0;
            font-size: 10px;
            font-weight: bold;
            color: #111;
            vertical-align: middle;
        }
        .info-table .label {
            width: 44%;
            font-weight: bold;
        }
        .info-table .value {
            text-align: right;
            font-weight: bold;
        }

        /* Description box */
        .desc-box {
            background: #f5f5f5;
            border-radius: 6px;
            padding: 14px;
            margin-top: 4px;
        }
        .desc-text {
            font-size: 10px;
            font-weight: bold;
            color: #111;
            line-height: 1.6;
        }

        /* Amount */
        .amount-label {
            float: left;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
            padding-top: 7px;
        }
        .amount-value {
            float: right;
            font-size: 22px;
            font-weight: bold;
            color: #111;
        }

        /* Footer */
        .footer {
            margin-top: 48px;
            padding-top: 18px;
            border-top: 1px solid #dddddd;
        }
        .footer-left {
            float: left;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
        }
        .footer-right {
            float: right;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: normal;
            color: #111;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="brand-row">
        <img class="brand-icon" src="data:image/png;base64,' . $book_icon . '" alt="">KIRJASTO
    </div>
    <div class="brand-sub">LUKEMISEN ILOA</div>
    <div class="badge">' . $receipt_type . '</div>
    <div class="clearfix"></div>

    <!-- Receipt number -->
    <div class="num-section">
        <div class="meta-label">KUITIN NUMERO</div>
        <div class="num-value">#' . $receipt_number . '</div>
    </div>

    <hr class="line">

    <!-- Member info -->
    <div class="section-label">JÄSENTIEDOT</div>
    <table class="info-table">
        <tr>
            <td class="label">Nimi</td>
            <td class="value">' . htmlspecialchars($receipt['etunimi'] . ' ' . $receipt['sukunimi']) . '</td>
        </tr>
        <tr>
            <td class="label">Jäsennumero</td>
            <td class="value">' . htmlspecialchars($receipt['jasennumero']) . '</td>
        </tr>
        <tr>
            <td class="label">Sähköposti</td>
            <td class="value">' . htmlspecialchars($receipt['email']) . '</td>
        </tr>
    </table>

    <hr class="line">

    <!-- Transaction -->
    <div class="section-label">TAPAHTUMA</div>
    <table class="info-table">
        <tr>
            <td class="label">Päivämäärä</td>
            <td class="value">' . $formatted_date . '</td>
        </tr>
        <tr>
            <td class="label">Kellonaika</td>
            <td class="value">' . $formatted_time . '</td>
        </tr>
    </table>

    <hr class="line">

    <!-- Description -->
    <div class="section-label">KUVAUS</div>
    <div class="desc-box">
        <div class="desc-text">' . nl2br(htmlspecialchars($receipt['kuvaus'])) . '</div>
    </div>

    <hr class="line-bold">

    <!-- Amount -->
    <div class="amount-label">YHTEENSÄ</div>
    <div class="amount-value">' . number_format($receipt['summa'], 2, ',', ' ') . ' €</div>
    <div class="clearfix"></div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">SÄHKÖINEN KUITTI</div>
        <div class="footer-right">KIITOS ASIOINNISTA</div>
        <div class="clearfix"></div>
    </div>

</body>
</html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("kuitti_" . $receipt_number . ".pdf", ["Attachment" => true]);
exit();
?>
