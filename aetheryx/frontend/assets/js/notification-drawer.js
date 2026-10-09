/* Notification bell + right-side drawer (used on every hero/user page). */
(function () {
    "use strict";

    var API = "../../backend/api/notifications/";
    var VIEW_ALL = location.pathname.indexOf("/hero/") !== -1 ? "../users/notifications.html" : "notifications.html";
    var items = [], filter = "all", lastFocus = null, timer = null;

    var css = [
        ".nd-bell{position:fixed;top:14px;right:24px;z-index:120;width:46px;height:46px;border-radius:50%;display:grid;place-items:center;cursor:pointer;background:var(--surface,#151C2F);color:var(--text,#E9EDF6);border:1px solid var(--border,#3A4566);}",
        ".nd-bell:hover{background:var(--surface-2,#1B2338);}",
        ".nd-bell svg{width:22px;height:22px;}",
        ".nd-badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:10px;background:#E66B7B;color:#fff;font:700 12px/20px system-ui,sans-serif;text-align:center;}",
        ".nd-overlay{position:fixed;inset:0;z-index:300;background:rgba(5,9,18,.6);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;}",
        ".nd-overlay.open{opacity:1;visibility:visible;}",
        ".nd-panel{position:fixed;top:0;right:0;bottom:0;z-index:310;width:min(420px,100vw);display:flex;flex-direction:column;background:var(--surface,#151C2F);color:var(--text,#E9EDF6);border-left:1px solid var(--border,#3A4566);transform:translateX(105%);transition:transform .22s ease;font-family:var(--font,system-ui,sans-serif);}",
        ".nd-panel.open{transform:translateX(0);}",
        ".nd-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--border,#3A4566);}",
        ".nd-head h2{font-size:20px;margin:0;}",
        ".nd-head p{margin:2px 0 0;font-size:14px;color:var(--muted,#AEB9D0);}",
        ".nd-close{width:44px;height:44px;border-radius:10px;border:1px solid var(--border,#3A4566);background:transparent;color:inherit;font-size:22px;cursor:pointer;}",
        ".nd-close:hover,.nd-tab:hover,.nd-link:hover{background:var(--surface-2,#1B2338);}",
        ".nd-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:12px 20px;border-bottom:1px solid var(--border,#3A4566);}",
        ".nd-tabs{display:flex;gap:6px;}",
        ".nd-tab,.nd-link{min-height:40px;padding:0 14px;border-radius:9px;border:1px solid transparent;background:transparent;color:var(--muted,#AEB9D0);font-weight:600;font-size:15px;font-family:inherit;cursor:pointer;}",
        ".nd-tab.active{background:var(--surface-2,#1B2338);color:var(--text,#E9EDF6);border-color:var(--border,#3A4566);}",
        ".nd-link{color:var(--primary,#4CC9B4);}",
        ".nd-list{flex:1;overflow-y:auto;}",
        ".nd-item{display:flex;gap:12px;width:100%;padding:16px 20px;border:0;border-bottom:1px solid var(--border,#3A4566);background:transparent;color:inherit;text-align:left;font:inherit;cursor:pointer;}",
        ".nd-item:hover{background:var(--surface-2,#1B2338);}",
        ".nd-item.unread{background:rgba(76,201,180,.07);}",
        ".nd-dot{flex:none;width:10px;height:10px;margin-top:7px;border-radius:50%;background:#8FA7F0;}",
        ".nd-dot.success{background:#5FD39A;}.nd-dot.warning{background:#E7B85D;}.nd-dot.danger{background:#E66B7B;}",
        ".nd-body{flex:1;min-width:0;}",
        ".nd-title,.nd-msg{display:block;}",
        ".nd-title{font-weight:700;font-size:16px;}",
        ".nd-msg{margin-top:2px;font-size:15px;color:var(--muted,#AEB9D0);line-height:1.45;}",
        ".nd-time{display:block;margin-top:6px;font-size:13px;color:var(--muted,#AEB9D0);}",
        ".nd-x{flex:none;align-self:flex-start;width:36px;height:36px;border-radius:8px;border:0;background:transparent;color:var(--muted,#AEB9D0);font-size:18px;cursor:pointer;}",
        ".nd-x:hover{background:var(--surface-2,#1B2338);color:var(--text,#E9EDF6);}",
        ".nd-empty{padding:48px 24px;text-align:center;color:var(--muted,#AEB9D0);font-size:16px;}",
        ".nd-foot{padding:14px 20px;border-top:1px solid var(--border,#3A4566);}",
        ".nd-foot a{display:block;text-align:center;padding:12px;border-radius:9px;background:var(--primary,#4CC9B4);color:var(--on-primary,#06201B);font-weight:700;text-decoration:none;}",
        "@media (max-width:900px){.nd-bell{top:6px;right:68px;width:44px;height:44px;}}"
    ].join("");

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function timeAgo(value) {
        var t = new Date(String(value).replace(" ", "T")).getTime();
        if (isNaN(t)) return "";
        var s = Math.max(0, Math.floor((Date.now() - t) / 1000));
        if (s < 60) return "Just now";
        if (s < 3600) return Math.floor(s / 60) + " min ago";
        if (s < 86400) return Math.floor(s / 3600) + " hr ago";
        if (s < 604800) return Math.floor(s / 86400) + " day" + (s >= 172800 ? "s" : "") + " ago";
        return new Date(t).toLocaleDateString();
    }

    async function call(endpoint, body) {
        var res = await fetch(API + endpoint, {
            method: body ? "POST" : "GET",
            credentials: "include",
            headers: { "Accept": "application/json", "Content-Type": "application/json" },
            body: body ? JSON.stringify(body) : undefined
        });
        var data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || "Request failed.");
        return data;
    }

    document.addEventListener("DOMContentLoaded", function () {
        var style = document.createElement("style");
        style.textContent = css;
        document.head.appendChild(style);

        var bell = el("button", "nd-bell");
        bell.type = "button";
        bell.setAttribute("aria-label", "Notifications");
        bell.setAttribute("aria-haspopup", "dialog");
        bell.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>';
        var badge = el("span", "nd-badge");
        badge.hidden = true;
        bell.appendChild(badge);

        var overlay = el("div", "nd-overlay");
        var panel = el("aside", "nd-panel");
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-modal", "true");
        panel.setAttribute("aria-label", "Notifications");

        var head = el("div", "nd-head");
        var titleWrap = el("div");
        titleWrap.appendChild(el("h2", null, "Notifications"));
        var sub = el("p", null, "Loading...");
        titleWrap.appendChild(sub);
        var close = el("button", "nd-close", "\u00D7");
        close.type = "button";
        close.setAttribute("aria-label", "Close notifications");
        head.appendChild(titleWrap);
        head.appendChild(close);

        var bar = el("div", "nd-bar");
        var tabs = el("div", "nd-tabs");
        var tabAll = el("button", "nd-tab active", "All");
        var tabUnread = el("button", "nd-tab", "Unread");
        tabAll.type = tabUnread.type = "button";
        tabs.appendChild(tabAll);
        tabs.appendChild(tabUnread);
        var markAll = el("button", "nd-link", "Mark all as read");
        markAll.type = "button";
        bar.appendChild(tabs);
        bar.appendChild(markAll);

        var list = el("div", "nd-list");
        list.setAttribute("aria-live", "polite");

        var foot = el("div", "nd-foot");
        var viewAll = el("a", null, "View all notifications");
        viewAll.href = VIEW_ALL;
        foot.appendChild(viewAll);

        [head, bar, list, foot].forEach(function (n) { panel.appendChild(n); });
        document.body.appendChild(bell);
        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        function unreadCount() { return items.filter(function (n) { return !n.is_read; }).length; }

        function renderBadge() {
            var n = unreadCount();
            badge.hidden = n === 0;
            badge.textContent = n > 99 ? "99+" : String(n);
            bell.setAttribute("aria-label", n ? "Notifications, " + n + " unread" : "Notifications");
            sub.textContent = n ? n + " unread" : "You are all caught up";
            markAll.hidden = n === 0;
        }

        function render() {
            renderBadge();
            list.textContent = "";
            var visible = filter === "unread" ? items.filter(function (n) { return !n.is_read; }) : items;
            if (!visible.length) {
                list.appendChild(el("div", "nd-empty", filter === "unread" ? "No unread notifications." : "No notifications yet."));
                return;
            }
            visible.slice(0, 30).forEach(function (n) {
                var row = el("div");
                row.style.display = "flex";
                row.style.borderBottom = "1px solid var(--border,#3A4566)";
                var btn = el("button", "nd-item" + (n.is_read ? "" : " unread"));
                btn.type = "button";
                btn.style.borderBottom = "0";
                btn.appendChild(el("span", "nd-dot " + String(n.type || "info").replace(/[^a-z]/g, "")));
                var body = el("span", "nd-body");
                body.appendChild(el("span", "nd-title", n.title));
                body.appendChild(el("span", "nd-msg", n.message));
                body.appendChild(el("span", "nd-time", timeAgo(n.created_at)));
                btn.appendChild(body);
                btn.addEventListener("click", function () { read(n); });
                var x = el("button", "nd-x", "\u00D7");
                x.type = "button";
                x.setAttribute("aria-label", "Delete notification");
                x.addEventListener("click", function () { remove(n); });
                row.appendChild(btn);
                row.appendChild(x);
                list.appendChild(row);
            });
        }

        async function load() {
            try {
                var data = await call("list.php");
                items = (data.notifications || []).map(function (n) { n.is_read = !!n.is_read; return n; });
                render();
            } catch (e) {
                if (panel.classList.contains("open")) {
                    list.textContent = "";
                    list.appendChild(el("div", "nd-empty", "Unable to load notifications. Please try again."));
                }
            }
        }

        async function read(n) {
            if (n.is_read) return;
            n.is_read = true; render();
            try { await call("mark-read.php", { id: Number(n.id) }); } catch (e) { n.is_read = false; render(); }
        }

        async function remove(n) {
            var before = items;
            items = items.filter(function (x) { return x !== n; }); render();
            try { await call("delete.php", { id: Number(n.id) }); } catch (e) { items = before; render(); }
        }

        markAll.addEventListener("click", async function () {
            var before = items.map(function (n) { return n.is_read; });
            items.forEach(function (n) { n.is_read = true; }); render();
            try { await call("mark-read.php", { all: true }); }
            catch (e) { items.forEach(function (n, i) { n.is_read = before[i]; }); render(); }
        });

        function setFilter(f) {
            filter = f;
            tabAll.classList.toggle("active", f === "all");
            tabUnread.classList.toggle("active", f === "unread");
            render();
        }
        tabAll.addEventListener("click", function () { setFilter("all"); });
        tabUnread.addEventListener("click", function () { setFilter("unread"); });

        function open() {
            lastFocus = document.activeElement;
            overlay.classList.add("open");
            panel.classList.add("open");
            close.focus();
            load();
        }
        function shut() {
            overlay.classList.remove("open");
            panel.classList.remove("open");
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }
        bell.addEventListener("click", open);
        close.addEventListener("click", shut);
        overlay.addEventListener("click", shut);
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && panel.classList.contains("open")) shut();
        });

        load();
        timer = setInterval(function () { if (!document.hidden) load(); }, 60000);
    });
})();
