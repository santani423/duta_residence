import { writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');

const C = {
  app: '#f5f7fb',
  surface: '#ffffff',
  surface2: '#eef2f7',
  text: '#0f172a',
  muted: '#64748b',
  border: '#d9e0ea',
  brand: '#0f766e',
  brandDark: '#115e59',
  info: '#2563eb',
  success: '#16a34a',
  warning: '#d97706',
  error: '#dc2626',
  darkApp: '#0b1120',
  darkSurface: '#111827',
  darkMuted: '#94a3b8',
};

const out = [];
const esc = (value) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;');

function attrs(input = {}) {
  return Object.entries(input)
    .filter(([, value]) => value !== undefined && value !== null)
    .map(([key, value]) => `${key}="${esc(value)}"`)
    .join(' ');
}

function rect(x, y, w, h, options = {}) {
  out.push(`<rect ${attrs({
    x, y, width: w, height: h,
    rx: options.rx ?? 0,
    fill: options.fill ?? 'none',
    stroke: options.stroke,
    'stroke-width': options.sw,
    opacity: options.opacity,
    filter: options.filter,
  })}/>`); 
}

function line(x1, y1, x2, y2, options = {}) {
  out.push(`<line ${attrs({
    x1, y1, x2, y2,
    stroke: options.stroke ?? C.border,
    'stroke-width': options.sw ?? 1,
    'stroke-dasharray': options.dash,
  })}/>`); 
}

function circle(cx, cy, r, options = {}) {
  out.push(`<circle ${attrs({
    cx, cy, r,
    fill: options.fill ?? 'none',
    stroke: options.stroke,
    'stroke-width': options.sw,
    opacity: options.opacity,
  })}/>`); 
}

function path(d, options = {}) {
  out.push(`<path ${attrs({
    d,
    fill: options.fill ?? 'none',
    stroke: options.stroke,
    'stroke-width': options.sw,
    'stroke-linecap': options.cap,
    'stroke-linejoin': options.join,
    opacity: options.opacity,
  })}/>`); 
}

function text(x, y, value, options = {}) {
  const {
    size = 14,
    weight = 400,
    fill = C.text,
    anchor = 'start',
    width,
    lh = Math.round(size * 1.45),
    opacity,
    family = 'Inter, Arial, sans-serif',
  } = options;
  if (width) {
    const words = String(value).split(' ');
    const maxChars = Math.max(8, Math.floor(width / (size * 0.55)));
    const lines = [];
    let current = '';
    for (const word of words) {
      const next = current ? `${current} ${word}` : word;
      if (next.length > maxChars && current) {
        lines.push(current);
        current = word;
      } else {
        current = next;
      }
    }
    if (current) lines.push(current);
    out.push(`<text ${attrs({ x, y, fill, 'font-size': size, 'font-weight': weight, 'font-family': family, 'text-anchor': anchor, opacity })}>`);
    lines.forEach((lineValue, index) => {
      out.push(`<tspan x="${x}" dy="${index === 0 ? 0 : lh}">${esc(lineValue)}</tspan>`);
    });
    out.push('</text>');
    return;
  }
  out.push(`<text ${attrs({ x, y, fill, 'font-size': size, 'font-weight': weight, 'font-family': family, 'text-anchor': anchor, opacity })}>${esc(value)}</text>`);
}

function label(x, y, value) {
  text(x, y, value, { size: 11, weight: 700, fill: C.brand });
}

function chip(x, y, value, color = C.brand, w) {
  const width = w ?? Math.max(54, value.length * 7 + 18);
  rect(x, y, width, 24, { rx: 12, fill: `${color}18`, stroke: `${color}55`, sw: 1 });
  text(x + width / 2, y + 16, value, { size: 11, weight: 700, fill: color, anchor: 'middle' });
  return width;
}

function iconBox(x, y, labelText, active = false) {
  rect(x, y, 24, 24, { rx: 6, fill: active ? C.brand : C.surface2 });
  text(x + 12, y + 16, labelText, { size: 10, weight: 800, fill: active ? C.surface : C.muted, anchor: 'middle' });
}

function button(x, y, value, options = {}) {
  const w = options.w ?? Math.max(86, value.length * 8 + 32);
  const h = options.h ?? 32;
  const primary = options.type === 'primary';
  const danger = options.type === 'danger';
  rect(x, y, w, h, {
    rx: 6,
    fill: primary ? C.brand : danger ? '#fef2f2' : C.surface,
    stroke: primary ? C.brand : danger ? C.error : C.border,
    sw: 1,
  });
  text(x + w / 2, y + 21, value, {
    size: 12,
    weight: 700,
    anchor: 'middle',
    fill: primary ? C.surface : danger ? C.error : C.text,
  });
  return w;
}

function input(x, y, value, options = {}) {
  const w = options.w ?? 220;
  rect(x, y, w, 36, { rx: 6, fill: C.surface, stroke: C.border, sw: 1 });
  if (options.label) text(x, y - 8, options.label, { size: 11, fill: C.muted });
  text(x + 12, y + 23, value, { size: 12, fill: options.muted ? C.muted : C.text });
}

function card(x, y, w, h, options = {}) {
  rect(x, y, w, h, { rx: 8, fill: C.surface, stroke: C.border, sw: 1, filter: options.shadow ? 'url(#shadow)' : undefined });
  if (options.title) text(x + 18, y + 30, options.title, { size: 15, weight: 700 });
}

function statCard(x, y, w, title, value, accent = C.brand) {
  card(x, y, w, 104, { shadow: true });
  rect(x + 16, y + 16, 36, 36, { rx: 8, fill: `${accent}18` });
  circle(x + 34, y + 34, 7, { fill: accent, opacity: 0.9 });
  text(x + 64, y + 32, title, { size: 12, fill: C.muted, width: w - 82 });
  text(x + 64, y + 68, value, { size: 24, weight: 700 });
}

function table(x, y, w, columns, rows, options = {}) {
  const rowH = options.rowH ?? 42;
  const widths = columns.map((col) => col.w);
  const total = widths.reduce((sum, n) => sum + n, 0);
  const scale = w / total;
  rect(x, y, w, rowH, { rx: 6, fill: C.surface2, stroke: C.border, sw: 1 });
  let cx = x;
  columns.forEach((col, index) => {
    const cw = widths[index] * scale;
    text(cx + 12, y + 26, col.label, { size: 11, weight: 800, fill: C.muted });
    if (index) line(cx, y, cx, y + rowH * (rows.length + 1), { stroke: C.border });
    cx += cw;
  });
  rows.forEach((row, r) => {
    const ry = y + rowH * (r + 1);
    rect(x, ry, w, rowH, { fill: r % 2 ? '#fbfdff' : C.surface, stroke: C.border, sw: 1 });
    let cellX = x;
    row.forEach((value, index) => {
      const cw = widths[index] * scale;
      if (String(value).startsWith('badge:')) {
        const [, labelValue, color] = String(value).split(':');
        chip(cellX + 12, ry + 9, labelValue, color || C.brand);
      } else {
        text(cellX + 12, ry + 26, value, { size: 12, fill: index === 0 ? C.text : C.muted, weight: index === 0 ? 700 : 400, width: cw - 22 });
      }
      cellX += cw;
    });
  });
}

function barChart(x, y, w, h) {
  card(x, y, w, h, { title: 'Pemasukan per Cluster', shadow: true });
  const ox = x + 52;
  const oy = y + h - 50;
  line(ox, y + 60, ox, oy, { stroke: C.border });
  line(ox, oy, x + w - 30, oy, { stroke: C.border });
  const clusters = ['AL', 'BR', 'CD', 'DL', 'ER', 'FG'];
  clusters.forEach((cluster, i) => {
    const bx = ox + 38 + i * 66;
    const billing = 120 + (i % 3) * 35;
    const paid = 78 + (i % 4) * 26;
    rect(bx, oy - billing, 22, billing, { rx: 4, fill: C.brand });
    rect(bx + 26, oy - paid, 22, paid, { rx: 4, fill: C.info });
    text(bx + 20, oy + 22, cluster, { size: 11, fill: C.muted, anchor: 'middle' });
  });
  chip(x + w - 210, y + 22, 'Tagihan', C.brand);
  chip(x + w - 124, y + 22, 'Terbayar', C.info);
}

function donut(x, y, titleText) {
  card(x, y, 360, 280, { title: titleText, shadow: true });
  circle(x + 118, y + 150, 72, { fill: '#e0f2fe' });
  path(`M ${x + 118} ${y + 150} L ${x + 118} ${y + 78} A 72 72 0 0 1 ${x + 190} ${y + 150} Z`, { fill: C.brand });
  path(`M ${x + 118} ${y + 150} L ${x + 190} ${y + 150} A 72 72 0 0 1 ${x + 118} ${y + 222} Z`, { fill: C.warning });
  circle(x + 118, y + 150, 42, { fill: C.surface });
  const labels = [['< 30 hari', C.brand], ['30-60 hari', C.info], ['60-90 hari', C.warning], ['> 90 hari', C.error]];
  labels.forEach(([value, color], i) => {
    circle(x + 230, y + 104 + i * 32, 5, { fill: color });
    text(x + 244, y + 109 + i * 32, value, { size: 12, fill: C.muted });
  });
}

function shell(x, y, w, h, selected, role = 'Super Admin') {
  rect(x, y, w, h, { rx: 18, fill: C.app, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  rect(x, y, 250, h, { rx: 18, fill: C.surface, stroke: C.border, sw: 1 });
  rect(x + 232, y, 18, h, { fill: C.surface });
  rect(x + 24, y + 22, 34, 34, { rx: 6, fill: C.brand });
  text(x + 41, y + 44, 'GD', { size: 12, weight: 800, fill: C.surface, anchor: 'middle' });
  text(x + 70, y + 44, 'Grand Duta', { size: 17, weight: 800, fill: C.brand });
  const items = [
    ['D', 'Dashboard'], ['C', 'Cluster'], ['P', 'Pelanggan'], ['T', 'Tagihan'], ['B', 'Pembayaran'],
    ['I', 'Cicilan'], ['R', 'Reversal'], ['A', 'Piutang'], ['L', 'Laporan'], ['F', 'Dokumen PDF'],
    ['U', 'User'], ['G', 'Audit Log'], ['S', 'Payment Gateway'],
  ];
  items.forEach((item, index) => {
    const yy = y + 86 + index * 38;
    const active = item[1] === selected;
    rect(x + 14, yy - 6, 222, 34, { rx: 8, fill: active ? '#e6fffb' : 'transparent' });
    iconBox(x + 26, yy - 1, item[0], active);
    text(x + 62, yy + 16, item[1], { size: 13, weight: active ? 800 : 500, fill: active ? C.brand : C.text });
  });
  rect(x + 250, y, w - 250, 64, { fill: C.surface, stroke: C.border, sw: 1 });
  text(x + 282, y + 28, 'Estate Management', { size: 14, weight: 800 });
  text(x + 282, y + 46, role, { size: 11, fill: C.muted });
  input(x + w - 392, y + 14, 'Light', { w: 104 });
  circle(x + w - 246, y + 32, 14, { fill: '#fef3c7', stroke: '#fde68a', sw: 1 });
  text(x + w - 246, y + 37, '3', { size: 11, weight: 800, fill: C.warning, anchor: 'middle' });
  rect(x + w - 204, y + 14, 160, 36, { rx: 6, fill: C.surface, stroke: C.border, sw: 1 });
  circle(x + w - 184, y + 32, 12, { fill: C.surface2 });
  text(x + w - 160, y + 37, 'Santani Admin', { size: 12, weight: 700 });
}

function pageHeader(x, y, titleValue, subtitle, actionText) {
  text(x, y, 'Dashboard / ' + titleValue, { size: 11, fill: C.muted });
  text(x, y + 34, titleValue, { size: 24, weight: 700 });
  if (subtitle) text(x, y + 58, subtitle, { size: 13, fill: C.muted, width: 650 });
  button(x + 840, y + 18, 'Refresh');
  if (actionText) button(x + 940, y + 18, actionText, { type: 'primary' });
}

function filterBar(x, y, fields = ['Cari data', 'Status', 'Periode'], action = 'Tambah') {
  card(x, y, 1030, 76, { shadow: false });
  let cx = x + 18;
  fields.forEach((field) => {
    input(cx, y + 20, field, { w: field.length > 9 ? 210 : 156, muted: true });
    cx += field.length > 9 ? 224 : 170;
  });
  button(x + 818, y + 22, 'Reload');
  if (action) button(x + 908, y + 22, action, { type: 'primary' });
}

function frameTitle(x, y, titleValue, subtitle) {
  text(x, y, titleValue, { size: 32, weight: 800, fill: C.text });
  if (subtitle) text(x, y + 28, subtitle, { size: 13, fill: C.muted, width: 860 });
}

function adminDashboard(x, y) {
  shell(x, y, 1440, 960, 'Dashboard');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Dashboard', 'Ringkasan operasional estate, tagihan, pembayaran, dan aktivitas terbaru.', 'Bulan ini');
  statCard(cx, cy + 96, 245, 'Total Cluster', '15', C.brand);
  statCard(cx + 263, cy + 96, 245, 'Total Pelanggan', '450', C.info);
  statCard(cx + 526, cy + 96, 245, 'Tagihan', '2.784', C.warning);
  statCard(cx + 789, cy + 96, 245, 'Penerimaan Hari Ini', 'Rp 18,4 jt', C.success);
  barChart(cx, cy + 232, 640, 320);
  donut(cx + 664, cy + 232, 'Aging Piutang');
  card(cx, cy + 576, 500, 164, { title: 'Status Tagihan', shadow: true });
  statCard(cx + 18, cy + 624, 215, 'Belum Bayar', '318', C.error);
  statCard(cx + 250, cy + 624, 215, 'Lunas', '2.466', C.success);
  card(cx + 520, cy + 576, 510, 250, { title: 'Aktivitas Pembayaran Terbaru', shadow: true });
  table(cx + 538, cy + 622, 474, [
    { label: 'Receipt', w: 140 }, { label: 'Customer', w: 150 }, { label: 'Status', w: 110 }, { label: 'Total', w: 120 },
  ], [
    ['GD.2026.001', 'AL001 - Budi', `badge:paid:${C.success}`, 'Rp 2.450.000'],
    ['GD.2026.002', 'BR082 - Ratna', `badge:paid:${C.success}`, 'Rp 1.120.000'],
    ['GD.2026.003', 'CD014 - Raka', `badge:pending:${C.warning}`, 'Rp 980.000'],
  ], { rowH: 38 });
}

function adminCustomers(x, y) {
  shell(x, y, 1440, 960, 'Pelanggan');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Pelanggan', 'Kelola data pelanggan, unit, status hunian, dan konversi properti.', 'Tambah');
  filterBar(cx, cy + 92, ['Cari pelanggan', 'Cluster', 'Status'], 'Tambah');
  card(cx, cy + 190, 1030, 385, { title: 'Daftar Pelanggan', shadow: true });
  table(cx + 18, cy + 240, 994, [
    { label: 'ID', w: 90 }, { label: 'Nama', w: 200 }, { label: 'Cluster', w: 130 }, { label: 'Unit', w: 150 },
    { label: 'Telepon', w: 150 }, { label: 'Status', w: 130 }, { label: 'Aksi', w: 160 },
  ], [
    ['AL001', 'Budi Santoso', 'Alamanda', 'A-12', '0812-1111-2222', `badge:active:${C.success}`, 'Detail / Edit'],
    ['AL002', 'Ratna Sari', 'Alamanda', 'A-14', '0812-2222-3333', `badge:overdue:${C.error}`, 'Detail / Edit'],
    ['BR041', 'Raka Mahendra', 'Bougenville', 'B-07', '0812-3333-4444', `badge:active:${C.success}`, 'Detail / Edit'],
    ['CD019', 'Nadia Putri', 'Cendana', 'C-19', '0812-4444-5555', `badge:inactive:${C.muted}`, 'Detail / Edit'],
  ]);
  rect(cx + 622, cy + 154, 490, 640, { rx: 12, fill: C.surface, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  text(cx + 650, cy + 190, 'Detail Pelanggan', { size: 20, weight: 800 });
  text(cx + 650, cy + 216, 'Tabs: Profil, Tagihan, Pembayaran, Dokumen', { size: 12, fill: C.muted });
  ['Profil', 'Tagihan', 'Pembayaran', 'Dokumen'].forEach((tab, i) => chip(cx + 650 + i * 95, cy + 246, tab, i === 0 ? C.brand : C.muted, 84));
  card(cx + 650, cy + 292, 432, 160, { title: 'Informasi Unit' });
  text(cx + 668, cy + 336, 'Nama: Budi Santoso', { size: 13 });
  text(cx + 668, cy + 362, 'Cluster: Alamanda / Unit A-12', { size: 13, fill: C.muted });
  text(cx + 668, cy + 388, 'Status: Aktif, Denda: eligible', { size: 13, fill: C.muted });
  card(cx + 650, cy + 472, 432, 210, { title: 'Riwayat Tagihan' });
  table(cx + 668, cy + 518, 396, [
    { label: 'Invoice', w: 120 }, { label: 'Periode', w: 90 }, { label: 'Status', w: 100 }, { label: 'Total', w: 100 },
  ], [
    ['INV-0626', '2026-06', `badge:paid:${C.success}`, 'Rp 1.2 jt'],
    ['INV-0526', '2026-05', `badge:paid:${C.success}`, 'Rp 1.2 jt'],
    ['INV-0426', '2026-04', `badge:overdue:${C.error}`, 'Rp 1.4 jt'],
  ], { rowH: 34 });
}

function adminBillings(x, y) {
  shell(x, y, 1440, 960, 'Tagihan');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Tagihan', 'Generate, approve, dan monitor tagihan bulanan, khusus, dan mundur.', 'Generate');
  ['Menunggu Approval', 'Disetujui', 'Ditolak'].forEach((tab, i) => chip(cx + i * 150, cy + 92, tab, i === 0 ? C.brand : C.muted, 136));
  filterBar(cx, cy + 134, ['Cari invoice', 'Periode', 'Status'], 'Tagihan Khusus');
  card(cx, cy + 232, 1030, 350, { title: 'Daftar Tagihan', shadow: true });
  table(cx + 18, cy + 282, 994, [
    { label: 'Invoice', w: 150 }, { label: 'Customer', w: 170 }, { label: 'Jenis', w: 120 }, { label: 'Periode', w: 100 },
    { label: 'Jatuh Tempo', w: 130 }, { label: 'Total', w: 140 }, { label: 'Status', w: 130 }, { label: 'Aksi', w: 150 },
  ], [
    ['INV-202606-001', 'AL001 Budi', 'IPL', '2026-06', '30 Jun 2026', 'Rp 1.240.000', `badge:pending:${C.warning}`, 'Approve'],
    ['INV-202606-002', 'AL002 Ratna', 'IPL', '2026-06', '30 Jun 2026', 'Rp 1.240.000', `badge:unpaid:${C.error}`, 'Detail'],
    ['INV-SP-004', 'BR041 Raka', 'Khusus', '2026-06', '15 Jul 2026', 'Rp 480.000', `badge:draft:${C.muted}`, 'Edit'],
  ]);
  card(cx, cy + 614, 470, 230, { title: 'Drawer Generate Tagihan Bulanan', shadow: true });
  input(cx + 22, cy + 670, '2026-06', { label: 'Periode', w: 220 });
  button(cx + 22, cy + 736, 'Generate', { type: 'primary' });
  card(cx + 500, cy + 614, 530, 230, { title: 'Drawer Tagihan Khusus / Mundur', shadow: true });
  input(cx + 522, cy + 670, 'AL001', { label: 'ID Pelanggan', w: 150 });
  input(cx + 690, cy + 670, '2026-06', { label: 'Periode', w: 150 });
  input(cx + 858, cy + 670, '480000', { label: 'Nominal', w: 150 });
  input(cx + 522, cy + 742, 'Catatan tagihan...', { label: 'Deskripsi', w: 486 });
}

function adminPayments(x, y) {
  shell(x, y, 1440, 960, 'Pembayaran');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Pembayaran', 'Pencarian customer, pembayaran loket, transaksi gateway, dan verifikasi manual.', 'Upload Bukti');
  ['Loket', 'Manual Verification', 'Transaksi Gateway'].forEach((tab, i) => chip(cx + i * 168, cy + 92, tab, i === 0 ? C.brand : C.muted, 154));
  card(cx, cy + 134, 360, 214, { title: 'Cari Customer', shadow: true });
  input(cx + 22, cy + 194, 'AL001', { label: 'ID Pelanggan', w: 220 });
  button(cx + 22, cy + 258, 'Cari Tagihan', { type: 'primary' });
  card(cx + 390, cy + 134, 640, 310, { title: 'AL001 - Budi Santoso', shadow: true });
  table(cx + 408, cy + 184, 604, [
    { label: 'Invoice', w: 150 }, { label: 'Periode', w: 90 }, { label: 'Total', w: 120 }, { label: 'Status', w: 110 }, { label: 'Bayar', w: 90 },
  ], [
    ['INV-0626-001', '2026-06', 'Rp 1.240.000', `badge:unpaid:${C.error}`, 'Pilih'],
    ['INV-0526-001', '2026-05', 'Rp 1.240.000', `badge:paid:${C.success}`, 'Receipt'],
    ['INV-0426-001', '2026-04', 'Rp 1.420.000', `badge:overdue:${C.error}`, 'Pilih'],
  ], { rowH: 38 });
  card(cx, cy + 474, 1030, 260, { title: 'Form Pembayaran Loket', shadow: true });
  input(cx + 22, cy + 534, 'Manual Transfer', { label: 'Metode', w: 210 });
  input(cx + 252, cy + 534, 'Loket Timur', { label: 'Channel', w: 210 });
  input(cx + 482, cy + 534, 'LK-01', { label: 'Kode Loket', w: 160 });
  input(cx + 662, cy + 534, 'Santani', { label: 'Nama Kasir', w: 210 });
  input(cx + 22, cy + 608, 'Catatan pembayaran', { label: 'Catatan', w: 860 });
  button(cx + 22, cy + 676, 'Proses Pembayaran', { type: 'primary', w: 170 });
  card(cx, cy + 758, 505, 150, { title: 'Modal Verifikasi Manual' });
  button(cx + 22, cy + 810, 'Approve', { type: 'primary' });
  button(cx + 122, cy + 810, 'Reject', { type: 'danger' });
}

function adminOps(x, y) {
  shell(x, y, 1440, 960, 'Laporan');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Laporan, Piutang, dan Dokumen', 'Monitoring receivable, laporan bulanan/harian, collector, dan generate PDF.', 'Export PDF');
  statCard(cx, cy + 92, 245, 'Outstanding', 'Rp 483 jt', C.error);
  statCard(cx + 263, cy + 92, 245, 'Aging > 90', 'Rp 96 jt', C.warning);
  statCard(cx + 526, cy + 92, 245, 'Harian Loket', 'Rp 18 jt', C.success);
  statCard(cx + 789, cy + 92, 245, 'Dokumen', 'SPT / SPK', C.info);
  card(cx, cy + 232, 500, 300, { title: 'Piutang Outstanding', shadow: true });
  table(cx + 18, cy + 282, 464, [
    { label: 'Customer', w: 160 }, { label: 'Cluster', w: 100 }, { label: 'Aging', w: 90 }, { label: 'Total', w: 120 },
  ], [
    ['AL002 Ratna', 'Alamanda', '> 90', 'Rp 4.8 jt'],
    ['BR041 Raka', 'Bougenville', '60-90', 'Rp 2.1 jt'],
    ['CD019 Nadia', 'Cendana', '30-60', 'Rp 1.2 jt'],
  ], { rowH: 38 });
  card(cx + 530, cy + 232, 500, 300, { title: 'Rekap Bulanan per Cluster', shadow: true });
  table(cx + 548, cy + 282, 464, [
    { label: 'Cluster', w: 130 }, { label: 'Tagihan', w: 120 }, { label: 'Terbayar', w: 120 }, { label: 'Sisa', w: 100 },
  ], [
    ['Alamanda', 'Rp 120 jt', 'Rp 98 jt', 'Rp 22 jt'],
    ['Bougenville', 'Rp 96 jt', 'Rp 84 jt', 'Rp 12 jt'],
    ['Cendana', 'Rp 80 jt', 'Rp 78 jt', 'Rp 2 jt'],
  ], { rowH: 38 });
  card(cx, cy + 560, 500, 230, { title: 'Generate Dokumen PDF', shadow: true });
  input(cx + 22, cy + 620, '2026-06', { label: 'Periode', w: 180 });
  input(cx + 220, cy + 620, 'GD.2026.06.001', { label: 'Nomor Receipt', w: 220 });
  button(cx + 22, cy + 688, 'Generate SPT', { type: 'primary' });
  button(cx + 142, cy + 688, 'Generate SPK');
  card(cx + 530, cy + 560, 500, 230, { title: 'Collector', shadow: true });
  table(cx + 548, cy + 610, 464, [
    { label: 'Collector', w: 160 }, { label: 'Transaksi', w: 100 }, { label: 'Total', w: 120 },
  ], [
    ['Loket Timur', '42', 'Rp 18 jt'],
    ['Loket Barat', '31', 'Rp 12 jt'],
    ['Mobile CS', '14', 'Rp 5 jt'],
  ], { rowH: 38 });
}

function adminUsersAudit(x, y) {
  shell(x, y, 1440, 960, 'User');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'User dan Audit Log', 'Kelola user, role, permission, status akun, password reset, dan riwayat aktivitas.', 'Tambah User');
  filterBar(cx, cy + 92, ['Cari user', 'Role', 'Status'], 'Tambah');
  card(cx, cy + 190, 500, 340, { title: 'Daftar User', shadow: true });
  table(cx + 18, cy + 240, 464, [
    { label: 'Nama', w: 150 }, { label: 'Role', w: 120 }, { label: 'Status', w: 100 }, { label: 'Aksi', w: 100 },
  ], [
    ['Santani Admin', 'root', `badge:active:${C.success}`, 'Detail'],
    ['Finance GD', 'loket', `badge:active:${C.success}`, 'Reset'],
    ['CS Duta', 'cs', `badge:inactive:${C.muted}`, 'Aktifkan'],
  ], { rowH: 38 });
  card(cx + 530, cy + 190, 500, 340, { title: 'Audit Log', shadow: true });
  table(cx + 548, cy + 240, 464, [
    { label: 'User', w: 120 }, { label: 'Action', w: 150 }, { label: 'Module', w: 120 }, { label: 'Waktu', w: 140 },
  ], [
    ['root', 'created', 'billings', '26 Jun 10:12'],
    ['finance', 'approved', 'payments', '26 Jun 10:18'],
    ['cs', 'updated', 'customers', '26 Jun 11:02'],
  ], { rowH: 38 });
  rect(cx + 608, cy + 456, 475, 330, { rx: 12, fill: C.surface, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  text(cx + 636, cy + 492, 'Drawer Detail Aktivitas', { size: 18, weight: 800 });
  card(cx + 636, cy + 526, 418, 210, { title: 'Perubahan Data' });
  text(cx + 654, cy + 570, 'old.status: pending', { size: 13, fill: C.error });
  text(cx + 654, cy + 598, 'new.status: paid', { size: 13, fill: C.success });
  text(cx + 654, cy + 626, 'ip_address: 127.0.0.1', { size: 13, fill: C.muted });
}

function paymentGateway(x, y) {
  shell(x, y, 1440, 960, 'Payment Gateway');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Payment Gateway', 'Konfigurasi gateway aktif, provider tersedia, manual transfer, timeout, dan callback URL.', 'Simpan');
  card(cx, cy + 92, 1030, 500, { title: 'Gateway Aktif', shadow: true });
  const fields = [
    ['Gateway utama', 'manual'], ['Gateway tersedia', 'manual, xendit, midtrans'], ['Status aktif', 'Aktif'],
    ['Mode', 'sandbox'], ['Mata uang', 'IDR'], ['Biaya administrasi', '5000'],
    ['Public key Xendit', 'xnd_public_***'], ['Client key Midtrans', 'MT_CLIENT_***'],
    ['Callback / webhook URL', 'https://grandduta.test/api/v1/payments/webhooks'],
    ['Bank manual', 'BCA'], ['Nomor rekening', '1234567890'], ['Nama pemilik rekening', 'Duta Indah Residence'],
  ];
  fields.forEach(([name, value], i) => {
    const col = i % 2;
    const row = Math.floor(i / 2);
    input(cx + 24 + col * 490, cy + 158 + row * 62, value, { label: name, w: 450 });
  });
  card(cx, cy + 626, 1030, 220, { title: 'Konfigurasi Public untuk Customer', shadow: true });
  text(cx + 24, cy + 678, 'Secret key, server key, API key, dan callback token tidak pernah ditampilkan di halaman ini.', { size: 13, fill: C.muted, width: 760 });
  chip(cx + 24, cy + 724, 'manual active', C.brand, 118);
  chip(cx + 156, cy + 724, 'xendit enabled', C.info, 126);
  chip(cx + 296, cy + 724, 'midtrans enabled', C.info, 138);
}

function customerShell(x, y, w, h, selected) {
  shell(x, y, w, h, selected, 'customer');
  const menuY = y + 86 + 13 * 38;
  const items = [['H', 'Dashboard Customer'], ['A', 'Akun'], ['U', 'Properti'], ['T', 'Tagihan'], ['B', 'Pembayaran'], ['M', 'Metode Bayar'], ['K', 'Komplain'], ['N', 'Maintenance'], ['D', 'Dokumen'], ['O', 'Notifikasi'], ['V', 'Aktivitas'], ['S', 'Pengaturan']];
  rect(x + 14, y + 86, 222, 510, { rx: 8, fill: C.surface });
  items.forEach((item, index) => {
    const yy = y + 86 + index * 38;
    const active = item[1] === selected;
    rect(x + 14, yy - 6, 222, 34, { rx: 8, fill: active ? '#e6fffb' : 'transparent' });
    iconBox(x + 26, yy - 1, item[0], active);
    text(x + 62, yy + 16, item[1], { size: 13, weight: active ? 800 : 500, fill: active ? C.brand : C.text });
  });
}

function customerDashboard(x, y) {
  customerShell(x, y, 1440, 960, 'Dashboard Customer');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Dashboard Customer', 'Ringkasan akun, unit, tagihan, pembayaran, layanan, dan aktivitas Anda.', 'Refresh');
  const stats = [
    ['Total Tagihan Aktif', 'Rp 4,8 jt', C.error], ['Belum Dibayar', '3 invoice', C.warning], ['Jatuh Tempo', '1 invoice', C.error], ['Pembayaran Berhasil', 'Rp 12,4 jt', C.success],
    ['Sedang Diproses', '2 transaksi', C.info], ['Menunggu Verifikasi', '1 manual', C.warning], ['Komplain Aktif', '1', C.error], ['Maintenance Aktif', '2', C.info],
  ];
  stats.forEach((item, i) => statCard(cx + (i % 4) * 258, cy + 92 + Math.floor(i / 4) * 122, 240, item[0], item[1], item[2]));
  card(cx, cy + 354, 500, 210, { title: 'Informasi Customer', shadow: true });
  text(cx + 22, cy + 408, 'Nama: Budi Santoso', { size: 13 });
  text(cx + 22, cy + 436, 'Email: customer.paid@example.com', { size: 13, fill: C.muted });
  text(cx + 22, cy + 464, 'Nomor Customer: AL001', { size: 13, fill: C.muted });
  text(cx + 22, cy + 492, 'Status: active', { size: 13, fill: C.success });
  card(cx + 530, cy + 354, 500, 210, { title: 'Estate dan Unit', shadow: true });
  text(cx + 552, cy + 408, 'Estate: Duta Indah Residence', { size: 13 });
  text(cx + 552, cy + 436, 'Cluster: Alamanda', { size: 13, fill: C.muted });
  text(cx + 552, cy + 464, 'Unit: A-12 / Tipe Properti: Rumah', { size: 13, fill: C.muted });
  card(cx, cy + 594, 1030, 86, { shadow: true });
  button(cx + 22, cy + 622, 'Pembayaran Cepat', { type: 'primary', w: 150 });
  button(cx + 190, cy + 622, 'Buat Komplain');
  button(cx + 322, cy + 622, 'Buat Maintenance', { w: 140 });
  button(cx + 480, cy + 622, 'Lihat Tagihan');
  button(cx + 610, cy + 622, 'Riwayat Pembayaran', { w: 160 });
  ['Tagihan Terbaru', 'Pembayaran Terbaru', 'Penggunaan Layanan', 'Dokumen Terbaru', 'Notifikasi Terbaru', 'Aktivitas Terbaru'].forEach((titleValue, i) => {
    const px = cx + (i % 3) * 344;
    const py = cy + 710 + Math.floor(i / 3) * 132;
    card(px, py, 326, 112, { title: titleValue, shadow: true });
    text(px + 18, py + 60, i < 2 ? 'INV-202606-001  Rp 1.240.000' : 'Data terbaru dari API customer', { size: 12, fill: C.muted, width: 286 });
  });
}

function customerBills(x, y) {
  customerShell(x, y, 1440, 960, 'Tagihan');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Tagihan Customer', 'Cari, filter, sortir, dan bayar invoice milik Anda.', 'Refresh');
  filterBar(cx, cy + 92, ['Cari invoice', 'Status', 'Periode'], null);
  card(cx, cy + 190, 1030, 340, { title: 'Invoice Table', shadow: true });
  table(cx + 18, cy + 240, 994, [
    { label: 'Invoice', w: 150 }, { label: 'Jenis', w: 120 }, { label: 'Periode', w: 100 }, { label: 'Terbit', w: 110 },
    { label: 'Jatuh Tempo', w: 130 }, { label: 'Total', w: 140 }, { label: 'Status', w: 130 }, { label: 'Aksi', w: 180 },
  ], [
    ['INV-202606-001', 'IPL', '2026-06', '01 Jun', '30 Jun', 'Rp 1.240.000', `badge:unpaid:${C.error}`, 'Detail / Bayar'],
    ['INV-202605-001', 'IPL', '2026-05', '01 Mei', '31 Mei', 'Rp 1.240.000', `badge:paid:${C.success}`, 'Detail'],
    ['INV-202604-001', 'IPL', '2026-04', '01 Apr', '30 Apr', 'Rp 1.420.000', `badge:overdue:${C.error}`, 'Bayar'],
  ]);
  rect(cx + 630, cy + 150, 430, 360, { rx: 12, fill: C.surface, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  text(cx + 658, cy + 186, 'Modal Pilih Metode Pembayaran', { size: 18, weight: 800 });
  card(cx + 658, cy + 220, 374, 210, { title: 'Metode Pembayaran' });
  chip(cx + 680, cy + 274, 'Gateway aktif: manual', C.info, 170);
  text(cx + 680, cy + 322, 'Bank BCA - 1234567890', { size: 13 });
  text(cx + 680, cy + 350, 'Duta Indah Residence', { size: 13, fill: C.muted });
  button(cx + 680, cy + 384, 'Bayar via Manual Transfer', { type: 'primary', w: 210 });
  card(cx, cy + 568, 1030, 260, { title: 'Detail Invoice + Payment History', shadow: true });
  text(cx + 22, cy + 622, 'Subtotal: Rp 1.200.000  | Pajak: Rp 0  | Admin: Rp 40.000  | Total: Rp 1.240.000', { size: 13 });
  table(cx + 22, cy + 660, 986, [
    { label: 'Transaksi', w: 170 }, { label: 'Gateway', w: 120 }, { label: 'Metode', w: 140 }, { label: 'Status', w: 130 }, { label: 'Total', w: 140 }, { label: 'Waktu', w: 160 },
  ], [
    ['TRX-202606-001', 'manual', 'transfer', `badge:waiting:${C.warning}`, 'Rp 1.240.000', '26 Jun 10:11'],
    ['TRX-202605-001', 'xendit', 'va', `badge:paid:${C.success}`, 'Rp 1.240.000', '26 Mei 09:00'],
  ], { rowH: 36 });
}

function customerServices(x, y) {
  customerShell(x, y, 1440, 960, 'Komplain');
  const cx = x + 280;
  const cy = y + 100;
  pageHeader(cx, cy, 'Layanan Customer', 'Komplain, maintenance, dokumen, notifikasi, aktivitas, dan pengaturan akun.', 'Buat Baru');
  filterBar(cx, cy + 92, ['Cari', 'Status', 'Tipe'], 'Buat Baru');
  card(cx, cy + 190, 500, 330, { title: 'Komplain / Maintenance', shadow: true });
  table(cx + 18, cy + 240, 464, [
    { label: 'Judul/Kategori', w: 180 }, { label: 'Prioritas', w: 100 }, { label: 'Status', w: 110 }, { label: 'Aksi', w: 90 },
  ], [
    ['Air mati blok A', 'urgent', `badge:in_review:${C.warning}`, 'Detail'],
    ['Lampu taman', 'normal', `badge:resolved:${C.success}`, 'Detail'],
    ['AC clubhouse', 'high', `badge:scheduled:${C.info}`, 'Detail'],
  ], { rowH: 38 });
  rect(cx + 540, cy + 150, 490, 430, { rx: 12, fill: C.surface, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  text(cx + 568, cy + 188, 'Drawer Buat Komplain', { size: 18, weight: 800 });
  input(cx + 568, cy + 236, 'Air mati blok A', { label: 'Judul', w: 420 });
  input(cx + 568, cy + 306, 'utility', { label: 'Kategori', w: 200 });
  input(cx + 790, cy + 306, 'urgent', { label: 'Prioritas', w: 198 });
  input(cx + 568, cy + 376, 'Deskripsi masalah...', { label: 'Deskripsi', w: 420 });
  button(cx + 568, cy + 448, 'Simpan', { type: 'primary' });
  card(cx, cy + 560, 330, 250, { title: 'Dokumen Customer', shadow: true });
  table(cx + 18, cy + 610, 294, [
    { label: 'Nama', w: 140 }, { label: 'Jenis', w: 70 }, { label: 'Aksi', w: 80 },
  ], [
    ['Invoice Jun', 'PDF', 'Download'],
    ['Receipt May', 'PDF', 'Download'],
  ], { rowH: 36 });
  card(cx + 352, cy + 560, 330, 250, { title: 'Notifikasi Customer', shadow: true });
  text(cx + 374, cy + 616, 'Tagihan baru diterbitkan', { size: 13, weight: 700 });
  text(cx + 374, cy + 644, 'Pembayaran manual menunggu verifikasi', { size: 13, fill: C.muted, width: 270 });
  chip(cx + 374, cy + 680, 'unread', C.warning);
  card(cx + 704, cy + 560, 326, 250, { title: 'Pengaturan Customer', shadow: true });
  input(cx + 726, cy + 624, 'system', { label: 'Tema', w: 120 });
  input(cx + 866, cy + 624, 'Indonesia', { label: 'Bahasa', w: 120 });
  button(cx + 726, cy + 694, 'Simpan Pengaturan', { type: 'primary', w: 160 });
}

function authErrors(x, y) {
  rect(x, y, 1440, 960, { rx: 18, fill: '#0f172a', stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  rect(x + 70, y + 70, 600, 820, { rx: 18, fill: '#132033' });
  text(x + 110, y + 130, 'Login Background', { size: 28, weight: 800, fill: C.surface });
  text(x + 110, y + 168, 'Source memakai Unsplash residence photo dengan overlay gelap.', { size: 13, fill: '#cbd5e1', width: 430 });
  rect(x + 120, y + 250, 430, 230, { rx: 16, fill: '#1e3a3a', stroke: '#2dd4bf55', sw: 1 });
  path(`M ${x + 160} ${y + 420} L ${x + 270} ${y + 320} L ${x + 410} ${y + 420} Z`, { fill: '#2dd4bf33', stroke: '#2dd4bf', sw: 2 });
  rect(x + 205, y + 420, 250, 70, { rx: 8, fill: '#2dd4bf22', stroke: '#2dd4bf88', sw: 1 });
  card(x + 840, y + 190, 420, 420, { shadow: true });
  text(x + 880, y + 250, 'Grand Duta', { size: 32, weight: 800 });
  text(x + 880, y + 282, 'Estate Management', { size: 14, fill: C.muted });
  input(x + 880, y + 340, 'username', { label: 'Username', w: 340 });
  input(x + 880, y + 414, 'password', { label: 'Password', w: 340 });
  button(x + 880, y + 486, 'Login', { type: 'primary', w: 340, h: 38 });
  text(x + 1038, y + 552, 'Lupa password', { size: 13, fill: C.brand, anchor: 'middle' });
  card(x + 70, y + 650, 380, 200, { title: 'Forgot Password' });
  input(x + 92, y + 720, 'Username atau email', { w: 300 });
  button(x + 92, y + 778, 'Kirim Link Reset', { type: 'primary', w: 160 });
  card(x + 490, y + 650, 380, 200, { title: 'Reset Password' });
  input(x + 512, y + 720, 'Token reset', { w: 300 });
  button(x + 512, y + 778, 'Reset Password', { type: 'primary', w: 160 });
  card(x + 910, y + 650, 430, 200, { title: 'Error States' });
  ['401 Unauthorized', '403 Forbidden', '419 Session Expired', '404 Not Found'].forEach((item, i) => {
    chip(x + 934 + (i % 2) * 180, y + 708 + Math.floor(i / 2) * 48, item, i === 1 ? C.error : C.warning, 160);
  });
}

function foundations(x, y) {
  rect(x, y, 1440, 960, { rx: 18, fill: C.surface, stroke: C.border, sw: 1, filter: 'url(#shadow)' });
  frameTitle(x + 56, y + 70, 'Grand Duta UI Foundations', 'Tokens and component inventory generated from React + Ant Design source.');
  const colors = [
    ['Brand', C.brand], ['App BG', C.app], ['Surface', C.surface], ['Text', C.text], ['Muted', C.muted], ['Border', C.border],
    ['Info', C.info], ['Success', C.success], ['Warning', C.warning], ['Error', C.error], ['Dark BG', C.darkApp], ['Dark Surface', C.darkSurface],
  ];
  colors.forEach(([name, color], i) => {
    const px = x + 56 + (i % 6) * 150;
    const py = y + 150 + Math.floor(i / 6) * 104;
    rect(px, py, 112, 64, { rx: 8, fill: color, stroke: C.border, sw: 1 });
    text(px, py + 86, name, { size: 12, weight: 700 });
    text(px, py + 104, color, { size: 11, fill: C.muted });
  });
  text(x + 56, y + 410, 'Typography', { size: 20, weight: 800 });
  text(x + 56, y + 458, 'Display / Inter Bold 32', { size: 32, weight: 800 });
  text(x + 56, y + 502, 'Page Title / Inter Semi Bold 24', { size: 24, weight: 700 });
  text(x + 56, y + 538, 'Body / Inter Regular 14 - aplikasi dashboard padat dan mudah dipindai.', { size: 14 });
  text(x + 56, y + 568, 'Caption / Inter Regular 12', { size: 12, fill: C.muted });
  text(x + 56, y + 640, 'Reusable Patterns', { size: 20, weight: 800 });
  card(x + 56, y + 670, 250, 130, { title: 'Card' });
  statCard(x + 330, y + 670, 250, 'Stat Card', 'Rp 18,4 jt', C.brand);
  input(x + 610, y + 710, 'Cari data', { label: 'Input', w: 220 });
  button(x + 860, y + 710, 'Primary', { type: 'primary' });
  chip(x + 980, y + 714, 'paid', C.success);
  table(x + 56, y + 830, 680, [
    { label: 'Table', w: 160 }, { label: 'Status', w: 110 }, { label: 'Action', w: 100 },
  ], [
    ['ResponsiveTable', `badge:ready:${C.success}`, 'Edit'],
  ], { rowH: 36 });
}

function mobileSample(x, y) {
  rect(x, y, 390, 844, { rx: 32, fill: C.app, stroke: '#111827', sw: 8, filter: 'url(#shadow)' });
  rect(x + 20, y + 22, 350, 48, { rx: 12, fill: C.surface, stroke: C.border, sw: 1 });
  text(x + 42, y + 52, 'Grand Duta', { size: 16, weight: 800, fill: C.brand });
  circle(x + 328, y + 46, 13, { fill: C.surface2 });
  text(x + 42, y + 108, 'Dashboard Customer', { size: 22, weight: 800 });
  text(x + 42, y + 132, 'Ringkasan akun dan tagihan Anda.', { size: 12, fill: C.muted });
  statCard(x + 42, y + 160, 306, 'Total Tagihan Aktif', 'Rp 4,8 jt', C.error);
  statCard(x + 42, y + 280, 306, 'Pembayaran Berhasil', 'Rp 12,4 jt', C.success);
  card(x + 42, y + 410, 306, 160, { title: 'Tagihan Terbaru', shadow: true });
  text(x + 62, y + 466, 'INV-202606-001', { size: 13, weight: 800 });
  text(x + 62, y + 492, 'Jatuh tempo 30 Jun 2026', { size: 12, fill: C.muted });
  chip(x + 62, y + 514, 'unpaid', C.error);
  button(x + 220, y + 510, 'Bayar', { type: 'primary', w: 88 });
  card(x + 42, y + 600, 306, 128, { title: 'Quick Actions', shadow: true });
  button(x + 62, y + 654, 'Komplain');
  button(x + 160, y + 654, 'Maintenance', { w: 112 });
  rect(x + 70, y + 774, 250, 48, { rx: 24, fill: C.surface, stroke: C.border, sw: 1 });
  ['D', 'T', 'B', 'A'].forEach((item, i) => iconBox(x + 94 + i * 56, y + 786, item, i === 0));
}

function draw() {
  foundations(80, 80);
  authErrors(1600, 80);
  adminDashboard(3120, 80);
  adminCustomers(80, 1120);
  adminBillings(1600, 1120);
  adminPayments(3120, 1120);
  adminOps(80, 2160);
  adminUsersAudit(1600, 2160);
  paymentGateway(3120, 2160);
  customerDashboard(80, 3200);
  customerBills(1600, 3200);
  customerServices(3120, 3200);
  mobileSample(4170, 3240);
  text(80, 40, 'Grand Duta Estate Management - Figma Import Canvas', { size: 28, weight: 800 });
  text(80, 66, 'Generated from frontend React source. Import this SVG into Figma if MCP write calls are rate-limited.', { size: 13, fill: C.muted });
}

out.push(`<?xml version="1.0" encoding="UTF-8"?>`);
out.push(`<svg xmlns="http://www.w3.org/2000/svg" width="4720" height="4240" viewBox="0 0 4720 4240">`);
out.push(`<defs>
  <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
    <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#0f172a" flood-opacity="0.10"/>
  </filter>
  <style>
    text { font-family: Inter, Arial, sans-serif; dominant-baseline: alphabetic; }
  </style>
</defs>`);
rect(0, 0, 4720, 4240, { fill: '#e9eef6' });
draw();
out.push('</svg>');

const svg = out.join('\n');
const svgPath = resolve(root, 'docs', 'grand-duta-figma-ui.svg');
writeFileSync(svgPath, svg, 'utf8');

const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Grand Duta Figma UI Preview</title>
  <style>
    body { margin: 0; background: #e9eef6; font-family: Inter, Arial, sans-serif; }
    .toolbar { position: sticky; top: 0; z-index: 1; padding: 12px 16px; background: white; border-bottom: 1px solid #d9e0ea; }
    .toolbar strong { color: #0f172a; }
    .toolbar span { color: #64748b; margin-left: 8px; }
    .canvas { width: 4720px; max-width: none; }
  </style>
</head>
<body>
  <div class="toolbar">
    <strong>Grand Duta Estate Management UI</strong>
    <span>Preview of docs/grand-duta-figma-ui.svg. Import the SVG into Figma when MCP writes are available.</span>
  </div>
  <img class="canvas" src="./grand-duta-figma-ui.svg" alt="Grand Duta UI canvas">
</body>
</html>`;
writeFileSync(resolve(root, 'docs', 'grand-duta-figma-ui.html'), html, 'utf8');

console.log(`Generated ${svgPath}`);
