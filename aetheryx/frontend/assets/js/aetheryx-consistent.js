/* ÆTHERYX CONSISTENT SHELL CONTROLLER */
(function () {
  'use strict';

  var STORAGE_KEY = 'aetheryx-sidebar-collapsed';

  /* Prevent a collapsed-state flash before the page shell is painted. */
  try {
    if (localStorage.getItem(STORAGE_KEY) === '1') {
      document.documentElement.classList.add('ax-sidebar-collapsed');
    }
  } catch (e) {}

  function cleanPrimaryNav(sidebar) {
    if (!sidebar) return;
    sidebar.querySelectorAll('.nav a').forEach(function (link) {
      var label = (link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (label === 'notifications') {
        link.remove();
      }
    });
  }


  function normalizeUserNav(sidebar) {
    if (!sidebar) return;
    var path = window.location.pathname.toLowerCase();
    if (path.indexOf('/frontend/admin/') !== -1) return;

    var nav = sidebar.querySelector('.nav');
    if (!nav) return;

    var existingDashboard = nav.querySelector('#dashboardLink, a[href*="hero-dashboard.html"], a[href*="villain-dashboard.html"], a[href*="admin-dashboard.html"]');
    var dashboardHref = existingDashboard && existingDashboard.getAttribute('href');
    if (!dashboardHref || dashboardHref === '#') {
      if (path.indexOf('/frontend/enemy/') !== -1) dashboardHref = 'villain-dashboard.html';
      else if (path.indexOf('/frontend/hero/') !== -1) dashboardHref = 'hero-dashboard.html';
      else dashboardHref = '../hero/hero-dashboard.html';
    }

    var links = [
      ['⌂', 'Dashboard', dashboardHref],
      ['◈', 'Marketplace', '../users/marketplace.html'],
      ['◇', 'Cart', '../users/cart.html'],
      ['◎', 'My Requests', '../users/my-requests.html'],
      ['▣', 'Inventory', '../users/inventory.html'],
      ['⌁', 'Compatibility', '../users/compatibility.html'],
      ['○', 'Profile', '../users/profile.html']
    ];

    /* Convert the relative paths for pages already inside /users/. */
    if (path.indexOf('/frontend/users/') !== -1) {
      links = [
        ['⌂', 'Dashboard', dashboardHref],
        ['◈', 'Marketplace', 'marketplace.html'],
        ['◇', 'Cart', 'cart.html'],
        ['◎', 'My Requests', 'my-requests.html'],
        ['▣', 'Inventory', 'inventory.html'],
        ['⌁', 'Compatibility', 'compatibility.html'],
        ['○', 'Profile', 'profile.html']
      ];
    } else if (path.indexOf('/frontend/hero/') !== -1) {
      links = [
        ['⌂', 'Dashboard', 'hero-dashboard.html'],
        ['◈', 'Marketplace', '../users/marketplace.html'],
        ['◇', 'Cart', '../users/cart.html'],
        ['◎', 'My Requests', '../users/my-requests.html'],
        ['▣', 'Inventory', '../users/inventory.html'],
        ['⌁', 'Compatibility', '../users/compatibility.html'],
        ['○', 'Profile', '../users/profile.html']
      ];
    } else if (path.indexOf('/frontend/enemy/') !== -1) {
      links = [
        ['⌂', 'Dashboard', 'villain-dashboard.html'],
        ['◈', 'Marketplace', '../users/marketplace.html'],
        ['◇', 'Cart', '../users/cart.html'],
        ['◎', 'My Requests', '../users/my-requests.html'],
        ['▣', 'Inventory', '../users/inventory.html'],
        ['⌁', 'Compatibility', '../users/compatibility.html'],
        ['○', 'Profile', '../users/profile.html']
      ];
    }

    var currentFile = path.split('/').pop();
    nav.innerHTML = '';
    links.forEach(function (item) {
      var a = document.createElement('a');
      a.href = item[2];
      a.className = '';
      var target = String(item[2]).split('/').pop().toLowerCase();
      var active = currentFile === target || (currentFile === '' && item[1] === 'Dashboard');
      if (active) a.classList.add('active');
      if (item[1] === 'Dashboard' && existingDashboard && existingDashboard.id) a.id = existingDashboard.id;
      a.title = item[1];
      a.setAttribute('aria-label', item[1]);
      a.innerHTML = '<span class="nav-icon">' + item[0] + '</span><span class="nav-text">' + item[1] + '</span>';
      nav.appendChild(a);
    });
  }

  function ensureSidebarForCompatibility() {
    var existing = document.querySelector('.sidebar');
    if (existing) return existing;

    var page = document.querySelector('.page');
    if (!page) return null;

    document.body.classList.add('ax-compatibility-page');

    var sidebar = document.createElement('aside');
    sidebar.className = 'sidebar ax-generated-sidebar';
    sidebar.id = 'sidebar';
    sidebar.innerHTML = `
      <div class="brand">
        <div class="brand-symbol">Æ</div>
        <div>
          <div class="brand-name">ÆTHERYX</div>
          <div class="brand-sub" id="axRoleNetwork">USER NETWORK</div>
        </div>
      </div>
      <div class="section-label">Command</div>
      <nav class="nav" aria-label="Primary navigation">
        <a href="../hero/hero-dashboard.html"><span class="nav-icon">⌂</span><span class="nav-text">Dashboard</span></a>
        <a href="marketplace.html"><span class="nav-icon">◈</span><span class="nav-text">Marketplace</span></a>
        <a href="cart.html"><span class="nav-icon">◇</span><span class="nav-text">Cart</span></a>
        <a href="my-requests.html"><span class="nav-icon">◎</span><span class="nav-text">My Requests</span></a>
        <a href="inventory.html"><span class="nav-icon">▣</span><span class="nav-text">Inventory</span></a>
        <a href="compatibility.html"><span class="nav-icon">⌁</span><span class="nav-text">Compatibility</span></a>
        <a href="profile.html"><span class="nav-icon">○</span><span class="nav-text">Profile</span></a>
      </nav>
      <div class="sidebar-bottom">
        <div class="user-mini">
          <div class="avatar" id="axUserAvatar">U</div>
          <div class="user-name"><strong id="axUserHandle">Authenticated</strong><span id="axUserRole">Checking role…</span></div>
        </div>
        <button class="logout-btn" id="axLogoutButton" type="button">Sign out</button>
      </div>`;
    document.body.insertBefore(sidebar, document.body.firstChild);
    return sidebar;
  }

  function ensureMobileToggle(sidebar) {
    if (!sidebar) return null;
    var button = document.getElementById('mobileToggle') || document.getElementById('menuButton') || document.querySelector('.mobile-toggle, .menu-btn');
    if (!button) {
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'mobile-toggle ax-consistent-mobile-toggle';
      button.id = 'axMobileToggle';
      button.setAttribute('aria-label', 'Open navigation');
      button.textContent = '☰';
      document.body.appendChild(button);
    }
    return button;
  }

  function ensureOverlay() {
    var overlay = document.querySelector('.ax-sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'ax-sidebar-overlay';
      overlay.setAttribute('aria-hidden', 'true');
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function ensureCollapseButton(sidebar) {
    if (!sidebar || window.matchMedia('(max-width: 900px)').matches) return;
    if (sidebar.querySelector('.ax-sidebar-toggle')) return;

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'ax-sidebar-toggle';
    button.setAttribute('aria-label', 'Collapse sidebar');
    button.setAttribute('title', 'Collapse sidebar');
    button.textContent = '‹';
    sidebar.appendChild(button);

    function updateButton() {
      var collapsed = document.documentElement.classList.contains('ax-sidebar-collapsed');
      button.textContent = collapsed ? '›' : '‹';
      button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      button.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    }

    button.addEventListener('click', function () {
      var collapsed = document.documentElement.classList.toggle('ax-sidebar-collapsed');
      try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
      updateButton();
    });

    updateButton();
  }

  function bindGeneratedSidebarAccount(sidebar) {
    if (!sidebar || !sidebar.classList.contains('ax-generated-sidebar')) return;
    var roleNode = sidebar.querySelector('#axUserRole');
    var handleNode = sidebar.querySelector('#axUserHandle');
    var avatarNode = sidebar.querySelector('#axUserAvatar');
    var networkNode = sidebar.querySelector('#axRoleNetwork');
    var dashboard = sidebar.querySelector('.nav a');
    var logout = sidebar.querySelector('#axLogoutButton');

    fetch('../../backend/api/auth/session.php', {
      method: 'GET', credentials: 'include', cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    }).then(function (response) { return response.json(); }).then(function (data) {
      if (!data || !data.success) throw new Error('No active session');
      var user = data.user || {};
      var rawRole = String(data.role || user.role || '').trim().toLowerCase();
      var role = rawRole === 'enemy' || rawRole === 'villain' ? 'Villain' :
                 rawRole === 'hero' ? 'Hero' : rawRole === 'admin' ? 'Admin' : 'User';
      var handle = String(data.handle || user.handle || user.username || 'Authenticated');
      if (roleNode) roleNode.textContent = role;
      if (handleNode) handleNode.textContent = handle;
      if (avatarNode) avatarNode.textContent = handle.charAt(0).toUpperCase();
      if (networkNode) networkNode.textContent = role.toUpperCase() + ' NETWORK';
      if (dashboard) {
        if (role === 'Villain') dashboard.href = '../enemy/villain-dashboard.html';
        else if (role === 'Hero') dashboard.href = '../hero/hero-dashboard.html';
        else if (role === 'Admin') dashboard.href = '../admin/admin-dashboard.html';
      }
    }).catch(function () {
      if (roleNode) roleNode.textContent = 'Session unavailable';
      if (networkNode) networkNode.textContent = 'ÆTHERYX';
    });

    if (logout && !logout.dataset.axBound) {
      logout.dataset.axBound = '1';
      logout.addEventListener('click', async function () {
        logout.disabled = true;
        logout.textContent = 'Signing out…';
        try {
          await fetch('../../backend/api/auth/logout.php', {
            method: 'POST', credentials: 'include', cache: 'no-store',
            headers: { 'Accept': 'application/json' }
          });
        } catch (e) {}
        window.location.replace('../public/login.html');
      });
    }
  }

  function init() {
    var sidebar = ensureSidebarForCompatibility() || document.querySelector('.sidebar');
    if (!sidebar) return;

    cleanPrimaryNav(sidebar);
    normalizeUserNav(sidebar);
    bindGeneratedSidebarAccount(sidebar);
    sidebar.querySelectorAll('.nav a').forEach(function (link) {
      var label = (link.textContent || '').replace(/\s+/g, ' ').trim();
      if (label) {
        link.title = label;
        link.setAttribute('aria-label', label);
      }
    });

    var mobileButton = ensureMobileToggle(sidebar);
    var overlay = ensureOverlay();
    var createdMobileButton = !!(mobileButton && mobileButton.classList.contains('ax-consistent-mobile-toggle'));

    if (mobileButton && !mobileButton.dataset.axBound && createdMobileButton) {
      mobileButton.dataset.axBound = '1';
      mobileButton.addEventListener('click', function (event) {
        event.stopPropagation();
        var isOpen = sidebar.classList.toggle('open');
        overlay.classList.toggle('visible', isOpen);
        mobileButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
      });
    }

    /* Existing pages already have their own mobile toggle listeners.
       Mirror their sidebar state instead of adding a second toggle listener. */
    if (mobileButton && !mobileButton.dataset.axMirrorBound) {
      mobileButton.dataset.axMirrorBound = '1';
      document.addEventListener('click', function () {
        window.setTimeout(function () {
          var isOpen = sidebar.classList.contains('open');
          overlay.classList.toggle('visible', isOpen);
          mobileButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        }, 0);
      }, true);
    }

    if (!overlay.dataset.axBound) {
      overlay.dataset.axBound = '1';
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
        if (mobileButton) mobileButton.setAttribute('aria-label', 'Open navigation');
      });
    }

    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 900px)').matches) {
          sidebar.classList.remove('open');
          overlay.classList.remove('visible');
          if (mobileButton) mobileButton.setAttribute('aria-label', 'Open navigation');
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        sidebar.classList.remove('open');
        overlay.classList.remove('visible');
        if (mobileButton) mobileButton.setAttribute('aria-label', 'Open navigation');
      }
    });

    ensureCollapseButton(sidebar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
