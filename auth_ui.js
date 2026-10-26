// ==========================================================================
// BEAUTY STORE POS - AUTHENTICATED UI CONTROLLER
// File: auth_ui.js
// ==========================================================================

document.addEventListener("DOMContentLoaded", () => {
    applyUserAccessUI();
});

function applyUserAccessUI() {
    const currentPath = window.location.pathname.toLowerCase();

    // 1. NEVER run redirect checks if the user is already on login.html
    if (currentPath.includes("login.html")) {
        return;
    }

    // Determine path relative to root or subfolders (/admin/)
    const isInAdminFolder = currentPath.includes("/admin/");
    const loginRedirect = isInAdminFolder ? "../login.html" : "login.html";

    const userJson = localStorage.getItem("pos_user");
    
    // 2. If no session exists, redirect to login page using correct relative path
    if (!userJson) {
        window.location.href = loginRedirect;
        return;
    }

    try {
        const user = JSON.parse(userJson);
        const userRole = (user.role || "").toLowerCase();

        // 3. Hide all Admin-only UI elements from Cashiers
        if (userRole !== "admin") {
            const adminElements = document.querySelectorAll(".admin-only, .admin-topbar, .btn-switch-admin");
            adminElements.forEach(element => {
                element.style.display = "none";
            });
        }

        // 4. Populate active user name & role badge in top bar (if present)
        const userProfileBadge = document.getElementById("nav-user-badge");
        if (userProfileBadge) {
            userProfileBadge.textContent = `${user.full_name} (${user.role.toUpperCase()})`;
        }

    } catch (err) {
        console.error("Error parsing pos_user from localStorage:", err);
        localStorage.removeItem("pos_user");
        window.location.href = loginRedirect;
    }
}

// Global logout helper function
async function logoutUser() {
    const isInAdminFolder = window.location.pathname.toLowerCase().includes("/admin/");
    const apiEndpoint = isInAdminFolder ? "../api/logout.php" : "api/logout.php";
    const loginRedirect = isInAdminFolder ? "../login.html" : "login.html";

    try {
        // Destroy PHP backend session
        await fetch(apiEndpoint, { method: "POST" });
    } catch (err) {
        console.error("Backend logout failed:", err);
    } finally {
        // Clear client-side profile
        localStorage.removeItem("pos_user");

        // Redirect safely
        window.location.href = loginRedirect;
    }
}