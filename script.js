// script.js

// Function to format currency as Rupiah
function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

// Update total price when quantity changes in buy_product.php
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.querySelector('input[name="quantity"]');
    const priceInput = document.querySelector('input[readonly]');
    
    if (quantityInput && priceInput) {
        quantityInput.addEventListener('input', function() {
            const quantity = parseInt(this.value) || 0;
            const price = parseFloat(priceInput.getAttribute('data-price')) || 0;
            const total = quantity * price;
            priceInput.value = formatRupiah(total);
        });
    }
});

// Add confirmation for delete actions
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('a[href*="delete"]');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
});