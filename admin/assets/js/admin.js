/**
 * Admin Panel — Global JavaScript
 * Exam6Lock — Sistem Ujian Online
 */
(function () {
  'use strict';

  // ─── Sidebar Toggle ─────────────────────────────
  window.toggleSidebar = function () {
    document.querySelector('.sidebar')?.classList.toggle('show');
    document.querySelector('.overlay')?.classList.toggle('show');
  };

  // ─── Toast Notification ──────────────────────────
  window.showToast = function (message, type) {
    if (!type) type = 'success';
    var container = document.getElementById('toastNotification');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastNotification';
      container.className = 'toast-notification';
      document.body.appendChild(container);
    }
    var icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill'
    };
    container.innerHTML =
      '<div class="toast toast-' + type + '">' +
        '<div class="toast-content">' +
          '<i class="bi ' + (icons[type] || icons.success) + ' toast-icon"></i>' +
          '<div class="toast-body">' +
            '<strong>' + (type === 'success' ? 'Berhasil!' : type === 'error' ? 'Gagal!' : 'Peringatan!') + '</strong>' +
            '<small>' + message + '</small>' +
          '</div>' +
        '</div>' +
      '</div>';
    container.classList.add('show');
    setTimeout(function () {
      container.classList.remove('show');
      container.innerHTML = '';
    }, 3200);
  };

  // ─── Close sidebar on mobile after link click ────
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sidebar a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 992) {
          document.querySelector('.sidebar')?.classList.remove('show');
          document.querySelector('.overlay')?.classList.remove('show');
        }
      });
    });

    // ─── Cegah dobel klik — disable tombol pas submit ────
    document.addEventListener('submit', function (e) {
      var btn = e.target.querySelector('button[type="submit"]');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      btn.dataset.html = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Memproses...';
    });

    // ─── Loading state untuk link export ────
    document.querySelectorAll('a[href*="ekspor"], a[href*="export"]').forEach(function (link) {
      link.addEventListener('click', function () {
        if (link.dataset.loading === '1') return;
        link.dataset.loading = '1';
        link.dataset.html = link.innerHTML;
        link.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyiapkan...';
      });
    });
  });

})();
