// Clinical exam preset chips: appends pre-configured exam phrases to module textareas.
(function() {
  'use strict';

  function initExamPresets() {
    // Click handlers for preset examination chips
    document.querySelectorAll('.zrx-exam-chip').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const wrapper = this.closest('.zrx-exam-wrapper');
        if (!wrapper) return;
        const textarea = wrapper.querySelector('.zrx-exam-textarea');
        if (!textarea) return;

        const presetText = this.getAttribute('data-preset') || this.textContent.trim();
        const currentVal = textarea.value.trim();
        if (!currentVal) {
          textarea.value = presetText;
        } else if (!currentVal.includes(presetText)) {
          textarea.value = currentVal + '\n' + presetText;
        }
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamPresets);
  } else {
    initExamPresets();
  }
})();
