<?php
// process-returns.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/session-init.php';

try {
    // Fetch pending returns
    $pendingStmt = $pdo->prepare("
        SELECT 
            r.return_id, 
            r.return_date, 
            r.reason, 
            ao.order_id, 
            u.name AS customer_name
        FROM returns r
        JOIN archived_orders ao ON r.order_id = ao.order_id
        JOIN users u ON ao.customer_id = u.user_id
        WHERE r.return_status = 'Pending'
        ORDER BY r.return_date DESC
    ");
    $pendingStmt->execute();
    $pendingReturns = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch processed returns
    $processedStmt = $pdo->prepare("
        SELECT 
            r.return_id, 
            r.return_date, 
            r.reason, 
            ao.order_id, 
            u.name AS customer_name, 
            r.return_status,
            r.last_status_update
        FROM returns r
        JOIN archived_orders ao ON r.order_id = ao.order_id
        JOIN users u ON ao.customer_id = u.user_id
        WHERE r.return_status IN ('Approved', 'Rejected')
        ORDER BY r.return_date DESC
    ");
    $processedStmt->execute();
    $processedReturns = $processedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div style='padding: 20px; color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Returns</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>

<body class="bg-light">
    <?php include '../includes/sidebar.php'; ?>

    <div class="container-fluid px-4 mt-4">
        <h1 class="mb-4">Process Returns</h1>

        <!-- Pending Returns -->
        <div class="card mb-5 shadow">
            <div class="card-header bg-warning text-dark d-flex align-items-center">
                <i class="bi bi-arrow-counterclockwise me-2"></i>
                <span>Pending Returns</span>
                <span class="badge bg-danger ms-2"><?= count($pendingReturns) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingReturns)): ?>
                    <div class="alert alert-info m-3">No pending return requests.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#Return ID</th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date Requested</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingReturns as $r): ?>
                                    <tr>
                                        <td><?= $r['return_id'] ?></td>
                                        <td>#<?= $r['order_id'] ?></td>
                                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($r['return_date'])) ?></td>
                                        <td><?= htmlspecialchars($r['reason']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success me-1 process-return-btn"
                                                data-action="approve"
                                                data-return-id="<?= $r['return_id'] ?>">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger process-return-btn"
                                                data-action="reject"
                                                data-return-id="<?= $r['return_id'] ?>">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Processed Returns -->
        <div class="card shadow">
            <div class="card-header bg-secondary text-white d-flex align-items-center">
                <i class="bi bi-check2-circle me-2"></i>
                <span>Processed Returns</span>
                <span class="badge bg-info ms-2"><?= count($processedReturns) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($processedReturns)): ?>
                    <div class="alert alert-info m-3">No processed returns yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 processed-returns">
                            <thead class="table-light">
                                <tr>
                                    <th>#Return ID</th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date Requested</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processedReturns as $r): ?>
                                    <tr>
                                        <td><?= $r['return_id'] ?></td>
                                        <td>#<?= $r['order_id'] ?></td>
                                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($r['return_date'])) ?></td>
                                        <td><?= htmlspecialchars($r['reason']) ?></td>
                                        <td>
                                            <?php if ($r['return_status'] === 'Approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Confirmation Modal -->
    <div class="modal fade" id="confirmReturnModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Return Action</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmReturnText" class="fw-semibold"></p>
                    <div class="card border-warning mb-3">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted">Customer:</small>
                                    <p class="mb-0 fw-bold" id="modalCustomerName"></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Order #:</small>
                                    <p class="mb-0 fw-bold" id="modalOrderId"></p>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Reason:</small>
                                    <p class="mb-0 text-break" id="modalReason"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-triangle-fill"></i> This action is permanent!</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button id="confirmReturnAction" class="btn">
                        <span class="action-text"></span>
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="returnToast" class="toast align-items-center text-white border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="toast-icon me-2"></i>
                    <span id="returnToastBody"></span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentReturnId, currentAction, currentRow;
            const confirmModal = new bootstrap.Modal('#confirmReturnModal');
            const confirmBtn = document.getElementById('confirmReturnAction');
            const toastEl = new bootstrap.Toast(document.getElementById('returnToast'));
            const toast = document.getElementById('returnToast');

            // Process Return Event Listeners
            document.querySelectorAll('.process-return-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    currentRow = e.target.closest('tr');
                    currentReturnId = btn.dataset.returnId;
                    currentAction = btn.dataset.action;

                    // Populate modal with row data
                    const rowCells = currentRow.cells;
                    document.getElementById('modalCustomerName').textContent = rowCells[2].textContent;
                    document.getElementById('modalOrderId').textContent = rowCells[1].textContent;
                    document.getElementById('modalReason').textContent = rowCells[4].textContent;

                    const actionText = currentAction === 'approve' ? 'Approve' : 'Reject';
                    document.getElementById('confirmReturnText').textContent =
                        `Confirm to ${actionText} Return #${currentReturnId}?`;

                    // Update confirm button
                    const confirmBtnContent = confirmBtn.querySelector('.action-text');
                    confirmBtnContent.textContent = actionText;
                    confirmBtn.className = `btn btn-${currentAction === 'approve' ? 'success' : 'danger'}`;
                    confirmModal.show();
                });
            });

            // Confirm Action Handler
            confirmBtn.addEventListener('click', async () => {
                const spinner = confirmBtn.querySelector('.spinner-border');
                const actionText = confirmBtn.querySelector('.action-text');

                // Show loading state
                actionText.classList.add('d-none');
                spinner.classList.remove('d-none');
                confirmBtn.disabled = true;

                try {
                    const response = await fetch('/pages/ajax/update_return_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            return_id: currentReturnId,
                            new_status: currentAction === 'approve' ? 'Approved' : 'Rejected'
                        })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Failed to process return');
                    }

                    // Remove from pending table
                    currentRow.remove();

                    // Add to processed table
                    const processedTable = document.querySelector('.processed-returns tbody');
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
        <td>${data.return.return_id}</td>
        <td>#${data.return.order_id}</td>
        <td>${data.return.customer_name}</td>
        <td>${new Date(data.return.return_date).toLocaleDateString('en-US', { 
          month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' 
        })}</td>
        <td>${data.return.reason}</td>
        <td>
          <span class="badge bg-${data.return.return_status === 'Approved' ? 'success' : 'danger'}">
            ${data.return.return_status}
          </span>
        </td>
      `;
                    processedTable.prepend(newRow);

                    showToast('success', `Return #${currentReturnId} ${currentAction}d successfully!`);
                } catch (error) {
                    console.error('Error:', error);
                    showToast('danger', `Error: ${error.message}`);
                } finally {
                    // Reset button state
                    actionText.classList.remove('d-none');
                    spinner.classList.add('d-none');
                    confirmBtn.disabled = false;
                    confirmModal.hide();
                }
            });

            // Toast Helper Function
            function showToast(type, message) {
                const iconMap = {
                    success: 'bi-check-circle-fill',
                    danger: 'bi-exclamation-triangle-fill',
                    info: 'bi-info-circle-fill'
                };

                toast.querySelector('.toast-icon').className = `toast-icon bi ${iconMap[type]} me-2`;
                toast.querySelector('#returnToastBody').textContent = message;
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toastEl.show();
            }
        });
    </script>

</body>

</html>