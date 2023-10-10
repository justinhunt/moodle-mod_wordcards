define([], function () {

    var btn = document.querySelector('#deleteconfirmation');
    var table = document.querySelector('#region-main .table');

    table.addEventListener('change', function () {
        if (table.querySelectorAll(':checked').length > 0) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    });
});