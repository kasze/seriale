const escapeHtml = (value) =>
    String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");

const debounce = (fn, delay = 220) => {
    let timeout;
    return (...args) => {
        window.clearTimeout(timeout);
        timeout = window.setTimeout(() => fn(...args), delay);
    };
};

const translateShowStatus = (status) => {
    const value = String(status ?? "").trim().toLowerCase();

    switch (value) {
        case "running":
            return "W emisji";
        case "ended":
            return "Zakończony";
        case "to be determined":
            return "Do ustalenia";
        case "in development":
        case "development":
            return "W przygotowaniu";
        case "pilot":
            return "Pilot";
        case "hiatus":
            return "Przerwa";
        case "stopped":
            return "Wstrzymany";
        case "canceled":
        case "cancelled":
            return "Skasowany";
        default:
            return status || "";
    }
};

const relativeCountdown = (iso) => {
    if (!iso) {
        return "Brak zapowiedzi";
    }

    const target = new Date(iso);

    if (Number.isNaN(target.getTime())) {
        return "Brak zapowiedzi";
    }

    const diffMs = target.getTime() - Date.now();

    if (diffMs <= 0) {
        return "Już wyemitowany";
    }

    const dayMs = 24 * 60 * 60 * 1000;
    const weekMs = 7 * dayMs;
    const monthMs = 30 * dayMs;
    const yearMs = 365 * dayMs;
    const plural = (value, one, few, many) => {
        const mod10 = value % 10;
        const mod100 = value % 100;

        if (value === 1) {
            return one;
        }

        if (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14)) {
            return few;
        }

        return many;
    };

    const minutes = Math.round(diffMs / 60000);

    if (minutes < 60) {
        return `za ${Math.max(1, minutes)} min`;
    }

    const hours = Math.round(minutes / 60);

    if (hours < 48) {
        return `za ${hours} h`;
    }

    if (diffMs >= yearMs) {
        const years = Math.floor(diffMs / yearMs);
        return `za ${years} ${plural(years, "rok", "lata", "lat")}`;
    }

    if (diffMs >= monthMs) {
        const months = Math.floor(diffMs / monthMs);
        return `za ${months} ${plural(months, "miesiąc", "miesiące", "miesięcy")}`;
    }

    if (diffMs >= weekMs) {
        const weeks = Math.floor(diffMs / weekMs);
        return `za ${weeks} ${plural(weeks, "tydzień", "tygodnie", "tygodni")}`;
    }

    return `za ${Math.round(hours / 24)} dni`;
};

const renderSearchResult = (item) => {
    const poster = item.poster_url
        ? `<div class="search-result__thumb"><img src="${escapeHtml(item.poster_url)}" alt="${escapeHtml(item.title)}"></div>`
        : `<div class="search-result__thumb"></div>`;
    const meta = [item.year, item.country || item.network || translateShowStatus(item.status)].filter(Boolean).join(" · ");

    return `
        <button type="button" class="search-result" data-provider="${escapeHtml(item.provider)}" data-source-id="${escapeHtml(item.source_id)}">
            ${poster}
            <div>
                <div class="search-result__title">${escapeHtml(item.title)}</div>
                <div class="search-result__meta">${escapeHtml(meta || "Brak danych")}</div>
            </div>
            <span class="pill pill--accent">Dodaj</span>
        </button>
    `;
};

const initSearchWidget = (widget) => {
    const endpoint = widget.dataset.endpoint;
    const input = widget.querySelector("[data-search-input]");
    const results = widget.querySelector("[data-search-results]");
    const form = widget.querySelector("[data-track-form]");
    const provider = widget.querySelector("[data-track-provider]");
    const sourceId = widget.querySelector("[data-track-source-id]");

    if (!endpoint || !input || !results || !form || !provider || !sourceId) {
        return;
    }

    const hideResults = () => {
        results.hidden = true;
        results.innerHTML = "";
    };

    const search = debounce(async () => {
        const query = input.value.trim();

        if (query.length < 2) {
            hideResults();
            return;
        }

        try {
            const response = await fetch(`${endpoint}?format=json&q=${encodeURIComponent(query)}`, {
                headers: {
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                hideResults();
                return;
            }

            const payload = await response.json();
            const items = payload.results || [];

            if (!items.length) {
                results.hidden = false;
                results.innerHTML = `<div class="empty-state">Brak wyników dla tego zapytania.</div>`;
                return;
            }

            results.hidden = false;
            results.innerHTML = items.map(renderSearchResult).join("");
        } catch {
            hideResults();
        }
    });

    input.addEventListener("input", search);
    input.addEventListener("focus", () => {
        if (input.value.trim().length >= 2) {
            search();
        }
    });

    results.addEventListener("click", (event) => {
        const button = event.target.closest("[data-provider][data-source-id]");

        if (!button) {
            return;
        }

        provider.value = button.dataset.provider || "tvmaze";
        sourceId.value = button.dataset.sourceId || "";
        form.submit();
    });

    document.addEventListener("click", (event) => {
        if (!widget.contains(event.target)) {
            hideResults();
        }
    });
};

const initCountdowns = () => {
    const update = () => {
        document.querySelectorAll("[data-countdown]").forEach((node) => {
            node.textContent = relativeCountdown(node.dataset.at);
        });
    };

    update();
    window.setInterval(update, 60000);
};

const initTabs = (root) => {
    const buttons = Array.from(root.querySelectorAll("[data-tab-button]"));
    const panels = Array.from(root.querySelectorAll("[data-tab-panel]"));

    if (!buttons.length || !panels.length) {
        return;
    }

    const activate = (name) => {
        buttons.forEach((button) => {
            const active = button.dataset.tabButton === name;
            button.classList.toggle("is-active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });

        panels.forEach((panel) => {
            const active = panel.dataset.tabPanel === name;
            panel.classList.toggle("is-active", active);
            panel.hidden = !active;
        });
    };

    buttons.forEach((button) => {
        button.addEventListener("click", () => activate(button.dataset.tabButton || ""));
    });
};

const buildTrackedBadge = (label) => {
    const badge = document.createElement("span");
    badge.className = "pill pill--accent discovery-status";
    badge.textContent = label;
    return badge;
};

const updateTrackedLinks = (slot, payload) => {
    const actions = slot.parentElement;

    if (!actions) {
        return;
    }

    const link = actions.querySelector("[data-track-link]");

    if (!link || !payload?.show?.url) {
        return;
    }

    link.href = payload.show.url;
    link.textContent = link.dataset.localLabel || "Szczegóły";
    link.dataset.localUrl = payload.show.url;
};

const initAjaxTrackForms = () => {
    document.querySelectorAll("form[data-ajax-track]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();

            const submit = form.querySelector('button[type="submit"]');
            const slot = form.closest("[data-track-slot]");
            const trackKey = slot?.dataset.trackKey || "";

            if (!submit || !slot || submit.disabled) {
                return;
            }

            const originalLabel = submit.textContent;
            submit.disabled = true;
            submit.textContent = "Dodawanie...";

            try {
                const response = await fetch(form.action, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                    },
                    body: new FormData(form),
                    credentials: "same-origin",
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.status !== "ok") {
                    throw new Error(payload.message || "Nie udało się dodać serialu.");
                }

                const slots = trackKey
                    ? document.querySelectorAll(`[data-track-slot][data-track-key="${CSS.escape(trackKey)}"]`)
                    : [slot];

                slots.forEach((node) => {
                    node.innerHTML = "";
                    node.appendChild(buildTrackedBadge(node === slot ? "Dodano" : "Już obserwowany"));
                    updateTrackedLinks(node, payload);
                });
            } catch (error) {
                submit.disabled = false;
                submit.textContent = originalLabel;
                window.alert(error instanceof Error ? error.message : "Nie udało się dodać serialu.");
            }
        });
    });
};

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-search-widget]").forEach(initSearchWidget);
    initCountdowns();
    document.querySelectorAll("[data-tabs]").forEach(initTabs);
    initAjaxTrackForms();
});
