(function () {
  "use strict";

  function mpeEventEnabled(key) {
    try {
      if (!window.mpePixelData || !window.mpePixelData.events) return true;
      return window.mpePixelData.events[key] !== false;
    } catch (e) {
      return true;
    }
  }

  function mpeLogEvent(eventName, params) {
    try {
      if (!window.mpePixelData || !window.mpePixelData.logEnabled) return;
      if (!window.mpePixelData.ajaxUrl || !window.mpePixelData.nonce) return;

      var form = new FormData();
      form.append("action", "mpe_pixel_event_log");
      form.append("nonce", window.mpePixelData.nonce);
      form.append("event", String(eventName || ""));
      form.append(
        "page",
        window.mpePixelData.page ? String(window.mpePixelData.page) : "",
      );
      form.append("url", window.location.href);
      if (params) form.append("params", JSON.stringify(params));

      if (navigator.sendBeacon) {
        navigator.sendBeacon(window.mpePixelData.ajaxUrl, form);
      } else {
        fetch(window.mpePixelData.ajaxUrl, {
          method: "POST",
          body: form,
          credentials: "same-origin",
        });
      }
    } catch (e) {
      // no-op
    }
  }

  function mpeFbqTrack(eventName, params, eventId) {
    try {
      // Always log first (helps debugging when fbq is blocked).
      mpeLogEvent(eventName, params);

      if (typeof window.fbq !== "function") return;

      if (eventId) {
        window.fbq("track", eventName, params || {}, {
          eventID: String(eventId),
        });
        return;
      }

      if (params) {
        window.fbq("track", eventName, params);
      } else {
        window.fbq("track", eventName);
      }
    } catch (e) {
      // no-op
    }
  }

  function mpeFireCooldown(key, cooldownMs, fn) {
    try {
      window.__mpeCooldown = window.__mpeCooldown || {};
      var now = Date.now();
      var last = window.__mpeCooldown[key] || 0;
      if (now - last < cooldownMs) return;
      window.__mpeCooldown[key] = now;
      fn();
    } catch (e) {
      fn();
    }
  }

  function mpeFireOnce(key, fn) {
    try {
      if (window.sessionStorage && sessionStorage.getItem(key) === "1") return;
      fn();
      if (window.sessionStorage) sessionStorage.setItem(key, "1");
    } catch (e) {
      window.__mpeFired = window.__mpeFired || {};
      if (window.__mpeFired[key]) return;
      fn();
      window.__mpeFired[key] = true;
    }
  }

  function mpeGetCurrency() {
    return window.mpePixelData && window.mpePixelData.currency
      ? window.mpePixelData.currency
      : undefined;
  }

  function mpeParseNumber(value) {
    var n = parseFloat(value);
    return isFinite(n) ? n : undefined;
  }

  function mpeClosestAttr(el, attr) {
    var node = el && el.closest ? el.closest("a,button,form") : null;
    if (!node) return null;
    return node.getAttribute(attr);
  }

  function mpeBuildAddToCartParams(btnEl) {
    var productId =
      (btnEl &&
        (btnEl.getAttribute("data-product_id") ||
          btnEl.getAttribute("value") ||
          btnEl.getAttribute("data-product"))) ||
      null;
    var qty = (btnEl && btnEl.getAttribute("data-quantity")) || null;
    var value =
      window.mpePixelData &&
      window.mpePixelData.product &&
      window.mpePixelData.product.value;
    var currency = mpeGetCurrency();

    var params = {};
    if (productId) params.content_ids = [String(productId)];
    if (productId)
      params.contents = [
        { id: String(productId), quantity: qty ? parseInt(qty, 10) : 1 },
      ];
    if (productId) params.content_type = "product";
    if (value != null) params.value = mpeParseNumber(value);
    if (currency) params.currency = currency;

    return Object.keys(params).length ? params : undefined;
  }

  function mpeIsAjaxAddToCart(btnEl) {
    return !!(
      btnEl &&
      btnEl.classList &&
      btnEl.classList.contains("ajax_add_to_cart")
    );
  }

  function firePageBasedEvents() {
    try {
      if (!window.mpePixelData || !window.mpePixelData.page) return;

      if (
        window.mpePixelData.page === "product" &&
        window.mpePixelData.product
      ) {
        if (!mpeEventEnabled("viewContent")) return;

        var params = {
          content_ids: [String(window.mpePixelData.product.id)],
          content_type: "product",
          value: window.mpePixelData.product.value,
          currency: window.mpePixelData.currency,
        };

        var eventId =
          window.mpePixelData.eventIds &&
          window.mpePixelData.eventIds.viewContent;
        mpeFireOnce("mpe_vc_once", function () {
          mpeFbqTrack("ViewContent", params, eventId);
        });
      }

      if (
        window.mpePixelData.page === "checkout" &&
        window.mpePixelData.checkout
      ) {
        if (!mpeEventEnabled("initiateCheckout")) return;

        var params = {
          value: window.mpePixelData.checkout.value,
          currency: window.mpePixelData.currency,
          num_items: window.mpePixelData.checkout.num_items,
        };

        var eventId =
          window.mpePixelData.eventIds &&
          window.mpePixelData.eventIds.initiateCheckout;
        mpeFireOnce("mpe_ic_once", function () {
          mpeFbqTrack("InitiateCheckout", params, eventId);
        });
      }

      if (
        window.mpePixelData.page === "purchase" &&
        window.mpePixelData.purchase
      ) {
        if (!mpeEventEnabled("purchase")) return;

        var params = {
          value: window.mpePixelData.purchase.value,
          currency: window.mpePixelData.purchase.currency,
          contents: window.mpePixelData.purchase.contents,
          content_type: window.mpePixelData.purchase.content_type,
          num_items: window.mpePixelData.purchase.num_items,
          order_id: window.mpePixelData.purchase.order_id,
        };

        var eventId = window.mpePixelData.purchase.event_id;
        mpeFireOnce(
          "mpe_purchase_" + String(window.mpePixelData.purchase.order_id || ""),
          function () {
            mpeFbqTrack("Purchase", params, eventId);
          },
        );
      }
    } catch (e) {
      // no-op
    }
  }

  function wireClickEvents() {
    // AddToCart (click capture). For Woo AJAX add-to-cart buttons, we avoid click fire to reduce duplicates.
    document.addEventListener(
      "click",
      function (e) {
        var target = e.target;
        if (!target || !target.closest) return;

        var btn = target.closest(
          "a.add_to_cart_button, button.add_to_cart_button, a.single_add_to_cart_button, button.single_add_to_cart_button",
        );
        if (!btn) return;

        if (mpeIsAjaxAddToCart(btn)) return;

        mpeFireCooldown("mpe_atc_click", 800, function () {
          if (!mpeEventEnabled("addToCart")) return;
          mpeFbqTrack("AddToCart", mpeBuildAddToCartParams(btn));
        });
      },
      true,
    );

    // AddToCart (WooCommerce AJAX success event)
    if (window.jQuery) {
      window
        .jQuery(document.body)
        .on("added_to_cart", function (event, fragments, cart_hash, $button) {
          try {
            var btn = $button && $button.length ? $button.get(0) : null;
            if (!btn) return;

            var params = mpeBuildAddToCartParams(btn);
            var productId = btn.getAttribute("data-product_id") || "";
            mpeFireCooldown(
              "mpe_atc_ajax_" + String(productId),
              1200,
              function () {
                if (!mpeEventEnabled("addToCart")) return;
                mpeFbqTrack("AddToCart", params);
              },
            );
          } catch (e) {
            // no-op
          }
        });
    }

    // AddToWishlist
    document.body.addEventListener("click", function (e) {
      var target = e.target;
      if (!target || !target.closest) return;

      var el = target.closest(".add_to_wishlist, .yith-wcwl-add-to-wishlist a");
      if (!el) return;

      if (!mpeEventEnabled("addToWishlist")) return;

      var productId =
        mpeClosestAttr(el, "data-product-id") ||
        mpeClosestAttr(el, "data-product_id") ||
        null;
      var currency = mpeGetCurrency();
      var params = {};
      if (productId) params.content_ids = [String(productId)];
      if (productId) params.content_type = "product";
      if (currency) params.currency = currency;

      mpeFbqTrack(
        "AddToWishlist",
        Object.keys(params).length ? params : undefined,
      );
    });

    // AddPaymentInfo (best-effort on checkout)
    if (window.mpePixelData && window.mpePixelData.page === "checkout") {
      var fireAddPaymentInfo = function () {
        if (!mpeEventEnabled("addPaymentInfo")) return;
        var params = {};
        if (window.mpePixelData.checkout) {
          if (window.mpePixelData.checkout.value != null)
            params.value = mpeParseNumber(window.mpePixelData.checkout.value);
          if (window.mpePixelData.checkout.num_items != null)
            params.num_items = parseInt(
              window.mpePixelData.checkout.num_items,
              10,
            );
        }
        var currency = mpeGetCurrency();
        if (currency) params.currency = currency;
        mpeFbqTrack(
          "AddPaymentInfo",
          Object.keys(params).length ? params : undefined,
        );
      };

      document.body.addEventListener("change", function (e) {
        var t = e.target;
        if (t && t.matches && t.matches('input[name="payment_method"]')) {
          mpeFireOnce("mpe_add_payment_info", fireAddPaymentInfo);
        }
      });

      document.body.addEventListener("click", function (e) {
        var t = e.target;
        if (
          t &&
          (t.id === "place_order" || (t.closest && t.closest("#place_order")))
        ) {
          mpeFireOnce("mpe_add_payment_info", fireAddPaymentInfo);
        }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    firePageBasedEvents();
    wireClickEvents();
  });
})();
