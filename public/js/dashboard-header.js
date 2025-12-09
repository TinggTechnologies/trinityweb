// Dashboard Header Component

// Get the correct base path for assets
const ASSETS_BASE = (typeof AppConfig !== 'undefined')
    ? AppConfig.assetsPath
    : (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? '/trinity/assets'
        : '/assets';

function loadDashboardHeader() {
    const headerHTML = `
    <!-- Page Loader -->
    <div id="page-loader">
      <img src="${ASSETS_BASE}/images/logo.png" alt="Loading..." class="loader-image">
    </div>

    <!-- Header -->
    <div class="header-container">
      <div class="logo-wrapper">
        <img src="${ASSETS_BASE}/images/logo.png" alt="logo" class="logo-img">
      </div>

      <!-- Desktop Menu -->
      <div class="menu-wrapper">
        <a class="menu-item" href="dashboard">HOME</a>
        <a class="menu-item" href="released-songs">RELEASES</a>
        <a class="menu-item" href="analytics">ANALYTICS</a>
        <a class="menu-item" href="royalty">PAYOUT</a>
        <a class="menu-item mr-3" href="split-shares"> SPLIT SHARE</a>
      </div>

      <!-- Desktop Dropdown -->
      <div class="dropdown">
        <button><i class="fa-solid fa-user"></i> PROFILE</button>
        <div class="dropdown-content">
          <a href="profile">My Profile</a>
          <a href="payment">Payment Details</a>
          <a href="help"><i class="bi bi-info-circle-fill"></i> Help</a>
          <a href="#" id="logoutBtn">Logout</a>
        </div>
      </div>

      <!-- Mobile Hamburger -->
      <button class="hamburger-menu" id="hamburgerMenu">
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
        <div class="hamburger-line"></div>
      </button>

      <!-- Mobile Nav -->
      <div class="mobile-nav" id="mobileNav">
        <a href="dashboard">HOME</a>
        <a href="released-songs">RELEASES</a>
        <a href="analytics">ANALYTICS</a>
        <a href="royalty">PAYOUT</a>

        <a href="split-shares"> SPLIT SHARE</a>

        <div class="mobile-dropdown">
          <div class="mobile-dropdown-header"><i class="fa-solid fa-user"></i> PROFILE</div>
          <a href="profile">My Profile</a>
          <a href="payment">Payment Details</a>
          <a href="help"><i class="bi bi-info-circle-fill"></i> Help</a>
          <a href="#" id="logoutBtnMobile">Logout</a>
        </div>
      </div>
    </div>
    `;

    // Insert header into the container
    const headerContainer = document.getElementById('header-container');
    if (headerContainer) {
        headerContainer.innerHTML = headerHTML;
    }

    // Initialize header functionality
    initializeHeader();
}

function initializeHeader() {
    // Page loader
    window.addEventListener("load", () => {
        const loader = document.getElementById("page-loader");
        if (loader) {
            setTimeout(() => {
                loader.classList.add("hidden");
            }, 1000);
        }
    });

    // Mobile menu toggle
    const hamburger = document.getElementById('hamburgerMenu');
    const mobileNav = document.getElementById('mobileNav');

    if (hamburger && mobileNav) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            mobileNav.classList.toggle('active');

            if (mobileNav.classList.contains('active')) {
                document.documentElement.style.overflow = 'hidden';
                document.body.style.overflow = 'hidden';
            } else {
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
            }
        });

        // Close nav on link click
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                mobileNav.classList.remove('active');
                document.documentElement.style.overflow = '';
                document.body.style.overflow = '';
            });
        });
    }

    // Dropdown logic - handle Profile dropdown
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const btn = dropdown.querySelector('button');
        const content = dropdown.querySelector('.dropdown-content');

        if (btn && content) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                // Close other dropdowns first
                dropdowns.forEach(other => {
                    if (other !== dropdown) {
                        const otherContent = other.querySelector('.dropdown-content');
                        const otherBtn = other.querySelector('button');
                        if (otherContent) otherContent.classList.remove('show');
                        if (otherBtn) otherBtn.classList.remove('active');
                    }
                });

                // Toggle this dropdown
                content.classList.toggle('show');
                btn.classList.toggle('active');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                const content = dropdown.querySelector('.dropdown-content');
                const btn = dropdown.querySelector('button');
                if (content) content.classList.remove('show');
                if (btn) btn.classList.remove('active');
            }
        });
    });

    // Logout functionality
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutBtnMobile = document.getElementById('logoutBtnMobile');

    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            await handleLogout();
        });
    }

    if (logoutBtnMobile) {
        logoutBtnMobile.addEventListener('click', async (e) => {
            e.preventDefault();
            await handleLogout();
        });
    }
}

async function handleLogout() {
    try {
        await API.logout();
        window.location.href = './';
    } catch (error) {
        console.error('Logout failed:', error);
        window.location.href = './';
    }
}

// Load header when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDashboardHeader);
} else {
    loadDashboardHeader();
}
