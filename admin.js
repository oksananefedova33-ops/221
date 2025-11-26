document.addEventListener('click', function (e) {
    var target = e.target;
    if (target.matches('[data-confirm]')) {
        var msg = target.getAttribute('data-confirm') || 'Вы уверены?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    }
});
