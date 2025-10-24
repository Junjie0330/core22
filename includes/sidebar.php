<?php
// Only keep current page detection here
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Seller Sidebar -->
<div class="sidebar bg-gradient-to-br from-blue-600 via-purple-600 to-blue-800 text-white fixed top-0 left-0 w-64 h-screen overflow-y-auto z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out" id="sidebar">
  
  <!-- Sidebar Header -->
  <div class="p-4 border-b border-white border-opacity-20">
    <div class="flex items-center">
      <div class="w-11 h-11 bg-white bg-opacity-15 backdrop-blur rounded-2xl flex items-center justify-center mr-3 shadow-sm">
        <i class="bi bi-speedometer2 text-white text-xl"></i>
      </div>
      <div>
        <h5 class="mb-1 font-bold text-white text-lg">iMARKET</h5>
      </div>
    </div>
  </div>

  <!-- Seller Profile Card -->
  <div class="mx-3 mt-3 mb-4">
    <div class="bg-white bg-opacity-10 backdrop-blur border-0 rounded-lg shadow-sm">
      <div class="p-3">
        <div class="flex items-center">
          <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3 shadow-sm">
            <i class="bi bi-person-fill text-white"></i>
          </div>
          <div class="flex-grow">
            <h6 class="mb-0 text-white font-semibold">
              <?= htmlspecialchars($_SESSION['name'] ?? '$name'); ?>
            </h6>
            <small class="text-white text-opacity-70">Seller</small>
          </div>
          <div class="relative">
            <button class="text-white hover:bg-white hover:bg-opacity-20 p-1 rounded transition-colors duration-200 dropdown-toggle" type="button" onclick="toggleDropdown()">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border-0 py-1 hidden z-50" id="profileDropdown">
              <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" href="profile.php">
                <i class="bi bi-person mr-2"></i>Profile
              </a>
              <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" href="preferences.php">
                <i class="bi bi-sliders mr-2"></i>Preferences
              </a>
              <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200" href="logout.php">
                <i class="bi bi-box-arrow-right mr-2"></i>Logout
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav class="px-3">
    <ul class="space-y-1">
      <!-- Dashboard -->
      <li>
        <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'homes.php' || $currentPage == 'index.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
           href="homes.php">
          <div class="w-5 h-5 flex items-center justify-center mr-3">
            <i class="bi bi-house-door"></i>
          </div>
          <span>Dashboard</span>
          <?php if ($currentPage == 'homes.php' || $currentPage == 'index.php'): ?>
            <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
          <?php endif; ?>
        </a>
      </li>

      <!-- Products -->
      <li>
        <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'product.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
           href="product.php">
          <div class="w-5 h-5 flex items-center justify-center mr-3">
            <i class="bi bi-box-seam"></i>
          </div>
          <span>Products</span>
          <?php if ($currentPage == 'product.php'): ?>
            <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
          <?php endif; ?>
        </a>
      </li>

      <!-- Orders -->
      <li>
        <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= in_array($currentPage, ['orders.php', 'order-details.php']) ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
           href="orders.php">
          <div class="w-5 h-5 flex items-center justify-center mr-3">
            <i class="bi bi-cart3"></i>
          </div>
          <span>Orders</span>
          <?php if (in_array($currentPage, ['orders.php', 'order-details.php'])): ?>
            <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
          <?php endif; ?>
        </a>
      </li>
 <li>

<li>
  <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'deleviry.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
     href="deleviry.php">
    <div class="w-5 h-5 flex items-center justify-center mr-3">
      <i class="bi bi-gear"></i>
    </div>
    <span>Deleviry</span>
    <?php if ($currentPage == 'deleviry.php'): ?>
      <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
    <?php endif; ?>
  </a>
</li>

        <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'product.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
           href="rr.php">
          <div class="w-5 h-5 flex items-center justify-center mr-3">
            <i class="bi bi-box-seam"></i>
          </div>
          <span>Return/Refund</span>
          <?php if ($currentPage == 'rr.php'): ?>
            <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
          <?php endif; ?>
        </a>
      </li>
      <!-- Divider -->
      <li>
        <hr class="my-4 border-white border-opacity-20">
      </li>

      <!-- CREATE -->

      <li>
  <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'shop_manage.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
     href="shop_manage.php">
    <div class="w-5 h-5 flex items-center justify-center mr-3">
      <i class="bi bi-gear"></i>
    </div>
    <span>CREATE SHOP</span>
    <?php if ($currentPage == 'shop_manage.php'): ?>
      <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
    <?php endif; ?>
  </a>
</li>

  

<!-- Logout -->
<li>
  <a class="modern-nav-link flex items-center px-4 py-3 rounded-lg transition-all duration-200 hover:bg-white hover:bg-opacity-10 hover:transform hover:scale-105 relative overflow-hidden <?= ($currentPage == 'logout.php') ? 'bg-white bg-opacity-20 shadow-lg' : ''; ?>" 
     href="logout.php">
    <div class="w-5 h-5 flex items-center justify-center mr-3">
      <i class="bi bi-box-arrow-right"></i>
    </div>
    <span>Logout</span>
    <?php if ($currentPage == 'logout.php'): ?>
      <div class="absolute right-2 w-1 h-6 bg-white rounded-full"></div>
    <?php endif; ?>
  </a>
</li>
    </ul>
  </nav>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden" id="sidebarOverlay"></div>

<!-- Mobile Menu Button (add this to your navbar) -->
<button class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-blue-600 text-white rounded-lg shadow-lg" onclick="toggleSidebar()" id="sidebarToggle">
  <i class="bi bi-list text-xl"></i>
</button>

<style>
@keyframes ripple { 
  to { 
    transform: scale(4); 
    opacity: 0; 
  } 
}

.ripple-effect {
  animation: ripple 0.6s linear;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const sidebarToggle = document.getElementById('sidebarToggle');

  function toggleSidebar() {
    if (window.innerWidth <= 1023) { // lg breakpoint in Tailwind
      sidebar.classList.toggle('-translate-x-full');
      overlay.classList.toggle('hidden');
      document.body.classList.toggle('overflow-hidden');
      
      // Update button icon
      const icon = sidebarToggle.querySelector('i');
      if (sidebar.classList.contains('-translate-x-full')) {
        icon.className = 'bi bi-list text-xl';
      } else {
        icon.className = 'bi bi-x text-xl';
      }
    }
  }

  function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    
    // Reset button icon
    const icon = sidebarToggle.querySelector('i');
    icon.className = 'bi bi-list text-xl';
  }

  // Toggle dropdown
  window.toggleDropdown = function() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('hidden');
  }

  // Close dropdown when clicking outside
  document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('profileDropdown');
    const button = e.target.closest('.dropdown-toggle');
    
    if (!button && !dropdown.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });

  // Event listeners
  if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
  });

  window.addEventListener('resize', function() {
    if (window.innerWidth > 1023) { // lg breakpoint
      closeSidebar();
    }
  });

  // Smooth scroll active link into view
  const activeLink = document.querySelector('.modern-nav-link.bg-white');
  if (activeLink) {
    activeLink.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Add ripple effect
  document.querySelectorAll('.modern-nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.className = 'absolute rounded-full bg-white bg-opacity-30 pointer-events-none ripple-effect';
      ripple.style.cssText = `
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        transform: scale(0);
        z-index: 0;
      `;
      
      this.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
  });
});

// Make functions globally available
window.toggleSidebar = function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const sidebarToggle = document.getElementById('sidebarToggle');

  if (window.innerWidth <= 1023) {
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
    document.body.classList.toggle('overflow-hidden');
    
    const icon = sidebarToggle.querySelector('i');
    if (sidebar.classList.contains('-translate-x-full')) {
      icon.className = 'bi bi-list text-xl';
    } else {
      icon.className = 'bi bi-x text-xl';
    }
  }
};
</script>