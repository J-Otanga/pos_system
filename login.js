document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.getElementById("login-form");
    const usernameInput = document.getElementById("login-username");
    const passwordInput = document.getElementById("login-password");
    const submitBtn = document.getElementById("btn-submit");
    const alertBox = document.getElementById("login-alert");

    // Display inline error or status messages
    function showAlert(message, type = "danger") {
        alertBox.textContent = message;
        alertBox.className = `alert-message alert-${type}`;
        alertBox.style.display = "block";
    }

    function hideAlert() {
        alertBox.style.display = "none";
    }

    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        hideAlert();

        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        if (!username || !password) {
            showAlert("Please fill in both fields.");
            return;
        }

        // Lock button while request is processing
        submitBtn.disabled = true;
        submitBtn.textContent = "Authenticating...";

        try {
            const response = await fetch("api/login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ username, password })
            });

            const result = await response.json();

            if (response.ok && result.status === "success") {
                showAlert("Login successful! Redirecting...", "success");

                // Save non-sensitive user profile to localStorage for UI badges
                if (result.user) {
                    localStorage.setItem("pos_user", JSON.stringify(result.user));
                }

                // Check for URL redirect parameter (e.g. ?redirect=admin/users.html)
                const urlParams = new URLSearchParams(window.location.search);
                const redirectTarget = urlParams.get("redirect");

                const userRole = (result.user?.role || "").toLowerCase();
                const forceReset = result.force_password_change || result.user?.force_password_change;

                setTimeout(() => {
                    // 1. Force password reset flow
                    if (forceReset) {
                        window.location.href = "password.html";
                        return;
                    }

                    // 2. Redirect back to destination page if provided in URL
                    if (redirectTarget) {
                        window.location.href = redirectTarget;
                        return;
                    }

                    // 3. Backend specified redirect target
                    if (result.redirect) {
                        window.location.href = result.redirect;
                        return;
                    }

                    // 4. Fallback based on user role
                    if (userRole === "admin") {
                        window.location.href = "dashboard.html";
                    } else {
                        window.location.href = "record_sale.html";
                    }
                }, 800);

            } else {
                showAlert(result.message || "Invalid credentials. Please try again.");
                submitBtn.disabled = false;
                submitBtn.textContent = "Sign In";
            }
        } catch (err) {
            console.error("Login request failed:", err);
            showAlert("Unable to connect to server. Check your connection.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Sign In";
        }
    });
});