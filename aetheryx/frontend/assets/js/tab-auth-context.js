"use strict";

(() => {
    const STORAGE_KEY = "aetheryx_tab_auth_token";
    const CHANNEL_NAME = "aetheryx_tab_auth_context";
    const apiRoot = new URL("../../../", document.currentScript.src);
    const cloneUrl = new URL("backend/api/auth/tab-session.php", apiRoot).href;
    const nativeFetch = window.fetch.bind(window);
    function canUseSessionStorage() {
        try {
            const probeKey = `${STORAGE_KEY}_probe`;
            sessionStorage.setItem(probeKey, "1");
            sessionStorage.removeItem(probeKey);
            return true;
        } catch (error) {
            console.error("ÆTHERYX requires sessionStorage for independent sign-in.", error);
            return false;
        }
    }

    const isolationSupported = canUseSessionStorage()
        && "BroadcastChannel" in window
        && Boolean(window.crypto?.subtle)
        && typeof window.crypto.randomUUID === "function";
    const instanceId = isolationSupported ? window.crypto.randomUUID() : "unsupported";
    let token = null;
    try {
        token = sessionStorage.getItem(STORAGE_KEY);
    } catch (error) {
        console.error("Unable to read the ÆTHERYX tab session.", error);
    }
    let tokenFingerprint = null;
    let channel = null;
    let duplicateContextDetected = false;
    const acknowledgedPeers = new Set();

    function startChannel() {
        if (channel || !isolationSupported) {
            return;
        }

        channel = new BroadcastChannel(CHANNEL_NAME);
        channel.addEventListener("message", event => {
            if (event.data?.fingerprint === tokenFingerprint && event.data?.instanceId !== instanceId) {
                duplicateContextDetected = true;
                if (!acknowledgedPeers.has(event.data.instanceId)) {
                    acknowledgedPeers.add(event.data.instanceId);
                    announceToken();
                }
            }
        });
    }

    async function saveToken(nextToken) {
        token = typeof nextToken === "string" && /^[a-f0-9]{64}$/.test(nextToken)
            ? nextToken
            : null;
        if (token) {
            try {
                sessionStorage.setItem(STORAGE_KEY, token);
            } catch (error) {
                token = null;
                tokenFingerprint = null;
                console.error("Unable to store the ÆTHERYX tab session.", error);
                return;
            }
            if (isolationSupported) {
                tokenFingerprint = await fingerprint(token);
                startChannel();
            }
            announceToken();
        } else {
            try {
                sessionStorage.removeItem(STORAGE_KEY);
            } catch (error) {
                console.error("Unable to clear the ÆTHERYX tab session.", error);
            }
            tokenFingerprint = null;
            duplicateContextDetected = false;
        }
    }

    window.setAetheryxTabAuthToken = saveToken;

    async function fingerprint(value) {
        const bytes = new TextEncoder().encode(value);
        const digest = await window.crypto.subtle.digest("SHA-256", bytes);
        return Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, "0")).join("");
    }

    function announceToken() {
        if (channel && tokenFingerprint) {
            channel.postMessage({ fingerprint: tokenFingerprint, instanceId });
        }
    }

    const sessionReady = (async () => {
        if (!token) {
            return;
        }

        if (!isolationSupported) {
            await saveToken(null);
            console.error("ÆTHERYX independent sessions require BroadcastChannel and Web Crypto support.");
            return;
        }

        tokenFingerprint = await fingerprint(token);
        startChannel();
        announceToken();
        await new Promise(resolve => setTimeout(resolve, 150));

        if (!duplicateContextDetected || !token) {
            return;
        }

        try {
            const response = await nativeFetch(cloneUrl, {
                method: "POST",
                credentials: "omit",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Aetheryx-Tab-Session": token,
                },
                body: "{}",
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.tab_session_token) {
                throw new Error(data.message || "Unable to isolate this browser tab.");
            }
            await saveToken(data.tab_session_token);
            duplicateContextDetected = false;
        } catch (error) {
            await saveToken(null);
            console.error("ÆTHERYX tab session isolation failed:", error);
        }
    })();

    window.fetch = async (input, init = {}) => {
        await sessionReady;

        const requestUrl = input instanceof Request ? input.url : String(input);
        const parsedUrl = new URL(requestUrl, window.location.href);
        if (parsedUrl.origin !== window.location.origin) {
            return nativeFetch(input, init);
        }

        if (
            !isolationSupported
            && parsedUrl.pathname.endsWith("/backend/api/auth/login.php")
        ) {
            return new Response(JSON.stringify({
                success: false,
                message: "Secure independent sessions require sessionStorage, BroadcastChannel, and Web Crypto over HTTPS or localhost.",
            }), {
                status: 503,
                headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
            });
        }

        const headers = new Headers(input instanceof Request ? input.headers : undefined);
        new Headers(init.headers || {}).forEach((value, key) => headers.set(key, value));
        if (token) {
            headers.set("X-Aetheryx-Tab-Session", token);
        }

        const response = await nativeFetch(input, {
            ...init,
            headers,
            credentials: "omit",
        });

        if (
            response.ok
            && (
                parsedUrl.pathname.endsWith("/backend/api/auth/login.php")
                || parsedUrl.pathname.endsWith("/backend/api/admin/change-password.php")
            )
        ) {
            const data = await response.clone().json();
            if (data.success && data.tab_session_token) {
                await saveToken(data.tab_session_token);
            }
        } else if (parsedUrl.pathname.endsWith("/backend/api/auth/logout.php") && response.ok) {
            await saveToken(null);
        } else if (response.status === 401 && token) {
            try {
                const data = await response.clone().json();
                if (["TAB_SESSION_REQUIRED", "TAB_SESSION_EXPIRED", "ACCOUNT_INACTIVE"].includes(data.code)) {
                    await saveToken(null);
                }
            } catch (error) {
                console.error("Unable to inspect the authentication response:", error);
            }
        }

        return response;
    };
})();
