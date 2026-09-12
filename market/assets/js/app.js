/* MARKET — storefront behaviour. No framework, no build step. */
(function () {
  'use strict';

  var CSRF = (window.MARKET && window.MARKET.csrf) || '';

  // ------------------------------------------------------------- helpers
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function toast(message, type) {
    var host = $('#toasts');
    if (!host) { return; }
    var el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' is-error' : '');
    el.textContent = message;
    host.appendChild(el);
    setTimeout(function () {
      el.classList.add('is-out');
      setTimeout(function () { el.remove(); }, 250);
    }, 2600);
  }
  window.toast = toast;

  function post(url, data) {
    var body = new URLSearchParams(data || {});
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': CSRF
      },
      body: body,
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Something went wrong.' }; }); });
  }

  function setCartCount(n) {
    $$('[data-cart-count]').forEach(function (el) {
      el.textContent = n;
      el.classList.toggle('is-zero', Number(n) === 0);
    });
  }

  // --------------------------------------------------------- add to cart
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-add-to-cart]');
    if (!btn) { return; }
    ev.preventDefault();

    var id = btn.getAttribute('data-add-to-cart');
    var qty = btn.getAttribute('data-qty') || 1;
    btn.disabled = true;

    post('api/cart.php', { action: 'add', product_id: id, qty: qty }).then(function (res) {
      btn.disabled = false;
      if (!res.ok) { toast(res.message || 'Could not add that item.', 'error'); return; }

      setCartCount(res.cart_count);
      toast(res.message || 'Added to basket');

      var label = btn.textContent;
      btn.textContent = '✓ Added';
      btn.classList.add('is-added');
      setTimeout(function () { btn.textContent = label; btn.classList.remove('is-added'); }, 1400);
    }).catch(function () {
      btn.disabled = false;
      toast('Network error — please try again.', 'error');
    });
  });

  // ---------------------------------------------------------- wishlist
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-wishlist]');
    if (!btn) { return; }
    ev.preventDefault();

    if (window.MARKET && !window.MARKET.loggedIn) {
      window.location.href = 'login.php?next=' + encodeURIComponent(location.pathname + location.search);
      return;
    }

    post('api/wishlist.php', { product_id: btn.getAttribute('data-wishlist') }).then(function (res) {
      if (!res.ok) { toast(res.message || 'Could not update favourites.', 'error'); return; }
      btn.classList.toggle('is-on', res.in_list);
      btn.setAttribute('aria-pressed', res.in_list ? 'true' : 'false');
      $$('[data-wish-count]').forEach(function (el) { el.textContent = res.wish_count; });
      toast(res.message);
    });
  });

  // ------------------------------------------------------- qty steppers
  // Cart page steppers submit to the server; PDP steppers are local only.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-qty-step]');
    if (!btn) { return; }
    ev.preventDefault();

    var box = btn.closest('.qty');
    var out = $('output', box);
    var step = Number(btn.getAttribute('data-qty-step'));
    var next = Math.max(0, Number(out.textContent) + step);
    var max = Number(box.getAttribute('data-max') || 10);
    if (next > max) { toast('Only ' + max + ' available.', 'error'); return; }

    var productId = box.getAttribute('data-product-id');
    if (!productId) {                                   // local stepper (product page)
      out.textContent = Math.max(1, next);
      var add = $('[data-add-to-cart]', box.closest('.pdp-actions') || document);
      if (add) { add.setAttribute('data-qty', out.textContent); }
      return;
    }

    box.classList.add('is-busy');
    post('api/cart.php', { action: 'set', product_id: productId, qty: next }).then(function (res) {
      box.classList.remove('is-busy');
      if (!res.ok) { toast(res.message || 'Could not update the basket.', 'error'); return; }
      window.location.reload();                        // totals, coupons and offers all move together
    });
  });

  // ------------------------------------------------------ search suggest
  var searchInput = $('[data-suggest]');
  var suggestBox = $('#suggest-box');
  if (searchInput && suggestBox) {
    var timer = null;
    var lastTerm = '';

    function closeSuggest() { suggestBox.hidden = true; suggestBox.innerHTML = ''; }

    function render(data, term) {
      var html = '';
      if (data.categories && data.categories.length) {
        html += '<div class="suggest-group"><div class="suggest-label">Categories</div>';
        data.categories.forEach(function (c) {
          html += '<a href="products.php?category=' + encodeURIComponent(c.slug) + '">'
                + '<span class="suggest-thumb" style="background:#eef3f0">' + c.emoji + '</span>'
                + '<span class="suggest-main"><strong>' + c.name + '</strong><small>Browse category</small></span></a>';
        });
        html += '</div>';
      }
      if (data.products && data.products.length) {
        html += '<div class="suggest-group"><div class="suggest-label">Products</div>';
        data.products.forEach(function (p) {
          html += '<a href="product.php?slug=' + encodeURIComponent(p.slug) + '">'
                + '<span class="suggest-thumb" style="background:' + p.tint + '">' + p.emoji + '</span>'
                + '<span class="suggest-main"><strong>' + p.name + '</strong><small>' + p.unit + ' · ' + p.category_name + '</small></span>'
                + '<span class="suggest-price">₹' + p.price + '</span></a>';
        });
        html += '</div>';
      }
      if (!html) {
        html = '<div class="suggest-empty">No matches for “' + term.replace(/[<>&]/g, '') + '”. Try a shorter word.</div>';
      } else {
        html += '<div class="suggest-group"><a href="products.php?q=' + encodeURIComponent(term) + '">'
              + '<span class="suggest-thumb" style="background:#dff3e5">🔎</span>'
              + '<span class="suggest-main"><strong>See all results for “' + term.replace(/[<>&]/g, '') + '”</strong></span></a></div>';
      }
      suggestBox.innerHTML = html;
      suggestBox.hidden = false;
    }

    searchInput.addEventListener('input', function () {
      var term = searchInput.value.trim();
      clearTimeout(timer);
      if (term.length < 2) { closeSuggest(); return; }
      timer = setTimeout(function () {
        if (term === lastTerm) { return; }
        lastTerm = term;
        fetch('api/suggest.php?q=' + encodeURIComponent(term), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) { render(data, term); })
          .catch(closeSuggest);
      }, 180);
    });

    searchInput.addEventListener('keydown', function (ev) {
      var links = $$('a', suggestBox);
      if (!links.length || suggestBox.hidden) { return; }
      var current = links.indexOf($('a.is-active', suggestBox));
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (current >= 0) { links[current].classList.remove('is-active'); }
        var next = ev.key === 'ArrowDown' ? (current + 1) % links.length : (current <= 0 ? links.length - 1 : current - 1);
        links[next].classList.add('is-active');
      } else if (ev.key === 'Enter' && current >= 0) {
        ev.preventDefault();
        window.location.href = links[current].href;
      } else if (ev.key === 'Escape') {
        closeSuggest();
      }
    });

    document.addEventListener('click', function (ev) {
      if (!ev.target.closest('.searchbar')) { closeSuggest(); }
    });
  }

  // ------------------------------------------------- drawer + menus
  document.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-drawer-open]')) { $('#drawer').hidden = false; document.body.style.overflow = 'hidden'; }
    if (ev.target.closest('[data-drawer-close]')) { $('#drawer').hidden = true; document.body.style.overflow = ''; }

    var toggle = ev.target.closest('[data-menu-toggle]');
    $$('[data-menu-panel]').forEach(function (panel) {
      var owner = panel.parentElement.querySelector('[data-menu-toggle]');
      if (toggle && owner === toggle) {
        panel.hidden = !panel.hidden;
        owner.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
      } else if (!ev.target.closest('[data-menu-panel]')) {
        panel.hidden = true;
        if (owner) { owner.setAttribute('aria-expanded', 'false'); }
      }
    });
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') { return; }
    var drawer = $('#drawer');
    if (drawer && !drawer.hidden) { drawer.hidden = true; document.body.style.overflow = ''; }
    $$('[data-menu-panel]').forEach(function (p) { p.hidden = true; });
  });

  // --------------------------------------------------------- hero slider
  var slides = $$('.hero-slide');
  if (slides.length > 1) {
    var dots = $$('.hero-dots button');
    var index = 0;
    var auto = null;

    function show(i) {
      index = (i + slides.length) % slides.length;
      slides.forEach(function (s, n) { s.classList.toggle('is-on', n === index); });
      dots.forEach(function (d, n) { d.classList.toggle('is-on', n === index); });
    }
    function play() { auto = setInterval(function () { show(index + 1); }, 5200); }
    function stop() { clearInterval(auto); }

    dots.forEach(function (d, n) { d.addEventListener('click', function () { stop(); show(n); play(); }); });
    var stage = $('.hero-slides');
    stage.addEventListener('mouseenter', stop);
    stage.addEventListener('mouseleave', play);
    show(0); play();
  }

  // ---------------------------------------------------------------- tabs
  $$('[data-tabs]').forEach(function (group) {
    group.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-tab]');
      if (!btn) { return; }
      var name = btn.getAttribute('data-tab');
      $$('[data-tab]', group).forEach(function (b) { b.classList.toggle('is-on', b === btn); });
      $$('[data-tab-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-tab-panel') !== name;
      });
    });
  });

  // A link (or redirect) to #reviews must open the reviews tab, not scroll to
  // a hidden panel.
  function openTab(name) {
    var btn = document.querySelector('[data-tab="' + name + '"]');
    if (btn) { btn.click(); }
  }
  function syncTabToHash() {
    if (location.hash === '#reviews') {
      openTab('revs');
      var target = $('#reviews');
      if (target) { target.scrollIntoView({ block: 'start' }); }
    }
  }
  syncTabToHash();
  window.addEventListener('hashchange', syncTabToHash);
  document.addEventListener('click', function (ev) {
    var link = ev.target.closest('a[href="#reviews"]');
    if (link) { openTab('revs'); }
  });

  // ------------------------------------------------------ filter drawer
  var filterToggle = $('[data-filter-toggle]');
  if (filterToggle) {
    filterToggle.addEventListener('click', function () { $('.filters').classList.toggle('is-open'); });
  }

  // Checkbox filters submit their form as soon as they change.
  $$('[data-autosubmit]').forEach(function (input) {
    input.addEventListener('change', function () { input.form.submit(); });
  });

  // ------------------------------------------------------- product page
  $$('[data-pdp-thumb]').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      $$('[data-pdp-thumb]').forEach(function (t) { t.classList.remove('is-on'); });
      thumb.classList.add('is-on');
      var stage = $('.pdp-stage');
      stage.style.background = thumb.getAttribute('data-tint');
      $('.pdp-emoji', stage).textContent = thumb.getAttribute('data-emoji');
    });
  });

  var pinForm = $('[data-pincode]');
  if (pinForm) {
    pinForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var value = $('input', pinForm).value.trim();
      var out = $('.pincode-result', pinForm.parentElement);
      if (!/^\d{6}$/.test(value)) {
        out.textContent = 'Enter a valid 6-digit pincode.';
        out.style.color = 'var(--red)';
        return;
      }
      // Demo rule: even pincodes get same-day slots, odd ones get next-day.
      var sameDay = Number(value.slice(-1)) % 2 === 0;
      out.textContent = sameDay
        ? '✓ Delivery available — today, ' + pinForm.getAttribute('data-slot')
        : '✓ Delivery available — tomorrow, 7 AM – 10 AM';
      out.style.color = 'var(--green-700)';
    });
  }

  // --------------------------------------------------- payment selector
  $$('[data-pay-option]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      $$('.pick').forEach(function (p) { p.classList.toggle('is-on', p.contains(radio) && radio.checked); });
      $$('[data-pay-fields]').forEach(function (box) {
        box.hidden = box.getAttribute('data-pay-fields') !== radio.value;
      });
    });
  });
  $$('[data-address-option]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      $$('[data-address-pick]').forEach(function (p) { p.classList.toggle('is-on', p.contains(radio) && radio.checked); });
    });
  });

  // Card number / expiry formatting on the demo gateway.
  var cardInput = $('[data-card-number]');
  if (cardInput) {
    cardInput.addEventListener('input', function () {
      var digits = cardInput.value.replace(/\D/g, '').slice(0, 16);
      cardInput.value = digits.replace(/(.{4})/g, '$1 ').trim();
    });
  }
  var expiryInput = $('[data-card-expiry]');
  if (expiryInput) {
    expiryInput.addEventListener('input', function () {
      var digits = expiryInput.value.replace(/\D/g, '').slice(0, 4);
      expiryInput.value = digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits;
    });
  }

  // ------------------------------------------------------- copy a coupon
  document.addEventListener('click', function (ev) {
    var code = ev.target.closest('[data-copy]');
    if (!code) { return; }
    var text = code.getAttribute('data-copy');
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () { toast('Coupon ' + text + ' copied'); });
    } else {
      toast('Coupon code: ' + text);
    }
  });

  // Guard against a double-submitted order.
  $$('form[data-once]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = $('button[type=submit]', form);
      if (btn) { btn.disabled = true; btn.textContent = btn.getAttribute('data-busy') || 'Please wait…'; }
    });
  });
})();
