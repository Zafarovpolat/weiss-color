// main.js — общая инициализация: мобильное меню, модальное окно звонка.
// Чистый JS без сборщика; подключается на страницах с defer.

/* ---------- Мобильное меню (п.3.1 / п.3.3 ТЗ) ---------- */
(() => {
  const burger = document.querySelector('.burger');
  const nav = document.querySelector('.site-nav');
  if (!burger || !nav) return;

  const label = burger.querySelector('.visually-hidden');
  /* 1280 — граница, на которой бургер скрывается и меню раскрыто всегда
     (см. layout.css / responsive.css), до неё меню — оверлей */
  const desktop = window.matchMedia('(min-width: 1280px)');

  const setOpen = (open) => {
    burger.setAttribute('aria-expanded', String(open));
    nav.toggleAttribute('data-open', open);
    if (label) label.textContent = open ? 'Скрыть меню' : 'Показать меню';
  };

  burger.addEventListener('click', () => {
    setOpen(burger.getAttribute('aria-expanded') !== 'true');
  });

  // Esc закрывает меню; фокус возвращаем на кнопку
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
      burger.focus();
    }
  });

  // Клик по пункту меню закрывает его (навигация происходит по ссылке)
  nav.addEventListener('click', (e) => {
    if (e.target.closest('a') && !desktop.matches) setOpen(false);
  });

  // На десктопе меню всегда раскрыто — сбрасываем состояние
  desktop.addEventListener('change', (e) => { if (e.matches) setOpen(false); });
})();

/* ---------- Окно «Спасибо» (М17) и блокировка прокрутки ---------- */
(() => {
  const scriptSrc = document.currentScript && document.currentScript.src;
  const logoUrl = scriptSrc
    ? new URL('../assets/img/logo.svg', scriptSrc).href
    : '../assets/img/logo.svg';

  const RESULT_HTML = `
    <button class="mcard__close" type="button" data-modal-close aria-label="Закрыть окно">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
        <path d="M3 3l18 18M21 3 3 21" stroke-linecap="round"/>
      </svg>
    </button>
    <div class="callback-photo">
      <img class="callback-photo__logo" src="${logoUrl}" width="186" height="52" alt="Weiss Color">
      <hr class="callback-photo__rule">
      <p class="callback-photo__slogan">Вдохновляем<br>на красивые<br>интерьеры</p>
    </div>
    <div class="callback-result" role="status" aria-live="polite">
      <p class="callback-result__icon" aria-hidden="true">
        <svg viewBox="0 0 72 51" fill="none" stroke="currentColor" stroke-width="6">
          <path d="M5 26 26 46 67 5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </p>
      <h2 class="callback-result__title" id="form-result-title">Спасибо!</h2>
      <p class="callback-result__text">
        Мы получили ваше обращение и в ближайшее время свяжемся с вами
      </p>
    </div>`;

  /* Пока открыт любой <dialog>, страница не прокручивается */
  const syncScrollLock = () => {
    const open = Boolean(document.querySelector('dialog[open]'));
    document.documentElement.classList.toggle('is-modal-open', open);
    document.body.classList.toggle('is-modal-open', open);
  };

  const wireDialog = (dialog) => {
    dialog.addEventListener('toggle', syncScrollLock);
    dialog.addEventListener('close', syncScrollLock);
  };

  /* Окно результата одно на страницу; если его нет в разметке — создаём */
  const getResultDialog = () => {
    let dialog = document.getElementById('form-result-dialog');
    if (dialog) return dialog;

    dialog = document.createElement('dialog');
    dialog.className = 'mcard mcard-callback mcard-result';
    dialog.id = 'form-result-dialog';
    dialog.setAttribute('aria-labelledby', 'form-result-title');
    dialog.innerHTML = RESULT_HTML;
    document.body.appendChild(dialog);

    dialog.querySelector('[data-modal-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) dialog.close();
    });
    wireDialog(dialog);
    return dialog;
  };

  const showResult = () => {
    const dialog = getResultDialog();
    if (typeof dialog.showModal === 'function') dialog.showModal();
  };

  document.querySelectorAll('dialog').forEach(wireDialog);
  syncScrollLock();

  window.WCModal = { showResult };
})();

/* ---------- Модальное окно «Заказать звонок» (М25, п.9.3) ---------- */
(() => {
  const dialog = document.getElementById('callback-dialog');

  if (dialog && typeof dialog.showModal === 'function') {
    document.querySelectorAll('[data-modal-open]').forEach((btn) => {
      btn.addEventListener('click', () => dialog.showModal());
    });

    dialog.querySelector('[data-modal-close]')?.addEventListener('click', () => dialog.close());

    // Клик по подложке закрывает окно (клиент по краю — не кнопка)
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog) dialog.close();
    });
  }

  /* Демо-отправка: на этапе 1 бэкенда нет. Поля проверяет validation.js,
     после успеха показываем окно «Спасибо» поверх текущей страницы. */
  document.querySelectorAll('#callback-form, #callback-modal-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const valid = window.WCForm ? window.WCForm.validate(form) : form.checkValidity();
      if (!valid) return;

      form.closest('dialog')?.close();
      form.reset();
      window.WCForm?.clear(form);
      window.WCModal?.showResult();
    });
  });
})();

/* ---------- Счётчик количества вне корзины (М06 «Пробник») ----------
   cart.js работает только при наличии .cart-list и на этой странице
   выходит сразу, поэтому кнопки «−»/«+» обрабатываются здесь.
   Правила те же: целое число не меньше min (по умолчанию 1). */
(() => {
  const LIMITS = (input) => ({
    min: input.min === '' ? 1 : Number(input.min),
    max: input.max === '' ? Infinity : Number(input.max),
    step: Number(input.step) || 1
  });

  /* Приводит любое число к допустимому: целое, в границах, кратно шагу */
  const normalize = (input, value) => {
    const { min, max, step } = LIMITS(input);
    let result = Math.round(value);
    if (!Number.isFinite(result)) result = min;
    result = Math.min(Math.max(result, min), max);
    // выравнивание по шагу от min, как у нативного input[type=number]
    if (step > 0) result = min + Math.round((result - min) / step) * step;
    return result;
  };

  const currentValue = (input) => normalize(input, Number(String(input.value).replace(',', '.')));

  const setValue = (input, value) => {
    if (input.value === String(value)) return;
    input.value = String(value);
    // событие нужно, чтобы validation.js снял ошибку под полем
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-qty]');
    // в корзине счётчиком управляет cart.js — не дублируем
    if (!button || button.closest('.cart-list')) return;

    const input = button.closest('.qty')?.querySelector('.qty__input');
    if (!input || input.disabled) return;

    const { step } = LIMITS(input);
    const direction = button.getAttribute('data-qty') === 'inc' ? 1 : -1;
    // границы применяем к результату, иначе «−» на минимуме уводит в 0
    setValue(input, normalize(input, currentValue(input) + direction * step));
  });

  // ручной ввод: приводим к допустимому значению при подтверждении
  document.addEventListener('change', (event) => {
    const input = event.target.closest?.('.qty__input');
    if (!input || input.closest('.cart-list')) return;
    setValue(input, normalize(input, Number(String(input.value).replace(',', '.'))));
  });
})();

/* ---------- Объём упаковки на карточке товара ----------
   Кнопки — toggle-группа: активной может быть только одна. */
(() => {
  const groups = document.querySelectorAll('.p-volumes');
  if (!groups.length) return;

  groups.forEach((group) => {
    group.addEventListener('click', (event) => {
      const button = event.target.closest('.p-volumes__item');
      if (!button || !group.contains(button)) return;

      group.querySelectorAll('.p-volumes__item').forEach((item) => {
        item.setAttribute('aria-pressed', String(item === button));
      });
    });
  });
})();

/* ---------- Каталог оттенков на странице визуализации ----------
   Кастомный селект: кнопка + список опций (как .pselect на palette-selector). */
(() => {
  const palette = document.querySelector('.viz-palette');
  if (!palette) return;

  const control = palette.querySelector('.viz-select');
  const label = palette.querySelector('.viz-select__label');
  const options = Array.from(palette.querySelectorAll('.viz-palette__option'));

  const setOpen = (open) => {
    palette.classList.toggle('is-open', open);
    control.setAttribute('aria-expanded', String(open));
  };

  control.addEventListener('click', () => {
    setOpen(control.getAttribute('aria-expanded') !== 'true');
  });

  options.forEach((option) => {
    const choose = () => {
      options.forEach((item) => item.setAttribute('aria-selected', String(item === option)));
      label.textContent = 'Каталог ' + option.textContent.trim();
      setOpen(false);
      control.focus();
    };

    option.addEventListener('click', choose);
    option.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        choose();
      }
    });
  });

  document.addEventListener('click', (event) => {
    if (!palette.contains(event.target)) setOpen(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && palette.classList.contains('is-open')) {
      setOpen(false);
      control.focus();
    }
  });
})();

/* ---------- Крестик в окнах-демо (М06, М17, М20, М23, М25) ----------
   На реальном сайте это модальное окно и оно просто закрывается. Отдельные
   страницы-демо показывают его на подложке, поэтому «Закрыть» возвращает
   на предыдущий экран — раньше кнопка не делала ничего. */
(() => {
  const buttons = document.querySelectorAll('.mcard__close');
  if (!buttons.length) return;

  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const host = btn.closest('dialog');
      if (host) { host.close(); return; }
      if (history.length > 1) history.back();
      else window.location.href = 'catalog.html';
    });
  });
})();
