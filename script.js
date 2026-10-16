/* =========================================
   LOGIN / REGISTRATION: SHOW OR HIDE PASSWORDS
========================================= */

function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);

    if (!input || !button) {
        return;
    }

    const icon = button.querySelector('svg');
    const showPassword = input.type === 'password';

    input.type = showPassword ? 'text' : 'password';

    if (icon) {
        if (showPassword) {
            // Eye with a slash: password is visible.
            icon.innerHTML = `
                <path d="M3 3l18 18"/>
                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                <path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a15.5 15.5 0 0 1-4 4.8"/>
                <path d="M6.2 6.2C3.5 8 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 4-.8"/>
            `;
        } else {
            // Normal eye: password is hidden.
            icon.innerHTML = `
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
            `;
        }
    }

    button.setAttribute(
        'aria-label',
        showPassword ? 'Hide password' : 'Show password'
    );

    button.setAttribute('aria-pressed', String(showPassword));
}

document.addEventListener('DOMContentLoaded', function () {
    // Login form validation.
    const loginForm = document.getElementById('loginForm');
    const formMessage = document.getElementById('formMessage');

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            if (formMessage) {
                formMessage.textContent = '';
            }

            if (!loginForm.checkValidity()) {
                event.preventDefault();

                if (formMessage) {
                    formMessage.textContent =
                        'Please enter a valid email address and password.';
                }

                loginForm.reportValidity();
            }
        });
    }
});


/* =========================================
   RESERVATION FORM VALIDATION
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    const reservationForms = document.querySelectorAll(
        '.sc-form-grid, .reservation-page form.room-form'
    );

    reservationForms.forEach(function (form) {
        const dateInput = form.querySelector(
            '[name="reservation_date"]'
        );
        const startInput = form.querySelector(
            '[name="start_time"]'
        );
        const endInput = form.querySelector(
            '[name="end_time"]'
        );

        function validateTime() {
            if (!startInput || !endInput) {
                return true;
            }

            const valid =
                !startInput.value ||
                !endInput.value ||
                startInput.value < endInput.value;

            endInput.setCustomValidity(
                valid
                    ? ''
                    : 'The end time must be later than the start time.'
            );

            return valid;
        }

        function validateDate() {
            if (!dateInput || !dateInput.value || !dateInput.min) {
                return true;
            }

            const valid = dateInput.value >= dateInput.min;

            dateInput.setCustomValidity(
                valid
                    ? ''
                    : 'Please choose today or a future date.'
            );

            return valid;
        }

        if (startInput) {
            startInput.addEventListener('input', validateTime);
            startInput.addEventListener('change', validateTime);
        }

        if (endInput) {
            endInput.addEventListener('input', validateTime);
            endInput.addEventListener('change', validateTime);
        }

        if (dateInput) {
            dateInput.addEventListener('input', validateDate);
            dateInput.addEventListener('change', validateDate);
        }

        form.addEventListener('submit', function (event) {
            const validTime = validateTime();
            const validDate = validateDate();

            if (!validTime || !validDate || !form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });
});


/* =========================================
   STUDENT: SELECT A CLASSROOM
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    const classroomSelect = document.getElementById('classroom_id');
    const bookingForm = document.getElementById('booking-form');

    document.querySelectorAll('[data-select-classroom]').forEach(
        function (button) {
            button.addEventListener('click', function () {
                if (!classroomSelect || !bookingForm) {
                    return;
                }

                const classroomId = button.dataset.selectClassroom;

                // Select only if the classroom exists in the dropdown.
                const optionExists = Array.from(
                    classroomSelect.options
                ).some(function (option) {
                    return option.value === classroomId;
                });

                if (!optionExists) {
                    return;
                }

                classroomSelect.value = classroomId;

                bookingForm.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });

                classroomSelect.focus({
                    preventScroll: true
                });
            });
        }
    );
});


/* =========================================
   ADMIN: CONFIRM RESERVATION ACTIONS
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    const actionForms = document.querySelectorAll(
        '.reservation-action-form[data-confirm-message]'
    );

    actionForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.dataset.confirmMessage;

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});


/* =========================================
   TEACHER: RESERVATION SIDEBAR
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    const navigation = document.querySelector('.sc-navigation');

    if (!navigation) {
        return;
    }

    const links = Array.from(
        navigation.querySelectorAll('a.sc-nav-link')
    );

    const requestLink = links.find(function (link) {
        const href = link.getAttribute('href') || '';

        return href.includes('reservations.php') &&
            !href.includes('#my-reservations');
    });

    const historyLink = links.find(function (link) {
        return (link.getAttribute('href') || '')
            .includes('#my-reservations');
    });

    // Other pages may have a sidebar without these two links.
    if (!requestLink || !historyLink) {
        return;
    }

    function updateNavigation() {
        const isHistory =
            window.location.hash.toLowerCase() === '#my-reservations';

        links.forEach(function (link) {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });

        const activeLink = isHistory ? historyLink : requestLink;

        activeLink.classList.add('active');
        activeLink.setAttribute('aria-current', 'page');
    }

    updateNavigation();

    window.addEventListener('hashchange', updateNavigation);
});


/* =========================================
   MY RESERVATIONS: CONFIRM LOGOUT
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-logout').forEach(
        function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm('Are you sure you want to log out?')) {
                    event.preventDefault();
                }
            });
        }
    );
});


/* =========================================
   MANAGE ACCOUNT: CLEAR
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('account-search');

    if (!searchInput) {
        return;
    }

    function showAllAccountsIfCleared() {
        const params = new URLSearchParams(window.location.search);

        if (searchInput.value.trim() === '' && params.has('search')) {
            window.location.href = window.location.pathname;
        }
    }

    // Detect when the browser's built-in X clears the search field.
    searchInput.addEventListener('search', showAllAccountsIfCleared);
    searchInput.addEventListener('input', showAllAccountsIfCleared);
});
