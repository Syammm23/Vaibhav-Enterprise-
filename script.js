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
function initReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add('active');
          obs.unobserve(e.target);
        }
      });
    },
    { threshold: 0.1 }
  );
  els.forEach((el) => obs.observe(el));
}

function initCounter() {
  const nums = document.querySelectorAll('[data-target]');
  if (!nums.length) return;
  const obs = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const target = +el.dataset.target;
        const suffix = el.dataset.suffix ?? '+';
        let cur = 0;
        const t = setInterval(() => {
          cur += target / 60;
          if (cur >= target) {
            cur = target;
            clearInterval(t);
          }
          el.textContent = Math.floor(cur) + suffix;
        }, 20);
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
  if (imgElement) imgElement.src = newImageSrc;
}

/* —— 04. Gallery Strip (Infinite Scroll on Home Page) —— */
const GALLERY_PHOTOS = [
  { img: 'images/D-cut-blue.png', alt: 'D-Cut Blue Bag' },
  { img: 'images/d-cut-red.png', alt: 'D-Cut Red Bag' },
  { img: 'images/D-cut-green.png', alt: 'D-Cut Green Bag' },
  { img: 'images/D-cut-yellow.png', alt: 'D-Cut Yellow Bag' },
  { img: 'images/w-cut-blue.png', alt: 'W-Cut Blue Bag' },
  { img: 'images/W-cut-red.png', alt: 'W-Cut Red Bag' },
  { img: 'images/W-cut-green.png', alt: 'W-Cut Green Bag' },
  { img: 'images/W-cut-orange.png', alt: 'W-Cut Orange Bag' },
  { img: 'images/loop-blue.png', alt: 'Loop Handle Blue Bag' },
  { img: 'images/loop-red.png', alt: 'Loop Handle Red Bag' },
  { img: 'images/loop-green.png', alt: 'Loop Handle Bag' },
  { img: 'images/D-cut-black.png', alt: 'D-Cut Black Bag' },
  { img: 'images/D-cut-Pink.png', alt: 'D-Cut Pink Bag' },
  { img: 'images/stitched-red.png', alt: 'Stitched Bag' },
  { img: 'images/box-green.png', alt: 'Box Bag' },
  { img: 'images/IMG_4591.jpg', alt: 'Bag Image 14' },
  { img: 'images/Company.jpg', alt: 'Company Image' }
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
      const src = this.dataset.src || this.querySelector('img')?.src;
      if (!src) return;
      lbImg.src = src;
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
function initSplash() {
  const splash = document.getElementById('splashScreen');
  if (!splash) return;
  document.body.style.overflow = 'hidden';
  setTimeout(() => {
    splash.classList.add('hidden');
    document.body.style.overflow = '';
  }, 3400);
}

/* —— 08. Initialization —— */
document.addEventListener('DOMContentLoaded', () => {
  initNav();
  initReveal();
  initCounter();
  initSplash();
  buildGalleryStrip();
  initGalleryFilter();
  initLightbox();

  // Auto popup — show once per session after 3 seconds
  setTimeout(() => {
    if (!sessionStorage.getItem('autoPopupShown')) {
      openAutoModal();
      sessionStorage.setItem('autoPopupShown', 'true');
    } else {
      document.getElementById('peekingTab')?.classList.remove('hidden');
    }
  }, 3000);
});