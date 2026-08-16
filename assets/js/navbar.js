document.addEventListener("DOMContentLoaded", () => {
    const navToggle = document.getElementById("navToggle");
    const navMenu = document.getElementById("navMenu");

    if (navToggle && navMenu) {
        navToggle.addEventListener("click", () => {
            navMenu.classList.toggle("open");
        });
    }

    // Profile Dropdown Toggle
    const profileChipBtn = document.getElementById("profileChipBtn");
    const profileMenuWrap = document.querySelector(".profile-menu-wrap");

    if (profileChipBtn && profileMenuWrap) {
        profileChipBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            profileMenuWrap.classList.toggle("active");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", (e) => {
            if (!profileMenuWrap.contains(e.target)) {
                profileMenuWrap.classList.remove("active");
            }
        });
    }
});
