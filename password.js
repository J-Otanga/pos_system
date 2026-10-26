// ==========================================================================
// BEAUTY STORE POS - CHANGE PASSWORD CONTROLLER
// File: password.js
// ==========================================================================

document.addEventListener("DOMContentLoaded", () => {
    const passwordForm = document.getElementById("password-form");
    const currentPasswordInput = document.getElementById("current-password");
    const newPasswordInput = document.getElementById("new-password");
    const confirmPasswordInput = document.getElementById("confirm-password");
    const submitBtn = document.getElementById("btn-submit");
    const alertBox = document.getElementById("password-alert");

    function showAlert(message, type = "danger") {
        alertBox.textContent = message;
        alertBox.className = `alert-message alert-${type}`;
        alertBox.style.display = "block";
    }

    function hideAlert() {
        alertBox.style.display = "none";
    }

    passwordForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideAlert();

        const currentPassword = currentPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Basic validations
        if (newPassword.length < 6) {
            showAlert("New password must be at least 6 characters long.");
            return;
        }

        if (newPassword !== confirmPassword) {
            showAlert("New password and confirmation do not match.");
            return;
        }

        if (currentPassword === newPassword) {
            showAlert("New password cannot be identical to current password.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Updating Password...";

        try {
            const response = await fetch("api/change_password.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            });

            const result = await response.json();

            if (response.ok && result.status === "success") {
                showAlert("Password updated successfully! Redirecting...", "success");

                // Update force_password_change in localStorage
                const userJson = localStorage.getItem("pos_user");
                if (userJson) {
                    try {
                        const user = JSON.parse(userJson);
                        user.force_password_change = false;
                        localStorage.setItem("pos_user", JSON.stringify(user));
                    } catch (e) {
                        console.error("Failed to update user profile in storage", e);
                    }
                }

                // Redirect user based on role
                setTimeout(() => {
                    const userJson = localStorage.getItem("pos_user");
                    let userRole = "cashier";
                    
                    if (userJson) {
                        const user = JSON.parse(userJson);
                        userRole = (user.role || "").toLowerCase();
                    }

                    if (userRole === "admin") {
                        window.location.href = "dashboard.html";
                    } else {
                        window.location.href = "record_sale.html";
                    }
                }, 1000);

            } else {
                showAlert(result.message || "Failed to update password. Please try again.");
                submitBtn.disabled = false;
                submitBtn.textContent = "Update Password";
            }
        } catch (err) {
            console.error("Password update request failed:", err);
            showAlert("Unable to connect to server. Check your connection.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Update Password";
        }
    });
});