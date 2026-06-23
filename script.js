// DOM Elements
const productSelect = document.getElementById('productSelect');
const quantityInput = document.getElementById('quantity');
const currentStockDisplay = document.getElementById('currentStock');
const totalAmountInput = document.getElementById('totalAmount');
const amountPaidInput = document.getElementById('amountPaid');
const changeAmountInput = document.getElementById('changeAmount');
const paymentButtons = document.querySelectorAll('.btn-payment');
const debtInfoBox = document.getElementById('debtInfoBox');
const debtorNameInput = document.getElementById('debtorName');
const debtorPhoneInput = document.getElementById('debtorPhone');
const resetBtn = document.getElementById('resetBtn');
const recordSaleBtn = document.querySelector('.btn-primary');

let inventory = [];
let selectedMethod = 'cash';

// Load dynamic inventory from localStorage
function loadSalesInventory() {
    const stored = localStorage.getItem('beauty_inventory');
    inventory = stored ? JSON.parse(stored) : [];
    
    productSelect.innerHTML = '';
    inventory.forEach(product => {
        const opt = document.createElement('option');
        opt.value = product.id;
        opt.textContent = `${product.brand} - ${product.name} — ${product.sellingPrice} Ksh`;
        productSelect.appendChild(opt);
    });

    updateSelectedProductDetails();
}

function updateSelectedProductDetails() {
    const matchedProduct = inventory.find(p => p.id === productSelect.value);
    if (matchedProduct) {
        currentStockDisplay.textContent = matchedProduct.stock;
        updateCalculations();
        if (selectedMethod !== 'debt') {
            amountPaidInput.value = parseFloat(totalAmountInput.value) || 0;
        }
        updateCalculations();
    }
}

function updateCalculations() {
    const matchedProduct = inventory.find(p => p.id === productSelect.value);
    const price = matchedProduct ? matchedProduct.sellingPrice : 0;
    const quantity = parseFloat(quantityInput.value) || 0;
    
    const total = price * quantity;
    totalAmountInput.value = total + ' Ksh';

    const paid = parseFloat(amountPaidInput.value) || 0;
    const change = paid - total;
    
    if (change >= 0) {
        changeAmountInput.value = change + ' Ksh';
    } else {
        changeAmountInput.value = '0 Ksh (Debt: ' + Math.abs(change) + ' Ksh)';
    }
}

// Event Listeners for inputs
productSelect.addEventListener('change', updateSelectedProductDetails);
quantityInput.addEventListener('input', updateCalculations);
amountPaidInput.addEventListener('input', updateCalculations);

// Payment Method Toggle Logic
paymentButtons.forEach(button => {
    button.addEventListener('click', () => {
        paymentButtons.forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
        selectedMethod = button.getAttribute('data-method');

        if (selectedMethod === 'debt') {
            debtInfoBox.style.display = 'block';
            debtorNameInput.required = true;
            debtorPhoneInput.required = true;
            amountPaidInput.value = 0; 
        } else {
            debtInfoBox.style.display = 'none';
            debtorNameInput.required = false;
            debtorPhoneInput.required = false;
            amountPaidInput.value = parseFloat(totalAmountInput.value) || 0;
        }
        updateCalculations();
    });
});

// Record Transaction, Deduct Stock & Write Logs
recordSaleBtn.addEventListener('click', (e) => {
    e.preventDefault();
    
    const prodId = productSelect.value;
    const targetIndex = inventory.findIndex(p => p.id === prodId);
    const qtyToBuy = parseInt(quantityInput.value) || 0;

    if (targetIndex !== -1) {
        const product = inventory[targetIndex];

        if (qtyToBuy > product.stock) {
            alert(`❌ Out of Stock Alert! Only ${product.stock} left.`);
            return;
        }

        let calculatedDebtValue = 0;
        const totalCostValue = product.sellingPrice * qtyToBuy;
        const paidAmountValue = parseFloat(amountPaidInput.value) || 0;

        // Process Debt validation & logging block if applicable
        if (selectedMethod === 'debt' || paidAmountValue < totalCostValue) {
            calculatedDebtValue = totalCostValue - paidAmountValue;

            if (!debtorNameInput.value.trim() || !debtorPhoneInput.value.trim()) {
                alert("⚠️ Attention Required!\nPlease enter the Customer's Name and Phone Number to log this outstanding debt balance.");
                return;
            }

            const currentDebts = JSON.parse(localStorage.getItem('beauty_debts')) || [];
            currentDebts.push({
                id: "d_" + Date.now(),
                customerName: debtorNameInput.value.trim(),
                customerPhone: debtorPhoneInput.value.trim(),
                productPurchased: product.name,
                initialDebt: calculatedDebtValue,
                amountPaid: 0,
                totalOwed: calculatedDebtValue,
                dateLogged: new Date().toLocaleDateString()
            });
            localStorage.setItem('beauty_debts', JSON.stringify(currentDebts));
        }

        // IMPROVED REVENUE TRACKING: Log descriptive receipts into master history files
        if (paidAmountValue > 0) {
            const currentSales = JSON.parse(localStorage.getItem('beauty_sales')) || [];
            currentSales.push({
                id: "s_" + Date.now(),
                // Appends a custom descriptor label if a partial payment deposit exists
                description: selectedMethod === 'debt' 
                    ? `${product.name} (x${qtyToBuy}) [Deposit by ${debtorNameInput.value.trim()}]` 
                    : `${product.name} (x${qtyToBuy})`,
                amount: paidAmountValue,
                // Assigns the 'debt-deposit' method tag for clean rendering on the View Sales report
                method: selectedMethod === 'debt' ? 'debt-deposit' : selectedMethod, 
                date: new Date().toLocaleDateString()
            });
            localStorage.setItem('beauty_sales', JSON.stringify(currentSales));
        }

        // Deduct from inventory layout block
        inventory[targetIndex].stock -= qtyToBuy;
        localStorage.setItem('beauty_inventory', JSON.stringify(inventory));

        alert(`🛍️ Transaction Saved Successfully!\n${calculatedDebtValue > 0 ? `Logged ${calculatedDebtValue} Ksh debt.` : 'Paid in Full.'}`);
        
        formResetRoutine();
        loadSalesInventory();
    }
});

function formResetRoutine() {
    quantityInput.value = 1;
    debtorNameInput.value = '';
    debtorPhoneInput.value = '';
    paymentButtons.forEach(btn => btn.classList.remove('active'));
    document.querySelector('[data-method="cash"]').classList.add('active');
    debtInfoBox.style.display = 'none';
    selectedMethod = 'cash';
}

resetBtn.addEventListener('click', () => {
    formResetRoutine();
    updateSelectedProductDetails();
});

loadSalesInventory();