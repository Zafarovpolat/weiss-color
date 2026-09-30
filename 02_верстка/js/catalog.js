// catalog.js — каталог (п.4): фильтр категорий (мультивыбор),
// диапазон цены (цветная полоска тянется между точками), сортировки,
// пагинация, пустая выдача. На этапе вёрстки — на демо-данных.

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
