<?php
session_start();
require_once '../../dbRelated/operation.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    header("Location: /LabFlow/index.php");
    exit();
}

$db = new DataManager();
$userId = $_SESSION['user_id'];

$userProfile = $db->getUserProfileData($userId);
$liabilityInfo = $db->checkLiability($userId);
$liabilities = $liabilityInfo['items'] ?? [];

if (!$liabilityInfo['has_liability']) {
    // Optional: redirect or show a message if there are no liabilities to report.
    die("You have no pending liabilities to generate a receipt for.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clearance Receipt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'VT323', monospace;
            background-color: #f3f4f6;
        }
        .receipt {
            width: 302px; /* Standard thermal receipt width */
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .receipt-header, .receipt-footer {
            text-align: center;
        }
        .receipt-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: bold;
        }
        .receipt-header p {
            margin: 0;
            font-size: 0.9rem;
        }
        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .receipt-table {
            width: 100%;
            font-size: 0.9rem;
        }
        .receipt-table th, .receipt-table td {
            padding: 2px 0;
        }
        .receipt-table th {
            text-align: left;
            border-bottom: 1px dashed #000;
        }
        .receipt-table .qty {
            text-align: center;
        }
        .receipt-table .item {
            text-align: left;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 0 10px;
            font-size: 0.9rem;
        }
        .info-grid .label {
            font-weight: bold;
        }
        @media print {
            body {
                background-color: white;
            }
            .no-print {
                display: none;
            }
            .receipt {
                box-shadow: none;
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="flex flex-col items-center pt-10">

    <div class="receipt mx-auto">
        <div class="receipt-header">
            <h1>WMSU-CSM LabFlow</h1>
            <p>Pending Liability Slip</p>
        </div>

        <div class="receipt-divider"></div>

        <div class="info-grid">
            <span class="label">Name:</span>
            <span><?= htmlspecialchars($userProfile['Full_Name'] ?? 'N/A') ?></span>
            <span class="label">ID No:</span>
            <span><?= htmlspecialchars($userProfile['ID_Number'] ?? 'N/A') ?></span>
            <span class="label">Date:</span>
            <span><?= date('Y-m-d H:i:s') ?></span>
        </div>

        <div class="receipt-divider"></div>

        <h2 class="text-center font-bold my-2">UNRESOLVED LIABILITIES</h2>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th class="item">ITEM</th>
                    <th class="qty">QTY</th>
                    <th>TYPE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($liabilities as $item): ?>
                    <tr>
                        <td class="item"><?= htmlspecialchars($item['Item_Name']) ?></td>
                        <td class="qty"><?= htmlspecialchars($item['qty_damaged']) ?></td>
                        <td><?= htmlspecialchars($item['damage_type']) ?></td>
                    </tr>
                    <tr class="text-xs">
                        <td colspan="3" class="pb-2">
                            Ref Slip: SLIP-<?= htmlspecialchars($item['SessionID']) ?> (<?= date('M d, Y', strtotime($item['SlipDate'])) ?>)
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-divider"></div>

        <div class="receipt-footer text-xs">
            <p class="font-bold">*** NOT A PROOF OF SETTLEMENT ***</p>
            <p>This slip is a record of pending liabilities. Please coordinate with the laboratory custodian to resolve these items.</p>
        </div>
    </div>

    <div class="mt-6 text-center no-print">
        <button onclick="window.print()" class="bg-orange-500 text-white px-8 py-3 rounded-lg font-bold text-sm hover:bg-orange-600 transition-all">
            Print Receipt
        </button>
    </div>

</body>
</html>