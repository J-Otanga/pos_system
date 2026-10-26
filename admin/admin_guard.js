// ==========================================================================
// BEAUTY STORE POS - ADMIN PAGE GUARD
// File: admin/admin_guard.js
// ==========================================================================

(function () {
    const userJson = localStorage.getItem("pos_user");
    
    // 1. If no user session in localStorage, force login
    if (!userJson) {
        window.location.href = "../login.html";
        return;
    }

    try {
        const user = JSON.parse(userJson);
        const userRole = (user.role || "").toLowerCase();

        // 2. If user is a cashier trying to view an admin page, kick them out
        if (userRole !== "admin") {
            alert("Access Denied: Admin privileges required.");
            window.location.href = "../record_sale.html";
        }
    } catch (e) {
        // If localStorage data is corrupted
        localStorage.removeItem("pos_user");
        window.location.href = "../login.html";
    }
})();