document.addEventListener("DOMContentLoaded", function() {
    if (typeof bkLib !== 'undefined') {
        bkLib.onDomLoaded(function() {
            // Initialize NicEditor
            var tpEditor = new nicEditor({
                fullPanel: true,
                iconsPath: 'vendor/nicedit/images/nicEditIcons-latest.gif'
            }).panelInstance('textpad-editor');

            // Force NicEditor container and main editing area to be 100% width
            // NicEditor sometimes sets hardcoded inline widths, this overrides it
            setTimeout(() => {
                const nicContainers = document.querySelectorAll('.nicEdit-panelContain');
                const nicMains = document.querySelectorAll('.nicEdit-main');

                nicContainers.forEach(container => {
                    container.style.width = '100%';
                });

                nicMains.forEach(main => {
                    main.style.width = '100%';
                    if (main.parentElement) {
                        main.parentElement.style.width = '100%';
                    }
                });
            }, 50);
        });
    }
});
