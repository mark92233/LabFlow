/**
 * This file handles: Modals, Carts, Filtering, and Admin Updates
*/

let cart = [];

// 1. MODAL SYSTEM [cite: 2025-12-06]
function viewPokemonCard(id) {
    console.log("Opening Card ID:", id); // Check console to see if this triggers
    const modal = document.getElementById('pokemon-modal');
    if (!modal) return console.error("Modal element #pokemon-modal not found!");

    modal.classList.remove('hidden');
    modal.innerHTML = '<div class="text-white font-black animate-pulse">LOADING CARD...</div>';

    fetch(`../../dbRelated/get_item_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            modal.innerHTML = html;
        })
        .catch(err => console.error("Fetch Error:", err));
}

function closeModal() {
    document.getElementById('pokemon-modal').classList.add('hidden');
}

// 2. SEARCH & FILTER [cite: 2025-12-06]
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('inventory-search');
    const categoryFilter = document.getElementById('category-filter');
    const cards = document.querySelectorAll('.apparatus-card');

    function filterInventory() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedCat = categoryFilter.value;

        cards.forEach(card => {
            const name = card.getAttribute('data-item-name');
            const id = card.getAttribute('data-item-id');
            const category = card.getAttribute('data-item-category');

            const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);
            const matchesCategory = (selectedCat === 'all' || category === selectedCat);

            if (matchesSearch && matchesCategory) {
                card.style.display = 'flex';
                card.style.opacity = '1';
            } else {
                card.style.display = 'none';
                card.style.opacity = '0';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterInventory);
    if (categoryFilter) categoryFilter.addEventListener('change', filterInventory);
});

// 3. CART LOGIC (STUDENT) [cite: 2025-12-06]
function addToCart(id, name) {
    const qtyInput = document.getElementById('modal-qty');
    const qty = qtyInput ? parseInt(qtyInput.value) : 1;
    
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({ id, name, qty });
    }
    
    updateSidebar();
    closeModal();
}

function updateSidebar() {
    const list = document.getElementById('cart-items-list');
    const input = document.getElementById('cart-data-input');
    
    if (!list) return;

    if (cart.length === 0) {
        list.innerHTML = '<div class="text-center text-[10px] text-slate-300 italic py-10">Your cart is empty</div>';
        return;
    }

    list.innerHTML = cart.map((item, index) => `
        <div class="flex justify-between items-center bg-slate-50 p-4 rounded-2xl border border-slate-100">
            <div>
                <p class="text-[10px] font-black text-slate-800 uppercase italic">${item.name}</p>
                <p class="text-[9px] text-blue-500 font-bold uppercase">Qty: ${item.qty}</p>
            </div>
            <button onclick="removeItem(${index})" class="text-red-400">×</button>
        </div>
    `).join('');

    if (input) input.value = JSON.stringify(cart);
}

function removeItem(index) {
    cart.splice(index, 1);
    updateSidebar();
}

// 4. ADMIN STOCK UPDATE [cite: 2025-12-06]
async function updateStockLevel(itemId) {
    const newTotal = document.getElementById('admin-total-qty').value;
    const response = await fetch('../../dbRelated/admin_update_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `item_id=${itemId}&new_total=${newTotal}`
    });
    const data = await response.json();
    if (data.success) {
        alert("Stock updated!");
        location.reload(); // Simple refresh to see new grid numbers [cite: 2025-12-06]
    }
}

// 5. MODAL QTY TOGGLE [cite: 2025-12-06]
function changeModalQty(step) {
    const input = document.getElementById('modal-qty');
    if (!input) return;
    let val = parseInt(input.value) + step;
    if (val >= 1 && val <= parseInt(input.max)) input.value = val;
}