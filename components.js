/* ================================================================
   VAIBHAV ENTERPRISE — components.js
   Shared HTML components injected into every page at runtime.
   Loaded BEFORE script.js so DOM is ready for event listeners.
   ================================================================ */

const WA_NUMBER  = '919023227934';
const WA_DEFAULT = `https://wa.me/${WA_NUMBER}?text=Hi%2C%20I%20want%20to%20enquire%20about%20your%20bags`;

/* ── SVG Snippets (single source of truth) ── */
const SVG = {
  whatsapp: `<svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`,

  /* Solid glyph — social icons `fill` se render hote hain, stroke se nahi.
     Purana Feather-style outline (rect + circle + line) fill hone par ek
     bhara hua rounded square ban jata tha aur <line> to dikhta hi nahi tha. */
  facebook: `<svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>`,

  instagram: `<svg viewBox="0 0 24 24"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41-.56-.22-.96-.48-1.38-.9-.42-.42-.68-.82-.9-1.38-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16M12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.13 1.38C1.35 2.68.93 3.35.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.13.67.67 1.34 1.08 2.13 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.79-.3 1.46-.71 2.13-1.38.67-.67 1.08-1.34 1.38-2.13.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.3-.79-.71-1.46-1.38-2.13C21.32 1.35 20.65.93 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0z"/><path d="M12 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84zm0 10.16A4 4 0 1 1 16 12a4 4 0 0 1-4 4z"/><circle cx="18.41" cy="5.59" r="1.44"/></svg>`,

  location: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>`,

  phone: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.06 1.22 2 2 0 012 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.16 6.16l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>`,

  email: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>`,
};

/* ── Address + map link ──
   MAPS_URL Google ka official maps URL scheme use karta hai: desktop par
   browser me khulta hai, phone par seedha Google Maps app me.
   Exact pin chahiye ho (Google Business Profile wala) to bas is constant
   ko apne "Share" link se badal do — footer aur contact dono uthha lenge. */
const ADDRESS =
  'Plot no 2B, Basement, Kusum Park, Near Power House, Atakpardi, Ta Valsad - 396001';
const MAPS_URL =
  'https://www.google.com/maps/search/?api=1&query=' +
  encodeURIComponent('Vaibhav Enterprise, ' + ADDRESS);

/* ── Social profile URLs — badalna ho to sirf yahan badlo ── */
const INSTAGRAM_URL = 'https://www.instagram.com/vaibhaventerprise2902/';

/* TODO: Facebook page ka asli URL yahan daalo, jaise
   'https://www.facebook.com/vaibhaventerprise'.
   Tab tak icon dikhta to hai par click karne par kahin nahi jaata. */
const FACEBOOK_URL = '#';

/* ── Social links — single source of truth ──
   Footer aur Contact page dono yahi list use karte hain, taaki dono
   jagah alag-alag icons na ho jayein. */
const SOCIALS = [
  { key: 'whatsapp', label: 'WhatsApp', href: `https://wa.me/${WA_NUMBER}` },
  { key: 'instagram', label: 'Instagram', href: INSTAGRAM_URL },
  { key: 'facebook', label: 'Facebook', href: FACEBOOK_URL }
];

function socialButtons() {
  return SOCIALS.map(({ key, label, href }) => {
    // Placeholder href ('#') ko naye tab me kholne ka koi matlab nahi —
    // wo ek khaali tab khol deta. Sirf asli URLs par target lagao.
    const external = /^https?:/i.test(href);
    const attrs = external ? ' target="_blank" rel="noopener"' : '';
    return `<a href="${href}"${attrs} class="social-btn" title="${label}" aria-label="${label}">${SVG[key]}</a>`;
  }).join('\n            ');
}

/* ── Nav links config ── */
const NAV_LINKS = [
  { href: 'index.html',    label: 'Home' },
  { href: 'products.html', label: 'Products' },
  { href: 'about.html',    label: 'About Us' },
  { href: 'gallery.html',  label: 'Gallery' },
  { href: 'contact.html',  label: 'Contact' },
];

/* ── Determine active page ── */
function getActivePage() {
  const file = window.location.pathname.split('/').pop() || 'index.html';
  return file;
}

/* ================================================================
   NAVBAR
================================================================ */
function buildNavbar() {
  const placeholder = document.getElementById('navbar-placeholder');
  if (!placeholder) return;

  const active = getActivePage();

  const desktopLinks = NAV_LINKS.map(({ href, label }) =>
    `<li><a href="${href}"${active === href ? ' class="active"' : ''}>${label}</a></li>`
  ).join('');

  const mobileLinks = NAV_LINKS.map(({ href, label }) =>
    `<a href="${href}"${active === href ? ' class="active"' : ''} onclick="closeMobileMenu()">${label}</a>`
  ).join('');

  placeholder.outerHTML = `
    <nav id="mainNav">
      <a href="index.html" class="nav-logo">
        <img src="images/logo2.webp" class="nav-logo-img" alt="Vaibhav Enterprise" />
        <div class="nav-logo-text">
          <span class="brand">Vaibhav</span>
          <span class="tagline">Enterprise · Be Responsible</span>
        </div>
      </a>

      <ul class="nav-links">
        ${desktopLinks}
        <li><a href="contact.html" class="nav-cta">Get Quote</a></li>
      </ul>

      <a href="https://wa.me/${WA_NUMBER}" target="_blank" class="nav-wa" title="WhatsApp" aria-label="WhatsApp">
        ${SVG.whatsapp}
      </a>

      <button type="button" class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </nav>

    <div class="mobile-menu" id="mobileMenu">
      ${mobileLinks}
      <a href="contact.html" class="mob-cta" onclick="closeMobileMenu()">Get Quote</a>
    </div>
  `;
}

/* ================================================================
   PAGE CHROME — scroll progress bar + back-to-top
   (har page par inject hota hai, koi HTML change nahi chahiye)
================================================================ */
function buildChrome() {
  if (document.getElementById('scrollProgress')) return;

  const bar = document.createElement('div');
  bar.className = 'scroll-progress';
  bar.id = 'scrollProgress';

  const top = document.createElement('button');
  top.className = 'to-top';
  top.id = 'toTop';
  top.type = 'button';
  top.setAttribute('aria-label', 'Back to top');
  top.innerHTML = `<svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>`;

  document.body.appendChild(bar);
  document.body.appendChild(top);
}

/* ================================================================
   FOOTER
================================================================ */
function buildFooter() {
  const placeholder = document.getElementById('footer-placeholder');
  if (!placeholder) return;

  placeholder.outerHTML = `
    <footer>
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.html" class="footer-brand-wrap" aria-label="Vaibhav Enterprise — Home">
            <img src="images/logo2.webp" class="nav-logo-img" alt="" />
            <div>
              <div class="footer-brand-name">Vaibhav</div>
              <div class="footer-brand-tagline">ENTERPRISE</div>
            </div>
          </a>
          <p>Manufacturing high-quality, eco-friendly non-woven bags with full customization options. Be Responsible — Choose Vaibhav.</p>
          <div class="footer-socials">
            ${socialButtons()}
          </div>
        </div>

        <div class="fc">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="about.html">About Us</a></li>
            <li><a href="products.html">Products</a></li>
            <li><a href="gallery.html">Gallery</a></li>
            <li><a href="contact.html">Contact</a></li>
          </ul>
        </div>

        <div class="fc">
          <h4>Our Products</h4>
          <ul>
            <li><a href="products.html#d-cut">D-Cut Bags</a></li>
            <li><a href="products.html#w-cut">W-Cut Bags</a></li>
            <li><a href="products.html#loop">Loop Handle Bags</a></li>
            <li><a href="products.html#stitched">Stitched Bags</a></li>
            <li><a href="products.html#box">Box Bags</a></li>
          </ul>
        </div>

        <div class="fc fc-contact">
          <h4>Contact Us</h4>
          <div>${SVG.location} <a href="${MAPS_URL}" target="_blank" rel="noopener" class="map-link" title="Google Maps me kholo">${ADDRESS}</a></div>
          <div>${SVG.phone} <a href="tel:+919023227934">+91 90232 27934</a></div>
          <div>${SVG.email} <a href="mailto:vaibhaventerprise29@gmail.com">vaibhaventerprise29@gmail.com</a></div>
        </div>
      </div>

      <div class="footer-bottom">
        <span>© 2026 Vaibhav Enterprise. All Rights Reserved.</span>
      </div>
    </footer>
  `;
}

/* ================================================================
   MAP LINKS
   Jis bhi element par data-map hai, use MAPS_URL par point kar dete
   hain — URL sirf ek jagah (upar MAPS_URL) rehta hai.
================================================================ */
function buildMapLinks() {
  document.querySelectorAll('[data-map]').forEach((el) => {
    el.setAttribute('href', MAPS_URL);
    el.setAttribute('target', '_blank');
    el.setAttribute('rel', 'noopener');
    el.classList.add('map-link');
    if (!el.title) el.title = 'Google Maps me kholo';
  });
}

/* ================================================================
   CONTACT PAGE SOCIALS
   Footer jaisi hi list — pehle contact page par sirf WhatsApp tha.
================================================================ */
function buildContactSocials() {
  const placeholder = document.getElementById('contact-socials-placeholder');
  if (!placeholder) return;

  placeholder.outerHTML = `
    <div class="contact-socials">
      ${socialButtons()}
    </div>
  `;
}

/* ================================================================
   WHATSAPP FLOAT BUTTON
================================================================ */
function buildWaFloat() {
  const placeholder = document.getElementById('wa-float-placeholder');
  if (!placeholder) return;

  placeholder.outerHTML = `
    <div class="wa-float">
      <div class="wa-tooltip">Chat with us!</div>
      <a href="${WA_DEFAULT}" target="_blank" class="wa-float-btn" title="WhatsApp" aria-label="Chat with us on WhatsApp">
        ${SVG.whatsapp}
      </a>
    </div>
  `;
}

/* ================================================================
   AUTO POPUP + PEEKING TAB
================================================================ */
function buildAutoPopup() {
  const placeholder = document.getElementById('auto-popup-placeholder');
  if (!placeholder) return;

  placeholder.outerHTML = `
    <div id="peekingTab" class="peeking-tab hidden" onclick="openAutoModal()">
      Request Quote
    </div>

    <div class="modal-overlay" id="autoEnquiryModal">
      <div class="modal auto-modal-design">
        <button class="modal-close" onclick="closeAutoModal()">✕</button>
        <h3>Request a Free Quote</h3>
        <p class="modal-sub">Drop your details — best wholesale price guaranteed.</p>
        <form onsubmit="event.preventDefault(); submitAutoModal();">
          <div class="form-grp">
          <label for="autoName">Full Name</label>
          <input type="text" id="autoName" placeholder="Enter your name" required />
        </div>
        <div class="form-grp">
          <label for="autoPhone">Phone Number</label>
          <input type="tel" id="autoPhone" placeholder="+91 XXXXX XXXXX" required />
        </div>
        <div class="form-grp">
          <label for="autoReq">Bag Requirement</label>
          <input type="text" id="autoReq" placeholder="e.g. 10,000 W-Cut Bags" />
        </div>
          <button type="submit" class="form-submit btn-premium">
            Send Details via WhatsApp
          </button>
        </form>
      </div>
    </div>
  `;
}

/* ================================================================
   ENQUIRY MODAL (used on index.html & products.html)
================================================================ */
function buildEnquiryModal() {
  const placeholder = document.getElementById('enquiry-modal-placeholder');
  if (!placeholder) return;

  placeholder.outerHTML = `
    <div class="modal-overlay" id="enquiryModal">
      <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h3>Quick Enquiry</h3>
        <p class="modal-sub">for <strong id="modalProductLabel"></strong></p>
        <form onsubmit="event.preventDefault(); submitModal();">
          <div class="form-grp">
          <label for="mName">Your Name *</label>
          <input type="text" id="mName" placeholder="Full name" required />
        </div>
        <div class="form-grp">
          <label for="mPhone">Phone *</label>
          <input type="tel" id="mPhone" placeholder="+91 XXXXX XXXXX" required />
        </div>
        <div class="form-grp">
          <label for="mQty">Quantity</label>
          <select id="mQty">
            <option>10,000 – 25,000</option>
            <option>25,000 – 50,000</option>
            <option>50,000+</option>
            <option>Custom Qty</option>
          </select>
        </div>
        <div class="form-grp">
          <label for="mNote">Color / Size Note</label>
          <input type="text" id="mNote" placeholder="e.g. Green, A4 size, with logo" />
        </div>
          <button type="submit" class="form-submit">
            Send Enquiry →
          </button>
        </form>
      </div>
    </div>
  `;
}

/* ================================================================
   INIT — runs synchronously when script loads (before script.js)
================================================================ */
(function initComponents() {
  // Wait for DOM to be ready before injecting
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }

  function inject() {
    buildChrome();
    buildNavbar();
    buildFooter();
    buildContactSocials();
    buildMapLinks();
    buildWaFloat();
    buildAutoPopup();
    buildEnquiryModal();
  }
})();

