/* ============================================================
   Syringe Box — Public Page JS
   ============================================================ */

'use strict';

// ---- State ------------------------------------------------
let activeCell = null;   // the button element currently being edited
let currentQty = 0;
let currentMax = 10;
let currentMedId = 0;

// ---- DOM refs ---------------------------------------------
const overlay    = document.getElementById('modalOverlay');
const medSelect  = document.getElementById('medSelect');
const medBadge   = document.getElementById('medBadge');
const qtySection = document.getElementById('qtySection');
const qtyValue   = document.getElementById('qtyValue');
const qtyUnit    = document.getElementById('qtyUnit');
const qtyMax     = document.getElementById('qtyMax');
const qtyBarFill = document.getElementById('qtyBarFill');
const qtyMinus   = document.getElementById('qtyMinus');
const qtyPlus    = document.getElementById('qtyPlus');
const saveBtn    = document.getElementById('saveBtn');
const saveBtnText    = document.getElementById('saveBtnText');
const saveBtnSpinner = document.getElementById('saveBtnSpinner');
const modalError     = document.getElementById('modalError');
const toast          = document.getElementById('toast');

// ---- Open cell modal --------------------------------------
function openCell(btn) {
  activeCell = btn;

  currentQty   = parseInt(btn.dataset.qty,   10) || 0;
  currentMax   = parseInt(btn.dataset.max,   10) || 10;
  currentMedId = parseInt(btn.dataset.medId, 10) || 0;

  // Set medicine select
  medSelect.value = currentMedId || '0';
  updateMedBadge();

  // Set quantity display
  updateQtyDisplay();

  // Show/hide qty section based on medicine selection
  updateQtyVisibility();

  // Clear error
  hideError();

  // Open
  overlay.classList.add('is-open');
  overlay.removeAttribute('aria-hidden');
  document.body.style.overflow = 'hidden';

  // Focus select after animation
  setTimeout(() => medSelect.focus(), 320);
}

// ---- Close modal ------------------------------------------
function closeModal(e) {
  if (e.target === overlay) closeModalBtn();
}

function closeModalBtn() {
  overlay.classList.remove('is-open');
  overlay.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  activeCell = null;
}

// ---- Medicine changed -------------------------------------
function onMedChange(sel) {
  currentMedId = parseInt(sel.value, 10) || 0;

  // If medicine cleared, reset quantity
  if (currentMedId === 0) currentQty = 0;

  updateMedBadge();
  updateQtyDisplay();
  updateQtyVisibility();
}

function updateMedBadge() {
  const opt = medSelect.options[medSelect.selectedIndex];
  const color = opt ? (opt.dataset.color || '') : '';
  medBadge.style.background = color || 'transparent';
  medBadge.style.border = color ? `2px solid ${color}` : '2px solid #334155';
}

function updateQtyVisibility() {
  qtySection.style.display = currentMedId > 0 ? '' : 'none';
}

// ---- Adjust quantity --------------------------------------
function adjustQty(delta) {
  currentQty = Math.min(currentMax, Math.max(0, currentQty + delta));
  updateQtyDisplay();
}

function updateQtyDisplay() {
  qtyValue.textContent = currentQty;

  const opt = medSelect.options[medSelect.selectedIndex];
  const unit = opt ? (opt.dataset.unit || '') : '';
  qtyUnit.textContent = unit;

  qtyMax.textContent = currentMax;

  // Progress bar
  const pct = currentMax > 0 ? Math.min(100, (currentQty / currentMax) * 100) : 0;
  qtyBarFill.style.width = pct + '%';

  // Color: green ≥ 60%, yellow < 60%, red ≤ 20%
  if (pct <= 20)       qtyBarFill.style.background = '#ef4444';
  else if (pct < 60)   qtyBarFill.style.background = '#f59e0b';
  else                 qtyBarFill.style.background = '#10b981';

  // Disable at limits
  qtyMinus.disabled = currentQty <= 0;
  qtyPlus.disabled  = currentQty >= currentMax;
}

// ---- Save cell --------------------------------------------
async function saveCell() {
  if (!activeCell) return;

  const boxId = parseInt(activeCell.dataset.box, 10);
  const row   = parseInt(activeCell.dataset.row, 10);
  const col   = parseInt(activeCell.dataset.col, 10);
  const medId = currentMedId > 0 ? currentMedId : null;

  setSaving(true);
  hideError();

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:      'update_cell',
        box_id:      boxId,
        row:         row,
        col:         col,
        medicine_id: medId,
        quantity:    currentQty,
      }),
    });

    const data = await res.json();

    if (!data.ok) {
      showError(data.error || 'Failed to save. Try again.');
      return;
    }

    // Update button in the grid
    updateCellUI(activeCell, data);
    showToast('Saved!');
    closeModalBtn();

  } catch (err) {
    showError('Network error. Check your connection.');
  } finally {
    setSaving(false);
  }
}

// ---- Update cell button in the grid -----------------------
function updateCellUI(btn, data) {
  const med = data.medicine;

  // Update data attributes
  btn.dataset.qty   = data.quantity;
  btn.dataset.max   = data.max;
  btn.dataset.medId = med ? med.id    : '0';
  btn.dataset.medColor = med ? med.color : '';
  btn.dataset.medName  = med ? med.name  : '';
  btn.dataset.medUnit  = med ? med.unit  : '';

  if (med) {
    btn.className = 'cell cell--filled';
    btn.style.setProperty('--cell-color', med.color);
    btn.innerHTML = `
      <span class="cell-dot" style="background:${med.color}"></span>
      <span class="cell-name">${escHtml(med.name)}</span>
      <span class="cell-qty">${data.quantity}<small> ${escHtml(med.unit)}</small></span>
    `;
  } else {
    btn.className = 'cell cell--empty';
    btn.style.removeProperty('--cell-color');
    btn.innerHTML = '<span class="cell-empty-icon">+</span>';
  }
}

// ---- Helpers ----------------------------------------------
function setSaving(on) {
  saveBtn.disabled = on;
  saveBtnText.hidden = on;
  saveBtnSpinner.hidden = !on;
}

function showError(msg) {
  modalError.textContent = msg;
  modalError.hidden = false;
}

function hideError() {
  modalError.hidden = true;
  modalError.textContent = '';
}

let toastTimer;
function showToast(msg) {
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2000);
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ---- Keyboard support -------------------------------------
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
    closeModalBtn();
  }
});
