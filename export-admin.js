// /ui/export-admin/export-admin.js
(function () {
  'use strict';

  // Добавляем блок с админ‑панелью и чекбоксом "без статистики"
  function addAdminFields(modal) {
    if (!modal) return;
    if (modal.querySelector('[data-admin-panel-block]')) return;

    var dlg = modal.querySelector('.xmodal__dlg') || modal;
    var actions = dlg.querySelector('.xmodal__actions');
    if (!actions) return;

    var row = document.createElement('div');
    row.className = 'xmodal__row';
    row.setAttribute('data-admin-panel-block', '1');

    row.innerHTML =
      '<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
      '  <input type="checkbox" id="expWithAdmin">' +
      '  <span>Экспортировать с админ‑панелью</span>' +
      '</label>' +
      '<div id="expAdminSettings" style="display:none;gap:8px;grid-template-columns:1fr 1fr 1fr;grid-auto-flow:row;margin-bottom:8px;">' +
      '  <div>' +
      '    <div style="font-size:13px;margin-bottom:4px;">Директория админки</div>' +
      '    <input type="text" name="admin_dir" placeholder="admin" style="width:100%;">' +
      '  </div>' +
      '  <div>' +
      '    <div style="font-size:13px;margin-bottom:4px;">Логин</div>' +
      '    <input type="text" name="admin_login" placeholder="admin" style="width:100%;">' +
      '  </div>' +
      '  <div>' +
      '    <div style="font-size:13px;margin-bottom:4px;">Пароль</div>' +
      '    <input type="password" name="admin_password" placeholder="пароль" style="width:100%;">' +
      '  </div>' +
      '</div>' +
      '<label style="display:flex;align-items:center;gap:8px;margin-top:4px;">' +
      '  <input type="checkbox" id="expAdminNoStats">' +
      '  <span>Экспортировать <b>без</b> статистики и внешних трекеров</span>' +
      '</label>';

    dlg.insertBefore(row, actions);

    var checkbox = row.querySelector('#expWithAdmin');
    var settings = row.querySelector('#expAdminSettings');

    checkbox.addEventListener('change', function () {
      var on = checkbox.checked;
      settings.style.display = on ? 'grid' : 'none';
    });
  }

  function patchOpenExportModal() {
    var original = window.openExportModal;
    if (typeof original !== 'function') return;

    window.openExportModal = function () {
      var res = original.apply(this, arguments);

      setTimeout(function () {
        var modal = document.querySelector('.xmodal');
        if (!modal) return;

        addAdminFields(modal);

        var goBtn = modal.querySelector('#expGo');
        if (!goBtn || goBtn.dataset.adminPatched === '1') return;
        goBtn.dataset.adminPatched = '1';

        var cancel = modal.querySelector('#expCancel');
        var bg = modal.querySelector('.xmodal__bg');
        var container = modal.parentNode || modal;

        function closeModal() {
          if (container && container.parentNode) {
            container.parentNode.removeChild(container);
          }
        }

        function valWWW() {
          var radios = modal.querySelectorAll('input[name="expWWW"]');
          for (var i = 0; i < radios.length; i++) {
            if (radios[i].checked) return radios[i].value;
          }
          return 'keep';
        }

        if (bg && !bg.dataset.adminPatchedClose) {
          bg.dataset.adminPatchedClose = '1';
          bg.onclick = closeModal;
        }
        if (cancel && !cancel.dataset.adminPatchedClose) {
          cancel.dataset.adminPatchedClose = '1';
          cancel.onclick = closeModal;
        }

        goBtn.onclick = function () {
          var domainInput = modal.querySelector('#expDomain');
          var httpsInput = modal.querySelector('#expHttps');
          var langSelect = modal.querySelector('#expLang');
          var forceInput = modal.querySelector('#expForce');

          var d = domainInput && domainInput.value ? domainInput.value.trim() : '';
          if (d && !/^https?:\/\//i.test(d)) d = 'https://' + d;
          if (d && !/^(https?:\/\/)[a-z0-9.\-]+(?::\d+)?\/?$/i.test(d)) {
            alert('Введите корректный домен');
            return;
          }

          var https = httpsInput && httpsInput.checked ? 1 : 0;
          var www = valWWW();
          var force = forceInput && forceInput.checked ? 1 : 0;
          var lang = langSelect && langSelect.value ? langSelect.value.trim() : 'ru';

          try {
            localStorage.setItem('export_domain', d || '');
            localStorage.setItem('export_https', String(https));
            localStorage.setItem('export_www_mode', www);
            localStorage.setItem('export_force_host', String(force));
            localStorage.setItem('export_primary_lang', lang);
          } catch (e) {}

          var qs = new URLSearchParams({
            action: 'export',
            domain: d,
            https: String(https),
            www_mode: www,
            force_host: String(force),
            primary_lang: lang,
            seo_per_lang: localStorage.getItem('export_seo_per_lang') || '1',
            seo_lang:     localStorage.getItem('export_seo_lang')      || lang,
            seo_langs:    localStorage.getItem('export_seo_langs')     || ''
          });

          // Админ‑панель
          var withAdminCheckbox = modal.querySelector('#expWithAdmin');
          if (withAdminCheckbox && withAdminCheckbox.checked) {
            qs.set('with_admin', '1');
            var dirInput   = modal.querySelector('input[name="admin_dir"]');
            var loginInput = modal.querySelector('input[name="admin_login"]');
            var passInput  = modal.querySelector('input[name="admin_password"]');
            if (dirInput && dirInput.value.trim())   qs.set('admin_dir', dirInput.value.trim());
            if (loginInput && loginInput.value.trim()) qs.set('admin_login', loginInput.value.trim());
            if (passInput && passInput.value)        qs.set('admin_password', passInput.value);
          } else {
            qs.set('with_admin', '0');
          }

          // Галочка "без статистики"
          var noStatsCheckbox = modal.querySelector('#expAdminNoStats');
          var adminStats = (!noStatsCheckbox || !noStatsCheckbox.checked) ? '1' : '0';
          qs.set('admin_stats', adminStats);

          // Галочка "без удалённого управления"
          var noRemoteApiCheckbox = modal.querySelector('#expNoRemoteApi');
          if (noRemoteApiCheckbox && noRemoteApiCheckbox.checked) {
            qs.set('no_remote_api', '1');
          }

          window.location.href = '/editor/export.php?' + qs.toString();
          closeModal();
        };
      }, 0);

      return res;
    };
  }

  document.addEventListener('DOMContentLoaded', patchOpenExportModal);
})();
