/**
 * Canonical Status Handler
 * Manages canonical URL comparison and status display in both Classic and Gutenberg editors
 */
(function ($) {
  "use strict";

  const CanonicalStatus = {
    init: function () {
      this.bindEvents();
      this.initializeStatusCheck();
      this.initializeGutenberg();
    },

    bindEvents: function () {
      $(document).ready(() => {
        this.initializeStatusCheck();
        this.setupAccordionListener();
      });

      if (this.isGutenberg()) {
        wp.data.subscribe(() => this.checkGutenbergCanonical());
      }
    },

    normalizeUrl: function (url) {
      if (!url) return "";
      let normalized = url
        .replace(/^https?:\/\//, "")
        .replace(/^www\./, "")
        .replace(/\/$/, "")
        .toLowerCase();

      try {
        normalized = decodeURIComponent(normalized);
      } catch (e) {
        console.error("Error decoding URL:", e);
      }
      return normalized.trim();
    },

    getStatusData: function (canonicalUrl, permalinkUrl) {
      if (!canonicalUrl) {
        return { text: "לא מוגדר", color: "#F44336" };
      }
      const normalizedCanonical = this.normalizeUrl(canonicalUrl);
      const normalizedPermalink = this.normalizeUrl(permalinkUrl);

      return normalizedCanonical === normalizedPermalink
        ? { text: "זהה ל-URL המקור", color: "#4CAF50" }
        : { text: "שונה מ-URL המקור", color: "#FFC107" };
    },

    createStatusElement: function (status) {
      return $(`
                <div class="canonical-status" style="display: inline-block; margin-bottom: 10px; margin-inline-start: 10px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; 
                           margin-block-end: -0.5px; background-color: ${status.color}; 
                           margin-inline-end: 2px; box-shadow: 0 2px 4px rgba(0,0,0,0.2), 
                           inset 0 2px 4px rgba(255,255,255,0.2);"></span>
                    <span><b>מצב שדה קנוניקל:</b> ${status.text}</span>
                </div>
            `);
    },

    setupAccordionListener: function () {
      const accordionButton = $("#collapsible-advanced-settings");
      if (accordionButton.length) {
        // Listen for clicks
        accordionButton.on("click", () => {
          setTimeout(
            () => this.checkCanonicalStatus(this.isTermEditPage(), this.isPostEditPage()),
            150
          );
        });

        // MutationObserver for attribute changes
        const observer = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.type === "attributes" && mutation.attributeName === "aria-expanded") {
              setTimeout(
                () => this.checkCanonicalStatus(this.isTermEditPage(), this.isPostEditPage()),
                150
              );
            }
          });
        });

        observer.observe(accordionButton[0], {
          attributes: true,
          attributeFilter: ["aria-expanded"],
        });
      }
    },

    ensureAccordionOpen: function (callback) {
      const accordionButton = $("#collapsible-advanced-settings");
      if (accordionButton.length) {
        if (accordionButton.attr("aria-expanded") !== "true") {
          accordionButton.click();
          setTimeout(callback, 150);
          return true;
        }
      }
      return false;
    },

    initializeStatusCheck: function () {
      const isTermEdit = this.isTermEditPage();
      const isPostEdit = this.isPostEditPage();

      if (!isTermEdit && !isPostEdit) return;

      if (
        !this.ensureAccordionOpen(() => {
          this.checkCanonicalStatus(isTermEdit, isPostEdit);
        })
      ) {
        this.checkCanonicalStatus(isTermEdit, isPostEdit);
      }

      this.attachCanonicalChangeListener();
    },

    isTermEditPage: function () {
      return (
        window.location.pathname.endsWith("term.php") &&
        new URLSearchParams(window.location.search).has("taxonomy") &&
        new URLSearchParams(window.location.search).has("tag_ID")
      );
    },

    isPostEditPage: function () {
      return (
        window.location.pathname.endsWith("post.php") &&
        new URLSearchParams(window.location.search).get("action") === "edit"
      );
    },

    checkCanonicalStatus: function (isTermEdit, isPostEdit) {
      if (
        this.ensureAccordionOpen(() => {
          this.performCanonicalCheck(isTermEdit, isPostEdit);
        })
      )
        return;

      this.performCanonicalCheck(isTermEdit, isPostEdit);
    },

    performCanonicalCheck: function (isTermEdit, isPostEdit) {
      const canonicalField = $("#yoast-canonical-metabox").first();
      if (!canonicalField.length) return;

      const canonicalUrl = canonicalField.val();
      let permalinkUrl = "";

      if (isPostEdit) {
        permalinkUrl = $("#sample-permalink a").attr("href");
      } else if (isTermEdit && window.canonicalStatusData) {
        permalinkUrl = window.canonicalStatusData.termPermalinkUrl;
      }

      const status = this.getStatusData(canonicalUrl, permalinkUrl);
      $(".canonical-status").remove();
      const statusElement = this.createStatusElement(status);

      if (isPostEdit) {
        $("#edit-slug-buttons").after(statusElement);
      } else {
        $("#slug").closest("td").prepend(statusElement);
      }
    },

    attachCanonicalChangeListener: function () {
      $(document).on("input", "#yoast-canonical-metabox", () => {
        const isTermEdit = this.isTermEditPage();
        const isPostEdit = this.isPostEditPage();
        this.checkCanonicalStatus(isTermEdit, isPostEdit);
      });
    },

    isGutenberg: function () {
      return typeof wp !== "undefined" && wp.plugins && wp.editPost && wp.element;
    },

    initializeGutenberg: function () {
      if (!this.isGutenberg()) return;

      // הוספת מעקב אחרי פתיחת הסיידבר
      this.setupSidebarObserver();

      // בדיקה ראשונית אם הסיידבר כבר פתוח
      this.checkAndInitGutenbergStatus();
    },

    setupSidebarObserver: function () {
      const sidebarObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (
            mutation.type === "childList" &&
            mutation.target.matches(".interface-interface-skeleton__sidebar")
          ) {
            this.checkAndInitGutenbergStatus();
          }
        });
      });

      const sidebar = document.querySelector(".interface-interface-skeleton__sidebar");
      if (sidebar) {
        sidebarObserver.observe(sidebar, {
          childList: true,
          subtree: true,
        });
      }
    },

    checkAndInitGutenbergStatus: function () {
      const urlRow = document.querySelector(".editor-post-url__panel-dropdown");
      if (!urlRow) return;

      // מחיקת סטטוס קיים אם יש
      const existingStatus = document.querySelector(".canonical-status-gutenberg");
      if (existingStatus) {
        existingStatus.remove();
      }

      // יצירת אלמנט הסטטוס
      const statusElement = this.createGutenbergStatusElement();

      // מציאת ההורה המתאים (הrow של ה-URL) והוספת הסטטוס אחריו
      const parentRow = urlRow.closest(".editor-post-panel__row");
      if (parentRow) {
        const newRow = document.createElement("div");
        newRow.className =
          "components-flex components-h-stack editor-post-panel__row css-13b06dz e19lxcc00";
        newRow.appendChild(statusElement);
        parentRow.after(newRow);
      }

      // עדכון הסטטוס הראשוני
      this.checkGutenbergCanonical();
    },

    createGutenbergStatusElement: function () {
      const statusDiv = document.createElement("div");
      statusDiv.className = "canonical-status-gutenberg";
      statusDiv.style.cssText = "display: flex; align-items: center; padding: 8px 0;";

      const indicator = document.createElement("span");
      indicator.className = "status-indicator";
      indicator.style.cssText = `
                display: inline-block;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background-color: #F44336;
                margin-right: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2), inset 0 2px 4px rgba(255,255,255,0.2)
            `;

      const text = document.createElement("span");
      text.className = "status-text";
      text.textContent = "מצב שדה קנוניקל: לא מוגדר";

      statusDiv.appendChild(indicator);
      statusDiv.appendChild(text);

      return statusDiv;
    },

    checkGutenbergCanonical: function () {
      if (!this.isGutenberg() || !wp.data.select("core/editor")) return;

      // קבלת ה-permalink מ-WordPress
      const permalink = wp.data.select("core/editor").getPermalink();
      let canonicalUrl = this.getGutenbergCanonicalUrl();

      const status = this.getStatusData(canonicalUrl, permalink);
      this.updateGutenbergStatus(status);
    },

    getGutenbergCanonicalUrl: function () {
      let canonicalUrl = "";

      // ניסיון לקבל את הערך מ-Yoast SEO
      const yoastSelect = wp.data.select("yoast-seo/editor");
      if (yoastSelect) {
        canonicalUrl = yoastSelect.getMetaValue
          ? yoastSelect.getMetaValue("canonical")
          : yoastSelect.getCanonical
          ? yoastSelect.getCanonical()
          : "";
      }

      return canonicalUrl;
    },

    updateGutenbergStatus: function (status) {
      const statusContainer = document.querySelector(".canonical-status-gutenberg");
      if (!statusContainer) return;

      const indicator = statusContainer.querySelector(".status-indicator");
      const text = statusContainer.querySelector(".status-text");

      if (indicator && text) {
        indicator.style.backgroundColor = status.color;
        text.textContent = `מצב שדה קנוניקל: ${status.text}`;
      }
    },
  };

  // Initialize when document is ready
  $(document).ready(() => CanonicalStatus.init());
})(jQuery);
