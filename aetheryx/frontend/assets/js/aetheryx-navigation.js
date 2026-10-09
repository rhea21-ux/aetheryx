/* ÆTHERYX Navigation Controller
 * - Removes redundant Notifications / Compatibility items from the sidebar.
 * - Adds a desktop collapse/expand control.
 * - Keeps the sidebar fixed while content scrolls.
 * - Keeps mobile navigation open/close behavior accessible.
 */
(function () {
    "use strict";

    var STORAGE_KEY = "aetheryx-sidebar-collapsed";
    var ICONS = {
        "hero-dashboard.html": "⌂",
        "marketplace.html": "◈",
        "cart.html": "◇",
        "my-requests.html": "◎",
        "inventory.html": "▣",
        "profile.html": "○",
        "resources.html": "◉",
        "notifications.html": "●",
        "compatibility.html": "⌁"
    };

    function basename(href) {
        try {
            var url = new URL(href, window.location.href);
            return url.pathname.split("/").pop().toLowerCase();
        } catch (e) {
            return String(href || "").split("/").pop().split("#")[0].toLowerCase();
        }
    }

    function labelForLink(link) {
        var label = link.querySelector(".nav-text, .ax-nav-label");
        if (label) return label.textContent.trim();
        return link.textContent.replace(/^[⌂◈◇◎▣○◉●⌁↪◌▦]+\s*/, "").trim();
    }

    function normalizeLink(link) {
        if (link.dataset.axNavReady === "1") return;

        var existingIcon = link.querySelector(".nav-icon, .ax-nav-icon");
        var icon = existingIcon;
        var label = link.querySelector(".nav-text, .ax-nav-label");

        if (!icon) {
            icon = document.createElement("span");
            icon.className = "ax-nav-icon";
            icon.setAttribute("aria-hidden", "true");
            icon.textContent = ICONS[basename(link.getAttribute("href"))] || "•";
            link.insertBefore(icon, link.firstChild);
        } else {
            icon.classList.add("ax-nav-icon");
        }

        if (!label) {
            var textNodes = [];
            Array.prototype.forEach.call(link.childNodes, function (node) {
                if (node === icon) return;
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                    textNodes.push(node);
                }
            });

            if (textNodes.length) {
                label = document.createElement("span");
                label.className = "ax-nav-label";
                textNodes.forEach(function (node) {
                    label.appendChild(node.cloneNode(true));
                    link.removeChild(node);
                });
                link.appendChild(label);
            }
        } else {
            label.classList.add("ax-nav-label");
        }

        link.title = labelForLink(link);
        link.dataset.axNavReady = "1";
    }

    function removeRedundantLinks(sidebar) {
        Array.prototype.forEach.call(sidebar.querySelectorAll("a[href]"), function (link) {
            var file = basename(link.getAttribute("href"));
            if (file === "notifications.html" || file === "compatibility.html") {
                link.remove();
            }
        });
    }

    function createToggle(sidebar) {
        if (sidebar.querySelector(".ax-sidebar-toggle")) return;

        var button = document.createElement("button");
        button.type = "button";
        button.className = "ax-sidebar-toggle";
        button.setAttribute("aria-label", "Collapse sidebar");
        button.setAttribute("title", "Collapse sidebar");
        button.textContent = "‹";

        var brand = sidebar.querySelector(".brand");
        if (brand && brand.parentNode === sidebar) {
            brand.insertAdjacentElement("afterend", button);
        } else {
            sidebar.insertBefore(button, sidebar.firstChild);
        }

        button.addEventListener("click", function () {
            if (window.innerWidth <= 900) {
                closeMobile(sidebar);
                return;
            }
            var collapsed = document.body.classList.toggle("ax-sidebar-collapsed");
            button.textContent = collapsed ? "›" : "‹";
            button.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
            button.setAttribute("title", collapsed ? "Expand sidebar" : "Collapse sidebar");
            try { localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0"); } catch (e) {}
        });

        var saved = false;
        try { saved = localStorage.getItem(STORAGE_KEY) === "1"; } catch (e) {}
        if (saved && window.innerWidth > 900) {
            document.body.classList.add("ax-sidebar-collapsed");
            button.textContent = "›";
            button.setAttribute("aria-label", "Expand sidebar");
            button.setAttribute("title", "Expand sidebar");
        }
    }

    function createMobileOverlay() {
        var overlay = document.querySelector(".ax-nav-overlay");
        if (overlay) return overlay;
        overlay = document.createElement("div");
        overlay.className = "ax-nav-overlay";
        overlay.setAttribute("aria-hidden", "true");
        document.body.appendChild(overlay);
        return overlay;
    }

    function openMobile(sidebar) {
        sidebar.classList.add("open");
        document.body.classList.add("ax-mobile-nav-open");
        var overlay = createMobileOverlay();
        overlay.classList.add("open");
    }

    function closeMobile(sidebar) {
        sidebar.classList.remove("open");
        document.body.classList.remove("ax-mobile-nav-open");
        var overlay = document.querySelector(".ax-nav-overlay");
        if (overlay) overlay.classList.remove("open");
    }

    function setupMobileButton(sidebar) {
        var button = document.getElementById("menuButton") || document.getElementById("mobileToggle");
        if (!button) {
            button = document.createElement("button");
            button.type = "button";
            button.id = "axMobileNavButton";
            button.className = "mobile-toggle ax-mobile-generated";
            button.setAttribute("aria-label", "Open navigation");
            button.textContent = "☰";
            document.body.appendChild(button);
        }
        if (button.dataset.axNavBound === "1") return;
        button.dataset.axNavBound = "1";
        button.addEventListener("click", function () {
            if (sidebar.classList.contains("open")) closeMobile(sidebar);
            else openMobile(sidebar);
        });

        var overlay = createMobileOverlay();
        overlay.addEventListener("click", function () { closeMobile(sidebar); });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") closeMobile(sidebar);
        });

        sidebar.addEventListener("click", function (event) {
            var link = event.target.closest("a");
            if (link && window.innerWidth <= 900) closeMobile(sidebar);
        });
    }

    function setup() {
        var sidebars = document.querySelectorAll(".sidebar");
        Array.prototype.forEach.call(sidebars, function (sidebar) {
            removeRedundantLinks(sidebar);
            Array.prototype.forEach.call(sidebar.querySelectorAll("nav a, .nav a, .nav-link"), normalizeLink);
            createToggle(sidebar);
            setupMobileButton(sidebar);
        });
    }

    document.addEventListener("DOMContentLoaded", setup);
})();
