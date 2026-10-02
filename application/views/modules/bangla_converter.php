<?php
declare(strict_types=1);

// Bangla phonetic converter: transforms Romanized Bengali into Bengali script for prescription instructions.
?>
<div class="module-header">
    <span>Bangla Phonetic Converter</span>
    <span class="bangla-header-badge">Clinical Notes</span>
</div>
<div class="module-body bangla-converter-body">
    <div class="bangla-hint-text">
        Type phonetic English to convert into Bengali prescription instructions:
    </div>
    <div class="bangla-input-group">
        <input type="text" id="bangla-phonetic-input" placeholder="e.g. 1 chamoch dine 3 bar khabar por..." class="bangla-phonetic-input">
        <div id="bangla-phonetic-output" class="bangla-phonetic-output">
            ১ চামচ দিনে ৩ বার খাবার পর
        </div>
    </div>
</div>