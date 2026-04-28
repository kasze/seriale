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

const buildTimelinePoster = (title, posterUrl) => {
    if (posterUrl) {
        return `<img src="${escapeHtml(posterUrl)}" alt="${escapeHtml(title)}" loading="lazy" decoding="async">`;
    }

    const letter = String(title || "?").trim().charAt(0) || "?";

    return `<div class="show-card__placeholder" aria-hidden="true">${escapeHtml(letter)}</div>`;
};

const renderSeasonProgress = (seasonProgress) => {
    if (!seasonProgress || !(seasonProgress.markers || []).length) {
        return "";
    }

    return `
        <div class="season-progress season-progress--compact" data-season-progress>
            <div class="season-progress__track" role="list" aria-label="Przebieg sezonu">
                ${(seasonProgress.markers || [])
                    .map(
                        (marker) => `
                            <span
                                class="season-progress__marker season-progress__marker--${escapeHtml(marker.status_key || "upcoming")} ${marker.is_latest ? "is-latest" : ""} ${marker.is_next ? "is-next" : ""}"
                                title="${escapeHtml([marker.full_code, marker.title, marker.date, marker.relative, marker.status].filter(Boolean).join(" · "))}"
                            >
                                <span>${escapeHtml(marker.code || "")}</span>
                            </span>
                        `
                    )
                    .join("")}
            </div>
        </div>
    `;
};

const renderTimelineEvent = (entry, selectedId) => `
    <button
        type="button"
        class="timeline-event ${selectedId === entry.id ? "is-active" : ""} timeline-event--${escapeHtml(entry.status_key || "upcoming")}"
        data-timeline-event
        data-id="${escapeHtml(entry.id || "")}"
        data-title="${escapeHtml(entry.title || "")}"
        data-show-url="${escapeHtml(entry.show_url || "")}"
        data-episode-code="${escapeHtml(entry.episode_code || "")}"
        data-episode-name="${escapeHtml(entry.episode_name || "")}"
        data-when="${escapeHtml(entry.when || "")}"
        data-relative="${escapeHtml(entry.relative || "")}"
        data-status="${escapeHtml(entry.status || "")}"
        data-status-key="${escapeHtml(entry.status_key || "upcoming")}"
        data-poster-url="${escapeHtml(entry.poster_url || "")}"
        data-tpb-url="${escapeHtml(entry.tpb_url || "")}"
        data-btdig-url="${escapeHtml(entry.btdig_url || "")}"
        data-season-progress="${escapeHtml(JSON.stringify(entry.season_progress || null))}"
        title="${escapeHtml(entry.title || "")}"
        aria-pressed="${selectedId === entry.id ? "true" : "false"}"
    >
        <span class="timeline-event__label"><span class="timeline-event__label-text">${escapeHtml(entry.short_title || "Bez tytułu")}</span></span>
    </button>
`;

const renderTimelineDay = (day, selectedId) => `
    <section class="timeline-day ${day.is_today ? "timeline-day--today" : ""} ${(day.episodes || []).length ? "" : "timeline-day--empty"}">
        <header class="timeline-day__head">
            <span class="timeline-day__label">${escapeHtml(day.label || "")}</span>
            <strong>${escapeHtml(day.day_number || "")}</strong>
        </header>
        <div class="timeline-day__rail"></div>
        <div class="timeline-day__events">
            ${(day.episodes || []).length
                ? day.episodes.map((entry) => renderTimelineEvent(entry, selectedId)).join("")
                : '<span class="timeline-day__empty">Brak</span>'}
        </div>
    </section>
`;

const initTimelineLabelScroll = (root) => {
    root.querySelectorAll(".timeline-event").forEach((eventNode) => {
        if (eventNode.dataset.marqueeReady === "true") {
            return;
        }

        eventNode.dataset.marqueeReady = "true";
        const label = eventNode.querySelector(".timeline-event__label");
        const text = eventNode.querySelector(".timeline-event__label-text");

        if (!label || !text) {
            return;
        }

        let hoverTimer = null;

        const reset = () => {
            if (hoverTimer) {
                window.clearTimeout(hoverTimer);
                hoverTimer = null;
            }
            eventNode.classList.remove("is-marquee");
            eventNode.style.removeProperty("--marquee-shift");
        };

        const start = () => {
            const overflow = text.scrollWidth - label.clientWidth;

            if (overflow <= 6) {
                return;
            }

            if (hoverTimer) {
                window.clearTimeout(hoverTimer);
            }

            hoverTimer = window.setTimeout(() => {
                eventNode.style.setProperty("--marquee-shift", `${overflow}px`);
                eventNode.classList.add("is-marquee");
            }, 180);
        };

        eventNode.addEventListener("mouseenter", start);
        eventNode.addEventListener("focus", start, true);
        eventNode.addEventListener("mouseleave", reset);
        eventNode.addEventListener("blur", reset, true);
    });
};

const initEpisodeTimeline = (root) => {
    const preview = root.querySelector("[data-timeline-preview]");
    const strip = root.querySelector("[data-timeline-strip]");
    const endpoint = root.dataset.endpoint || "";
    const range = root.querySelector("[data-timeline-range]");
    const prevButton = root.querySelector('[data-timeline-nav="prev"]');
    const nextButton = root.querySelector('[data-timeline-nav="next"]');
    const todayButton = root.querySelector("[data-timeline-today]");

    if (!preview || !strip || !endpoint || !range || !prevButton || !nextButton || !todayButton) {
        return;
    }

    let currentOffset = Number.parseInt(root.dataset.offset || "-2", 10) || -2;
    let selectedId = root.querySelector("[data-timeline-event].is-active")?.dataset.id || "";

    const updateLink = (link, href) => {
        if (!link) {
            return;
        }

        if (href) {
            link.href = href;
            link.hidden = false;
        } else {
            link.hidden = true;
        }
    };

    const setPreview = (entry) => {
        if (!entry) {
            preview.classList.add("is-empty");
            preview.innerHTML = '<div class="empty-state empty-state--soft timeline-preview__empty" data-timeline-empty>Brak odcinków w tym zakresie. Zmień zakres przyciskami powyżej.</div>';
            return;
        }

        const canOpenSources = entry.status_key === "aired";
        const sourceLinks = canOpenSources
            ? [
                  entry.tpb_url
                      ? `<a class="button button--ghost" href="${escapeHtml(entry.tpb_url)}" data-timeline-tpb data-open-external target="_blank" rel="noreferrer noopener">TPB</a>`
                      : "",
                  entry.btdig_url
                      ? `<a class="button button--ghost" href="${escapeHtml(entry.btdig_url)}" data-timeline-btdig data-open-external target="_blank" rel="noreferrer noopener">BTDig</a>`
                      : "",
              ].join("")
            : "";

        preview.classList.remove("is-empty");
        preview.innerHTML = `
            <div class="timeline-preview__poster" data-timeline-poster>
                ${buildTimelinePoster(entry.title || "", entry.poster_url || "")}
            </div>
            <div class="timeline-preview__body">
                <div class="timeline-preview__meta">
                    <span class="pill ${entry.status_key === "aired" ? "pill--aired" : "pill--upcoming"}" data-timeline-status>${escapeHtml(entry.status || "")}</span>
                    <span data-timeline-when>${escapeHtml(entry.when || "")}</span>
                    <strong data-timeline-relative>${escapeHtml(entry.relative || "")}</strong>
                </div>
                <h3>
                    <a href="${escapeHtml(entry.show_url || "#")}" data-timeline-title>${escapeHtml(entry.title || "")}</a>
                </h3>
                <p data-timeline-episode>${escapeHtml([entry.episode_code, entry.episode_name].filter(Boolean).join(" · "))}</p>
                <div class="timeline-preview__actions">
                    <a class="button button--primary" href="${escapeHtml(entry.show_url || "#")}" data-timeline-show>Przejdź do serialu</a>
                    ${sourceLinks}
                </div>
                ${renderSeasonProgress(entry.season_progress || null)}
            </div>
        `;

    };

    const bindEvents = () => {
        strip.querySelectorAll("[data-timeline-event]").forEach((button) => {
            button.addEventListener("click", () => {
                selectedId = button.dataset.id || "";
                strip.querySelectorAll("[data-timeline-event]").forEach((item) => {
                    const active = item === button;
                    item.classList.toggle("is-active", active);
                    item.setAttribute("aria-pressed", active ? "true" : "false");
                });

                setPreview({
                    id: button.dataset.id || "",
                    title: button.dataset.title || "",
                    show_url: button.dataset.showUrl || "",
                    episode_code: button.dataset.episodeCode || "",
                    episode_name: button.dataset.episodeName || "",
                    when: button.dataset.when || "",
                    relative: button.dataset.relative || "",
                    status: button.dataset.status || "",
                    status_key: button.dataset.statusKey || "upcoming",
                    poster_url: button.dataset.posterUrl || "",
                    tpb_url: button.dataset.tpbUrl || "",
                    btdig_url: button.dataset.btdigUrl || "",
                    season_progress: JSON.parse(button.dataset.seasonProgress || "null"),
                });
            });
        });

        initTimelineLabelScroll(strip);
    };

    const renderTimeline = (timeline) => {
        currentOffset = Number.parseInt(timeline.start_offset ?? currentOffset, 10) || currentOffset;
        root.dataset.offset = String(currentOffset);
        range.textContent = timeline.window_label || "";
        prevButton.dataset.offset = String(timeline.previous_offset ?? currentOffset - 7);
        nextButton.dataset.offset = String(timeline.next_offset ?? currentOffset + 7);
        todayButton.dataset.offset = "-2";
        todayButton.hidden = currentOffset === -2;

        const selected = timeline.selected || null;
        selectedId = selected?.id || "";
        strip.innerHTML = (timeline.days || []).map((day) => renderTimelineDay(day, selectedId)).join("");
        setPreview(selected);
        bindEvents();
    };

    const loadTimeline = async (offset) => {
        if (root.dataset.loading === "true") {
            return;
        }

        root.dataset.loading = "true";
        preview.classList.add("is-loading");
        prevButton.disabled = true;
        nextButton.disabled = true;
        todayButton.disabled = true;

        try {
            const response = await fetch(`${endpoint}?format=json&offset=${encodeURIComponent(offset)}`, {
                headers: {
                    Accept: "application/json",
                },
                credentials: "same-origin",
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok || payload.status !== "ok" || !payload.timeline) {
                throw new Error(payload.message || "Nie udało się wczytać osi czasu.");
            }

            renderTimeline(payload.timeline);
        } catch (error) {
            window.alert(error instanceof Error ? error.message : "Nie udało się wczytać osi czasu.");
        } finally {
            root.dataset.loading = "false";
            preview.classList.remove("is-loading");
            prevButton.disabled = false;
            nextButton.disabled = false;
            todayButton.disabled = false;
        }
    };

    prevButton.addEventListener("click", () => {
        loadTimeline(Number.parseInt(prevButton.dataset.offset || String(currentOffset - 7), 10));
    });

    nextButton.addEventListener("click", () => {
        loadTimeline(Number.parseInt(nextButton.dataset.offset || String(currentOffset + 7), 10));
    });

    todayButton.addEventListener("click", () => {
        loadTimeline(-2);
    });

    preview.addEventListener("click", (event) => {
        const link = event.target.closest("[data-open-external]");

        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        const href = link.getAttribute("href") || "";

        if (!href) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        window.open(href, "_blank", "noopener,noreferrer");
    });

    bindEvents();
    todayButton.hidden = currentOffset === -2;
};

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-search-widget]").forEach(initSearchWidget);
    initCountdowns();
    document.querySelectorAll("[data-tabs]").forEach(initTabs);
    initAjaxTrackForms();
    document.querySelectorAll("[data-episode-timeline]").forEach(initEpisodeTimeline);
});
