/* ==========================================
   Register Page - client side validation
   The same rules are checked again in register.php,
   because a user can turn JavaScript off.
   ========================================== */

document.getElementById("registerForm").addEventListener("submit", function (event) {

    let valid = true;

    // ---------- get the values ----------

    let name = document.getElementById("name").value.trim();
    let email = document.getElementById("email").value.trim();
    let phone = document.getElementById("phone").value.trim();
    let university = document.getElementById("university").value.trim();
    let city = document.getElementById("city").value;
    let password = document.getElementById("password").value;
    let confirm = document.getElementById("confirm").value;
    let rules = document.getElementById("rules");

    let checkedSkills = document.querySelectorAll("input[name='skill[]']:checked");

    // ---------- clear the old messages ----------

    document.getElementById("nameError").innerHTML = "";
    document.getElementById("emailError").innerHTML = "";
    document.getElementById("phoneError").innerHTML = "";
    document.getElementById("universityError").innerHTML = "";
    document.getElementById("cityError").innerHTML = "";
    document.getElementById("skillError").innerHTML = "";
    document.getElementById("passwordError").innerHTML = "";
    document.getElementById("confirmError").innerHTML = "";
    document.getElementById("rulesError").innerHTML = "";

    // ---------- Name ----------

    if (name == "") {
        document.getElementById("nameError").innerHTML = "Name is required.";
        valid = false;
    }
    else if (!/^[A-Za-z ]+$/.test(name)) {
        document.getElementById("nameError").innerHTML = "Only letters are allowed in the name.";
        valid = false;
    }

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

    // ---------- Phone ----------

    if (phone == "") {
        document.getElementById("phoneError").innerHTML = "Phone number is required.";
        valid = false;
    }
    else if (!/^01[3-9]\d{2}-?\d{6}$/.test(phone)) {
        document.getElementById("phoneError").innerHTML = "Write a valid number like 01711-223344.";
        valid = false;
    }

    // ---------- University ----------

    if (university == "") {
        document.getElementById("universityError").innerHTML = "University name is required.";
        valid = false;
    }

    // ---------- City ----------

    if (city == "") {
        document.getElementById("cityError").innerHTML = "Please select your city.";
        valid = false;
    }

    // ---------- Skills ----------

    if (checkedSkills.length == 0) {
        document.getElementById("skillError").innerHTML = "Select at least one skill.";
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

    // ---------- Confirm password ----------

    if (confirm != password) {
        document.getElementById("confirmError").innerHTML = "The two passwords are not same.";
        valid = false;
    }

    // ---------- Rules checkbox ----------

    if (rules.checked == false) {
        document.getElementById("rulesError").innerHTML = "Please accept the rules.";
        valid = false;
    }

    // stop the form if something is wrong
    if (!valid) {
        event.preventDefault();
    }

});


/* ==========================================
   AJAX : check the email in the database
   while the user is still filling the form
   ========================================== */

document.getElementById("email").addEventListener("blur", function () {

    let email = this.value.trim();
    let box = document.getElementById("emailError");

    if (email == "") {
        return;
    }

    let xhr = new XMLHttpRequest();

    xhr.open("GET", "check_email.php?email=" + encodeURIComponent(email), true);

    xhr.onreadystatechange = function () {

        if (xhr.readyState == 4 && xhr.status == 200) {

            // the PHP file sends JSON like  {"exists":true,"message":"..."}
            let response = JSON.parse(xhr.responseText);

            if (response.exists) {
                box.innerHTML = response.message;
            }
            else {
                box.innerHTML = "";
            }

        }

    };

    xhr.send();

});
