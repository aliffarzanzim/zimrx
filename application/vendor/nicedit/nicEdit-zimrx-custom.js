// ============================================================================
// Monkey-patch NicEdit panel to show button/dropdown names on hover
// ============================================================================
if (typeof nicEditorPanel !== 'undefined' && nicEditorPanel.prototype) {
    var originalAddButton = nicEditorPanel.prototype.addButton;
    nicEditorPanel.prototype.addButton = function(buttonName, options, noOrder) {
        originalAddButton.call(this, buttonName, options, noOrder);
        var btn = this.panelButtons[this.panelButtons.length - 1];
        if (btn && btn.txt) {
            var name = (btn.options && btn.options.name) ? btn.options.name : buttonName;
            if (btn.margin && typeof btn.margin.setAttribute === 'function') {
                btn.margin.setAttribute('title', name);
            }
        }
    };
}

// ============================================================================
// Fix NicEdit checkNodes: the original has a JS precedence bug that prevents
// tag-walking. Also adds alignment state detection (NicEdit noActive by default).
// ============================================================================
if (typeof nicEditorButton !== 'undefined' && nicEditorButton.prototype) {
    nicEditorButton.prototype.checkNodes = function(startNode) {
        var btnName = this.name;

        // Resolve effective node:
        // - text node → its parent element
        // - element at end-of-line (cursor after all children) → descend into last child
        function zrxResolveNode(n) {
            if (!n) return n;
            if (n.nodeType === 3) return n.parentNode; // text node → parent
            // Element node: cursor is AFTER its children; descend into lastChild
            var cur = n;
            while (cur && cur.nodeType === 1 && cur.lastChild) {
                cur = cur.lastChild;
                if (cur.nodeType === 3) { cur = cur.parentNode; break; }
            }
            return cur;
        }
        var resolvedNode = zrxResolveNode(startNode);

        // --- Alignment state ---
        if (btnName === 'left' || btnName === 'center' || btnName === 'right' || btnName === 'justify') {
            var alignMap = { left: 'left', center: 'center', right: 'right', justify: 'justify' };
            var targetAlign = alignMap[btnName];
            // Walk up to find nearest block element and read its text-align
            var node = resolvedNode;
            while (node && node.nodeName && node.nodeName.toLowerCase() !== 'body') {
                var tag = node.nodeName.toLowerCase();
                if (['p','div','h1','h2','h3','h4','h5','h6','li','blockquote','pre'].indexOf(tag) !== -1) {
                    var cs = window.getComputedStyle(node);
                    var align = (node.style && node.style.textAlign) ? node.style.textAlign : (cs ? cs.textAlign : '');
                    if (align === targetAlign || (targetAlign === 'left' && (align === '' || align === 'start'))) {
                        this.activate(); return true;
                    }
                    break;
                }
                node = node.parentNode;
            }
            this.deactivate(); return false;
        }

        // --- Bold / Italic / Underline: walk ancestors correctly ---
        var boldTags   = { B: 1, STRONG: 1 };
        var italicTags = { EM: 1, I: 1 };
        var underlineTags = { U: 1 };

        var node = resolvedNode;
        while (node && node.nodeName && node.nodeName.toLowerCase() !== 'body') {
            var nn = node.nodeName.toUpperCase();
            if (btnName === 'bold'      && (boldTags[nn]      || (node.style && (node.style.fontWeight === 'bold' || node.style.fontWeight === '700')))) { this.activate(); return true; }
            if (btnName === 'italic'    && (italicTags[nn]    || (node.style && node.style.fontStyle === 'italic'))) { this.activate(); return true; }
            if (btnName === 'underline' && (underlineTags[nn] || (node.style && node.style.textDecoration === 'underline'))) { this.activate(); return true; }

            // Also check via computed style for bold/italic/underline
            if (btnName === 'bold' || btnName === 'italic' || btnName === 'underline') {
                var cs = window.getComputedStyle(node);
                if (btnName === 'bold'      && cs && (cs.fontWeight === 'bold' || parseInt(cs.fontWeight) >= 700)) { this.activate(); return true; }
                if (btnName === 'italic'    && cs && cs.fontStyle === 'italic')   { this.activate(); return true; }
                if (btnName === 'underline' && cs && cs.textDecoration.indexOf('underline') !== -1) { this.activate(); return true; }
            }
            node = node.parentNode;
        }

        // Fall back to original tag/css check for other buttons (ol, ul, subscript, etc.)
        if (this.options.tags || this.options.css) {
            var B = startNode;
            do {
                if (this.options.tags && B && B.nodeName && bkLib.inArray(this.options.tags, B.nodeName)) {
                    this.activate(); return true;
                }
                B = B ? B.parentNode : null;
            } while (B && B.nodeName && B.nodeName.toLowerCase() !== 'body');

            var B2 = startNode;
            while (B2 && B2.nodeType === 3) { B2 = B2.parentNode; }
            if (this.options.css && B2) {
                for (var itm in this.options.css) {
                    if (B2.getStyle && B2.getStyle(itm, this.ne.selectedInstance.instanceDoc) === this.options.css[itm]) {
                        this.activate(); return true;
                    }
                }
            }
        }

        this.deactivate(); return false;
    };
}


// ============================================================================
// Monkey-patch NicEdit dropdowns to show active formatting at cursor position
// Uses document.queryCommandValue() as primary source (works idle + typing),
// falls back to DOM traversal for custom fonts (SolaimanLipi etc.)
// A setTimeout(0) defers read so browser commits click position first.
// ============================================================================

// --- Size map: NicEdit size attr → display label ---
const _zrxSizeMap = { '1': '8pt', '2': '10pt', '3': '12pt', '4': '14pt', '5': '18pt', '6': '24pt' };
// px → size attr approximation for fallback
const _zrxPxToSize = (px) => {
    if (px <= 11) return '1';
    if (px <= 14) return '2';
    if (px <= 16) return '3';
    if (px <= 20) return '4';
    if (px <= 28) return '5';
    return '6';
};

// --- Family map: lowercase key → display name ---
const _zrxFamilyMap = {
    // English
    'times new roman': 'Times New Roman',
    'arial': 'Arial',
    'calibri': 'Calibri',
    'tahoma': 'Tahoma',
    'georgia': 'Georgia',
    'gabriola': 'Gabriola',
    'courier new': 'Courier New',
    'comic sans': 'Comic Sans',
    'comic sans ms': 'Comic Sans',
    'bradley hand itc': 'Bradley Hand ITC',
    
    // Bangla
    'solaimanlipi': 'SolaimanLipi',
    'adorsholipi': 'Adorsho Lipi',
    'kongsho': 'Kongsho',
    'bensenhandwriting': 'BenSen Handwriting',
    'nikosh': 'Nikosh',
    'siyamrupali': 'Siyam Rupali',
    'kumarkhaliunicode': 'Kumarkhali Unicode',
    'mangalikunicode': 'Mangalik Unicode',
    
    // Rx fonts
    'lucida calligraphy': 'Lucida Calligraphy',
    'akayakanadaka': 'Akaya Kanadaka',
    'birthstone': 'Birthstone',
    'charm': 'Charm',
    'cookie': 'Cookie',
    'damion': 'Damion',
    'engagement': 'Engagement',
    'happymonkey': 'Happy Monkey',
    'jimnightshade': 'Jim Nightshade',
    'kings': 'Kings',
    'macondo': 'Macondo',
    'metamorphous': 'Metamorphous',
    'montecarlo': 'MonteCarlo',
    'parisienne': 'Parisienne',
    'shantellsans': 'Shantell Sans',
    'texgyrechorus': 'TeX Gyre Chorus',
    
    // Legacy/System fallbacks
    'helvetica': 'Helvetica',
    'impact': 'Impact',
    'trebuchet ms': 'Trebuchet',
    'verdana': 'Verdana'
};

// --- Format map: block tag → display label ---
const _zrxFormatMap = {
    'p': 'Paragraph', 'pre': 'Pre',
    'h6': 'Heading&nbsp;6', 'h5': 'Heading&nbsp;5', 'h4': 'Heading&nbsp;4',
    'h3': 'Heading&nbsp;3', 'h2': 'Heading&nbsp;2', 'h1': 'Heading&nbsp;1'
};

/**
 * Get the real element at the current caret using DOM traversal.
 * Used as fallback when queryCommandValue returns empty/generic.
 */
const getSelectedElement = (defaultEl) => {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return defaultEl;
    const range = sel.getRangeAt(0);
    let node = range.startContainer;
    if (node.nodeType === 3) return node.parentNode; // text node → its parent
    if (node.nodeType === 1) {
        const offset = range.startOffset;
        // caret at end of a line: offset > 0, look at prev child
        if (offset > 0 && node.childNodes[offset - 1]) {
            let prev = node.childNodes[offset - 1];
            while (prev && prev.nodeType === 1 && prev.childNodes.length > 0) prev = prev.lastChild;
            return prev.nodeType === 3 ? prev.parentNode : prev;
        }
        // caret at start: look at first child
        if (node.childNodes[offset]) {
            let next = node.childNodes[offset];
            while (next && next.nodeType === 1 && next.childNodes.length > 0) next = next.firstChild;
            return next.nodeType === 3 ? next.parentNode : next;
        }
    }
    return defaultEl;
};

/**
 * Resolve font size from queryCommandValue → DOM fallback → computed style.
 */
const _zrxGetSize = (el) => {
    // Try queryCommandValue first — works in idle state
    try {
        const qcv = document.queryCommandValue('fontSize');
        if (qcv && qcv !== '0' && _zrxSizeMap[qcv]) return qcv;
    } catch(e) {}
    // DOM traversal fallback
    const activeEl = getSelectedElement(el);
    if (!activeEl) return null;
    let node = activeEl;
    while (node && node.nodeName !== 'BODY') {
        if (node.nodeName === 'FONT' && node.getAttribute('size')) return node.getAttribute('size');
        node = node.parentNode;
    }
    // computed style last resort
    const px = parseFloat(window.getComputedStyle(activeEl).fontSize);
    return isNaN(px) ? null : _zrxPxToSize(px);
};

/**
 * Resolve font family. queryCommandValue is unreliable for custom fonts,
 * so we use it only as a hint and validate against our known map.
 */
const _zrxGetFamily = (el) => {
    // Try queryCommandValue
    try {
        const qcv = document.queryCommandValue('fontName');
        if (qcv) {
            // strip quotes and compare
            const clean = qcv.replace(/['"]/g, '').toLowerCase().trim();
            for (let f in _zrxFamilyMap) {
                if (clean.indexOf(f) !== -1) return f;
            }
        }
    } catch(e) {}
    // DOM traversal fallback
    const activeEl = getSelectedElement(el);
    if (!activeEl) return null;
    let node = activeEl;
    while (node && node.nodeName !== 'BODY') {
        if (node.nodeName === 'FONT' && node.getAttribute('face')) return node.getAttribute('face').toLowerCase();
        node = node.parentNode;
    }
    // computed style last resort
    const fontFam = window.getComputedStyle(activeEl).fontFamily.toLowerCase();
    for (let f in _zrxFamilyMap) {
        if (fontFam.indexOf(f) !== -1) return f;
    }
    return null;
};

/**
 * Resolve block format. queryCommandValue('formatBlock') returns 'p','h1',etc.
 */
const _zrxGetFormat = (el) => {
    // Try queryCommandValue
    try {
        const qcv = document.queryCommandValue('formatBlock').toLowerCase().replace(/[<>]/g, '');
        if (qcv && _zrxFormatMap[qcv]) return qcv;
    } catch(e) {}
    // DOM traversal fallback
    const activeEl = getSelectedElement(el);
    if (!activeEl) return null;
    let node = activeEl;
    while (node && node.nodeName !== 'BODY') {
        const tag = node.nodeName.toLowerCase();
        if (_zrxFormatMap[tag]) return tag;
        node = node.parentNode;
    }
    return null;
};

if (typeof nicEditorFontSizeSelect !== 'undefined') {
    const origFontSizeInit = nicEditorFontSizeSelect.prototype.init;
    nicEditorFontSizeSelect.prototype.init = function() {
        origFontSizeInit.apply(this, arguments);
        this.ne.addEvent("selected", (instance, el) => {
            // defer so browser commits caret position before we read it
            const self = this;
            setTimeout(() => {
                const size = _zrxGetSize(el);
                if (size && _zrxSizeMap[size]) {
                    self.setDisplay(size + '&nbsp;(' + _zrxSizeMap[size] + ')');
                } else {
                    self.setDisplay("Font&nbsp;Size...");
                }
            }, 0);
        });
    };

    nicEditorFontSizeSelect.prototype.update = function(val) {
        this.ne.nicCommand(this.options.command, val);
        if (val && _zrxSizeMap[val]) {
            this.setDisplay(val + '&nbsp;(' + _zrxSizeMap[val] + ')');
        } else {
            this.setDisplay("Font&nbsp;Size...");
        }
        this.close();
    };
}

if (typeof nicEditorFontFamilySelect !== 'undefined') {
    nicEditorFontFamilySelect.prototype.init = function() {
        this.setDisplay("Font&nbsp;Family...");
        this.selOptions = []; // Reset options
        
        // Define grouped lists
        const groups = [
            {
                title: "English Fonts",
                fonts: {
                    'times new roman': 'Times New Roman',
                    'arial': 'Arial',
                    'calibri': 'Calibri',
                    'tahoma': 'Tahoma',
                    'georgia': 'Georgia',
                    'gabriola': 'Gabriola',
                    'courier new': 'Courier New',
                    'comic sans': 'Comic Sans',
                    'bradley hand itc': 'Bradley Hand ITC'
                }
            },
            {
                title: "Bangla Fonts",
                fonts: {
                    'solaimanlipi': 'SolaimanLipi',
                    'adorsholipi': 'Adorsho Lipi',
                    'kongsho': 'Kongsho',
                    'bensenhandwriting': 'BenSen Handwriting',
                    'nikosh': 'Nikosh',
                    'siyamrupali': 'Siyam Rupali',
                    'kumarkhaliunicode': 'Kumarkhali Unicode',
                    'mangalikunicode': 'Mangalik Unicode'
                }
            },
            {
                title: "Rx fonts",
                fonts: {
                    'lucida calligraphy': 'Lucida Calligraphy',
                    'akayakanadaka': 'Akaya Kanadaka',
                    'birthstone': 'Birthstone',
                    'charm': 'Charm',
                    'cookie': 'Cookie',
                    'damion': 'Damion',
                    'engagement': 'Engagement',
                    'happymonkey': 'Happy Monkey',
                    'jimnightshade': 'Jim Nightshade',
                    'kings': 'Kings',
                    'macondo': 'Macondo',
                    'metamorphous': 'Metamorphous',
                    'montecarlo': 'MonteCarlo',
                    'parisienne': 'Parisienne',
                    'shantellsans': 'Shantell Sans',
                    'texgyrechorus': 'TeX Gyre Chorus'
                }
            }
        ];

        for (const group of groups) {
            // Add a header option (3rd element in array is isHeader flag)
            this.selOptions.push([null, group.title, true]);
            for (const key in group.fonts) {
                const label = group.fonts[key];
                this.selOptions.push([key, `<font face="${key}">${label}</font>`, false]);
            }
        }

        // Register standard selection update listener
        this.ne.addEvent("selected", (instance, el) => {
            const self = this;
            setTimeout(() => {
                const face = _zrxGetFamily(el);
                if (face && _zrxFamilyMap[face]) {
                    self.setDisplay(_zrxFamilyMap[face]);
                } else {
                    self.setDisplay("Font&nbsp;Family...");
                }
            }, 0);
        });
    };

    // Override open method to handle width and styling for headers
    nicEditorFontFamilySelect.prototype.open = function() {
        this.pane = new nicEditorPane(this.items, this.ne, {
            width: "160px",
            padding: "0px",
            borderTop: 0,
            borderLeft: "1px solid #ccc",
            borderRight: "1px solid #ccc",
            borderBottom: "0px",
            backgroundColor: "#fff"
        });
        
        for (var C = 0; C < this.selOptions.length; C++) {
            var B = this.selOptions[C];
            var val = B[0];
            var htmlContent = B[1];
            var isHeader = B[2];
            
            var A = new bkElement("div").setStyle({
                overflow: "hidden",
                borderBottom: "1px solid #e2e8f0",
                width: "158px",
                textAlign: "left",
                cursor: isHeader ? "default" : "pointer"
            });
            
            var D = new bkElement("div").setStyle({
                padding: isHeader ? "4px 8px" : "3px 12px",
                background: isHeader ? "#f1f5f9" : "#fff",
                fontWeight: isHeader ? "bold" : "normal",
                fontSize: isHeader ? "11px" : "13px",
                color: isHeader ? "#475569" : "#1e293b",
                fontFamily: isHeader ? "sans-serif" : "inherit"
            }).setContent(htmlContent).appendTo(A).noSelect();
            
            if (!isHeader) {
                D.addEvent("click", this.update.closure(this, val))
                 .addEvent("mouseover", this.over.closure(this, D))
                 .addEvent("mouseout", this.out.closure(this, D))
                 .setAttributes("id", val);
            }
            
            if (!window.opera) {
                D.onmousedown = bkLib.cancelEvent;
            }
            
            this.pane.append(A);
        }
    };
    
    // Set custom hover colors
    nicEditorFontFamilySelect.prototype.over = function(A) {
        A.setStyle({ backgroundColor: "#f8fafc", color: "#2563eb" });
    };
    nicEditorFontFamilySelect.prototype.out = function(A) {
        A.setStyle({ backgroundColor: "#fff", color: "#1e293b" });
    };

    // Override update to instantly display selected family in control panel
    nicEditorFontFamilySelect.prototype.update = function(val) {
        this.ne.nicCommand(this.options.command, val);
        if (val && _zrxFamilyMap[val]) {
            this.setDisplay(_zrxFamilyMap[val]);
        } else {
            this.setDisplay("Font&nbsp;Family...");
        }
        this.close();
    };
}

if (typeof nicEditorFontFormatSelect !== 'undefined') {
    const origFontFormatInit = nicEditorFontFormatSelect.prototype.init;
    nicEditorFontFormatSelect.prototype.init = function() {
        origFontFormatInit.apply(this, arguments);
        this.ne.addEvent("selected", (instance, el) => {
            const self = this;
            setTimeout(() => {
                const format = _zrxGetFormat(el);
                if (format && _zrxFormatMap[format]) {
                    self.setDisplay(_zrxFormatMap[format]);
                } else {
                    self.setDisplay("Font&nbsp;Format");
                }
            }, 0);
        });
    };
}

// ============================================================================
// Custom NicEdit plugins: lineHeight | letterSpacing | wordSpacing | scaleX
// KEY FIX: saveRng() called in open() (selection still active),
// NOT in update() (focus already stolen by dropdown click).
// ============================================================================

/**
 * Walk a DocumentFragment and remove all <span> elements that have the given
 * CSS property set, unwrapping their children in place.
 * For 'transform' we also strip the companion display/margin/origin styles.
 */
function zrxFlattenProp(frag, cssProp) {
    if (!frag.querySelectorAll) return;
    var cssKebab = cssProp.replace(/([A-Z])/g, '-$1').toLowerCase();

    if (cssProp === 'fontSize') {
        var fonts = Array.prototype.slice.call(frag.querySelectorAll('font'));
        for (var i = 0; i < fonts.length; i++) {
            fonts[i].removeAttribute('size');
        }
    }

    var spans = Array.prototype.slice.call(frag.querySelectorAll('span'));
    for (var i = 0; i < spans.length; i++) {
        var s = spans[i];
        if (!s.style) continue;
        // Check camelCase or inline style contains the property
        var hasProp = s.style[cssProp] ||
            (s.getAttribute('style') || '').indexOf(cssKebab) !== -1;
        if (!hasProp) continue;

        // Remove the property (and companions for transform)
        if (cssProp === 'transform') {
            s.style.removeProperty('transform');
            s.style.removeProperty('-webkit-transform');
            s.style.removeProperty('transform-origin');
            s.style.removeProperty('-webkit-transform-origin');
            s.style.removeProperty('display');
            s.style.removeProperty('margin-right');
        } else {
            s.style.removeProperty(cssKebab);
        }

        // If span has no remaining styles, unwrap it (keep its children)
        var remaining = (s.getAttribute('style') || '').replace(/\s/g, '');
        if (remaining === '') {
            var parent = s.parentNode;
            while (s.firstChild) parent.insertBefore(s.firstChild, s);
            parent.removeChild(s);
        }
    }
}

/**
 * Temporarily wraps active selection in a blue highlight span.
 * Keeps selection visible while user types in input boxes.
 */
function zrxAddTempHighlight(inst) {
    if (!inst) return null;
    var sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    var range = sel.getRangeAt(0);
    if (range.collapsed) return null;

    var existing = document.getElementById('zrx-temp-highlight');
    if (existing) return existing;

    var frag = range.extractContents();
    var span = document.createElement('span');
    span.id = 'zrx-temp-highlight';
    span.style.backgroundColor = '#b4d5fe';
    span.style.color = '#000';
    span.appendChild(frag);
    range.insertNode(span);

    var newRange = document.createRange();
    newRange.selectNodeContents(span);
    sel.removeAllRanges();
    sel.addRange(newRange);
    
    inst.savedRange = newRange;
    inst.savedSel = sel;
    return span;
}

/**
 * Removes the temporary selection highlight span and restores selection range.
 */
function zrxRemoveTempHighlight(inst, span) {
    if (!span || !span.parentNode) return;
    var first = span.firstChild;
    var last = span.lastChild;
    var par = span.parentNode;
    
    while (span.firstChild) {
        par.insertBefore(span.firstChild, span);
    }
    par.removeChild(span);
    
    if (first && last) {
        var range = document.createRange();
        range.setStartBefore(first);
        range.setEndAfter(last);
        
        var sel = window.getSelection();
        if (sel) {
            sel.removeAllRanges();
            sel.addRange(range);
        }
        inst.savedRange = range;
        inst.savedSel = sel;
    }
}

function zrxApplySpanStyle(ne, cssProp, cssValue, isBlock) {
    var inst = ne.selectedInstance;
    if (!inst) return;

    // First check and clean up any temporary selection highlight span
    var tempSpan = document.getElementById('zrx-temp-highlight');
    if (tempSpan) {
        zrxRemoveTempHighlight(inst, tempSpan);
    }

    inst.restoreRng && inst.restoreRng();

    var sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    var range = sel.getRangeAt(0);

    // Helper to save content and trigger synthetic input event for live preview sync
    function triggerSync() {
        inst.saveContent && inst.saveContent();
        var editorEl = inst.getElm();
        if (editorEl) {
            var event = new Event('input', { bubbles: true, cancelable: true });
            editorEl.dispatchEvent(event);
        }
    }

    var isReset = (cssProp === 'transform'    && cssValue === 'scaleX(1)') ||
                  (cssProp === 'letterSpacing' && (cssValue === '0em' || cssValue === 'normal')) ||
                  (cssProp === 'wordSpacing'   && (cssValue === '0em' || cssValue === 'normal')) ||
                  (cssProp === 'lineHeight'    && (cssValue === 'normal' || cssValue === '1')) ||
                  (cssProp === 'marginBottom'  && (cssValue === '0px' || cssValue === '0')) ||
                  (cssProp === 'marginTop'     && (cssValue === '0px' || cssValue === '0'));

    var cssKebab = cssProp.replace(/([A-Z])/g, '-$1').toLowerCase();

    // --- Block-level property (line-height/margins): apply to nearest block ancestor ---
    if (isBlock) {
        var node = range.startContainer;
        while (node && node !== inst.getElm()) {
            var tag = node.nodeName ? node.nodeName.toLowerCase() : '';
            if (['p','div','h1','h2','h3','h4','h5','h6','li','blockquote','pre'].indexOf(tag) !== -1) {
                if (isReset) {
                    node.style.removeProperty(cssKebab);
                } else {
                    node.style[cssProp] = cssValue;
                }
                triggerSync();
                return;
            }
            node = node.parentNode;
        }
        if (isReset) {
            inst.getElm().style.removeProperty(cssKebab);
        } else {
            inst.getElm().style[cssProp] = cssValue;
        }
        triggerSync();
        return;
    }

    if (range.collapsed) {
        if (isReset) return;
        var span = document.createElement('span');
        span.setAttribute('style', styleAttr);
        span.innerHTML = '&#8203;'; // Zero-width space
        range.insertNode(span);
        
        var newRange = document.createRange();
        newRange.setStart(span.firstChild, 1);
        newRange.collapse(true);
        sel.removeAllRanges();
        sel.addRange(newRange);
        inst.savedRange = newRange;
        inst.savedSel = sel;
        
        triggerSync();
        return;
    }

    // --- Tier 1: walk up DOM to find an existing ancestor span with this property ---
    // This handles the common case where the selection is INSIDE an already-styled span.
    // If we just extractContents(), the outer span stays empty in the DOM → ghost nesting.
    function findAncestorSpan(startNode) {
        var n = (startNode.nodeType === 3) ? startNode.parentNode : startNode;
        while (n && n !== inst.getElm()) {
            if (n.nodeName && n.nodeName.toLowerCase() === 'span' && n.style &&
                ((n.style[cssProp] && n.style[cssProp] !== '') ||
                 (n.getAttribute('style') || '').indexOf(cssKebab) !== -1)) {
                return n;
            }
            n = n.parentNode;
        }
        return null;
    }

    var startSpan = findAncestorSpan(range.startContainer);
    var endSpan   = findAncestorSpan(range.endContainer);

    if (startSpan && startSpan === endSpan) {
        // Selection is entirely inside one existing span — modify it in-place
        if (isReset) {
            // Unwrap: lift children up to parent, remove empty span
            var first = startSpan.firstChild;
            var last = startSpan.lastChild;
            var par = startSpan.parentNode;
            while (startSpan.firstChild) par.insertBefore(startSpan.firstChild, startSpan);
            par.removeChild(startSpan);
            
            // Re-select the unwrapped elements
            if (first && last) {
                var newRange = document.createRange();
                newRange.setStartBefore(first);
                newRange.setEndAfter(last);
                sel.removeAllRanges();
                sel.addRange(newRange);
                inst.savedRange = newRange;
                inst.savedSel = sel;
            }
        } else {
            // Update the span's style properties
            if (cssProp === 'transform') {
                startSpan.style.setProperty('transform', cssValue);
                startSpan.style.setProperty('display', 'inline-block');
                startSpan.style.setProperty('transform-origin', 'left center');
                var sv = parseFloat(cssValue.replace(/[^0-9.]/g, ''));
                if (!isNaN(sv) && sv < 1) {
                    startSpan.style.setProperty('margin-right', ((sv - 1) * 100).toFixed(1) + '%');
                } else {
                    startSpan.style.removeProperty('margin-right');
                }
            } else {
                startSpan.style.setProperty(cssKebab, cssValue);
            }
            
            var newRange = document.createRange();
            newRange.selectNodeContents(startSpan);
            sel.removeAllRanges();
            sel.addRange(newRange);
            inst.savedRange = newRange;
            inst.savedSel = sel;
        }
        triggerSync();
        return;
    }

    // --- Tier 2: fresh selection or multi-span — extract, flatten, re-wrap ---
    var styleAttr = cssKebab + ':' + cssValue;
    if (cssProp === 'transform') {
        var sv2 = parseFloat(cssValue.replace(/[^0-9.]/g, ''));
        styleAttr += ';display:inline-block;transform-origin:left center';
        if (!isNaN(sv2) && sv2 < 1) {
            styleAttr += ';margin-right:' + ((sv2 - 1) * 100).toFixed(1) + '%';
        }
    }

    var frag = range.extractContents();
    zrxFlattenProp(frag, cssProp); // remove any nested same-property spans

    if (isReset) {
        var first = frag.firstChild;
        var last = frag.lastChild;
        range.insertNode(frag);
        if (first && last) {
            var newRange = document.createRange();
            newRange.setStartBefore(first);
            newRange.setEndAfter(last);
            sel.removeAllRanges();
            sel.addRange(newRange);
            inst.savedRange = newRange;
            inst.savedSel = sel;
        }
    } else {
        var span = document.createElement('span');
        span.setAttribute('style', styleAttr);
        span.appendChild(frag);
        range.insertNode(span);

        var newRange = document.createRange();
        newRange.selectNodeContents(span);
        sel.removeAllRanges();
        sel.addRange(newRange);
        inst.savedRange = newRange;
        inst.savedSel = sel;
    }

    triggerSync();
}

function zrxReadStyleAtCaret(cssProp) {
    var el = getSelectedElement(null);
    if (!el) return null;

    // For block properties (margins), walk up to the nearest block ancestor to read computed style
    if (cssProp === 'marginTop' || cssProp === 'marginBottom') {
        var node = el;
        while (node && node.nodeName && node.nodeName.toLowerCase() !== 'body') {
            var tag = node.nodeName.toLowerCase();
            if (['p','div','h1','h2','h3','h4','h5','h6','li','blockquote','pre'].indexOf(tag) !== -1) {
                el = node;
                break;
            }
            node = node.parentNode;
        }
    }

    // Try reading exact inline style from element or its ancestors up to body
    var n = (el.nodeType === 3) ? el.parentNode : el;
    while (n && n.nodeName && n.nodeName.toLowerCase() !== 'body') {
        if (n.style && n.style[cssProp]) {
            return n.style[cssProp];
        }
        n = n.parentNode;
    }

    var cs = window.getComputedStyle(el);
    if (cssProp === 'lineHeight') {
        var lhPx = parseFloat(cs.lineHeight);
        var fsPx = parseFloat(cs.fontSize);
        if (!isNaN(lhPx) && !isNaN(fsPx) && fsPx > 0) return (lhPx / fsPx).toFixed(2);
        return null;
    }
    if (cssProp === 'fontSize') {
        var fs = cs.fontSize;
        var pxVal = parseFloat(fs);
        if (!isNaN(pxVal)) {
            var pt = pxVal * 0.75;
            var rounded = Math.round(pt * 2) / 2;
            return rounded + 'pt';
        }
        return fs;
    }
    return cs[cssProp] || null;
}



// Shared toggle() override: intercepts BEFORE dropdown pane opens.
// NicEdit calls toggle() on every click of the select button.
// At this moment the editor still has focus, so saveRng() captures the live selection.
// We then call the parent toggle() which calls open() internally.
function zrxSelectToggle() {
    // Save range while editor still has focus
    if (this.ne.selectedInstance && this.ne.selectedInstance.saveRng) {
        this.ne.selectedInstance.saveRng();
    }
    // Call parent toggle via the prototype object NicEdit actually stores methods on
    var proto = nicEditorSelect.prototype;
    if (proto && typeof proto.toggle === 'function') {
        proto.toggle.call(this);
    } else {
        // Fallback: replicate toggle logic inline
        if (!this.isDisabled) {
            if (this.pane) { this.close(); } else { this.open(); }
        }
    }
}

// ============================================================================
// nicEditorComboSelect: Combo/Writing Box control for NicEdit
// Displays a symbol label, a text input, and an arrow control.
// Allows manual typing + dropdown selection.
// ============================================================================
var nicEditorComboSelect = nicEditorSelect.extend({
    construct: function(D, A, C, B) {
        this.options = C.buttons[A];
        this.elm = D;
        this.ne = B;
        this.name = A;
        this.selOptions = new Array();
        this.isPaneClicking = false;
        
        this.margin = new bkElement("div").setStyle({"float": "left", margin: "2px 1px 0 1px"}).appendTo(this.elm);
        this.contain = new bkElement("div").setStyle({width: "90px", height: "20px", cursor: "pointer", overflow: "hidden"}).addClass("selectContain").addEvent("click", this.toggle.closure(this)).appendTo(this.margin);
        this.items = new bkElement("div").setStyle({overflow: "hidden", zoom: 1, border: "1px solid #ccc", paddingLeft: "3px", backgroundColor: "#fff"}).appendTo(this.contain);
        this.control = new bkElement("div").setStyle({overflow: "hidden", "float": "right", height: "18px", width: "16px"}).addClass("selectControl").setStyle(this.ne.getIcon("arrow", C)).appendTo(this.items);
        
        // Symbol prefix label (e.g. ↕, ⇔, etc.)
        this.label = new bkElement("div").setStyle({
            "float": "left",
            width: "16px",
            height: "18px",
            lineHeight: "18px",
            textAlign: "center",
            fontSize: "12px",
            fontFamily: "sans-serif",
            color: "#555",
            userSelect: "none",
            webkitUserSelect: "none"
        }).appendTo(this.items);
        
        // Manual input box
        this.txt = new bkElement("input").setAttributes({
            type: "text",
            value: ""
        }).setStyle({
            border: "none",
            outline: "none",
            background: "transparent",
            "float": "left",
            width: "50px",
            height: "16px",
            marginTop: "1px",
            fontFamily: "sans-serif",
            textAlign: "center",
            fontSize: "11px",
            padding: "0"
        }).addClass("selectTxt").appendTo(this.items);

        var self = this;

        // Prevent clicking the text field from toggling the dropdown
        this.txt.addEvent("click", function(e) {
            if (e && e.stopPropagation) { e.stopPropagation(); }
        });

        // Save range on mousedown BEFORE focus shifts to the text input
        this.txt.addEvent("mousedown", function(e) {
            if (e && e.stopPropagation) { e.stopPropagation(); }
            if (self.ne.selectedInstance) {
                var inst = self.ne.selectedInstance;
                if (inst.saveRng) { inst.saveRng(); }
                zrxAddTempHighlight(inst);
            }
        });

        // Keydown handler: enter key saves & focuses editor back
        this.txt.addEvent("keydown", function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                self.applyManual(self.txt.value);
            }
        });

        // Blur handler: saves manual typing & closes panel
        this.txt.addEvent("blur", function() {
            self.isFocused = false;
            self.applyManual(self.txt.value);
            setTimeout(function() {
                if (document.activeElement !== self.txt) {
                    self.close(); // Close dropdown options
                    
                    // Only disable if focus has left the editor entirely
                    var editorFocused = false;
                    if (self.ne.selectedInstance) {
                        var elm = self.ne.selectedInstance.getElm();
                        if (document.activeElement === elm || elm.contains(document.activeElement)) {
                            editorFocused = true;
                        }
                    }
                    if (!editorFocused) {
                        self.disable();
                    }
                }
            }, 100);
        });

        // Focus handler: automatically shows dropdown when input is active
        this.txt.addEvent("focus", function() {
            self.isFocused = true;
            self.enable();
            self.open(); // Open options dropdown
        });

        // Input handler: filters dropdown options in real-time
        this.txt.addEvent("input", function() {
            self.filterDropdown(self.txt.value);
        });

        if(!window.opera){
            this.contain.onmousedown = this.control.onmousedown = this.label.onmousedown = bkLib.cancelEvent;
        }

        this.margin.noSelect();
        this.ne.addEvent("selected", this.enable.closure(this)).addEvent("blur", this.disable.closure(this));
        this.disable();
        this.init();
    },

    setLabel: function(sym) {
        this.label.setContent(sym);
    },

    setDisplay: function(val) {
        this.txt.value = val;
    },

    open: function() {
        var proto = nicEditorSelect.prototype;
        if (proto && typeof proto.open === 'function') {
            proto.open.call(this);
        }
        if (this.pane && this.pane.pane) {
            var self = this;
            
            // Widen the pane container to fit negative values cleanly
            if (this.pane.contain) {
                this.pane.contain.setStyle({ width: "105px" });
            }
            this.pane.pane.setStyle({ width: "103px" });

            var options = this.pane.pane.getElementsByTagName("div");
            for (var i = 0; i < options.length; i++) {
                var opt = options[i];
                
                // Prevent option wrapper layout clipping
                if (opt.parentNode) {
                    opt.parentNode.setStyle({
                        width: "100%",
                        whiteSpace: "nowrap",
                        overflow: "visible"
                    });
                }

                // Wrap option divs' onmousedown handlers to ensure self.isPaneClicking gets set
                // before bkLib.cancelEvent stops propagation.
                var oldMousedown = opt.onmousedown;
                (function(o, oldMd) {
                    o.onmousedown = function(e) {
                        self.isPaneClicking = true;
                        if (oldMd) {
                            return oldMd.call(this, e);
                        }
                    };
                })(opt, oldMousedown);
            }
        }
    },

    filterDropdown: function(val) {
        if (!this.pane || !this.pane.pane) return;
        var cleanVal = val.trim().toLowerCase();
        var childDivs = this.pane.pane.childNodes;
        for (var i = 0; i < childDivs.length; i++) {
            var outerDiv = childDivs[i];
            if (outerDiv.nodeName && outerDiv.nodeName.toLowerCase() === 'div') {
                var text = (outerDiv.innerText || outerDiv.textContent || '').toLowerCase();
                // strip symbols for text comparison
                var cleanText = text.replace(/[\u2195\u21d4\u2194\u21b5]/g, '').trim();
                if (cleanVal === '' || cleanText.indexOf(cleanVal) !== -1 || text.indexOf(cleanVal) !== -1) {
                    outerDiv.style.display = 'block';
                } else {
                    outerDiv.style.display = 'none';
                }
            }
        }
    },

    applyManual: function(val) {
        if (this.isPaneClicking) {
            this.isPaneClicking = false;
            return;
        }
        this.txt.blur();
        this.handleManualInput(val);
        if (this.ne.selectedInstance) {
            var inst = this.ne.selectedInstance;
            inst.getElm().focus();
            if (inst.restoreRng) {
                inst.restoreRng();
            }
        }
    },

    disable: function() {
        if (this.isFocused || document.activeElement === this.txt) {
            return;
        }
        this.isDisabled = true;
        this.close();
        this.contain.setStyle({opacity: 0.6});
        this.txt.disabled = true;
    },

    enable: function() {
        this.isDisabled = false;
        this.contain.setStyle({opacity: 1});
        this.txt.disabled = false;
    }
});

// ---- Line Height ----
var nicEditorLineHeightSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u2195');
        this.setDisplay('1.5');
        var opts = [
            ['1','1\u00a0(tight)'],['1.15','1.15'],['1.3','1.3'],
            ['1.5','1.5\u00a0(normal)'],['1.75','1.75'],['2','2\u00a0(double)'],['2.5','2.5'],['3','3']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span style="line-height:'+opts[i][0]+'">\u2195 '+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('lineHeight');
                self.setDisplay(v ? v : '1.5');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'lineHeight', val, true);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.replace(/[^0-9.]/g, '');
        if (clean) {
            zrxApplySpanStyle(this.ne, 'lineHeight', clean, true);
            this.setDisplay(clean);
        }
    }
});

// ---- Letter Spacing ----
var nicEditorLetterSpacingSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u21d4');
        this.setDisplay('0em');
        var opts = [
            ['-0.05em','-0.05em'],['0em','0\u00a0(normal)'],['0.02em','0.02em'],
            ['0.05em','0.05em'],['0.08em','0.08em'],['0.1em','0.1em'],['0.15em','0.15em'],['0.2em','0.2em']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span style="letter-spacing:'+opts[i][0]+'">\u21d4 '+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('letterSpacing');
                self.setDisplay(v && v !== 'normal' && v !== '0px' ? v : '0em');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'letterSpacing', val, false);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        if (/^-?[0-9.]+$/.test(clean)) {
            clean += 'em';
        }
        zrxApplySpanStyle(this.ne, 'letterSpacing', clean, false);
        this.setDisplay(clean);
    }
});

// ---- Word Spacing ----
var nicEditorWordSpacingSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u2194');
        this.setDisplay('0em');
        var opts = [
            ['-0.05em','-0.05em'],['0em','0\u00a0(normal)'],['0.05em','0.05em'],
            ['0.1em','0.1em'],['0.2em','0.2em'],['0.3em','0.3em'],['0.5em','0.5em']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span style="word-spacing:'+opts[i][0]+'">\u2194 '+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('wordSpacing');
                self.setDisplay(v && v !== 'normal' && v !== '0px' ? v : '0em');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'wordSpacing', val, false);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        if (/^-?[0-9.]+$/.test(clean)) {
            clean += 'em';
        }
        zrxApplySpanStyle(this.ne, 'wordSpacing', clean, false);
        this.setDisplay(clean);
    }
});

// ---- ScaleX ----
var nicEditorScaleXSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u21b5');
        this.setDisplay('100%');
        var opts = [
            ['0.7','70%'],['0.75','75%'],['0.8','80%'],['0.85','85%'],['0.88','88%'],
            ['0.9','90%'],['0.95','95%'],['1','100%\u00a0(normal)'],['1.05','105%'],['1.1','110%']
        ];
        for (var i = 0; i < opts.length; i++) {
            var sv = opts[i][0], sl = opts[i][1];
            this.add(sv, '<span style="display:inline-block;transform:scaleX('+sv+');transform-origin:left center;">\u21b5 '+sl+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var el = getSelectedElement(null);
                if (!el) { self.setDisplay('100%'); return; }
                var t = window.getComputedStyle(el).transform;
                if (t && t !== 'none') {
                    var m = t.match(/matrix\(([^,]+)/);
                    if (m) { self.setDisplay((parseFloat(m[1]) * 100).toFixed(0) + '%'); return; }
                }
                self.setDisplay('100%');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'transform', 'scaleX(' + val + ')', false);
        this.setDisplay((parseFloat(val) * 100).toFixed(0) + '%');
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        var scaleVal = 1.0;
        if (clean.indexOf('%') !== -1) {
            var pct = parseFloat(clean.replace(/[^0-9.]/g, ''));
            if (!isNaN(pct)) {
                scaleVal = pct / 100;
            }
        } else {
            var num = parseFloat(clean);
            if (!isNaN(num)) {
                scaleVal = num;
            }
        }
        zrxApplySpanStyle(this.ne, 'transform', 'scaleX(' + scaleVal + ')', false);
        this.setDisplay((scaleVal * 100).toFixed(0) + '%');
    }
});

// ---- Paragraph Spacing (Bottom) ----
var nicEditorParagraphSpacingSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u2913'); // ⤓ symbol
        this.setDisplay('0px');
        var opts = [
            ['-12px','-12px'],['-8px','-8px'],['-4px','-4px'],
            ['0px','0px'],['4px','4px'],['8px','8px'],['12px','12px'],
            ['16px','16px'],['20px','20px'],['24px','24px'],['30px','30px']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span>\u2913 '+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('marginBottom');
                self.setDisplay(v && v !== 'normal' ? v : '0px');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'marginBottom', val, true);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        if (/^-?[0-9.]+$/.test(clean)) {
            clean += 'px';
        }
        zrxApplySpanStyle(this.ne, 'marginBottom', clean, true);
        this.setDisplay(clean);
    }
});

// ---- Paragraph Spacing (Top) ----
var nicEditorParagraphSpacingTopSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('\u2912'); // ⤒ symbol
        this.setDisplay('0px');
        var opts = [
            ['-12px','-12px'],['-8px','-8px'],['-4px','-4px'],
            ['0px','0px'],['4px','4px'],['8px','8px'],['12px','12px'],
            ['16px','16px'],['20px','20px'],['24px','24px'],['30px','30px']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span>⤒ '+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('marginTop');
                self.setDisplay(v && v !== 'normal' ? v : '0px');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(ne = this.ne, 'marginTop', val, true);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        if (/^-?[0-9.]+$/.test(clean)) {
            clean += 'px';
        }
        zrxApplySpanStyle(this.ne, 'marginTop', clean, true);
        this.setDisplay(clean);
    }
});

// ---- Custom Font Size (Combo Select Override) ----
var nicEditorFontSizeSelect = nicEditorComboSelect.extend({
    init: function() {
        this.setLabel('Sz');
        this.setDisplay('12pt');
        var opts = [
            ['9pt','9pt'], ['10pt','10pt'], ['10.5pt','10.5pt'], ['11pt','11pt'], ['11.5pt','11.5pt'], ['12pt','12pt'],
            ['14pt','14pt'], ['16pt','16pt'], ['18pt','18pt'], ['20pt','20pt'],
            ['24pt','24pt'], ['30pt','30pt'], ['36pt','36pt']
        ];
        for (var i = 0; i < opts.length; i++) {
            this.add(opts[i][0], '<span style="font-size:'+opts[i][0]+'">'+opts[i][1]+'</span>');
        }
        var self = this;
        this.ne.addEvent('selected', function() {
            setTimeout(function() {
                var v = zrxReadStyleAtCaret('fontSize');
                self.setDisplay(v ? v : '12pt');
            }, 0);
        });
    },
    toggle: zrxSelectToggle,
    update: function(val) {
        zrxApplySpanStyle(this.ne, 'fontSize', val, false);
        this.setDisplay(val);
        this.close();
    },
    handleManualInput: function(val) {
        if (!val) return;
        var clean = val.trim();
        if (/^[0-9.]+$/.test(clean)) {
            clean += 'pt';
        }
        zrxApplySpanStyle(this.ne, 'fontSize', clean, false);
        this.setDisplay(clean);
    }
});

// Register all six as a NicEdit plugin
nicEditors.registerPlugin(nicPlugin, {
    buttons: {
        'lineHeight':          { name: 'Line Height',          type: 'nicEditorLineHeightSelect' },
        'paragraphSpacingTop': { name: 'Paragraph Spacing Top', type: 'nicEditorParagraphSpacingTopSelect' },
        'paragraphSpacing':    { name: 'Paragraph Spacing',    type: 'nicEditorParagraphSpacingSelect' },
        'letterSpacing':       { name: 'Letter Spacing',       type: 'nicEditorLetterSpacingSelect' },
        'wordSpacing':         { name: 'Word Spacing',         type: 'nicEditorWordSpacingSelect' },
        'scaleX':              { name: 'ScaleX',               type: 'nicEditorScaleXSelect' }
    }
});

// ============================================================================
// Monkey-patch all dropdown select classes (standard & custom) to track active
// instances globally and automatically close other dropdowns when one opens.
// ============================================================================
(function() {
    var classesToPatch = [
        'nicEditorFontSizeSelect',
        'nicEditorFontFamilySelect',
        'nicEditorFontFormatSelect',
        'nicEditorLineHeightSelect',
        'nicEditorParagraphSpacingTopSelect',
        'nicEditorParagraphSpacingSelect',
        'nicEditorLetterSpacingSelect',
        'nicEditorWordSpacingSelect',
        'nicEditorScaleXSelect'
    ];
    
    window.zrxActiveSelects = [];
    
    classesToPatch.forEach(function(clsName) {
        var cls = window[clsName];
        if (cls && cls.prototype) {
            var origConstruct = cls.prototype.construct;
            if (origConstruct) {
                cls.prototype.construct = function() {
                    window.zrxActiveSelects.push(this);
                    origConstruct.apply(this, arguments);
                };
            }
            
            var origOpen = cls.prototype.open;
            if (origOpen) {
                cls.prototype.open = function() {
                    // Close all other open select dropdowns
                    for (var i = 0; i < window.zrxActiveSelects.length; i++) {
                        var s = window.zrxActiveSelects[i];
                        if (s !== this) {
                            s.close();
                        }
                    }
                    origOpen.apply(this, arguments);
                };
            }
        }
    });

    // Constrain NicEdit panes to viewport bounds to prevent off-screen clipping on the left/right
    if (typeof nicEditorPane !== 'undefined') {
        var origPanePosition = nicEditorPane.prototype.position;
        nicEditorPane.prototype.position = function() {
            origPanePosition.call(this);
            if (this.contain && this.pane) {
                var left = parseInt(this.contain.getStyle('left')) || 0;
                var width = parseInt(this.pane.getStyle('width')) || 270;
                
                // 1. Constrain to left viewport edge (minimum 10px)
                if (left < 10) {
                    left = 10;
                }
                
                // 2. Constrain to right viewport edge
                var maxLeft = window.innerWidth - width - 20;
                if (left > maxLeft) {
                    left = maxLeft;
                }
                
                // 3. Re-enforce left boundary in case maxLeft < 10
                if (left < 10) {
                    left = 10;
                }
                
                this.contain.setStyle({ left: left + 'px' });
            }
        };
    }
})();

