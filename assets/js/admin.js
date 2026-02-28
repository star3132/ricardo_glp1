/* ============================================================
   Syringe Box — Admin Panel JS
   ============================================================ */

'use strict';

// ============================================================
// Boxes page — Create modal
// ============================================================
function openCreateModal() {
  const el = document.getElementById('createModalOverlay');
  if (el) {
    el.hidden = false;
    document.getElementById('newName')?.focus();
  }
}

function closeCreateModal(e) {
  if (e.target === document.getElementById('createModalOverlay')) closeCreateModalBtn();
}

function closeCreateModalBtn() {
  const el = document.getElementById('createModalOverlay');
  if (el) el.hidden = true;
}

// ============================================================
// Boxes page — QR Code modal
// ============================================================
let qrInstance = null;

function showQR(hash, name) {
  const overlay = document.getElementById('qrModalOverlay');
  const titleEl = document.getElementById('qrModalTitle');
  const urlEl   = document.getElementById('qrUrl');
  const qrEl    = document.getElementById('qrcode');

  if (!overlay) return;

  const url = (window.APP_URL || window.location.origin) + '/?h=' + hash;

  titleEl.textContent = 'QR Code — ' + name;
  urlEl.textContent   = url;

  // Clear previous QR
  qrEl.innerHTML = '';
  qrInstance = null;

  if (typeof QRCode !== 'undefined') {
    qrInstance = new QRCode(qrEl, {
      text:          url,
      width:         220,
      height:        220,
      colorDark:     '#000000',
      colorLight:    '#ffffff',
      correctLevel:  QRCode.CorrectLevel.M,
    });
  } else {
    qrEl.innerHTML = '<p style="color:#64748b;font-size:.85rem;">QR library not loaded.<br>Copy the URL below.</p>';
  }

  overlay.hidden = false;
}

function closeQRModal(e) {
  if (e.target === document.getElementById('qrModalOverlay')) closeQRModalBtn();
}

function closeQRModalBtn() {
  const el = document.getElementById('qrModalOverlay');
  if (el) el.hidden = true;
  if (qrInstance) {
    qrInstance = null;
  }
}

function copyQRUrl() {
  const url = document.getElementById('qrUrl')?.textContent;
  if (!url) return;

  navigator.clipboard.writeText(url).then(() => {
    const btn = event.target;
    const orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(() => { btn.textContent = orig; }, 1500);
  }).catch(() => {
    // Fallback
    const range = document.createRange();
    const el = document.getElementById('qrUrl');
    range.selectNode(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    document.execCommand('copy');
    window.getSelection().removeAllRanges();
  });
}

function downloadQR() {
  const qrEl = document.getElementById('qrcode');
  if (!qrEl) return;

  const canvas = qrEl.querySelector('canvas');
  const img    = qrEl.querySelector('img');

  const title = (document.getElementById('qrModalTitle')?.textContent || 'qrcode')
    .replace(/[^a-z0-9]/gi, '_').toLowerCase();

  if (canvas) {
    const link = document.createElement('a');
    link.download = title + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  } else if (img) {
    const link = document.createElement('a');
    link.download = title + '.png';
    link.href = img.src;
    link.click();
  }
}

// ============================================================
// Medicines page — Edit modal
// ============================================================
function openEditMed(med) {
  const overlay = document.getElementById('editMedOverlay');
  if (!overlay) return;

  document.getElementById('editMedId').value    = med.id;
  document.getElementById('editMedName').value  = med.name;
  document.getElementById('editMedColor').value = med.color || '#3b82f6';

  const unitSel = document.getElementById('editMedUnit');
  if (unitSel) {
    // Try to select existing unit, fallback to first option
    let found = false;
    for (let opt of unitSel.options) {
      if (opt.value === med.unit) { opt.selected = true; found = true; break; }
    }
    if (!found) {
      // Add as custom option
      const newOpt = new Option(med.unit, med.unit, true, true);
      unitSel.add(newOpt, 0);
    }
  }

  overlay.hidden = false;
  document.getElementById('editMedName')?.focus();
}

function closeEditMed(e) {
  if (e.target === document.getElementById('editMedOverlay')) closeEditMedBtn();
}

function closeEditMedBtn() {
  const el = document.getElementById('editMedOverlay');
  if (el) el.hidden = true;
}

// ============================================================
// Global keyboard handler
// ============================================================
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeCreateModalBtn();
    closeQRModalBtn();
    closeEditMedBtn();
  }
});
