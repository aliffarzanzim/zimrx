// Prescription header, footer, background, and doctor seal setup editor.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zimrx-header-form');
    
    // Onboarding form submission and skip button handling
    const onboardBackdrop = document.getElementById('zrx-onboard-backdrop');
    const onboardForm = document.getElementById('zrx-onboard-form');
    const onboardSkipBtn = document.getElementById('zrx-onboard-skip-btn');

    if (onboardForm) {
        onboardForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('zrx-onboard-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }

            const formData = new FormData(onboardForm);

            try {
                const response = await fetch('api/header_onboarding_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData).toString(),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                if ((await response.text()).trim() === '1') {
                    if (onboardBackdrop) onboardBackdrop.remove();
                    window.location.reload();
                } else {
                    alert('Failed to save profile. Please try again.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Save & Apply Header';
                    }
                }
            } catch (err) {
                alert('Connection error occurred.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save & Apply Header';
                }
            }
        });
    }

    if (onboardSkipBtn && onboardForm) {
        onboardSkipBtn.addEventListener('click', () => {
            // Populate Bangla defaults
            onboardForm.querySelector('[name="name_bn"]').value = 'ডা. শাফায়েত মাহমুদ';
            onboardForm.querySelector('[name="qualifications_bn"]').value = 'এমবিবিএস, এমডি (কার্ডিওলজি), এফসিপিএস (মেডিসিন), বিসিএস(স্বাস্থ্য)';
            onboardForm.querySelector('[name="designation_bn"]').value = 'চিফ কনসালটেন্ট ও বিভাগীয় প্রধান (কার্ডিওলজি)';
            onboardForm.querySelector('[name="institute_bn"]').value = 'এপেক্স কার্ডিয়াক ইনস্টিটিউট';
            onboardForm.querySelector('[name="speciality_bn"]').value = 'হৃদরোগ, উচ্চ রক্তচাপ ও মেডিসিন বিশেষজ্ঞ';
            onboardForm.querySelector('[name="bmdc_bn"]').value = 'বিএমডিসি রেজি নং: A-112233';
            onboardForm.querySelector('[name="phone_bn"]').value = 'মোবাইলঃ ০১৭১০-XXXXXX';

            // Populate English defaults
            onboardForm.querySelector('[name="name_en"]').value = 'Dr. Shafayet Mahmud';
            onboardForm.querySelector('[name="qualifications_en"]').value = 'MBBS, MD (Cardiology), FCPS (Medicine), BCS (Health)';
            onboardForm.querySelector('[name="designation_en"]').value = 'Chief Consultant & HOD (Cardiology)';
            onboardForm.querySelector('[name="institute_en"]').value = 'Apex Cardiac Institute';
            onboardForm.querySelector('[name="speciality_en"]').value = 'Cardiology, Hypertension & Medicine Specialist';
            onboardForm.querySelector('[name="bmdc_en"]').value = 'BMDC Reg. No: A-112233';
            onboardForm.querySelector('[name="phone_en"]').value = 'Mobile: 01710-XXXXXX';

            // Submit form
            const submitBtn = document.getElementById('zrx-onboard-submit-btn');
            if (submitBtn) submitBtn.click();
        });
    }

    const showToast = (message) => {
        const toast = document.getElementById('print-setup-toast');
        const toastMessage = document.getElementById('print-setup-toast-message');
        const toastClose = document.getElementById('print-setup-toast-close');
        if (toast && toastMessage) {
            toastMessage.textContent = message;
            toast.hidden = false;
            if (toastClose) {
                toastClose.focus();
                toastClose.onclick = () => { toast.hidden = true; };
            }
            toast.onclick = (e) => {
                if (e.target === toast) {
                    toast.hidden = true;
                }
            };
        } else {
            alert(message);
        }
    };

    const zrxConfirm = (message, onConfirm) => {
        const confirmOverlay = document.getElementById('zrx-confirm-overlay');
        const confirmMsg     = document.getElementById('zrx-confirm-message');
        const confirmOk      = document.getElementById('zrx-confirm-ok');
        const confirmCancel  = document.getElementById('zrx-confirm-cancel');

        if (!confirmOverlay || !confirmMsg || !confirmOk || !confirmCancel) {
            if (confirm(message)) onConfirm();
            return;
        }

        confirmMsg.textContent = message;
        confirmOverlay.classList.remove('is-hidden');

        const cleanUp = () => {
            confirmOverlay.classList.add('is-hidden');
            // Remove previous handlers to avoid multiple calls
            const newOk = confirmOk.cloneNode(true);
            const newCancel = confirmCancel.cloneNode(true);
            confirmOk.parentNode.replaceChild(newOk, confirmOk);
            confirmCancel.parentNode.replaceChild(newCancel, confirmCancel);
        };

        // Wire fresh listeners
        document.getElementById('zrx-confirm-ok').addEventListener('click', () => {
            cleanUp();
            onConfirm();
        });

        document.getElementById('zrx-confirm-cancel').addEventListener('click', () => {
            cleanUp();
        });

        confirmOverlay.onclick = (e) => {
            if (e.target === confirmOverlay) {
                cleanUp();
            }
        };
    };
    const headerDraft = document.getElementById('zrx-header-draft');

    const colorPicker = document.getElementById('header-color-picker');
    const colorValue = document.getElementById('header-color-value');
    const uploadTrigger = document.getElementById('upload-logo-trigger');
    const uploadInput = document.getElementById('header-logo-file');
    const uploadStatus = document.getElementById('logo-upload-status');
    const logoWrap = document.getElementById('header-logo-wrap');
    const logoPreview = document.getElementById('header-logo-preview');
    const logoPlaceholder = document.getElementById('header-logo-placeholder');
    const logoPathInput = document.getElementById('logo_path');
    const footerInput = document.getElementById('footer_html');

    const normalizeColor = value => String(value || '').replace(/[^a-fA-F0-9]/g, '').slice(0, 6).toUpperCase().padEnd(6, 'F');

    // Responsive dynamic scale for iframe preview
    const hfFrame = document.getElementById('hf-preview-frame');
    const hfWrap  = document.getElementById('zrx-paper-wrap');
    const sheetStage = document.querySelector('.zrx-sheet-stage');
    const PAGE_W_CM = 21;
    const PAGE_H_CM = 29.7;

    const scaleIframe = () => {
        if (!hfFrame || !hfWrap) return;
        const availableWidth = sheetStage ? Math.max(200, sheetStage.clientWidth - 40) : 500;
        const fullWidthPx = PAGE_W_CM * (96 / 2.54); // ~793.7px
        
        let dynamicScale = availableWidth / fullWidthPx;
        if (dynamicScale > 0.95) dynamicScale = 0.95;
        if (dynamicScale < 0.35) dynamicScale = 0.35;

        hfFrame.style.width  = `${PAGE_W_CM}cm`;
        hfFrame.style.height = `${PAGE_H_CM}cm`;
        hfFrame.style.transform = `scale(${dynamicScale})`;
        hfWrap.style.width  = `calc(${PAGE_W_CM}cm * ${dynamicScale})`;
        hfWrap.style.height = `calc(${PAGE_H_CM}cm * ${dynamicScale})`;
    };

    window.addEventListener('resize', scaleIframe);
    if (typeof ResizeObserver !== 'undefined' && sheetStage) {
        new ResizeObserver(scaleIframe).observe(sheetStage);
    }
    scaleIframe();

    // Real-time live preview sync
    const getPreviewDoc = () => {
        try {
            return hfFrame?.contentDocument || null;
        } catch (e) {
            return null;
        }
    };

    const updatePreviewHeaderColor = (hex) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const pageHeader = doc.querySelector('#pageHeader');
        if (pageHeader) pageHeader.style.backgroundColor = `#${hex}`;
    };

    const sanitizePreviewHtml = (rawHtml) => {
        if (!rawHtml || typeof rawHtml !== 'string') return '';
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(rawHtml, 'text/html');
            const dangerousElements = doc.querySelectorAll('script, iframe, object, embed, form, link, meta, base');
            dangerousElements.forEach(el => el.remove());

            const allElements = doc.body.querySelectorAll('*');
            allElements.forEach(el => {
                Array.from(el.attributes).forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const val = attr.value.trim().toLowerCase();
                    if (name.startsWith('on') || val.startsWith('javascript:') || val.startsWith('data:text/html')) {
                        el.removeAttribute(attr.name);
                    }
                });
            });

            return doc.body.innerHTML;
        } catch (e) {
            return '';
        }
    };

    const updatePreviewLeftHeader = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const leftBox = doc.querySelector('.zrx-header-left');
        if (leftBox) leftBox.innerHTML = sanitizePreviewHtml(html);
    };

    const updatePreviewRightHeader = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const rightBox = doc.querySelector('.zrx-header-right');
        if (rightBox) rightBox.innerHTML = sanitizePreviewHtml(html);
    };

    const updatePreviewFooter = (html) => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const footerBox = doc.querySelector('#preview-footer');
        if (footerBox) {
            footerBox.innerHTML = sanitizePreviewHtml(html);
            footerBox.style.display = 'block';
        }
    };

    const updatePreviewLogo = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const displayLogo = form.querySelector('input[name="display_logo"]:checked')?.value || 'yes';
        const logoPath = logoPathInput?.value || '';
        const layout = doc.querySelector('.zrx-header-layout');
        let logoDiv = doc.querySelector('.zrx-header-logo');

        if (displayLogo === 'yes' && logoPath) {
            if (layout) {
                layout.classList.remove('zrx-no-logo');
                layout.classList.add('zrx-has-logo');
            }
            if (!logoDiv && layout) {
                logoDiv = doc.createElement('div');
                logoDiv.className = 'zrx-header-logo';
                logoDiv.innerHTML = `<img src="${logoPath}" alt="Header logo">`;
                const rightBox = doc.querySelector('.zrx-header-right');
                if (rightBox) layout.insertBefore(logoDiv, rightBox);
                else layout.appendChild(logoDiv);
            } else if (logoDiv) {
                logoDiv.style.display = 'block';
                const img = logoDiv.querySelector('img');
                if (img) img.src = logoPath;
            }
        } else {
            if (layout) {
                layout.classList.remove('zrx-has-logo');
                layout.classList.add('zrx-no-logo');
            }
            if (logoDiv) {
                logoDiv.style.display = 'none';
            }
        }
    };

    const syncHeaderCustomizationPreview = () => {
        const doc = getPreviewDoc();
        if (!doc) return;

        const leftWidthInput = document.getElementById('header_left_width');
        const logoWidthInput = document.getElementById('header_logo_width');
        const rightWidthInput = document.getElementById('header_right_width');

        const leftWidth = leftWidthInput ? leftWidthInput.value : '40';
        const logoWidth = logoWidthInput ? logoWidthInput.value : '18';
        const rightWidth = rightWidthInput ? rightWidthInput.value : '40';

        const scale = document.getElementById('logo_scale').value;
        const rotation = document.getElementById('logo_rotation').value;
        const opacity = document.getElementById('logo_opacity').value;
        const offsetX = document.getElementById('logo_offset_x').value;
        const offsetY = document.getElementById('logo_offset_y').value;

        // Update strong label values
        document.getElementById('logo-scale-val').textContent = `${scale}%`;
        document.getElementById('logo-rotation-val').textContent = `${rotation}°`;
        document.getElementById('logo-opacity-val').textContent = `${opacity}%`;
        document.getElementById('logo-offset-x-val').textContent = `${offsetX}px`;
        document.getElementById('logo-offset-y-val').textContent = `${offsetY}px`;

        const leftCol = doc.querySelector('.zrx-header-left');
        const logoCol = doc.querySelector('.zrx-header-logo');
        const rightCol = doc.querySelector('.zrx-header-right');
        const logoImg = doc.querySelector('.zrx-header-logo img');

        if (leftCol) leftCol.style.width = `${leftWidth}%`;
        if (rightCol) rightCol.style.width = `${rightWidth}%`;
        if (logoCol) logoCol.style.width = `${logoWidth}%`;
        if (logoImg) {
            logoImg.style.transform = `translate(${offsetX}px, ${offsetY}px) rotate(${rotation}deg) scale(${scale / 100})`;
            logoImg.style.opacity = opacity / 100;
        }
    };

    const updatePreviewBackground = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        const isImageHeader = form.querySelector('input[name="header_type"]:checked')?.value === 'image';
        const page = doc.querySelector('.zrx-print-page');
        const header = doc.querySelector('#pageHeader');
        const footer = doc.querySelector('#preview-footer');
        let watermark = doc.querySelector('#preview-watermark-layer');

        const headerLayout = doc.querySelector('.zrx-header-layout');
        const curFooterHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('footer_html'))
            ? nicEditors.findEditor('footer_html').getContent()
            : (footerInput?.value || '');

        if (footer) {
            footer.style.display = 'block';
        }

        if (isImageHeader) {
            const fullBodyPath = fullBodyHeaderPathInput?.value || '';
            if (header) {
                header.style.display = 'block';
                header.style.background = 'transparent';
            }
            if (headerLayout) {
                headerLayout.style.display = 'none';
            }
            if (watermark) watermark.style.display = 'none';
            if (page) {
                page.style.backgroundImage = fullBodyPath ? `url('${fullBodyPath}')` : 'none';
                page.style.backgroundSize = '100% 100%';
                page.style.backgroundRepeat = 'no-repeat';
                page.style.backgroundPosition = 'top center';
            }
        } else {
            const hex = normalizeColor(colorValue?.value || 'FFFFFF');
            if (header) {
                header.style.display = 'block';
                header.style.backgroundColor = `#${hex}`;
            }
            if (headerLayout) {
                headerLayout.style.display = 'flex';
            }
            if (page) page.style.backgroundImage = 'none';

            const bgPath = bgImgPathInput?.value || '';
            const opacity = parseFloat(bgImgOpacityIn?.value || '0.10');
            const scale = parseFloat(bgImgScaleIn?.value || '1.0');
            const angle = parseFloat(bgImgAngleIn?.value || '0.0');
            const offX = parseFloat(bgImgOffsetXIn?.value || '0.0');
            const offY = parseFloat(bgImgOffsetYIn?.value || '0.0');

            if (!watermark && page) {
                watermark = doc.createElement('div');
                watermark.id = 'preview-watermark-layer';
                watermark.className = 'zrx-watermark-layer';
                page.insertBefore(watermark, page.firstChild);
            }

            if (watermark) {
                if (bgPath) {
                    watermark.style.display = 'block';
                    watermark.style.backgroundImage = `url('${bgPath}')`;
                    watermark.style.opacity = String(opacity);
                    watermark.style.transform = `translate(${offX}px, ${offY}px) rotate(${angle}deg) scale(${scale})`;
                } else {
                    watermark.style.display = 'none';
                }
            }
        }
    };

    const syncAllToPreview = () => {
        const hex = normalizeColor(colorValue?.value || 'FFFFFF');
        updatePreviewHeaderColor(hex);
        updatePreviewLogo();
        updatePreviewBackground();
        updatePreviewStamp();
        const leftHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('left_block_html'))
            ? nicEditors.findEditor('left_block_html').getContent()
            : (document.getElementById('left_block_html')?.value || '');
        updatePreviewLeftHeader(leftHtml);

        const rightHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('right_block_html'))
            ? nicEditors.findEditor('right_block_html').getContent()
            : (document.getElementById('right_block_html')?.value || '');
        updatePreviewRightHeader(rightHtml);

        const footerHtml = (typeof nicEditors !== 'undefined' && nicEditors.findEditor('footer_html'))
            ? nicEditors.findEditor('footer_html').getContent()
            : (footerInput?.value || '');
        updatePreviewFooter(footerHtml);
    };

    if (hfFrame) {
        hfFrame.addEventListener('load', () => {
            scaleIframe();
            syncAllToPreview();
        });
    }

    const applyColor = value => {
        const hex = normalizeColor(value);
        colorValue.value = hex;
        colorPicker.value = `#${hex}`;
        const leftEdit = document.querySelector('.panel-left .panel-content');
        const rightEdit = document.querySelector('.panel-right .panel-content');
        if (leftEdit) leftEdit.style.background = `#${hex}`;
        if (rightEdit) rightEdit.style.background = `#${hex}`;
        updatePreviewHeaderColor(hex);
    };

    const updateLogoVisibility = () => {
        const selected = form.querySelector('input[name="display_logo"]:checked')?.value || 'yes';
        
        const logoCtrl = document.getElementById('logo-customization-controls');
        const logoWidthCtrl = document.querySelector('.width-ctrl-logo');
        if (logoCtrl) logoCtrl.classList.toggle('is-hidden', selected !== 'yes');
        if (logoWidthCtrl) logoWidthCtrl.classList.toggle('is-hidden', selected !== 'yes');

        const leftWidthInput = document.getElementById('header_left_width');
        const rightWidthInput = document.getElementById('header_right_width');
        if (leftWidthInput && rightWidthInput) {
            if (selected === 'yes') {
                if (leftWidthInput.value === '49') leftWidthInput.value = '40';
                if (rightWidthInput.value === '49') rightWidthInput.value = '40';
            } else {
                if (leftWidthInput.value === '40') leftWidthInput.value = '49';
                if (rightWidthInput.value === '40') rightWidthInput.value = '49';
            }
        }

        const previewBox = document.querySelector('.logo-preview-box');
        if (previewBox) {
            previewBox.classList.toggle('logo-hidden', selected !== 'yes');
        }
        headerDraft.classList.toggle('zrx-has-logo', selected === 'yes');
        headerDraft.classList.toggle('zrx-no-logo', selected !== 'yes');
        
        const titleRow = document.querySelector('.header-column-titles');
        if (titleRow) {
            titleRow.classList.toggle('zrx-has-logo', selected === 'yes');
            titleRow.classList.toggle('zrx-no-logo', selected !== 'yes');
        }
        updatePreviewLogo();
        syncHeaderCustomizationPreview();
    };

    // Toggle between Text Header (4 boxes) and Image Header (With Body)
    const textHeaderView = document.getElementById('zrx-header-draft');
    const imageHeaderView = document.getElementById('zrx-image-header-view');
    form.querySelectorAll('input[name="header_type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const isImage = form.querySelector('input[name="header_type"]:checked')?.value === 'image';
            if (textHeaderView) textHeaderView.classList.toggle('is-hidden', isImage);
            if (imageHeaderView) imageHeaderView.classList.toggle('is-hidden', !isImage);
            syncBgCardState();
            updatePreviewBackground();
        });
    });

    // Image Header (With Body) Upload Handler
    const imageBodyInput = document.getElementById('image-body-file');
    const imageBodySubmit = document.getElementById('image-body-submit');
    const imageBodyStatus = document.getElementById('image-body-upload-status');
    const imageBodyPreview = document.getElementById('image-body-preview');
    const imageBodyPreviewContainer = document.getElementById('image-body-preview-container');
    const fullBodyHeaderPathInput = document.getElementById('full_body_header_path');

    if (imageBodySubmit && imageBodyInput) {
        imageBodySubmit.addEventListener('click', async () => {
            if (!imageBodyInput.files.length) {
                imageBodyStatus.textContent = 'à¦ªà§à¦°à¦¥à¦®à§‡ à¦«à¦¾à¦‡à¦² à¦¸à¦¿à¦²à§‡à¦•à§à¦Ÿ à¦•à¦°à§à¦¨ à¦à¦°à¦ªà¦° à¦¸à¦¾à¦¬à¦®à¦¿à¦Ÿ à¦šà¦¾à¦ªà§à¦¨à¥¤';
                imageBodyStatus.style.color = '#ef4444';
                return;
            }
            imageBodyStatus.textContent = 'Uploading...';
            imageBodyStatus.style.color = 'var(--zrx-primary)';

            const fd = new FormData();
            fd.append('image_body', imageBodyInput.files[0]);

            try {
                const res = await fetch('api/upload_full_body_header.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok && data.full_body_header_path) {
                    fullBodyHeaderPathInput.value = data.full_body_header_path;
                    imageBodyPreview.src = data.full_body_header_path;
                    imageBodyPreviewContainer.classList.remove('is-hidden');
                    imageBodyStatus.textContent = data.message || 'à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦«à§à¦²à¦¬à¦¡à¦¿ à¦‡à¦®à§‡à¦œ à¦¹à§‡à¦¡à¦¾à¦° à¦†à¦ªà¦²à§‹à¦¡ à¦¸à¦®à§à¦ªà¦¨à§à¦¨ à¦¹à§Ÿà§‡à¦›à§‡à¥¤';
                    imageBodyStatus.style.color = '#16a34a';
                    updatePreviewBackground();
                } else {
                    imageBodyStatus.textContent = data.error ? `à¦¤à§à¦°à§à¦Ÿà¦¿: ${data.error}` : 'Upload failed';
                    imageBodyStatus.style.color = '#ef4444';
                }
            } catch (e) {
                imageBodyStatus.textContent = 'Network error during upload';
                imageBodyStatus.style.color = '#ef4444';
            }
        });
    }

    colorPicker.addEventListener('input', () => applyColor(colorPicker.value));
    colorValue.addEventListener('input', () => applyColor(colorValue.value));
    form.querySelectorAll('input[name="display_logo"]').forEach(input => input.addEventListener('change', updateLogoVisibility));

    const logoGalleryOpenBtn     = document.getElementById('logo-open-gallery');
    const logoGalleryOverlay     = document.getElementById('zrx-logo-gallery-overlay');
    const logoGalleryGrid        = document.getElementById('zrx-logo-gallery-grid');
    const logoGalleryClose       = document.getElementById('zrx-logo-gallery-close');
    const logoGallerySearch      = document.getElementById('logo-gallery-search');
    const logoGalleryFilter      = document.getElementById('logo-gallery-filter');
    const logoRemoveBtn          = document.getElementById('logo-remove-btn');

    // Inside-gallery upload elements
    const logoGalleryUploadBtn   = document.getElementById('logo-gallery-upload-btn');
    const logoGalleryUploadInput = document.getElementById('logo-gallery-upload-input');
    const logoGalleryUploadStatus= document.getElementById('logo-gallery-upload-status');

    let logoGalleryImages = null;

    const applyLogoSelection = (url) => {
        logoPathInput.value = url;
        logoPreview.src = url;
        logoPreview.classList.remove('is-hidden');
        logoPlaceholder.classList.add('is-hidden');
        if (logoRemoveBtn) logoRemoveBtn.classList.remove('is-hidden');
        uploadStatus.textContent = '';

        // Auto check Show radio button
        const showRadio = form.querySelector('input[name="display_logo"][value="yes"]');
        if (showRadio) showRadio.checked = true;

        updateLogoVisibility();
        updatePreviewLogo();
    };

    const renderLogoGallery = (images) => {
        const q   = (logoGallerySearch?.value || '').toLowerCase();
        const cat = (logoGalleryFilter?.value || '').toLowerCase();
        const filtered = images.filter(img =>
            (cat === '' || img.category.toLowerCase() === cat) &&
            (q   === '' || img.name.toLowerCase().includes(q) || img.category.toLowerCase().includes(q))
        );
        
        logoGalleryGrid.innerHTML = filtered.length === 0
            ? '<div class="zrx-gallery-empty">No logos found</div>'
            : filtered.map(img => {
                const isUploaded = img.category.toLowerCase() === 'uploaded';
                const filename = img.url.split('/').pop();
                const deleteBtnHtml = isUploaded 
                    ? `<button type="button" class="zrx-gallery-item-delete" data-filename="${filename}" title="Delete Logo">&#x2715;</button>` 
                    : '';
                return `
                <div class="zrx-gallery-item-wrapper">
                    <button type="button" class="zrx-gallery-item" data-url="${img.url}" title="${img.name} (${img.category})">
                        <img src="${img.url}" alt="${img.name}" loading="lazy">
                        <span class="zrx-gallery-item-name">${img.name}</span>
                        <span class="zrx-gallery-item-cat">${img.category}</span>
                    </button>
                    ${deleteBtnHtml}
                </div>`;
            }).join('');

        // Wire select events
        logoGalleryGrid.querySelectorAll('.zrx-gallery-item').forEach(btn => {
            btn.addEventListener('click', () => {
                applyLogoSelection(btn.dataset.url);
                logoGalleryOverlay.classList.add('is-hidden');
            });
        });

        // Wire delete events
        logoGalleryGrid.querySelectorAll('.zrx-gallery-item-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                
                zrxConfirm('Are you sure you want to permanently delete this uploaded logo?', async () => {
                    const filename = btn.dataset.filename;
                    const fd = new FormData();
                    fd.append('filename', filename);

                    try {
                        const res = await fetch('api/delete_header_logo.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.ok) {
                            // Remove from local cache list
                            logoGalleryImages = logoGalleryImages.filter(img => img.url.split('/').pop() !== filename);
                            renderLogoGallery(logoGalleryImages);

                            // Reset display if this logo is currently selected
                            const currentLogoUrl = logoPathInput.value;
                            if (currentLogoUrl && currentLogoUrl.split('/').pop() === filename) {
                                logoPathInput.value = '';
                                logoPreview.src = '';
                                logoPreview.classList.add('is-hidden');
                                logoPlaceholder.classList.remove('is-hidden');
                                if (logoRemoveBtn) logoRemoveBtn.classList.add('is-hidden');

                                // Auto check Hide radio button
                                const hideRadio = form.querySelector('input[name="display_logo"][value="no"]');
                                if (hideRadio) hideRadio.checked = true;

                                updateLogoVisibility();
                            }
                        } else {
                            alert(data.error || 'Failed to delete logo.');
                        }
                    } catch (err) {
                        alert('Network error while deleting logo.');
                    }
                });
            });
        });
    };

    const loadLogoGallery = async () => {
        if (logoGalleryImages) { renderLogoGallery(logoGalleryImages); return; }
        logoGalleryGrid.innerHTML = '<div class="zrx-gallery-loading">Loading&hellip;</div>';
        try {
            const res  = await fetch('api/list_header_logos.php');
            const data = await res.json();
            logoGalleryImages = data.images || [];
            const cats = data.categories || [];
            logoGalleryFilter.innerHTML = '<option value="">All</option>' +
                cats.map(c => `<option value="${c.toLowerCase()}">${c}</option>`).join('');
            renderLogoGallery(logoGalleryImages);
        } catch (e) {
            logoGalleryGrid.innerHTML = '<div class="zrx-gallery-empty">Failed to load logos</div>';
        }
    };

    if (logoGalleryOpenBtn) {
        logoGalleryOpenBtn.addEventListener('click', () => {
            logoGalleryOverlay.classList.remove('is-hidden');
            loadLogoGallery();
        });
    }
    if (logoGalleryClose) logoGalleryClose.addEventListener('click', () => logoGalleryOverlay.classList.add('is-hidden'));
    logoGalleryOverlay?.addEventListener('click', e => { if (e.target === logoGalleryOverlay) logoGalleryOverlay.classList.add('is-hidden'); });
    logoGallerySearch?.addEventListener('input', () => logoGalleryImages && renderLogoGallery(logoGalleryImages));
    logoGalleryFilter?.addEventListener('change', () => logoGalleryImages && renderLogoGallery(logoGalleryImages));

    // Inside gallery upload wiring
    if (logoGalleryUploadBtn && logoGalleryUploadInput) {
        logoGalleryUploadBtn.addEventListener('click', () => logoGalleryUploadInput.click());
        logoGalleryUploadInput.addEventListener('change', async () => {
            if (!logoGalleryUploadInput.files.length) return;
            logoGalleryUploadStatus.textContent = 'Uploading...';
            logoGalleryUploadStatus.style.color = 'var(--zrx-primary)';

            const fd = new FormData();
            fd.append('logo', logoGalleryUploadInput.files[0]);

            try {
                const res = await fetch('api/upload_header_logo.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.logo_path) {
                    logoGalleryUploadStatus.textContent = 'Uploaded successfully!';
                    logoGalleryUploadStatus.style.color = '#16a34a';
                    logoGalleryImages = null; // Invalidate cache
                    await loadLogoGallery(); // Reload and render
                    applyLogoSelection(data.logo_path); // Apply to preview
                    setTimeout(() => { logoGalleryUploadStatus.textContent = ''; }, 2000);
                } else {
                    logoGalleryUploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
                    logoGalleryUploadStatus.style.color = '#ef4444';
                }
            } catch (e) {
                logoGalleryUploadStatus.textContent = 'Network error';
                logoGalleryUploadStatus.style.color = '#ef4444';
            }
        });
    }

    // Remove logo
    if (logoRemoveBtn) {
        logoRemoveBtn.addEventListener('click', () => {
            logoPathInput.value = '';
            logoPreview.src = '';
            logoPreview.classList.add('is-hidden');
            logoPlaceholder.classList.remove('is-hidden');
            logoRemoveBtn.classList.add('is-hidden');
            uploadStatus.textContent = '';

            // Auto check Hide radio button
            const hideRadio = form.querySelector('input[name="display_logo"][value="no"]');
            if (hideRadio) hideRadio.checked = true;

            updateLogoVisibility();
        });
    }


    // Upload new logo via file input
    uploadTrigger.addEventListener('click', () => uploadInput.click());
    uploadInput.addEventListener('change', async () => {
        if (!uploadInput.files.length) return;
        uploadStatus.textContent = 'Uploading...';

        const fd = new FormData();
        fd.append('logo', uploadInput.files[0]);

        try {
            const res = await fetch('api/upload_header_logo.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.logo_path) {
                // Invalidate logo gallery cache so new upload appears next time
                logoGalleryImages = null;
                applyLogoSelection(data.logo_path);
                uploadStatus.textContent = 'Uploaded';
            } else {
                uploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
            }
        } catch (e) {
            uploadStatus.textContent = 'Network error';
        }
    });

    // Background image gallery and controls
    const bgImgCard       = document.getElementById('bgimg-card');
    const bgImgBody       = bgImgCard?.querySelector('.zrx-bgimg-body');
    const bgImgPathInput  = document.getElementById('bg_image_path');
    const bgImgOpacityIn  = document.getElementById('bg_image_opacity');
    const bgImgScaleIn    = document.getElementById('bg_image_scale');
    const bgImgAngleIn    = document.getElementById('bg_image_angle');
    const bgImgOffsetXIn  = document.getElementById('bg_image_offset_x');
    const bgImgOffsetYIn  = document.getElementById('bg_image_offset_y');

    const bgImgThumb      = document.getElementById('bgimg-current-thumb');
    const bgImgThumbImg   = document.getElementById('bgimg-thumb-img');
    const bgImgControls   = document.getElementById('bgimg-controls');
    const bgImgRemoveBtn  = document.getElementById('bgimg-remove');
    const bgImgOpenBtn    = document.getElementById('bgimg-open-gallery');

    const opacityRange    = document.getElementById('bgimg-opacity-range');
    const opacityVal      = document.getElementById('bgimg-opacity-val');
    const scaleRange      = document.getElementById('bgimg-scale-range');
    const scaleVal        = document.getElementById('bgimg-scale-val');
    const angleRange      = document.getElementById('bgimg-angle-range');
    const angleVal        = document.getElementById('bgimg-angle-val');
    const offsetXRange    = document.getElementById('bgimg-offsetx-range');
    const offsetXVal      = document.getElementById('bgimg-offsetx-val');
    const offsetYRange    = document.getElementById('bgimg-offsety-range');
    const offsetYVal      = document.getElementById('bgimg-offsety-val');

    // Seal and stamp gallery and controls
    const stampCard       = document.getElementById('stamp-card');
    const stampPathInput  = document.getElementById('stamp_path');
    const stampOpacityIn  = document.getElementById('stamp_opacity');
    const stampScaleIn    = document.getElementById('stamp_scale');
    const stampAngleIn    = document.getElementById('stamp_angle');
    const stampOffsetXIn  = document.getElementById('stamp_offset_x');
    const stampOffsetYIn  = document.getElementById('stamp_offset_y');

    const stampThumb      = document.getElementById('stamp-current-thumb');
    const stampThumbImg   = document.getElementById('stamp-thumb-img');
    const stampControls   = document.getElementById('stamp-controls');
    const stampRemoveBtn  = document.getElementById('stamp-remove');
    const stampOpenBtn    = document.getElementById('stamp-open-gallery');

    const stampOpacityRange = document.getElementById('stamp-opacity-range');
    const stampOpacityVal   = document.getElementById('stamp-opacity-val');
    const stampScaleRange   = document.getElementById('stamp-scale-range');
    const stampScaleVal     = document.getElementById('stamp-scale-val');
    const stampAngleRange   = document.getElementById('stamp-angle-range');
    const stampAngleVal     = document.getElementById('stamp-angle-val');
    const stampOffsetXRange = document.getElementById('stamp-offsetx-range');
    const stampOffsetXVal   = document.getElementById('stamp-offsetx-val');
    const stampOffsetYRange = document.getElementById('stamp-offsety-range');
    const stampOffsetYVal   = document.getElementById('stamp-offsety-val');

    const stampColorInput = document.getElementById('stamp_color');
    const stampColorPicker = document.getElementById('stamp-color-picker');
    const stampColorHex = document.getElementById('stamp-color-hex');
    const stampColorEnableInput = document.getElementById('stamp_color_enable');
    const stampColorEnableChk = document.getElementById('stamp-color-enable-chk');

    // Disable bg card when image header is chosen
    const syncBgCardState = () => {
        const isText = form.querySelector('input[name="header_type"]:checked')?.value !== 'image';
        if (bgImgCard) bgImgCard.classList.toggle('is-disabled', !isText);
        if (bgImgBody) bgImgBody.classList.toggle('is-locked', !isText);
    };
    syncBgCardState();

    // Slider events
    const bindSlider = (range, valEl, hiddenIn, suffix, decimals, divisor, callback) => {
        if (!range) return;
        range.addEventListener('input', () => {
            const raw = parseFloat(range.value);
            const stored = raw / divisor;
            if (hiddenIn) hiddenIn.value = stored.toFixed(decimals);
            if (valEl) valEl.textContent = raw + suffix;
            callback?.();
        });
    };

    const toggleStampColorRow = () => {
        const path = stampPathInput?.value || '';
        const isSvg = path.toLowerCase().endsWith('.svg');
        const enableRow = document.getElementById('stamp-color-enable-row');
        const colorRow = document.getElementById('stamp-color-row');
        
        if (enableRow) {
            enableRow.style.display = isSvg ? 'flex' : 'none';
        }
        if (colorRow) {
            const isEnabled = stampColorEnableChk?.checked;
            colorRow.style.display = (isSvg && isEnabled) ? 'flex' : 'none';
        }
    };

    const updatePreviewStamp = () => {
        const doc = getPreviewDoc();
        if (!doc) return;
        let stamp = doc.querySelector('#preview-stamp-layer');
        if (!stamp) {
            const page = doc.querySelector('.zrx-print-page');
            if (page) {
                stamp = doc.createElement('div');
                stamp.id = 'preview-stamp-layer';
                stamp.className = 'zrx-stamp-layer';
                page.appendChild(stamp);
            }
        }

        const path = stampPathInput?.value || '';
        const opacity = parseFloat(stampOpacityIn?.value || '1.0');
        const scale = parseFloat(stampScaleIn?.value || '1.0');
        const angle = parseFloat(stampAngleIn?.value || '0.0');
        const offX = parseFloat(stampOffsetXIn?.value || '0.0');
        const offY = parseFloat(stampOffsetYIn?.value || '0.0');
        const stampColor = stampColorInput?.value || '#000000';
        const isColorEnabled = stampColorEnableChk?.checked;

        // Toggle visibility of the SVG color picker rows
        toggleStampColorRow();

        if (stamp) {
            if (path) {
                stamp.style.display = 'block';
                stamp.style.transform = `translate(${offX}px, ${offY}px) rotate(${angle}deg) scale(${scale})`;

                let inner = stamp.querySelector('#preview-stamp-inner');
                if (!inner) {
                    inner = doc.createElement('div');
                    inner.id = 'preview-stamp-inner';
                    inner.style.width = '100%';
                    inner.style.height = '100%';
                    inner.style.backgroundRepeat = 'no-repeat';
                    inner.style.backgroundPosition = 'center center';
                    inner.style.backgroundSize = 'contain';
                    inner.style.position = 'absolute';
                    inner.style.top = '0';
                    inner.style.left = '0';
                    inner.style.zIndex = '1';
                    stamp.appendChild(inner);
                }

                inner.style.opacity = String(opacity);

                const isSvg = path.toLowerCase().endsWith('.svg');
                if (isSvg && isColorEnabled) {
                    inner.style.backgroundImage = 'none';
                    inner.style.maskImage = `url('${path}')`;
                    inner.style.webkitMaskImage = `url('${path}')`;
                    inner.style.maskSize = 'contain';
                    inner.style.webkitMaskSize = 'contain';
                    inner.style.maskRepeat = 'no-repeat';
                    inner.style.webkitMaskRepeat = 'no-repeat';
                    inner.style.maskPosition = 'center center';
                    inner.style.webkitMaskPosition = 'center center';
                    inner.style.backgroundColor = stampColor;
                } else {
                    inner.style.backgroundImage = `url('${path}')`;
                    inner.style.maskImage = 'none';
                    inner.style.webkitMaskImage = 'none';
                    inner.style.backgroundColor = 'transparent';
                }

                // Bind mouse drag listeners for interactive stamp moving
                if (!stamp._dragBound) {
                    stamp._dragBound = true;
                    stamp.style.cursor = 'move';
                    stamp.style.pointerEvents = 'auto';

                    let isDragging = false;
                    let startX = 0;
                    let startY = 0;
                    let startOffsetX = 0;
                    let startOffsetY = 0;

                    stamp.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        isDragging = true;
                        startX = e.clientX;
                        startY = e.clientY;
                        startOffsetX = parseFloat(stampOffsetXIn?.value || '0.0');
                        startOffsetY = parseFloat(stampOffsetYIn?.value || '0.0');

                        const onMouseMove = (moveEvent) => {
                            if (!isDragging) return;
                            const dx = moveEvent.clientX - startX;
                            const dy = moveEvent.clientY - startY;

                            const newX = Math.round(startOffsetX + dx);
                            const newY = Math.round(startOffsetY + dy);

                            const clampedX = Math.max(-1200, Math.min(1200, newX));
                            const clampedY = Math.max(-1200, Math.min(1200, newY));

                            if (stampOffsetXIn) stampOffsetXIn.value = clampedX;
                            if (stampOffsetYIn) stampOffsetYIn.value = clampedY;

                            if (stampOffsetXRange) stampOffsetXRange.value = clampedX;
                            if (stampOffsetYRange) stampOffsetYRange.value = clampedY;

                            if (stampOffsetXVal) stampOffsetXVal.textContent = clampedX + 'px';
                            if (stampOffsetYVal) stampOffsetYVal.textContent = clampedY + 'px';

                            // Dynamically update element style for smooth drag feel
                            const currentOpacity = parseFloat(stampOpacityIn?.value || '1.0');
                            const currentScale = parseFloat(stampScaleIn?.value || '1.0');
                            const currentAngle = parseFloat(stampAngleIn?.value || '0.0');
                            stamp.style.transform = `translate(${clampedX}px, ${clampedY}px) rotate(${currentAngle}deg) scale(${currentScale})`;
                        };

                        const onMouseUp = () => {
                            isDragging = false;
                            doc.removeEventListener('mousemove', onMouseMove);
                            doc.removeEventListener('mouseup', onMouseUp);
                            window.removeEventListener('mouseup', onMouseUp);
                        };

                        doc.addEventListener('mousemove', onMouseMove);
                        doc.addEventListener('mouseup', onMouseUp);
                        window.addEventListener('mouseup', onMouseUp);
                    });
                }
            } else {
                stamp.style.display = 'none';
            }
        }
    };

    bindSlider(opacityRange, opacityVal, bgImgOpacityIn, '%', 2, 100, updatePreviewBackground);
    bindSlider(scaleRange,   scaleVal,   bgImgScaleIn,   '%', 2, 100, updatePreviewBackground);
    bindSlider(angleRange,   angleVal,   bgImgAngleIn,   '°', 1, 1,   updatePreviewBackground);
    bindSlider(offsetXRange, offsetXVal, bgImgOffsetXIn, 'px', 1, 1,  updatePreviewBackground);
    bindSlider(offsetYRange, offsetYVal, bgImgOffsetYIn, 'px', 1, 1,  updatePreviewBackground);

    bindSlider(stampOpacityRange, stampOpacityVal, stampOpacityIn, '%', 2, 100, updatePreviewStamp);
    bindSlider(stampScaleRange,   stampScaleVal,   stampScaleIn,   '%', 2, 100, updatePreviewStamp);
    bindSlider(stampAngleRange,   stampAngleVal,   stampAngleIn,   '°', 1, 1,   updatePreviewStamp);
    bindSlider(stampOffsetXRange, stampOffsetXVal, stampOffsetXIn, 'px', 1, 1,  updatePreviewStamp);
    bindSlider(stampOffsetYRange, stampOffsetYVal, stampOffsetYIn, 'px', 1, 1,  updatePreviewStamp);

    // Remove bg image
    if (bgImgRemoveBtn) {
        bgImgRemoveBtn.addEventListener('click', () => {
            if (bgImgPathInput)  bgImgPathInput.value = '';
            if (bgImgThumb)      bgImgThumb.classList.add('is-hidden');
            if (bgImgControls)   bgImgControls.classList.add('is-hidden');
            updatePreviewBackground();
        });
    }

    // Remove stamp
    if (stampRemoveBtn) {
        stampRemoveBtn.addEventListener('click', () => {
            if (stampPathInput)  stampPathInput.value = '';
            if (stampThumb)      stampThumb.classList.add('is-hidden');
            if (stampControls)   stampControls.classList.add('is-hidden');
            updatePreviewStamp();
        });
    }

    // Select background: set image after gallery pick
    const setSelectedBgImage = (url) => {
        if (bgImgPathInput && !bgImgPathInput.value) {
            if (bgImgOpacityIn) bgImgOpacityIn.value = '0.1';
            const opacityRange = document.getElementById('bgimg-opacity-range');
            const opacityVal = document.getElementById('bgimg-opacity-val');
            if (opacityRange) opacityRange.value = '10';
            if (opacityVal) opacityVal.textContent = '10%';
        }
        if (bgImgPathInput)  bgImgPathInput.value = url;
        if (bgImgThumbImg)   bgImgThumbImg.src = url;
        if (bgImgThumb)      bgImgThumb.classList.remove('is-hidden');
        if (bgImgControls)   bgImgControls.classList.remove('is-hidden');
        updatePreviewBackground();
    };

    // Select stamp: set image after gallery pick
    const setSelectedStampImage = (url) => {
        if (stampPathInput)  stampPathInput.value = url;
        if (stampThumbImg)   stampThumbImg.src = url;
        if (stampThumb)      stampThumb.classList.remove('is-hidden');
        if (stampControls)   stampControls.classList.remove('is-hidden');
        updatePreviewStamp();
    };

    // Gallery modal
    const overlay      = document.getElementById('zrx-gallery-overlay');
    const galleryGrid  = document.getElementById('zrx-gallery-grid');
    const galleryClose = document.getElementById('zrx-gallery-close');
    const gallerySearch = document.getElementById('gallery-search');
    const galleryFilter = document.getElementById('gallery-filter');

    let gallerySelectionCallback = null;
    let galleryCache = {};
    let currentIsBgGallery = false;
    let activeEndpointUrl = '';

    const renderGallery = (images, isBgGallery = false) => {
        const q   = (gallerySearch?.value || '').toLowerCase();
        const cat = (galleryFilter?.value || '').toLowerCase();
        const filtered = images.filter(img =>
            (cat === '' || img.category.toLowerCase() === cat) &&
            (q   === '' || img.name.toLowerCase().includes(q) || img.category.toLowerCase().includes(q))
        );
        galleryGrid.innerHTML = filtered.length === 0
            ? '<div class="zrx-gallery-empty">No images found</div>'
            : filtered.map(img => {
                const isUploaded = img.category.toLowerCase() === 'uploaded';
                const filename = img.url.split('/').pop();
                const deleteBtnHtml = isUploaded
                    ? `<button type="button" class="zrx-gallery-item-delete" data-filename="${filename}" title="Delete Uploaded Image">&#x2715;</button>`
                    : '';
                return `
                <div class="zrx-gallery-item-wrapper">
                    <button type="button" class="zrx-gallery-item" data-url="${img.url}" title="${img.name} (${img.category})">
                        <img src="${img.url}" alt="${img.name}" loading="lazy">
                        <span class="zrx-gallery-item-name">${img.name}</span>
                        <span class="zrx-gallery-item-cat">${img.category}</span>
                    </button>
                    ${deleteBtnHtml}
                </div>`;
            }).join('');

        // Wire select events
        galleryGrid.querySelectorAll('.zrx-gallery-item').forEach(btn => {
            btn.addEventListener('click', () => {
                if (gallerySelectionCallback) {
                    gallerySelectionCallback(btn.dataset.url);
                }
                overlay.classList.add('is-hidden');
            });
        });

        // Wire delete events
        galleryGrid.querySelectorAll('.zrx-gallery-item-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const filename = btn.dataset.filename;
                const confirmMsg = isBgGallery
                    ? 'Are you sure you want to permanently delete this uploaded background image?'
                    : 'Are you sure you want to permanently delete this uploaded seal/stamp image?';
                const deleteApi = isBgGallery ? 'api/delete_background_image.php' : 'api/delete_seal_and_stamp.php';
                const reloadApi = isBgGallery ? 'api/list_background_images.php' : 'api/list_seal_and_stamps.php';

                zrxConfirm(confirmMsg, async () => {
                    const fd = new FormData();
                    fd.append('filename', filename);
                    try {
                        const res = await fetch(deleteApi, { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.ok) {
                            galleryCache = {};
                            await loadGallery(reloadApi, isBgGallery);
                        } else {
                            alert(data.error || 'Failed to delete file');
                        }
                    } catch (err) {
                        alert('Network error during delete');
                    }
                });
            });
        });
    };

    const loadGallery = async (endpointUrl, isBgGallery = false) => {
        activeEndpointUrl = endpointUrl;
        currentIsBgGallery = isBgGallery;
        if (galleryCache[endpointUrl]) {
            renderGallery(galleryCache[endpointUrl], isBgGallery);
            return;
        }
        galleryGrid.innerHTML = '<div class="zrx-gallery-loading">Loading…</div>';
        try {
            const res  = await fetch(endpointUrl);
            const data = await res.json();
            const images = data.images || [];
            galleryCache[endpointUrl] = images;

            const cats = data.categories || [];
            galleryFilter.innerHTML = '<option value="">All Categories</option>' +
                cats.map(c => `<option value="${c.toLowerCase()}">${c}</option>`).join('');

            renderGallery(images, isBgGallery);
        } catch (e) {
            galleryGrid.innerHTML = '<div class="zrx-gallery-empty">Failed to load images</div>';
        }
    };

    if (bgImgOpenBtn) {
        bgImgOpenBtn.addEventListener('click', () => {
            if (bgImgCard?.classList.contains('is-disabled')) return;
            
            // Set dynamic title
            const titleEl = document.getElementById('zrx-gallery-title');
            if (titleEl) titleEl.textContent = 'Select Background Image';
            
            // Show upload container
            const uploadCont = document.getElementById('bg-gallery-upload-container');
            if (uploadCont) uploadCont.style.display = 'flex';
            
            overlay.classList.remove('is-hidden');
            gallerySelectionCallback = setSelectedBgImage;
            loadGallery('api/list_background_images.php', true);
        });
    }

    if (stampOpenBtn) {
        stampOpenBtn.addEventListener('click', () => {
            // Set dynamic title
            const titleEl = document.getElementById('zrx-gallery-title');
            if (titleEl) titleEl.textContent = 'Select Seal & Stamp';
            
            // Show upload container
            const uploadCont = document.getElementById('bg-gallery-upload-container');
            if (uploadCont) uploadCont.style.display = 'flex';
            
            overlay.classList.remove('is-hidden');
            gallerySelectionCallback = setSelectedStampImage;
            loadGallery('api/list_seal_and_stamps.php', false);
        });
    }

    // Background & Stamp gallery upload wiring
    const bgGalleryUploadBtn = document.getElementById('bg-gallery-upload-btn');
    const bgGalleryUploadInput = document.getElementById('bg-gallery-upload-input');
    const bgGalleryUploadStatus = document.getElementById('bg-gallery-upload-status');

    if (bgGalleryUploadBtn && bgGalleryUploadInput) {
        bgGalleryUploadBtn.addEventListener('click', () => bgGalleryUploadInput.click());
        bgGalleryUploadInput.addEventListener('change', async () => {
            if (!bgGalleryUploadInput.files.length) return;
            bgGalleryUploadStatus.style.display = 'block';
            bgGalleryUploadStatus.textContent = 'Uploading...';
            bgGalleryUploadStatus.style.color = 'var(--zrx-primary)';

            const uploadApi = currentIsBgGallery ? 'api/upload_background_image.php' : 'api/upload_seal_and_stamp.php';
            const fieldName = currentIsBgGallery ? 'bg_image' : 'stamp_image';
            const reloadApi = currentIsBgGallery ? 'api/list_background_images.php' : 'api/list_seal_and_stamps.php';

            const fd = new FormData();
            fd.append(fieldName, bgGalleryUploadInput.files[0]);

            try {
                const res = await fetch(uploadApi, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    bgGalleryUploadStatus.textContent = 'Uploaded successfully!';
                    bgGalleryUploadStatus.style.color = '#16a34a';
                    galleryCache = {};
                    await loadGallery(reloadApi, currentIsBgGallery);
                    setTimeout(() => {
                        bgGalleryUploadStatus.textContent = '';
                        bgGalleryUploadStatus.style.display = 'none';
                    }, 2000);
                } else {
                    bgGalleryUploadStatus.textContent = data.error ? `Error: ${data.error}` : 'Upload failed';
                    bgGalleryUploadStatus.style.color = '#ef4444';
                }
            } catch (err) {
                bgGalleryUploadStatus.textContent = 'Network error';
                bgGalleryUploadStatus.style.color = '#ef4444';
            }
            bgGalleryUploadInput.value = '';
        });
    }

    if (galleryClose) galleryClose.addEventListener('click', () => overlay.classList.add('is-hidden'));
    overlay?.addEventListener('click', e => { if (e.target === overlay) overlay.classList.add('is-hidden'); });
    gallerySearch?.addEventListener('input', () => {
        if (activeEndpointUrl) {
            renderGallery(galleryCache[activeEndpointUrl] || [], currentIsBgGallery);
        }
    });
    galleryFilter?.addEventListener('change', () => {
        if (activeEndpointUrl) {
            renderGallery(galleryCache[activeEndpointUrl] || [], currentIsBgGallery);
        }
    });

    // Initialize NicEditors
    if (typeof bkLib !== 'undefined') {
        const baseConfig = {
            fullPanel: true,
            iconsPath: 'vendor/nicedit/images/nicEditIcons-latest.gif'
        };

        const leftPanel  = document.querySelector('.panel-left  .panel-content');
        const rightPanel = document.querySelector('.panel-right .panel-content');
        const footerWrap = document.querySelector('.footer-editor-wrap');

        const leftWidth  = leftPanel  ? leftPanel.clientWidth  : 400;
        const rightWidth = rightPanel ? rightPanel.clientWidth  : 400;
        const footerWidth = footerWrap ? footerWrap.clientWidth : 715;

        const myFooterEditor = new nicEditor({ ...baseConfig, width: footerWidth }).panelInstance('footer_html');
        const myLeftEditor   = new nicEditor({ ...baseConfig, width: leftWidth  }).panelInstance('left_block_html');
        const myRightEditor  = new nicEditor({ ...baseConfig, width: rightWidth }).panelInstance('right_block_html');

        const attachLiveSync = (editorInstance, updateFn) => {
            if (!editorInstance) return;
            const sync = () => {
                editorInstance.saveContent();
                updateFn(editorInstance.getContent());
            };
            const el = editorInstance.getElm ? editorInstance.getElm() : editorInstance.elm;
            if (el) {
                el.addEventListener('input', sync);
                el.addEventListener('keyup', sync);
                el.addEventListener('blur', sync);
                el.addEventListener('paste', sync);
                el.addEventListener('cut', sync);
            }
        };

        setTimeout(() => {
            const edLeft = nicEditors.findEditor('left_block_html');
            const edRight = nicEditors.findEditor('right_block_html');
            const edFooter = nicEditors.findEditor('footer_html');

            attachLiveSync(edLeft, updatePreviewLeftHeader);
            attachLiveSync(edRight, updatePreviewRightHeader);
            attachLiveSync(edFooter, updatePreviewFooter);
        }, 300);
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Save Settings';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        if (typeof nicEditors !== 'undefined') {
            ['footer_html', 'left_block_html', 'right_block_html'].forEach(id => {
                const editorInstance = nicEditors.findEditor(id);
                if (editorInstance) {
                    editorInstance.saveContent();
                }
            });
        }

        try {
            const response = await fetch('api/header_edit_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(new FormData(form)).toString()
            });

            const ok = (await response.text()).trim() === '1';
            if (ok) {
                showToast('Saved successfully');
                // Refresh preview iframe
                const frame = document.getElementById('hf-preview-frame');
                if (frame) frame.contentWindow.location.reload();
            } else {
                showToast('Save error');
            }
        } catch (e) {
            showToast('Network error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });

    // Bind Header Customization inputs
    ['header_left_width', 'header_logo_width', 'header_right_width', 'logo_scale', 'logo_rotation', 'logo_opacity', 'logo_offset_x', 'logo_offset_y'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', syncHeaderCustomizationPreview);
            input.addEventListener('change', syncHeaderCustomizationPreview);
        }
    });

    const resetHeaderBtn = document.getElementById('btn-reset-header-customization');
    if (resetHeaderBtn) {
        resetHeaderBtn.addEventListener('click', () => {
            const hasLogo = form.querySelector('input[name="display_logo"]:checked')?.value === 'yes';

            const leftWidthInput = document.getElementById('header_left_width');
            const logoWidthInput = document.getElementById('header_logo_width');
            const rightWidthInput = document.getElementById('header_right_width');

            if (leftWidthInput) leftWidthInput.value = hasLogo ? '40' : '49';
            if (logoWidthInput) logoWidthInput.value = '18';
            if (rightWidthInput) rightWidthInput.value = hasLogo ? '40' : '49';

            const scaleInput = document.getElementById('logo_scale');
            const rotationInput = document.getElementById('logo_rotation');
            const opacityInput = document.getElementById('logo_opacity');
            const offsetXInput = document.getElementById('logo_offset_x');
            const offsetYInput = document.getElementById('logo_offset_y');

            if (scaleInput) scaleInput.value = '100';
            if (rotationInput) rotationInput.value = '0';
            if (opacityInput) opacityInput.value = '100';
            if (offsetXInput) offsetXInput.value = '0';
            if (offsetYInput) offsetYInput.value = '0';

            syncHeaderCustomizationPreview();
            showToast('Default settings loaded. Save to apply.');
        });
    }

    const applyStampColor = (color) => {
        const hex = normalizeColor(color);
        if (stampColorInput) stampColorInput.value = `#${hex}`;
        if (stampColorHex) stampColorHex.value = hex;
        if (stampColorPicker) stampColorPicker.value = `#${hex}`;
        updatePreviewStamp();
    };

    if (stampColorPicker && stampColorHex) {
        stampColorPicker.addEventListener('input', () => applyStampColor(stampColorPicker.value));
        stampColorHex.addEventListener('input', () => applyStampColor(stampColorHex.value));
    }

    if (stampColorEnableChk) {
        stampColorEnableChk.addEventListener('change', () => {
            if (stampColorEnableInput) {
                stampColorEnableInput.value = stampColorEnableChk.checked ? 'yes' : 'no';
            }
            updatePreviewStamp();
        });
    }

    applyColor(colorValue.value);
    applyStampColor(stampColorInput?.value || '#000000');
    updateLogoVisibility();
    syncHeaderCustomizationPreview();
    updatePreviewStamp();
});
