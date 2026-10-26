// ==========================================================================
// BEAUTY STORE POS - USER MANAGEMENT CONTROLLER
// File: admin/users.js
// ==========================================================================

let allUsers = [];
let editingUserId = null;

document.addEventListener("DOMContentLoaded", () => {
    loadUsers();

    // Form Event Listeners
    document.getElementById("user-form").addEventListener("submit", handleSaveUser);
    document.getElementById("btn-clear-user-form").addEventListener("click", resetUserForm);

    // Search & Filter Listeners
    document.getElementById("search-users").addEventListener("input", filterAndRenderUsers);
    document.getElementById("filter-user-role").addEventListener("change", filterAndRenderUsers);
    document.getElementById("filter-user-status").addEventListener("change", filterAndRenderUsers);
});

// --------------------------------------------------------------------------
// 1. Fetch Users & Calculate Metrics
// --------------------------------------------------------------------------
async function loadUsers() {
    try {
        const response = await fetch("../api/users.php");
        const result = await response.json();

        if (response.status === 401 || response.status === 403) {
            alert("Unauthorized. Admin privileges required.");
            window.location.href = "../login.html?redirect=admin/users.html";
            return;
        }

        if (result.status === "success") {
            allUsers = result.data;
            updateMetrics(allUsers);
            filterAndRenderUsers();
        }
    } catch (err) {
        console.error("Failed to fetch user accounts:", err);
    }
}

function updateMetrics(users) {
    const totalUsers = users.length;
    const activeCashiers = users.filter(u => u.role === 'cashier' && u.status === 'active').length;
    const adminCount = users.filter(u => u.role === 'admin').length;

    document.getElementById("metric-total-users").textContent = totalUsers;
    document.getElementById("metric-active-cashiers").textContent = activeCashiers;
    document.getElementById("metric-admin-count").textContent = adminCount;
}

// --------------------------------------------------------------------------
// 2. Search, Filter & Render Table
// --------------------------------------------------------------------------
function filterAndRenderUsers() {
    const searchQuery = document.getElementById("search-users").value.toLowerCase().trim();
    const roleFilter = document.getElementById("filter-user-role").value;
    const statusFilter = document.getElementById("filter-user-status").value;

    const filtered = allUsers.filter(user => {
        const matchesSearch = user.full_name.toLowerCase().includes(searchQuery) ||
                              user.username.toLowerCase().includes(searchQuery) ||
                              user.email.toLowerCase().includes(searchQuery);

        const matchesRole = roleFilter === 'all' || user.role === roleFilter;
        const matchesStatus = statusFilter === 'all' || user.status === statusFilter;

        return matchesSearch && matchesRole && matchesStatus;
    });

    renderTable(filtered);
}

function renderTable(users) {
    const tbody = document.getElementById("users-table-body");
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: #6b7280; padding: 24px;">
                    No staff accounts match your current criteria.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = users.map(user => {
        const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
        const roleBadgeClass = user.role === 'admin' ? 'badge-admin' : 'badge-cashier';
        const statusBadgeStyle = user.status === 'active' 
            ? 'background: #dcfce7; color: #15803d;' 
            : 'background: #fee2e2; color: #b91c1c;';

        return `
            <tr>
                <td>
                    <strong>${escapeHtml(user.full_name)}</strong><br>
                    <small style="color: #6b7280;">@${escapeHtml(user.username)}</small>
                </td>
                <td>${escapeHtml(user.email)}</td>
                <td class="text-center">
                    <span class="badge ${roleBadgeClass}" style="padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;">
                        ${user.role.toUpperCase()}
                    </span>
                </td>
                <td class="text-center">
                    <span style="padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; ${statusBadgeStyle}">
                        ${user.status.toUpperCase()}
                    </span>
                </td>
                <td class="text-center">${createdDate}</td>
                <td class="text-center">
                    <button onclick="editUser(${user.id})" class="btn-sm" style="background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; margin-right: 4px;">Edit</button>
                    <button onclick="deleteUser(${user.id})" class="btn-sm" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">Delete</button>
                </td>
            </tr>
        `;
    }).join('');
}

// --------------------------------------------------------------------------
// 3. Create & Edit User Submission
// --------------------------------------------------------------------------
async function handleSaveUser(e) {
    e.preventDefault();

    const fullName = document.getElementById("user-fullname").value.trim();
    const username = document.getElementById("user-username").value.trim();
    const email = document.getElementById("user-email").value.trim();
    const password = document.getElementById("user-password").value.trim();
    const role = document.getElementById("user-role").value;
    const status = document.getElementById("user-status").value;

    if (!editingUserId && !password) {
        alert("Password is required when creating a new account.");
        return;
    }

    const payload = {
        id: editingUserId,
        full_name: fullName,
        username: username,
        email: email,
        password: password,
        role: role,
        status: status
    };

    const method = editingUserId ? "PUT" : "POST";

    try {
        const response = await fetch("../api/users.php", {
            method: method,
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && result.status === "success") {
            alert(result.message);
            resetUserForm();
            loadUsers();
        } else {
            alert(result.message || "Failed to save user account.");
        }
    } catch (err) {
        alert("Network error while saving user account.");
    }
}

// --------------------------------------------------------------------------
// 4. Form Utilities (Populate Edit & Reset)
// --------------------------------------------------------------------------
function editUser(id) {
    const user = allUsers.find(u => u.id === id);
    if (!user) return;

    editingUserId = user.id;

    document.getElementById("user-fullname").value = user.full_name;
    document.getElementById("user-username").value = user.username;
    document.getElementById("user-email").value = user.email;
    document.getElementById("user-role").value = user.role;
    document.getElementById("user-status").value = user.status;
    document.getElementById("user-password").value = "";

    // UI Updates for Edit State
    document.getElementById("form-title").textContent = "Edit Staff Account";
    document.getElementById("form-title-icon").textContent = "✏️";
    document.getElementById("btn-save-user").textContent = "Update Account";
    document.getElementById("password-help-note").textContent = "(Leave blank to keep current password)";
    
    // Scroll smoothly up to the form
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetUserForm() {
    editingUserId = null;
    document.getElementById("user-form").reset();
    document.getElementById("form-title").textContent = "Add New Staff Account";
    document.getElementById("form-title-icon").textContent = "➕";
    document.getElementById("btn-save-user").textContent = "Save Account";
    document.getElementById("password-help-note").textContent = "(Required for new user)";
}

// --------------------------------------------------------------------------
// 5. Delete User Account
// --------------------------------------------------------------------------
async function deleteUser(id) {
    if (!confirm("Are you sure you want to delete this staff account?")) return;

    try {
        const response = await fetch(`../api/users.php?id=${id}`, { method: "DELETE" });
        const result = await response.json();

        if (response.ok && result.status === "success") {
            alert(result.message);
            loadUsers();
        } else {
            alert(result.message || "Failed to delete account.");
        }
    } catch (err) {
        alert("Network error while deleting account.");
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}