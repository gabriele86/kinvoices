/*
 * Invoice lines: add/remove rows from the Symfony CollectionType prototype and
 * keep the "Total with VAT" column and the invoice totals in sync while typing.
 *
 * The server recomputes every total on submit, so this is purely a live preview.
 */
(function () {
    'use strict';

    var container = document.getElementById('invoice-lines');
    if (!container) {
        return;
    }

    var addButton = document.getElementById('add-invoice-line');

    function toNumber(value) {
        var parsed = parseFloat(String(value).replace(',', '.'));

        return isNaN(parsed) ? 0 : parsed;
    }

    function format(value) {
        return value.toFixed(2);
    }

    function target(row, name) {
        return row.querySelector('[data-invoice-line-target="' + name + '"]');
    }

    function refreshRow(row) {
        var amount = target(row, 'amount');
        var vat = target(row, 'vat');
        var total = target(row, 'total');

        if (!amount || !vat || !total) {
            return 0;
        }

        var value = toNumber(amount.value) + toNumber(vat.value);
        total.value = format(value);

        return value;
    }

    function refreshAll() {
        var amountTotal = 0;
        var vatTotal = 0;
        var grandTotal = 0;

        Array.prototype.forEach.call(container.querySelectorAll('.invoice-line'), function (row) {
            grandTotal += refreshRow(row);
            amountTotal += toNumber((target(row, 'amount') || {}).value);
            vatTotal += toNumber((target(row, 'vat') || {}).value);
        });

        var footers = {amount: amountTotal, vat: vatTotal, total: grandTotal};
        Object.keys(footers).forEach(function (key) {
            var cell = document.querySelector('[data-invoice-total="' + key + '"]');
            if (cell) {
                cell.textContent = format(footers[key]);
            }
        });

        updateRemoveButtons();
    }

    function updateRemoveButtons() {
        var rows = container.querySelectorAll('.invoice-line');
        // An invoice must keep at least one line.
        Array.prototype.forEach.call(rows, function (row) {
            var button = row.querySelector('[data-remove-line]');
            if (button) {
                button.disabled = rows.length < 2;
            }
        });
    }

    function addRow() {
        var index = parseInt(container.dataset.index, 10) || 0;
        var html = container.dataset.prototype.replace(/__line__/g, String(index));

        var template = document.createElement('tbody');
        template.innerHTML = html.trim();

        var row = template.firstElementChild;
        container.appendChild(row);
        container.dataset.index = String(index + 1);

        var firstInput = row.querySelector('textarea, input');
        if (firstInput) {
            firstInput.focus();
        }

        refreshAll();
    }

    if (addButton) {
        addButton.addEventListener('click', addRow);
    }

    container.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-line]');
        if (!button) {
            return;
        }

        var row = button.closest('.invoice-line');
        if (row && container.querySelectorAll('.invoice-line').length > 1) {
            row.parentNode.removeChild(row);
            refreshAll();
        }
    });

    container.addEventListener('input', function (event) {
        if (event.target.matches('[data-invoice-line-target="amount"], [data-invoice-line-target="vat"]')) {
            refreshAll();
        }
    });

    refreshAll();
})();
