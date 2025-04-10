// order_management.js

function showToast(message, type = 'info') {
    const toastEl = document.getElementById('liveToast');
    if (!toastEl) return;
    
    const toastBody = toastEl.querySelector('.toast-body');
    toastEl.classList.remove('bg-primary', 'bg-success', 'bg-danger');
    toastEl.classList.add(`bg-${type}`);
    toastBody.textContent = message;
    new bootstrap.Toast(toastEl).show();
}

// Update Status (Uses update_order_status.php in pages/ajax)
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-update-status')) {
        const orderId = e.target.dataset.orderId;
        const currentStatus = document.querySelector(`#status-${orderId}`).textContent.trim();

        document.querySelectorAll('.status-card').forEach(card => {
            card.classList.remove('selected');
            if (card.dataset.status.toLowerCase() === currentStatus.toLowerCase()) {
                card.classList.add('selected');
                card.querySelector('input').checked = true;
            }
        });
        
        new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
    }
});

// Global Status Selection Function
window.selectStatus = function(status) {
    document.querySelectorAll('.status-card').forEach(card => {
        card.classList.remove('selected');
        if (card.dataset.status === status) {
            card.classList.add('selected');
            card.querySelector('input').checked = true;
        }
    });
};

// Save Status Handler
document.getElementById('saveStatus').addEventListener('click', async () => {
    const orderId = document.getElementById('modalOrderId').value;
    const status = document.querySelector('input[name="new_status"]:checked')?.value;
    
    if (!status) {
        showToast('Please select a status', 'danger');
        return;
    }

    try {
        // Adjusted path for update_order_status.php:
        const response = await fetch(`../../pages/ajax/update_order_status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, new_status: status })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.querySelector(`#status-${orderId}`).textContent = status;
            showToast('Status updated successfully!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('updateStatusModal'));
            if (modal) modal.hide();
        } else {
            showToast(result.error || 'Update failed', 'danger');
        }
    } catch (error) {
        showToast('Network error - please try again', 'danger');
        console.error('Update error:', error);
    }
});

// Real-time Alerts System
let lastCheck = Math.floor(Date.now() / 1000);
const alertSound = new Audio('../../assets/sounds/new-order.mp3'); 
// Adjust if needed, depending on where new-order.mp3 actually is.

async function checkNewOrders() {
    try {
        // Adjusted path for get_new_orders.php:
        const response = await fetch(`../../pages/ajax/get_new_orders.php?lastCheck=${lastCheck}`);
        console.log("HTTP Status:", response.status);
        if (!response.ok) {
            const text = await response.text();
            console.error("Server response:", text);
            throw new Error("Network response was not ok");
        }
        
        const data = await response.json();
        console.log("Data received:", data);
        
        // Display new orders, if any
        if (Array.isArray(data.orders) && data.orders.length > 0) {
            data.orders.forEach(order => {
                const date = new Date(order.timestamp * 1000);
                const alertHTML = `
                    <div class="list-group-item list-group-item-warning new-order-alert">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>New Order #${order.order_id}</strong><br>
                                <small>${order.customer} - ₱${order.total_price}</small>
                            </div>
                            <small>${date.toLocaleTimeString()}</small>
                        </div>
                    </div>
                `;
                document.getElementById('liveAlerts').insertAdjacentHTML('afterbegin', alertHTML);

                // Attempt to play alert sound
                alertSound.play().catch(() => {});

                // Remove alert after 10s
                setTimeout(() => {
                    const alertElem = document.querySelector('.new-order-alert');
                    if (alertElem) alertElem.remove();
                }, 10000);
            });
        }
        
        // Update lastCheck based on server response
        lastCheck = data.lastCheck ? data.lastCheck : Math.floor(Date.now() / 1000);
    } catch (error) {
        console.error('Error fetching orders:', error);
        const errorAlert = `
          <div class="list-group-item list-group-item-danger">
              Connection Error: ${error.message}
          </div>
        `;
        document.getElementById('liveAlerts').insertAdjacentHTML('afterbegin', errorAlert);
    }
}

// Poll for new orders every 5 seconds
setInterval(checkNewOrders, 5000);

// If you have a 'refreshAlerts' button, attach the handler
// document.getElementById('refreshAlerts').addEventListener('click', checkNewOrders);

checkNewOrders();
