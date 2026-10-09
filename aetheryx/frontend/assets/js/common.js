/* Shared helpers: adjustable text size + skip link. */
(function () {
    "use strict";
    var KEY = "aetheryx-text-scale";
    var SIZES = [1, 1.15, 1.3];

    function read() {
        try { return Math.min(Math.max(parseInt(localStorage.getItem(KEY), 10) || 0, 0), SIZES.length - 1); }
        catch (e) { return 0; }
    }
    function apply(level) {
        document.body.style.zoom = SIZES[level];
        try { localStorage.setItem(KEY, String(level)); } catch (e) {}
        var label = document.getElementById("textSizeLabel");
        if (label) label.textContent = level === 0 ? "Normal" : level === 1 ? "Large" : "Extra large";
    }

    document.addEventListener("DOMContentLoaded", function () {
        var main = document.querySelector("main");
        if (main) {
            main.id = main.id || "mainContent";
            var skip = document.createElement("a");
            skip.className = "skip-link";
            skip.href = "#" + main.id;
            skip.textContent = "Skip to main content";
            document.body.insertBefore(skip, document.body.firstChild);
        }

        var bottom = document.querySelector(".sidebar-bottom");
        if (bottom) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "text-size-btn";
            btn.setAttribute("aria-label", "Change text size");
            btn.innerHTML = 'Text size: <span id="textSizeLabel"></span>';
            btn.addEventListener("click", function () { apply((read() + 1) % SIZES.length); });
            bottom.insertBefore(btn, bottom.firstChild);
        }
        apply(read());
    });
})();
