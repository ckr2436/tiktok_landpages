define([], function () {
    'use strict';

    function initDropzones() {
        document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
            var input = zone.querySelector('[data-file-input]');
            var meta = zone.querySelector('[data-file-meta]');

            if (!input || !meta) {
                return;
            }

            var refreshMeta = function () {
                if (input.files && input.files.length > 0) {
                    var file = input.files[0];
                    var sizeKb = Math.round(file.size / 1024);
                    meta.textContent = file.name + ' (' + sizeKb + ' KB)';
                } else {
                    meta.textContent = 'No file selected';
                }
            };

            zone.addEventListener('click', function (e) {
                if (e.target === input) {
                    return;
                }
                input.click();
            });

            input.addEventListener('change', refreshMeta);

            zone.addEventListener('dragover', function (e) {
                e.preventDefault();
                zone.classList.add('is-dragover');
            });

            zone.addEventListener('dragleave', function () {
                zone.classList.remove('is-dragover');
            });

            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                zone.classList.remove('is-dragover');

                if (!e.dataTransfer || !e.dataTransfer.files || e.dataTransfer.files.length === 0) {
                    return;
                }

                input.files = e.dataTransfer.files;
                refreshMeta();
            });
        });
    }

    return function () {
        initDropzones();
    };
});
