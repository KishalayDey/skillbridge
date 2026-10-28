/* ==========================================
   Login Page - client side validation
   ========================================== */

document.getElementById("loginForm").addEventListener("submit", function (event) {

    let valid = true;

    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value;

    document.getElementById("emailError").innerHTML = "";
    document.getElementById("passwordError").innerHTML = "";

    // ---------- Email ----------

    let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email == "") {
        document.getElementById("emailError").innerHTML = "Email is required.";
        valid = false;
    }
    else if (!emailPattern.test(email)) {
        document.getElementById("emailError").innerHTML = "Invalid email address.";
        valid = false;
    }

    // ---------- Password ----------

    if (password == "") {
        document.getElementById("passwordError").innerHTML = "Password is required.";
        valid = false;
    }
    else if (password.length < 6) {
        document.getElementById("passwordError").innerHTML = "Password must be at least 6 characters.";
        valid = false;
    }

    if (!valid) {
        event.preventDefault();
    }

});
