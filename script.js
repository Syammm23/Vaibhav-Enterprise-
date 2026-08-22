/* ================================================================
   VAIBHAV ENTERPRISE — script.js
   Requires: components.js loaded first (provides WA_NUMBER, SVG, etc.)
   ================================================================ */

/* —— 01. Navbar & Mobile Menu —— */
function initNav() {
  const nav = document.getElementById('mainNav');
  if (nav) {
    window.addEventListener('scroll', () =>
      nav.classList.toggle('scrolled', window.scrollY > 50)
    );
  }
}

function toggleMenu() {
  document.getElementById('mobileMenu')?.classList.toggle('open');
  document.getElementById('hamburger')?.classList.toggle('open');
}

function closeMobileMenu() {
  document.getElementById('mobileMenu')?.classList.remove('open');
  document.getElementById('hamburger')?.classList.remove('open');
}

/* —— 02. Scroll Reveal & Stats Counter —— */
/* Ek hi row/grid ke items ko thoda-thoda delay dete hain taaki
   sab ek saath na aayein — cascade effect banta hai. */
function initReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;

  const obs = new IntersectionObserver(
    (entries) => {
      // same batch ke items ko stagger delay
      const shown = entries.filter((e) => e.isIntersecting);
      shown.forEach((e, i) => {
        e.target.style.transitionDelay = `${Math.min(i, 6) * 80}ms`;
        e.target.classList.add('active');
        obs.unobserve(e.target);
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
  );
  els.forEach((el) => obs.observe(el));
}

function initCounter() {
  const nums = document.querySelectorAll('[data-target]');
  if (!nums.length) return;

  // easeOutExpo — end pe smoothly settle hota hai, linear se zyada premium lagta hai
  const ease = (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t));

  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const target = +el.dataset.target;
        const suffix = el.dataset.suffix ?? '+';
        const dur = 1800;
        const start = performance.now();

        const step = (now) => {
          const p = Math.min((now - start) / dur, 1);
          const val = Math.floor(target * ease(p));
          el.textContent = val.toLocaleString('en-IN') + suffix;
          if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
        obs.unobserve(el);
      });
    },
    { threshold: 0.5 }
  );
  nums.forEach((el) => obs.observe(el));
}

/* —— 03. Product Color Swap —— */
function changeBagColor(imgId, newImageSrc) {
  const imgElement = document.getElementById(imgId);
  if (!imgElement) return;
  if (imgElement.getAttribute('src') === newImageSrc) return;

  // cross-fade: purani image fade out → nayi load hone par fade in
  imgElement.classList.add('swapping');
  const pre = new Image();
  pre.onload = pre.onerror = () => {
    imgElement.src = newImageSrc;
    requestAnimationFrame(() => imgElement.classList.remove('swapping'));
  };
  pre.src = newImageSrc;
}

/* Selected color dot ko highlight karna (event delegation — HTML change nahi chahiye) */
function initColorDots() {
  document.querySelectorAll('.color-dots, .pd-dots').forEach((group) => {
    const dots = group.querySelectorAll('.color-dot, .cdot');
    if (!dots.length) return;
    dots[0].classList.add('is-active');
    group.addEventListener('click', (e) => {
      const dot = e.target.closest('.color-dot, .cdot');
      if (!dot || !group.contains(dot)) return;
      dots.forEach((d) => d.classList.remove('is-active'));
      dot.classList.add('is-active');
    });
  });
}

/* —— 03b. Micro-interactions —— */

/* Product card par cursor ke peeche chalne wala soft gold spotlight */
function initCardSpotlight() {
  if (window.matchMedia('(hover: none)').matches) return;
  document.querySelectorAll('.product-card').forEach((card) => {
    card.addEventListener('mousemove', (e) => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', `${e.clientX - r.left}px`);
      card.style.setProperty('--my', `${e.clientY - r.top}px`);
    });
  });
}

/* Features strip ko infinite marquee bana dete hain (content duplicate karke) */
function initFeatureMarquee() {
  const strip = document.querySelector('.features-strip');
  if (!strip || strip.dataset.marquee) return;
  strip.dataset.marquee = '1';

  const group = document.createElement('div');
  group.className = 'feat-group';
  while (strip.firstChild) group.appendChild(strip.firstChild);

  const marquee = document.createElement('div');
  marquee.className = 'feat-marquee';
  marquee.appendChild(group);
  marquee.appendChild(group.cloneNode(true)); // seamless loop ke liye 2nd copy
  strip.appendChild(marquee);
}

/* Top scroll-progress bar + back-to-top button */
function initScrollUI() {
  const bar = document.getElementById('scrollProgress');
  const top = document.getElementById('toTop');
  if (!bar && !top) return;

  let ticking = false;
  const update = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    const y = window.scrollY;
    if (bar) bar.style.transform = `scaleX(${max > 0 ? y / max : 0})`;
    if (top) top.classList.toggle('show', y > 600);
    ticking = false;
  };
  window.addEventListener(
    'scroll',
    () => {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    },
    { passive: true }
  );
  top?.addEventListener('click', () =>
    window.scrollTo({ top: 0, behavior: 'smooth' })
  );
  update();
}

/* —— 04. Gallery Strip (Infinite Scroll on Home Page) —— */
const GALLERY_PHOTOS = [
  { img: 'images/D-cut-blue.webp', alt: 'Blue D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/d-cut-red.webp', alt: 'Red D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/D-cut-green.webp', alt: 'Green D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/D-cut-yellow.webp', alt: 'Yellow D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/w-cut-blue.webp', alt: 'Blue W-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/W-cut-red.webp', alt: 'Red W-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/W-cut-green.webp', alt: 'Green W-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/W-cut-orange.webp', alt: 'Orange W-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/loop-blue.webp', alt: 'Blue Loop Handle non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/loop-red.webp', alt: 'Red Loop Handle non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/loop-green.webp', alt: 'Green Loop Handle non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/D-cut-black.webp', alt: 'Black D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/D-cut-Pink.webp', alt: 'Pink D-Cut non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/stitched-red.webp', alt: 'Red Stitched non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/box-green.webp', alt: 'Green Box non-woven carry bag with custom logo printing by Vaibhav Enterprise' },
  { img: 'images/Manufacturing Unit.webp', alt: 'Non-woven bag production machines inside the Vaibhav Enterprise manufacturing unit' },
  { img: 'images/Company.webp', alt: 'Vaibhav Enterprise non-woven bag factory entrance in Atakpardi, Valsad' }
];

function buildGalleryStrip() {
  const track = document.getElementById('galleryTrack');
  if (!track) return;
  track.innerHTML = [...GALLERY_PHOTOS, ...GALLERY_PHOTOS]
    .map(
      (item) =>
        `<div class="gallery-item"><img src="${item.img}" alt="${item.alt}"></div>`
    )
    .join('');
}

/* —— 05. Unified Modal & WhatsApp Logic —— */
function toggleModal(modalId, show) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.toggle('open', show);
    document.body.style.overflow = show ? 'hidden' : '';
  }
}

function sendWA(msg) {
  window.open(
    `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(msg)}`,
    '_blank'
  );
}

/* Auto Popup */
function openAutoModal() {
  toggleModal('autoEnquiryModal', true);
  document.getElementById('peekingTab')?.classList.add('hidden');
}

function closeAutoModal() {
  toggleModal('autoEnquiryModal', false);
  document.getElementById('peekingTab')?.classList.remove('hidden');
}

function submitAutoModal() {
  const name = document.getElementById('autoName')?.value.trim();
  const phone = document.getElementById('autoPhone')?.value.trim();
  const req = document.getElementById('autoReq')?.value.trim();
  if (!name || !phone) return alert('Please enter your name and phone number.');
  sendWA(`Hi, I'm *${name}*.\nPhone: ${phone}\nRequirement: ${req || 'N/A'}`);
  closeAutoModal();
}

/* Quick Enquiry Modal */
let _product = '';

function openModal(productName) {
  _product = productName;
  const lbl = document.getElementById('modalProductLabel');
  if (lbl) lbl.textContent = productName;
  toggleModal('enquiryModal', true);
}

function closeModal() {
  toggleModal('enquiryModal', false);
}

function submitModal() {
  const name = document.getElementById('mName')?.value.trim();
  const phone = document.getElementById('mPhone')?.value.trim();
  const qty = document.getElementById('mQty')?.value;
  const note = document.getElementById('mNote')?.value.trim();
  if (!name || !phone) return alert('Please enter your name and phone number.');
  sendWA(
    `Hi, I'm *${name}*.\nProduct: *${_product}*\nPhone: ${phone}\nQty: ${qty}\nNote: ${note || 'N/A'}`
  );
  closeModal();
}

/* Direct WA Buttons */
function waEnquire(product) {
  sendWA(`Hi, I want to enquire about *${product}* from Vaibhav Enterprise.`);
}

/* Contact Page Form */
function submitForm() {
  const name = document.getElementById('cName')?.value.trim();
  const phone = document.getElementById('cPhone')?.value.trim();
  const email = document.getElementById('cEmail')?.value.trim();
  const bagType = document.getElementById('cBagType')?.value;
  const qty = document.getElementById('cQty')?.value;
  const message = document.getElementById('cMessage')?.value.trim();
  if (!name || !phone) return alert('Please provide Name and Phone number.');
  let waMsg = `Hi, I'm *${name}*.\nPhone: ${phone}`;
  if (email) waMsg += `\nEmail: ${email}`;
  waMsg += `\nBag Type: *${bagType}*\nQuantity: ${qty}`;
  if (message) waMsg += `\nMessage: ${message}`;
  sendWA(waMsg);
}

/* —— 06. Gallery Page Filters & Lightbox —— */
function initGalleryFilter() {
  const gItems = document.querySelectorAll('.g-item');
  const countEl = document.getElementById('galleryCount');
  
  // Set initial count
  if (countEl) countEl.textContent = gItems.length;

  document.querySelectorAll('.filter-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.filter-btn').forEach((b) => b.classList.remove('active'));
      this.classList.add('active');
      const filter = this.dataset.filter;
      let visible = 0;
      gItems.forEach((item) => {
        const show = filter === 'all' || item.dataset.cat === filter;
        item.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      if (countEl) countEl.textContent = visible;
    });
  });
}

function initLightbox() {
  const lb = document.getElementById('lightbox');
  const lbImg = document.getElementById('lbImg');
  if (!lb || !lbImg) return;

  document.querySelectorAll('.g-item').forEach((item) => {
    item.addEventListener('click', function () {
      const img = this.querySelector('img');
      const src = this.dataset.src || img?.src;
      if (!src) return;
      lbImg.src = src;
      lbImg.alt = img?.alt || '';
      toggleModal('lightbox', true);
    });
  });

  const closeLb = () => toggleModal('lightbox', false);
  document.getElementById('lbClose')?.addEventListener('click', closeLb);
  lb.addEventListener('click', (e) => {
    if (e.target === lb) closeLb();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLb();
  });
}

/* —— 07. Splash Screen —— */
/* body.loaded lagne par hero ka staggered entrance start hota hai. */
function initSplash() {
  const splash = document.getElementById('splashScreen');
  if (!splash) {
    document.body.classList.add('loaded');
    return;
  }
  document.body.style.overflow = 'hidden';
  setTimeout(() => {
    splash.classList.add('hidden');
    document.body.style.overflow = '';
    document.body.classList.add('loaded');
  }, 2100);
}

/* —— 08. Initialization —— */
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initScrollUI();
  initFeatureMarquee();
  initReveal();
  initCounter();
  initSplash();
  buildGalleryStrip();
  initGalleryFilter();
  initLightbox();
  initColorDots();
  initCardSpotlight();

  // Auto popup — show once per session (splash ke baad)
  setTimeout(() => {
    if (!sessionStorage.getItem('autoPopupShown')) {
      openAutoModal();
      sessionStorage.setItem('autoPopupShown', 'true');
    } else {
      document.getElementById('peekingTab')?.classList.remove('hidden');
    }
  }, 6000);
});

