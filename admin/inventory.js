document.addEventListener("DOMContentLoaded", () => {
    // Input Elements
    const inventoryForm = document.getElementById("inventory-form");
    const productNameInput = document.getElementById("product-name");
    const productBrandInput = document.getElementById("product-brand");
    const productCategoryInput = document.getElementById("product-category");
    const costPriceInput = document.getElementById("cost-price");
    const sellingPriceInput = document.getElementById("selling-price");
    const stockQuantityInput = document.getElementById("stock-quantity");
    const reorderLevelInput = document.getElementById("reorder-level");

    // UI Feedback & Form Controls
    const profitDisplay = document.getElementById("calculated-profit");
    const marginDisplay = document.getElementById("calculated-margin-pct");
    const btnSaveProduct = document.getElementById("btn-save-product");
    const btnClearForm = document.getElementById("btn-clear-form");
    const formTitle = document.getElementById("form-title");
    const formTitleIcon = document.getElementById("form-title-icon");

    // Table & Filter Controls
    const searchInput = document.getElementById("search-inventory");
    const statusFilter = document.getElementById("filter-stock-status");
    const tableBody = document.getElementById("inventory-table-body");

    let inventoryData = [];
    let editingProductId = null;

    // --- 1. LIVE PROFIT MARGIN CALCULATOR ---
    function updateMarginCalculation() {
        const cost = parseFloat(costPriceInput.value) || 0;
        const price = parseFloat(sellingPriceInput.value) || 0;
        const profit = price - cost;
        const marginPct = cost > 0 ? ((profit / cost) * 100).toFixed(1) : "0.0";

        profitDisplay.textContent = `KSh ${profit.toFixed(2)}`;
        marginDisplay.textContent = `${profit >= 0 ? '+' : ''}${marginPct}%`;

        if (profit > 0) {
            marginDisplay.style.color = "#16a34a";
            marginDisplay.style.backgroundColor = "#dcfce7";
        } else if (profit < 0) {
            marginDisplay.style.color = "#dc2626";
            marginDisplay.style.backgroundColor = "#fee2e2";
        } else {
            marginDisplay.style.color = "#475569";
            marginDisplay.style.backgroundColor = "#e2e8f0";
        }
    }

    costPriceInput.addEventListener("input", updateMarginCalculation);
    sellingPriceInput.addEventListener("input", updateMarginCalculation);

    // --- 2. FETCH PRODUCTS FROM POSTGRESQL API ---
    async function loadProducts() {
        try {
            const response = await fetch("../api/products.php");
            if (!response.ok) throw new Error("Network error fetching products");
            
            const data = await response.json();
            
            // Map Database Schema columns to local state
            inventoryData = data.map(item => ({
                id: item.id,
                name: item.name,
                brand: item.brand || '',
                category: item.category,
                cost: parseFloat(item.buying_price),
                price: parseFloat(item.selling_price),
                stock: parseInt(item.stock),
                reorder: parseInt(item.alert_level)
            }));

            renderInventory();
        } catch (err) {
            console.error("Error loading beauty products:", err);
        }
    }

    // --- 3. RENDER TABLE & METRICS ---
    function renderInventory() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedStatus = statusFilter.value;

        tableBody.innerHTML = "";
        
        let totalProducts = 0;
        let totalValue = 0;
        let lowStockCount = 0;

        const filteredData = inventoryData.filter(item => {
            const matchesSearch = item.name.toLowerCase().includes(searchTerm) || 
                                  item.category.toLowerCase().includes(searchTerm) ||
                                  item.brand.toLowerCase().includes(searchTerm);
            
            let status = "healthy";
            if (item.stock === 0) status = "out";
            else if (item.stock <= item.reorder) status = "low";

            const matchesStatus = (selectedStatus === "all") || (selectedStatus === status);
            return matchesSearch && matchesStatus;
        });

        filteredData.forEach(item => {
            totalProducts++;
            totalValue += item.price * item.stock;

            let statusBadge = '';
            let stockQtyClass = 'text-bold';

            if (item.stock === 0) {
                statusBadge = `<span class="badge badge-danger">Out of Stock</span>`;
                stockQtyClass += ' amount-unpaid';
            } else if (item.stock <= item.reorder) {
                statusBadge = `<span class="badge badge-debt">Low Stock</span>`;
                lowStockCount++;
            } else {
                statusBadge = `<span class="badge badge-cash">In Stock</span>`;
            }

            const marginPct = item.cost > 0 ? (((item.price - item.cost) / item.cost) * 100).toFixed(1) : "0.0";
            const isMarginPositive = (item.price - item.cost) >= 0;

            const row = document.createElement("tr");
            row.innerHTML = `
                <td class="cell-desc">
                    <strong>${escapeHtml(item.name)}</strong>
                    ${item.brand ? `<small style="color:#6b7280; display:block;">Brand: ${escapeHtml(item.brand)}</small>` : ''}
                </td>
                <td><span class="product-tag">${escapeHtml(item.category)}</span></td>
                <td class="text-right">KSh ${item.cost.toFixed(2)}</td>
                <td class="text-right text-bold">KSh ${item.price.toFixed(2)}</td>
                <td class="text-center ${isMarginPositive ? 'text-success' : 'amount-unpaid'} font-bold">
                    ${isMarginPositive ? '+' : ''}${marginPct}%
                </td>
                <td class="text-center ${stockQtyClass}">${item.stock}</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-center">
                    <div class="table-actions">
                        <button class="btn-mini btn-mini-edit btn-edit" data-id="${item.id}">Edit</button>
                        <button class="btn-mini btn-mini-delete btn-delete" data-id="${item.id}">Delete</button>
                    </div>
                </td>
            `;
            tableBody.appendChild(row);
        });

        document.getElementById("total-products-count").textContent = totalProducts;
        document.getElementById("total-inventory-value").textContent = `KSh ${totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        document.getElementById("low-stock-count").textContent = lowStockCount;
    }

    // --- 4. FORM RESET & CLEAR HANDLER ---
    function resetForm() {
        editingProductId = null;
        inventoryForm.reset();
        btnSaveProduct.textContent = "Save Product";
        if (formTitle) formTitle.textContent = "Add / Update Beauty Product";
        if (formTitleIcon) formTitleIcon.textContent = "➕";
        updateMarginCalculation();
    }

    btnClearForm.addEventListener("click", resetForm);

    // --- 5. SAVE / UPDATE PRODUCT ---
    inventoryForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const payload = {
            id: editingProductId || null,
            name: productNameInput.value.trim(),
            brand: productBrandInput.value.trim(),
            category: productCategoryInput.value,
            cost_price: parseFloat(costPriceInput.value),
            selling_price: parseFloat(sellingPriceInput.value),
            stock_quantity: parseInt(stockQuantityInput.value),
            reorder_level: parseInt(reorderLevelInput.value) || 5
        };

        try {
            const response = await fetch("../api/products.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (result.status === "success") {
                resetForm();
                loadProducts();
            } else {
                alert(result.error || "Failed to save product.");
            }
        } catch (err) {
            console.error("Error saving product:", err);
        }
    });

    // --- 6. EDIT & DELETE BUTTON EVENT DELEGATION ---
    tableBody.addEventListener("click", async (e) => {
        const target = e.target;

        // EDIT PRODUCT
        if (target.classList.contains("btn-edit")) {
            const id = target.getAttribute("data-id");
            const product = inventoryData.find(p => String(p.id) === String(id));
            if (product) {
                editingProductId = product.id;
                productNameInput.value = product.name;
                productBrandInput.value = product.brand;
                productCategoryInput.value = product.category;
                costPriceInput.value = product.cost;
                sellingPriceInput.value = product.price;
                stockQuantityInput.value = product.stock;
                reorderLevelInput.value = product.reorder;

                btnSaveProduct.textContent = "Update Product";
                if (formTitle) formTitle.textContent = "Update Beauty Product";
                if (formTitleIcon) formTitleIcon.textContent = "✏️";
                
                updateMarginCalculation();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        // DELETE PRODUCT
        if (target.classList.contains("btn-delete")) {
            const id = target.getAttribute("data-id");
            if (confirm("Are you sure you want to delete this product?")) {
                try {
                    const response = await fetch("../api/products.php", {
                        method: "DELETE",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ id })
                    });
                    const result = await response.json();
                    if (result.status === "success") {
                        loadProducts();
                    } else {
                        alert(result.error || "Failed to delete product.");
                    }
                } catch (err) {
                    console.error("Error deleting product:", err);
                }
            }
        }
    });

    // Search and Filter Listeners
    searchInput.addEventListener("input", renderInventory);
    statusFilter.addEventListener("change", renderInventory);

    // HTML Sanitizer
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, match => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match]));
    }

    // Initial Load
    loadProducts();
});