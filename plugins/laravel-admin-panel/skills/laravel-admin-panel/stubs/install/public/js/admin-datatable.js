/*
 * Dependency-free admin data tables.
 * Wrap a <table> in <div class="data-table-wrap"> to enable:
 *   - a text filter: <input class="data-table-search"> inside the wrap
 *   - sortable columns: <th data-sort="text|number">; a cell may carry
 *     data-value="..." to sort by something other than its visible text
 * Both work on the rows of the current page only (pagination stays server-side).
 */
(function () {
  function enhanceTable(wrap) {
    var table = wrap.querySelector('table');
    if (!table || !table.tBodies.length) {
      return;
    }

    var tbody = table.tBodies[0];
    var searchInput = wrap.querySelector('.data-table-search');

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        var q = searchInput.value.trim().toLowerCase();
        Array.prototype.forEach.call(tbody.rows, function (row) {
          var visible = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
          row.style.display = visible ? '' : 'none';
        });
      });
    }

    Array.prototype.forEach.call(table.querySelectorAll('thead th[data-sort]'), function (th) {
      th.classList.add('is-sortable');

      th.addEventListener('click', function () {
        var headerRow = th.parentNode;
        var index = Array.prototype.indexOf.call(headerRow.children, th);
        var nextDir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';

        Array.prototype.forEach.call(headerRow.children, function (h) {
          h.removeAttribute('data-sort-dir');
        });
        th.setAttribute('data-sort-dir', nextDir);

        var type = th.getAttribute('data-sort');
        var rows = Array.prototype.slice.call(tbody.rows);

        rows.sort(function (a, b) {
          var cellA = a.children[index];
          var cellB = b.children[index];
          var av = cellA ? (cellA.getAttribute('data-value') || cellA.textContent.trim()) : '';
          var bv = cellB ? (cellB.getAttribute('data-value') || cellB.textContent.trim()) : '';

          if (type === 'number') {
            av = parseFloat(String(av).replace(/[^0-9.-]/g, '')) || 0;
            bv = parseFloat(String(bv).replace(/[^0-9.-]/g, '')) || 0;
            return nextDir === 'asc' ? av - bv : bv - av;
          }

          return nextDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
        });

        rows.forEach(function (row) {
          tbody.appendChild(row);
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('.data-table-wrap'), enhanceTable);
  });
})();
