/*!
 * Universal-PWA Translator — drop-in translation widget.
 *
 * Works on any site: a plain HTML page, a WordPress theme, or a client-side
 * rendered app (Lovable/React/Vue). Zero build step, zero dependencies.
 *
 * Usage:
 *   <link rel="stylesheet" href="https://.../widget/translator.css">
 *   <script
 *     src="https://.../widget/translator.js"
 *     data-supabase-url="https://YOUR-PROJECT.supabase.co"
 *     data-supabase-anon-key="YOUR-ANON-KEY"
 *     data-project-slug="zuri-safari-navigator"
 *     data-position="bottom-right"
 *     data-auto-scan="true"
 *   ></script>
 *
 * Public API (available after the "upwa:ready" event fires on window):
 *   window.UniversalPWA.setLanguage(code)
 *   window.UniversalPWA.getCurrentLanguage()
 *   window.UniversalPWA.getLanguages()
 *   window.UniversalPWA.translatePage()   // re-run translation, e.g. after
 *                                          // manually injecting new content
 */
(function () {
  "use strict";

  var CURRENT_SCRIPT =
    document.currentScript ||
    (function () {
      var scripts = document.getElementsByTagName("script");
      for (var i = scripts.length - 1; i >= 0; i--) {
        if (/translator\.js/.test(scripts[i].src)) return scripts[i];
      }
      return null;
    })();

  function attr(name, fallback) {
    if (!CURRENT_SCRIPT) return fallback;
    var v = CURRENT_SCRIPT.getAttribute(name);
    return v === null ? fallback : v;
  }

  var config = {
    supabaseUrl: (attr("data-supabase-url", "") || "").replace(/\/$/, ""),
    supabaseAnonKey: attr("data-supabase-anon-key", ""),
    projectSlug: attr("data-project-slug", ""),
    position: attr("data-position", "bottom-right"),
    autoScan: attr("data-auto-scan", "true") === "true",
    logEndpoint: attr("data-log-endpoint", ""), // optional log-language-stat function URL
  };

  if (!config.supabaseUrl || !config.supabaseAnonKey || !config.projectSlug) {
    console.error(
      "[UniversalPWA] Missing required data-supabase-url / data-supabase-anon-key / data-project-slug attributes on the translator.js <script> tag.",
    );
    return;
  }

  var STORAGE_KEY = "upwa_lang_" + config.projectSlug;
  var IGNORE_TAGS = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEXTAREA: 1, INPUT: 1, CODE: 1, PRE: 1 };

  var state = {
    project: null,
    languages: [],
    currentCode: null,
    // sourceText (trimmed) -> translatedText, for the currently active language
    translationMap: {},
    // key -> translatedText, for elements using explicit data-i18n="key"
    keyMap: {},
    originalText: new WeakMap(), // node -> its untranslated text, so switching
                                  // back to the source language is instant
  };

  function restGet(path) {
    return fetch(config.supabaseUrl + "/rest/v1/" + path, {
      headers: {
        apikey: config.supabaseAnonKey,
        Authorization: "Bearer " + config.supabaseAnonKey,
      },
    }).then(function (res) {
      if (!res.ok) throw new Error("Supabase request failed: " + res.status);
      return res.json();
    });
  }

  function loadProjectAndLanguages() {
    return restGet(
      "projects?slug=eq." + encodeURIComponent(config.projectSlug) + "&select=id,default_locale",
    )
      .then(function (rows) {
        if (!rows || !rows.length) throw new Error("Unknown project slug: " + config.projectSlug);
        state.project = rows[0];
        return restGet(
          "languages?project_id=eq." + state.project.id + "&enabled=eq.true&select=*&order=is_default.desc,name.asc",
        );
      })
      .then(function (langs) {
        state.languages = langs;
      });
  }

  function loadTranslations(languageId) {
    return restGet(
      "translation_keys?project_id=eq." +
        state.project.id +
        "&select=key,source_text,translations!inner(translated_text)&translations.language_id=eq." +
        languageId,
    ).then(function (rows) {
      var textMap = {};
      var keyMap = {};
      rows.forEach(function (row) {
        var translated = row.translations && row.translations[0] && row.translations[0].translated_text;
        if (!translated) return;
        textMap[normalize(row.source_text)] = translated;
        keyMap[row.key] = translated;
      });
      state.translationMap = textMap;
      state.keyMap = keyMap;
    });
  }

  function normalize(text) {
    return (text || "").replace(/\s+/g, " ").trim();
  }

  function languageByCode(code) {
    for (var i = 0; i < state.languages.length; i++) {
      if (state.languages[i].code === code) return state.languages[i];
    }
    return null;
  }

  function detectInitialLanguage() {
    var stored = null;
    try {
      stored = localStorage.getItem(STORAGE_KEY);
    } catch (e) {}
    if (stored && languageByCode(stored)) return stored;

    var browserLangs = navigator.languages || [navigator.language];
    for (var i = 0; i < browserLangs.length; i++) {
      var short = (browserLangs[i] || "").split("-")[0];
      if (languageByCode(short)) return short;
    }
    return state.project.default_locale;
  }

  // --- DOM translation --------------------------------------------------

  function walkTextNodes(root, cb) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var parent = node.parentElement;
        if (!parent) return NodeFilter.FILTER_REJECT;
        if (IGNORE_TAGS[parent.tagName]) return NodeFilter.FILTER_REJECT;
        if (parent.closest("[data-i18n-ignore]")) return NodeFilter.FILTER_REJECT;
        if (parent.closest(".upwa-switcher")) return NodeFilter.FILTER_REJECT;
        if (!normalize(node.nodeValue)) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      },
    });
    var node;
    while ((node = walker.nextNode())) cb(node);
  }

  function applyTranslations(root) {
    root = root || document.body;
    var isSource = state.currentCode === state.project.default_locale;

    // 1) Explicit data-i18n="key" elements — most reliable, works regardless
    //    of auto-scan setting.
    root.querySelectorAll("[data-i18n]").forEach(function (el) {
      var key = el.getAttribute("data-i18n");
      if (!state.originalText.has(el)) state.originalText.set(el, el.textContent);
      if (isSource) {
        el.textContent = state.originalText.get(el);
      } else if (state.keyMap[key]) {
        el.textContent = state.keyMap[key];
      }
    });

    // 2) Best-effort auto-scan by exact text match, for sites that can't add
    //    data-i18n markup (e.g. a generated Lovable/React build).
    if (config.autoScan) {
      walkTextNodes(root, function (node) {
        if (node.parentElement.hasAttribute("data-i18n")) return; // already handled above
        if (!state.originalText.has(node)) state.originalText.set(node, node.nodeValue);

        if (isSource) {
          node.nodeValue = state.originalText.get(node);
          return;
        }
        var original = state.originalText.get(node);
        var translated = state.translationMap[normalize(original)];
        if (translated) node.nodeValue = translated;
      });
    }
  }

  var observer = null;
  function watchForChanges() {
    if (observer) observer.disconnect();
    observer = new MutationObserver(
      debounce(function (mutations) {
        mutations.forEach(function (m) {
          m.addedNodes.forEach(function (n) {
            if (n.nodeType === 1 || n.nodeType === 3) {
              applyTranslations(n.nodeType === 1 ? n : n.parentElement || document.body);
            }
          });
        });
      }, 150),
    );
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
  }

  function debounce(fn, wait) {
    var t;
    return function () {
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(null, args);
      }, wait);
    };
  }

  function applyDocumentDirection(code) {
    var lang = languageByCode(code);
    document.documentElement.lang = code;
    document.documentElement.dir = lang && lang.rtl ? "rtl" : "ltr";
  }

  function logSelection(code) {
    if (!config.logEndpoint) return;
    fetch(config.logEndpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        project_slug: config.projectSlug,
        language_code: code,
        page_url: location.href,
      }),
      keepalive: true,
    }).catch(function () {});
  }

  // --- Switcher UI --------------------------------------------------------

  var switcherEl = null;

  function buildSwitcher() {
    switcherEl = document.createElement("div");
    switcherEl.className = "upwa-switcher upwa-switcher--" + config.position;

    var current = languageByCode(state.currentCode);

    var button = document.createElement("button");
    button.type = "button";
    button.className = "upwa-switcher-button";
    button.setAttribute("aria-haspopup", "listbox");
    button.innerHTML =
      '<span class="upwa-switcher-flag">' +
      (current && current.flag_emoji ? current.flag_emoji : "🌐") +
      '</span><span class="upwa-switcher-code">' +
      state.currentCode +
      '</span><span class="upwa-switcher-caret">▾</span>';
    button.addEventListener("click", function (e) {
      e.stopPropagation();
      switcherEl.classList.toggle("upwa-open");
    });

    var menu = document.createElement("div");
    menu.className = "upwa-switcher-menu";
    menu.setAttribute("role", "listbox");

    state.languages.forEach(function (lang) {
      var opt = document.createElement("button");
      opt.type = "button";
      opt.className = "upwa-switcher-option";
      opt.setAttribute("role", "option");
      opt.setAttribute("aria-current", String(lang.code === state.currentCode));
      opt.innerHTML =
        '<span class="upwa-switcher-flag">' +
        (lang.flag_emoji || "") +
        "</span><span>" +
        escapeHtml(lang.name) +
        '</span><span class="upwa-switcher-native">' +
        escapeHtml(lang.native_name) +
        "</span>";
      opt.addEventListener("click", function () {
        switcherEl.classList.remove("upwa-open");
        setLanguage(lang.code);
      });
      menu.appendChild(opt);
    });

    switcherEl.appendChild(button);
    switcherEl.appendChild(menu);
    document.body.appendChild(switcherEl);

    document.addEventListener("click", function () {
      switcherEl.classList.remove("upwa-open");
    });
  }

  function refreshSwitcher() {
    if (switcherEl) switcherEl.remove();
    buildSwitcher();
  }

  function escapeHtml(s) {
    var div = document.createElement("div");
    div.textContent = s;
    return div.innerHTML;
  }

  // --- Public API -----------------------------------------------------------

  function setLanguage(code) {
    var lang = languageByCode(code);
    if (!lang) {
      console.warn("[UniversalPWA] Unknown language code:", code);
      return Promise.resolve();
    }
    state.currentCode = code;
    try {
      localStorage.setItem(STORAGE_KEY, code);
    } catch (e) {}
    applyDocumentDirection(code);
    logSelection(code);

    var work =
      code === state.project.default_locale
        ? Promise.resolve()
        : loadTranslations(lang.id);

    return work.then(function () {
      applyTranslations(document.body);
      refreshSwitcher();
      watchForChanges();
      window.dispatchEvent(new CustomEvent("upwa:languagechange", { detail: { code: code } }));
    });
  }

  function init() {
    loadProjectAndLanguages()
      .then(function () {
        state.currentCode = detectInitialLanguage();
        return state.currentCode === state.project.default_locale
          ? Promise.resolve()
          : loadTranslations(languageByCode(state.currentCode).id);
      })
      .then(function () {
        applyDocumentDirection(state.currentCode);
        applyTranslations(document.body);
        buildSwitcher();
        watchForChanges();

        window.UniversalPWA = {
          setLanguage: setLanguage,
          getCurrentLanguage: function () {
            return state.currentCode;
          },
          getLanguages: function () {
            return state.languages.slice();
          },
          translatePage: function () {
            applyTranslations(document.body);
          },
        };
        window.dispatchEvent(new CustomEvent("upwa:ready", { detail: { code: state.currentCode } }));
      })
      .catch(function (err) {
        console.error("[UniversalPWA] Failed to initialize translator widget:", err);
      });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
