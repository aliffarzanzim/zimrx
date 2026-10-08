// Free-text notepad module initializing the rich-text NicEditor instance.
document.addEventListener("DOMContentLoaded", function() {
    if (typeof bkLib !== 'undefined') {
        bkLib.onDomLoaded(function() {
            // Initialize NicEditor
            var tpEditor = new nicEditor({
                fullPanel: true,
                iconsPath: 'assets/vendor/nicedit/images/nicEditIcons-latest.gif'
            }).panelInstance('textpad-editor');

            // Override hardcoded inline widths to keep editor full width
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
