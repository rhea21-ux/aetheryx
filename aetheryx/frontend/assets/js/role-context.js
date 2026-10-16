/* Resolve shared marketplace navigation labels from the server session. */
(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    fetch("../../backend/api/auth/session.php", { credentials: "include", cache: "no-store", headers: { Accept: "application/json" } })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data || !data.success) return;
        var role = String(data.role || "").toLowerCase();
        if (role !== "hero" && role !== "enemy" && role !== "admin") return;
        var villain = role === "enemy";
        var dashboard = role === "admin" ? "../admin/admin-dashboard.html" : villain ? "../enemy/villain-dashboard.html" : "../hero/hero-dashboard.html";
        document.querySelectorAll('a[href*="hero-dashboard.html"], a[href*="villain-dashboard.html"]').forEach(function (link) { link.href = dashboard; });
        document.querySelectorAll(".brand-subtitle").forEach(function (node) {
          if (/HERO NETWORK|VILLAIN NETWORK/i.test(node.textContent)) node.textContent = villain ? "VILLAIN NETWORK" : "HERO NETWORK";
        });
        document.querySelectorAll(".user-name span, #accountRole").forEach(function (node) { node.textContent = villain ? "Villain" : role === "admin" ? "Administrator" : "Hero"; });
        document.querySelectorAll(".user-mini .avatar").forEach(function (node) { node.textContent = villain ? "V" : role === "admin" ? "A" : "H"; });
      }).catch(function () {});
  });
})();
