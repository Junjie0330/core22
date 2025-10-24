<?php // includes/navbar.php ?>
<nav class="bg-gradient-to-r from-blue-800 via-blue-600 to-blue-500 shadow-sm fixed top-0 left-0 right-0 z-50 px-4 flex justify-between items-center h-16">
  
  <!-- Mobile Sidebar Toggle -->
  <button class="lg:hidden text-white hover:text-yellow-300 transition-colors duration-200" type="button" onclick="toggleSidebar()" id="mobileToggle">
    <i class="bi bi-list text-2xl"></i>
  </button>

  <!-- Brand -->
  <a class="font-bold text-white ml-2 hover:text-yellow-300 transition-colors duration-200 flex items-center" href="homes.php">
    <i class="bi bi-speedometer2 mr-2"></i>
    iMARKET
  </a>

  <!-- Right Side -->
  <div class="flex items-center space-x-4">
    
    <!-- Notifications -->
    <div class="relative">
      <a class="text-white hover:text-yellow-300 transition-colors duration-200 relative" href="notifications.php">
        <i class="bi bi-bell text-xl"></i>
        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
      </a>
    </div>

    <!-- Profile Dropdown -->
    <div class="relative">
      <button class="flex items-center text-white hover:text-yellow-300 transition-colors duration-200" onclick="toggleProfileDropdown()" id="profileButton">
        <i class="bi bi-person-circle text-2xl mr-2"></i>
        <span class="font-semibold hidden sm:inline">
          <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : ($_SESSION['name'] ?? 'Admin') ?> </span>
        <i class="bi bi-chevron-down ml-1 text-sm"></i>
      </button>
      
      <!-- Dropdown Menu -->
      <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border-0 py-1 hidden z-50" id="profileDropdownMenu">
        <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" href="profile.php">
          <i class="bi bi-person mr-2"></i>Profile
        </a>
        <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" href="settings.php">
          <i class="bi bi-gear mr-2"></i>Settings
        </a>
        <hr class="my-1 border-gray-200">
        <a class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200" href="logout.php">
          <i class="bi bi-box-arrow-right mr-2"></i>Logout
        </a>
      </div>
    </div>
    
  </div>
</nav>

<style>
/* Reset styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body {
  margin: 0 !important;
  padding: 0 !important;
}

/* Additional gradient overlay for depth */
nav::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
  z-index: -1;
}

/* Ensure proper spacing for content below navbar */
.main-content {
  margin-top: 4rem; /* 64px to account for h-16 navbar */
  margin-left: 0;
}

@media (min-width: 1024px) {
  .main-content {
    margin-left: 16rem; /* 256px to account for w-64 sidebar */
  }
}

/* Dropdown animation */
.dropdown-enter {
  opacity: 0;
  transform: translateY(-10px);
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.dropdown-enter-active {
  opacity: 1;
  transform: translateY(0);
}

/* Mobile sidebar adjustments */
@media (max-width: 1023px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease-in-out;
  }
  
  .sidebar.show {
    transform: translateX(0);
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Global functions for navbar interactions
  
  // Toggle profile dropdown
  window.toggleProfileDropdown = function() {
    const dropdown = document.getElementById('profileDropdownMenu');
    const button = document.getElementById('profileButton');
    
    dropdown.classList.toggle('hidden');
    
    // Add animation class
    if (!dropdown.classList.contains('hidden')) {
      dropdown.classList.add('dropdown-enter');
      setTimeout(() => {
        dropdown.classList.add('dropdown-enter-active');
      }, 10);
    } else {
      dropdown.classList.remove('dropdown-enter', 'dropdown-enter-active');
    }
  };
  
  // Toggle sidebar for mobile
  window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleButton = document.getElementById('mobileToggle');
    
    if (!sidebar) return;
    
    // Toggle sidebar visibility
    sidebar.classList.toggle('show');
    sidebar.classList.toggle('-translate-x-full');
    
    // Toggle overlay
    if (overlay) {
      overlay.classList.toggle('hidden');
    }
    
    // Toggle body scroll
    document.body.classList.toggle('overflow-hidden');
    
    // Update toggle button icon
    const icon = toggleButton.querySelector('i');
    if (sidebar.classList.contains('show')) {
      icon.className = 'bi bi-x text-2xl';
    } else {
      icon.className = 'bi bi-list text-2xl';
    }
  };
  
  // Close dropdowns when clicking outside
  document.addEventListener('click', function(e) {
    // Close profile dropdown
    const profileDropdown = document.getElementById('profileDropdownMenu');
    const profileButton = document.getElementById('profileButton');
    
    if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
      profileDropdown.classList.add('hidden');
      profileDropdown.classList.remove('dropdown-enter', 'dropdown-enter-active');
    }
    
    // Close sidebar when clicking overlay
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay && e.target === overlay) {
      toggleSidebar();
    }
  });
  
  // Close sidebar on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const sidebar = document.getElementById('sidebar');
      if (sidebar && sidebar.classList.contains('show')) {
        toggleSidebar();
      }
      
      // Also close profile dropdown
      const profileDropdown = document.getElementById('profileDropdownMenu');
      if (profileDropdown && !profileDropdown.classList.contains('hidden')) {
        profileDropdown.classList.add('hidden');
        profileDropdown.classList.remove('dropdown-enter', 'dropdown-enter-active');
      }
    }
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) { // lg breakpoint
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      const toggleButton = document.getElementById('mobileToggle');
      
      if (sidebar) {
        sidebar.classList.remove('show');
        sidebar.classList.add('-translate-x-full');
      }
      
      if (overlay) {
        overlay.classList.add('hidden');
      }
      
      document.body.classList.remove('overflow-hidden');
      
      // Reset toggle button icon
      if (toggleButton) {
        const icon = toggleButton.querySelector('i');
        icon.className = 'bi bi-list text-2xl';
      }
    }
  });
  
  // Notification badge animation (optional enhancement)
  const notificationBadge = document.querySelector('.bg-red-500');
  if (notificationBadge) {
    setInterval(() => {
      notificationBadge.classList.add('animate-pulse');
      setTimeout(() => {
        notificationBadge.classList.remove('animate-pulse');
      }, 2000);
    }, 5000);
  }
});

// Additional utility functions
function updateNotificationCount(count) {
  const badge = document.querySelector('.bg-red-500');
  if (badge) {
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
  }
}

function showNotification(message, type = 'info') {
  // Create a simple notification system
  const notification = document.createElement('div');
  notification.className = `fixed top-20 right-4 p-4 rounded-lg shadow-lg z-50 ${
    type === 'success' ? 'bg-green-500' : 
    type === 'error' ? 'bg-red-500' : 
    type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
  } text-white max-w-sm`;
  
  notification.innerHTML = `
    <div class="flex items-center">
      <span class="flex-1">${message}</span>
      <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
        <i class="bi bi-x"></i>
      </button>
    </div>
  `;
  
  document.body.appendChild(notification);
  
  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}
</script>