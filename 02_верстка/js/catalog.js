// catalog.js — каталог (п.4): фильтр категорий (мультивыбор),
// диапазон цены (цветная полоска тянется между точками), сортировки,
// пагинация, пустая выдача. На этапе вёрстки — на демо-данных.

// ---------------------------------------------------------------------------
// Кастомный дропдаун сортировки.
// Нативный <select> нельзя оформить под макет (список рисует ОС), поэтому
// разметка — кнопка + ul[role="listbox"], а поведение повторяет select:
// клик/Enter/Space открывают, стрелки ходят по пунктам, Esc и клик мимо
// закрывают, выбранный пункт подставляется в кнопку.
// ---------------------------------------------------------------------------
(function () {
  "use strict";

  var root = document.querySelector("[data-sort]");
  if (!root) return;

  var trigger = root.querySelector(".sort__trigger");
  var valueEl = root.querySelector(".sort__value");
  var list = root.querySelector(".sort__list");
  var options = Array.prototype.slice.call(root.querySelectorAll(".sort__option"));
  if (!trigger || !valueEl || !list || !options.length) return;

  var selected = 0;
  options.forEach(function (opt, i) {
    if (opt.getAttribute("aria-selected") === "true") selected = i;
  });

  function isOpen() {
    return trigger.getAttribute("aria-expanded") === "true";
  }

  function highlight(index) {
    options.forEach(function (opt, i) {
      opt.classList.toggle("is-active", i === index);
    });
  }

  function open() {
    list.hidden = false;
    trigger.setAttribute("aria-expanded", "true");
    root.classList.add("is-open");
    highlight(selected);
    options[selected].focus();
  }

  function close(refocus) {
    if (!isOpen()) return;
    list.hidden = true;
    trigger.setAttribute("aria-expanded", "false");
    root.classList.remove("is-open");
    options.forEach(function (opt) { opt.classList.remove("is-active"); });
    if (refocus) trigger.focus();
  }

  function choose(index) {
    selected = index;
    options.forEach(function (opt, i) {
      opt.setAttribute("aria-selected", i === index ? "true" : "false");
    });
    valueEl.textContent = options[index].textContent.trim();
    trigger.setAttribute("data-value", options[index].getAttribute("data-value") || "");
    close(true);
    // на этапе 2 здесь будет пересортировка выдачи по выбранному значению
  }

  trigger.addEventListener("click", function () {
    if (isOpen()) close(true);
    else open();
  });

  trigger.addEventListener("keydown", function (event) {
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      open();
    }
  });

  list.addEventListener("keydown", function (event) {
    var i = options.indexOf(document.activeElement);

    if (event.key === "ArrowDown") {
      event.preventDefault();
      var next = (i + 1) % options.length;
      highlight(next);
      options[next].focus();
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      var prev = (i - 1 + options.length) % options.length;
      highlight(prev);
      options[prev].focus();
    } else if (event.key === "Home") {
      event.preventDefault();
      highlight(0);
      options[0].focus();
    } else if (event.key === "End") {
      event.preventDefault();
      highlight(options.length - 1);
      options[options.length - 1].focus();
    } else if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      if (i >= 0) choose(i);
    } else if (event.key === "Escape") {
      event.preventDefault();
      close(true);
    } else if (event.key === "Tab") {
      close(false);
    }
  });

  options.forEach(function (opt, i) {
    opt.addEventListener("click", function () { choose(i); });
    opt.addEventListener("mousemove", function () { highlight(i); });
  });

  // клик или фокус вне компонента закрывают список
  document.addEventListener("click", function (event) {
    if (isOpen() && !root.contains(event.target)) close(false);
  });
  document.addEventListener("focusin", function (event) {
    if (isOpen() && !root.contains(event.target)) close(false);
  });
})();

(function () {
  "use strict";

  var range = document.querySelector(".range");
  if (!range) return;

  var inputs = range.querySelectorAll('input[type="range"]');
  var fill = range.querySelector(".range__fill");
  var outputs = document.querySelectorAll(".filter__prices output");

  if (inputs.length !== 2 || !fill) return;

  var min = Number(inputs[0].min) || 0;
  var max = Number(inputs[0].max) || 100;

  function formatPrice(value) {
    return String(Math.round(value)).replace(/\B(?=(\d{3})+(?!\d))/g, "\u00A0");
  }

  function render() {
    var a = Number(inputs[0].value);
    var b = Number(inputs[1].value);

    // левая точка не может уехать правее правой (и наоборот)
    if (a > b) {
      if (document.activeElement === inputs[0]) {
        inputs[1].value = a;
        b = a;
      } else {
        inputs[0].value = b;
        a = b;
      }
    }

    var span = max - min || 1;
    var left = ((a - min) / span) * 100;
    var right = 100 - ((b - min) / span) * 100;
    fill.style.left = left.toFixed(2) + "%";
    fill.style.right = right.toFixed(2) + "%";

    if (outputs.length === 2) {
      outputs[0].textContent = formatPrice(a);
      outputs[1].textContent = formatPrice(b);
    }
  }

  inputs.forEach(function (input) {
    input.addEventListener("input", render);
  });

  render();
})();
