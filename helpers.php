<?php
function showMessage($error = null, $success = null) {
    if (isset($error)) {
        echo "<p class='error'>" . $error . "</p>";
    }
    if (isset($success)) {
        echo "<p class='success'>" . $success . "</p>";
    }
}

function requireLogin() {
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function renderNav() {
    $currentPage = basename($_SERVER["PHP_SELF"]);

    $links = [
        "index.php" => "Dashboard",
        "add.php" => "Add Record",
        "categories.php" => "Categories",
        "recurring.php" => "Recurring",
        "charts.php" => "Charts",
        "profile.php" => "Profile",
    ];

    $navLinks = "";
    foreach ($links as $page => $label) {
        $isActive = $currentPage === $page ? "text-white" : "text-slate-400 hover:text-white";
        $navLinks .= "<a href='{$page}' class='{$isActive} transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm'>{$label}</a>";
    }

    echo '
    <nav class="bg-[#111827] border-b border-slate-700 border-l-4 border-l-indigo-500 px-4 py-3 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto flex items-center justify-between">
            <a href="index.php" class="text-white font-bold text-lg tracking-tight">💰 Money Tracker</a>

            <!-- Hamburger button (mobile) -->
            <button id="menuToggle" class="sm:hidden text-slate-400 hover:text-white p-2 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="menuIconOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path id="menuIconClose" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" class="hidden"/>
                </svg>
            </button>

            <!-- Desktop links -->
            <div class="hidden sm:flex items-center gap-1">
                ' . $navLinks . '
                <a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm">Logout</a>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobileMenu" class="hidden sm:hidden mt-3 pb-2 border-t border-slate-700 pt-3">
            <div class="flex flex-col gap-1">
                ' . $navLinks . '
                <a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm">Logout</a>
            </div>
        </div>
    </nav>

    <script>
        const menuToggle = document.getElementById("menuToggle");
        const mobileMenu = document.getElementById("mobileMenu");
        const menuIconOpen = document.getElementById("menuIconOpen");
        const menuIconClose = document.getElementById("menuIconClose");

        menuToggle.addEventListener("click", function() {
            mobileMenu.classList.toggle("hidden");
            menuIconOpen.classList.toggle("hidden");
            menuIconClose.classList.toggle("hidden");
        });
    </script>
    ';
}
?>