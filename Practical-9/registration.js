
document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("registerForm");

    if (!form) {
        return;
    }

    form.addEventListener("submit", function (event) {

        event.preventDefault();

        let valid = true;

        const fullName = document.getElementById("fullName");
        const course = document.getElementById("course");
        const semester = document.getElementById("semester");
        const username = document.getElementById("username");
        const email = document.getElementById("email");
        const password = document.getElementById("password");
        const confirmPassword = document.getElementById("confirmPassword");

        const nameError = document.getElementById("nameError");
        const courseError = document.getElementById("courseError");
        const semesterError = document.getElementById("semesterError");
        const usernameError = document.getElementById("usernameError");
        const emailError = document.getElementById("emailError");
        const passwordError = document.getElementById("passwordError");
        const confirmError = document.getElementById("confirmError");

        // Clear previous errors
        [
            nameError,
            courseError,
            semesterError,
            usernameError,
            emailError,
            passwordError,
            confirmError
        ].forEach(function (error) {
            if (error) {
                error.textContent = "";
            }
        });

        [
            fullName,
            course,
            semester,
            username,
            email,
            password,
            confirmPassword
        ].forEach(function (input) {
            if (input) {
                input.classList.remove("input-error");
            }
        });

        // Full Name validation
        const namePattern = /^[A-Za-z ]{2,50}$/;

        if (!namePattern.test(fullName.value.trim())) {
            nameError.textContent = "Please enter a valid name.";
            fullName.classList.add("input-error");
            valid = false;
        }

        // Course validation
        if (course.value === "") {
            courseError.textContent = "Please select your course.";
            course.classList.add("input-error");
            valid = false;
        }

        // Semester validation
        if (semester.value === "") {
            semesterError.textContent = "Please select your semester.";
            semester.classList.add("input-error");
            valid = false;
        }

        // Username validation
        const usernamePattern = /^[A-Za-z0-9_]{3,50}$/;

        if (!usernamePattern.test(username.value.trim())) {
            usernameError.textContent =
                "Username must be 3-50 characters (letters, numbers, underscore).";
            username.classList.add("input-error");
            valid = false;
        }

        // Email validation
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailPattern.test(email.value.trim())) {
            emailError.textContent = "Enter a valid email address.";
            email.classList.add("input-error");
            valid = false;
        }

        // Password validation
        const passwordPattern =
            /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&]).{8,}$/;

        if (!passwordPattern.test(password.value)) {
            passwordError.textContent =
                "Use 8+ characters with a letter, number and special character.";
            password.classList.add("input-error");
            valid = false;
        }

        // Confirm Password validation
        if (
            confirmPassword.value === "" ||
            confirmPassword.value !== password.value
        ) {
            confirmError.textContent = "Passwords do not match.";
            confirmPassword.classList.add("input-error");
            valid = false;
        }

        // Submit valid data to PHP
        if (valid) {
            form.submit();
        }

    });

});
