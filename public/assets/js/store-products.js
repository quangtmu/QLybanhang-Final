document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('btn-add-product');
    const modal = document.getElementById('product-modal');
    const cancelBtn = document.getElementById('btn-cancel-product');
    const form = document.getElementById('product-form');

    if (addBtn && modal) {
        addBtn.addEventListener('click', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit')) {
                window.location.href = '/store/products.php?openCreate=1';
                return;
            }
            
            if (form) {
                form.reset();
                const idInput = form.querySelector('input[name="id"]');
                if (idInput) idInput.value = '';
                const actionInput = form.querySelector('input[name="form_action"]');
                if (actionInput) actionInput.value = 'create';
                
                const variantsList = document.getElementById('variants-list');
                if (variantsList) variantsList.innerHTML = '';
                const variantsText = document.getElementById('variants_text');
                if (variantsText) variantsText.value = '';
                
                const richSurface = document.querySelector('.rich-editor-surface');
                if (richSurface) richSurface.innerHTML = '<p></p>';
                
                const currentImg = document.getElementById('current-main-image');
                if (currentImg) currentImg.style.display = 'none';
            }
            
            modal.style.display = 'block';
        });
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('openCreate') && modal) {
        modal.style.display = 'block';
        urlParams.delete('openCreate');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, document.title, newUrl);
    }
    if (cancelBtn && modal) {
        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }

    // Stepper logic
    let currentStep = window.START_STEP || 1;
    const totalSteps = 2;
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step-indicator');
    const btnNext = document.querySelector('.btn-next-step');
    const btnPrev = document.querySelector('.btn-prev-step');
    const btnSubmits = document.querySelectorAll('.btn-submit-step');

    function updateStepper() {
        steps.forEach(step => {
            step.style.display = parseInt(step.dataset.step) === currentStep ? 'block' : 'none';
        });
        indicators.forEach(indicator => {
            const stepNum = parseInt(indicator.dataset.step);
            if (stepNum === currentStep) {
                indicator.style.background = '#e0f2fe';
                indicator.style.color = '#0284c7';
            } else if (stepNum < currentStep) {
                indicator.style.background = 'transparent';
                indicator.style.color = '#10b981'; // Green for completed
            } else {
                indicator.style.background = 'transparent';
                indicator.style.color = '#64748b';
            }
        });

        if (btnPrev) btnPrev.style.display = currentStep > 1 ? 'inline-block' : 'none';
        if (btnNext) btnNext.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
        if (btnSubmits) btnSubmits.forEach(b => b.style.display = currentStep === totalSteps ? 'inline-block' : 'none');
    }

    if (btnNext) {
        btnNext.addEventListener('click', () => {
            // Check HTML5 validity for current step (optional enhancement)
            const inputs = steps[currentStep - 1].querySelectorAll('input[required], select[required], textarea[required]');
            let valid = true;
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    valid = false;
                }
            });
            if (valid && currentStep < totalSteps) {
                currentStep++;
                updateStepper();
            }
        });
    }

    if (btnPrev) {
        btnPrev.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateStepper();
            }
        });
    }

    // Initialize stepper UI
    if (steps.length > 0) {
        updateStepper();
    }

    // Category 3-level logic
    const catLarge = document.getElementById('cat_large');
    const catMedium = document.getElementById('cat_medium');
    const catSmall = document.getElementById('cat_small');

    if (catLarge && catMedium && catSmall && window.ALL_CATEGORIES) {
        const categories = window.ALL_CATEGORIES;
        const largeCats = categories.filter(c => c.level === 'large');

        catLarge.innerHTML = '<option value="">Chọn danh mục lớn</option>' +
            largeCats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

        catLarge.addEventListener('change', () => {
            const parentId = parseInt(catLarge.value);
            const mediumCats = categories.filter(c => c.level === 'medium' && parseInt(c.parent_id) === parentId);

            if (mediumCats.length === 0 && parentId) {
                catMedium.innerHTML = `<option value="${parentId}">-- Không có danh mục vừa --</option>`;
                catSmall.innerHTML = `<option value="${parentId}">-- Không có danh mục nhỏ --</option>`;
            } else {
                catMedium.innerHTML = '<option value="">Chọn danh mục vừa</option>' +
                    mediumCats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                catSmall.innerHTML = '<option value="">Chọn danh mục nhỏ</option>';
            }
        });

        catMedium.addEventListener('change', () => {
            const parentId = parseInt(catMedium.value);
            const smallCats = categories.filter(c => c.level === 'small' && parseInt(c.parent_id) === parentId);

            if (smallCats.length === 0 && parentId) {
                catSmall.innerHTML = `<option value="${parentId}">-- Không có danh mục nhỏ --</option>`;
            } else {
                catSmall.innerHTML = '<option value="">Chọn danh mục nhỏ</option>' +
                    smallCats.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            }
        });
    }

    // Price formatting
    const priceInputs = document.querySelectorAll('.js-price-input');
    priceInputs.forEach(input => {
        input.addEventListener('input', function (e) {
            let val = this.value.replace(/\D/g, '');
            if (val === '') {
                this.value = '';
                return;
            }
            let num = parseInt(val, 10);
            this.value = num.toLocaleString('vi-VN');
            const hiddenInput = document.getElementById(this.dataset.hiddenTarget);
            if (hiddenInput) {
                hiddenInput.value = num;
            }
        });
    });

    // Image Upload Previews
    const uploadMain = document.getElementById('upload-main');
    const mainImg = document.getElementById('main-image-img');
    const mainPlaceholder = document.getElementById('main-image-placeholder');
    const imageCountText = document.getElementById('image-count-text');
    const uploadGallery = document.getElementById('upload-gallery');
    const galleryGrid = document.getElementById('gallery-grid');
    
    function updateImageCount() {
        if (imageCountText && galleryGrid) {
            let count = galleryGrid.querySelectorAll('.gallery-preview-item').length + (mainImg && mainImg.style.display === 'block' ? 1 : 0);
            imageCountText.textContent = `${count}/9 ảnh`;
        }
    }

    if (uploadMain && mainImg && mainPlaceholder) {
        uploadMain.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    mainImg.src = e.target.result;
                    mainImg.style.display = 'block';
                    mainPlaceholder.style.opacity = '0';
                    updateImageCount();
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    if (uploadGallery && galleryGrid) {
        uploadGallery.addEventListener('change', function() {
            if (this.files) {
                Array.from(this.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'gallery-preview-item';
                        div.style.cssText = 'background: #f8fafc; border-radius: 8px; aspect-ratio: 1; border: 1px solid #e2e8f0; overflow:hidden; position:relative;';
                        div.innerHTML = `
                            <img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">
                            <button type="button" class="remove-gallery-btn" style="position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.5); color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x"></i></button>
                        `;
                        div.querySelector('.remove-gallery-btn').addEventListener('click', function() {
                            div.remove();
                            // Note: removing from FileList is complex for basic UI. User should re-upload if they want changes, or we can use DataTransfer.
                            updateImageCount();
                        });
                        galleryGrid.insertBefore(div, uploadGallery.closest('.gallery-upload-btn'));
                        updateImageCount();
                    }
                    reader.readAsDataURL(file);
                });
            }
        });
    }
    
    // Render existing images if editing
    const existingInput = document.querySelector('input[name="existing_images"]');
    if (existingInput && existingInput.value && galleryGrid) {
        try {
            const images = JSON.parse(existingInput.value);
            if (Array.isArray(images)) {
                images.forEach((imgUrl) => {
                    const div = document.createElement('div');
                    div.className = 'gallery-preview-item';
                    div.style.cssText = 'background: #f8fafc; border-radius: 8px; aspect-ratio: 1; border: 1px solid #e2e8f0; overflow:hidden; position:relative;';
                    // We assume imgUrl is something like 'uploads/products/...' which should be served via /public/uploads/products/...
                    const src = imgUrl.startsWith('http') ? imgUrl : `/public/${imgUrl}`;
                    div.innerHTML = `
                        <img src="${src}" style="width:100%; height:100%; object-fit:cover;">
                        <input type="hidden" name="keep_existing_images[]" value="${imgUrl}">
                        <button type="button" class="remove-gallery-btn" style="position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.5); color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer; display:flex; align-items:center; justify-content:center;"><i class="bi bi-x"></i></button>
                    `;
                    div.querySelector('.remove-gallery-btn').addEventListener('click', function() {
                        div.remove();
                        updateImageCount();
                    });
                    galleryGrid.insertBefore(div, uploadGallery.closest('.gallery-upload-btn'));
                });
            }
        } catch(e) {}
    }
    updateImageCount();

    // ==========================================
    // VARIANT GENERATOR APP (Cartesian Product)
    // ==========================================
    const variantApp = document.getElementById('variant-generator-app');
    
    if (variantApp) {
        let attributeGroups = []; // e.g. [{ name: 'Màu sắc', values: ['Đỏ', 'Xanh'] }]
        let variantsList = []; // The generated cartesian product rows

        // Basic parser for existing pipe-delimited variants format
        try {
            const existingRaw = document.querySelector('textarea[name="variants_text"]');
            if (existingRaw && existingRaw.value.trim()) {
                const lines = existingRaw.value.trim().split('\n');
                let colors = new Set();
                let sizes = new Set();
                
                lines.forEach(line => {
                    const parts = line.split('|');
                    if (parts[0]) colors.add(parts[0].trim());
                    if (parts[1]) sizes.add(parts[1].trim());
                });
                
                if (colors.size > 0) attributeGroups.push({ name: 'Màu sắc', values: Array.from(colors) });
                if (sizes.size > 0) attributeGroups.push({ name: 'Kích cỡ', values: Array.from(sizes) });
                
                // We will let generateCartesianVariants() build the list, 
                // but we should ideally merge existing price/stock. For simplicity here, we'll parse it.
                variantsList = lines.map(line => {
                    const parts = line.split('|');
                    let c = parts[0] ? parts[0].trim() : '';
                    let s = parts[1] ? parts[1].trim() : '';
                    let sku = parts[2] ? parts[2].trim() : '';
                    let price = parts[3] ? parts[3].trim() : '';
                    let stock = parts[4] ? parts[4].trim() : '0';
                    let attrs = [];
                    if (c) attrs.push(c);
                    if (s) attrs.push(s);
                    return { key: attrs.join('-'), attrs: attrs, price: price, stock: stock, sku: sku };
                });
            }
        } catch(e) {}

        function loadSavedTemplates() {
            let saved = [];
            try { saved = JSON.parse(localStorage.getItem('variantTemplates')) || []; } catch(e) {}
            return saved;
        }

        window.promptSaveTemplate = function() {
            let name = prompt("Nhập tên bộ phân loại mẫu (VD: Quần Áo, Giày Dép, ...):");
            if (!name) return;
            let saved = loadSavedTemplates();
            // Clone attribute groups without their specific values
            let groupsToSave = attributeGroups.map(g => ({ name: g.name, values: [] }));
            saved.push({ name: name, groups: groupsToSave });
            localStorage.setItem('variantTemplates', JSON.stringify(saved));
            renderVariantApp();
        };

        window.applyVariantTemplate = function(val) {
            if (!val) return;
            if (val === 'color-size') {
                attributeGroups = [{ name: 'Màu sắc', values: [] }, { name: 'Kích cỡ', values: [] }];
            } else if (val === 'color') {
                attributeGroups = [{ name: 'Màu sắc', values: [] }];
            } else if (val === 'size') {
                attributeGroups = [{ name: 'Kích cỡ', values: [] }];
            } else if (val === 'custom') {
                attributeGroups = [];
            } else if (val.startsWith('saved-')) {
                let idx = parseInt(val.replace('saved-', ''));
                let saved = loadSavedTemplates();
                if (saved[idx]) {
                    attributeGroups = JSON.parse(JSON.stringify(saved[idx].groups));
                }
            }
            generateCartesianVariants();
            renderVariantApp();
        };

        function renderVariantApp() {
            let html = '';
            
            // Template Selector Header (Compact)
            let savedTemplates = loadSavedTemplates();
            let savedOptions = savedTemplates.map((t, i) => `<option value="saved-${i}">${t.name}</option>`).join('');

            html += `<div style="display:flex; align-items:center; gap:12px; margin-bottom: 16px;">
                <div style="font-size:14px; font-weight:600; color:#334155;">Thiết lập nhanh:</div>
                <select id="variant-template-selector" onchange="applyVariantTemplate(this.value)" style="padding:4px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; outline:none; background:#fff; min-width:180px;">
                    <option value="">-- Chọn mẫu biến thể --</option>
                    <option value="color-size">Màu sắc & Kích cỡ</option>
                    <option value="color">Chỉ Màu sắc</option>
                    <option value="size">Chỉ Kích cỡ</option>
                    ${savedOptions}
                    <option value="custom">Tạo bộ mới (Trống)</option>
                </select>
                ${attributeGroups.length > 0 ? `<button type="button" onclick="promptSaveTemplate()" style="background:none; border:none; color:#0f766e; font-size:13px; font-weight:600; cursor:pointer;" title="Lưu mẫu để dùng sau"><i class="bi bi-floppy"></i> Lưu thành mẫu</button>` : ''}
            </div>`;

            // Attribute Groups Editor
            html += `<div style="display:flex; flex-direction:column; gap:16px; margin-bottom: 24px;">`;
            attributeGroups.forEach((group, gIdx) => {
                html += `
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <input type="text" value="${group.name}" placeholder="Tên nhóm (VD: Màu sắc)" onchange="updateGroupName(${gIdx}, this.value)" style="border:none; border-bottom:1px solid #cbd5e1; background:transparent; font-weight:600; font-size:14px; width:200px; outline:none; padding:4px 0;">
                            <button type="button" onclick="removeGroup(${gIdx})" style="background:none; border:none; color:#ef4444; cursor:pointer;"><i class="bi bi-x-circle-fill" style="font-size:16px;"></i></button>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            ${group.values.map((val, vIdx) => `
                                <div style="display:flex; align-items:center; background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:4px 8px; font-size:13px;">
                                    <span>${val}</span>
                                    <button type="button" onclick="removeValue(${gIdx}, ${vIdx})" style="background:none; border:none; margin-left:6px; color:#94a3b8; cursor:pointer; padding:0;"><i class="bi bi-x"></i></button>
                                </div>
                            `).join('')}
                            <input type="text" placeholder="Thêm phân loại..." onkeydown="addValue(event, ${gIdx}, this)" onblur="addValue({key:'Enter', preventDefault:()=>{} }, ${gIdx}, this)" style="border:1px dashed #cbd5e1; background:#fff; border-radius:4px; padding:4px 8px; font-size:13px; width:120px; outline:none;">
                        </div>
                    </div>
                `;
            });
            
            if (attributeGroups.length < 2) {
                html += `<button type="button" onclick="addGroup()" style="align-self:flex-start; background:#fff; border:1px dashed #0f766e; color:#0f766e; padding:8px 16px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;"><i class="bi bi-plus"></i> Thêm nhóm phân loại</button>`;
            }
            html += `</div>`;
            
            html += `<div id="variant-table-container"></div>`;
            
            variantApp.innerHTML = html;
            renderVariantTable();
        }
        
        function renderVariantTable() {
            const tableContainer = document.getElementById('variant-table-container');
            if (!tableContainer) return;
            
            if (attributeGroups.length === 0 || variantsList.length === 0) {
                tableContainer.innerHTML = `<div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:30px; text-align:center; color:#64748b; font-size:13px;">Bạn chưa thiết lập phân loại hàng. Thêm nhóm phân loại để tạo các biến thể như Màu sắc, Kích cỡ.</div>`;
                return;
            }
            
            let html = `<div style="overflow-x:auto; border:1px solid #e2e8f0; border-radius:8px;">`;
            html += `<table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">`;
            
            // Header
            html += `<thead><tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0;">`;
            attributeGroups.forEach(g => {
                html += `<th style="padding:10px 12px; font-weight:600; color:#334155; min-width:140px;">${g.name || 'Phân loại'}</th>`;
            });
            html += `<th style="padding:10px 12px; font-weight:600; color:#334155; width:130px;">Giá bán</th>`;
            html += `<th style="padding:10px 12px; font-weight:600; color:#334155; width:100px;">Kho hàng</th>`;
            html += `<th style="padding:10px 12px; font-weight:600; color:#334155; width:100px;">SKU</th>`;
            html += `<th style="padding:10px 12px; width:40px;"></th>`;
            html += `</tr></thead><tbody>`;
            
            // Rows
            variantsList.forEach((v, vIdx) => {
                html += `<tr style="border-bottom:1px solid #f1f5f9;">`;
                // Attribute Columns (Dropdowns)
                attributeGroups.forEach((g, aIdx) => {
                    let currentVal = v.attrs[aIdx] || '';
                    let options = g.values.map(val => `<option value="${val}" ${val === currentVal ? 'selected' : ''}>${val}</option>`).join('');
                    html += `<td style="padding:10px 12px;">
                        <select onchange="updateVariantRowAttr(${vIdx}, ${aIdx}, this.value)" style="width:100%; padding:6px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; outline:none; background:#fff; color:#475569;">
                            ${options}
                            ${!g.values.includes(currentVal) && currentVal ? `<option value="${currentVal}" selected>${currentVal}</option>` : ''}
                        </select>
                    </td>`;
                });
                
                html += `<td style="padding:10px 12px;"><div style="position:relative;"><span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:11px;">đ</span><input type="text" class="js-price-input" value="${v.price ? parseInt(v.price).toLocaleString('vi-VN') : ''}" onchange="updateVariantRow(${vIdx}, 'price', this.value.replace(/\\D/g, ''))" style="width:100%; padding:6px 8px 6px 20px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px; color:#0f766e; font-weight:600;" placeholder="0"></div></td>`;
                html += `<td style="padding:10px 12px;"><input type="number" value="${v.stock}" onchange="updateVariantRow(${vIdx}, 'stock', this.value)" style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px;" placeholder="0"></td>`;
                html += `<td style="padding:10px 12px;"><input type="text" value="${v.sku}" onchange="updateVariantRow(${vIdx}, 'sku', this.value)" style="width:100%; padding:6px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:13px;" placeholder="SKU"></td>`;
                html += `<td style="padding:10px 12px; text-align:center;"><button type="button" onclick="removeVariantRow(${vIdx})" style="background:none; border:none; color:#ef4444; cursor:pointer;" title="Xóa dòng này"><i class="bi bi-trash"></i></button></td>`;
                html += `</tr>`;
            });
            html += `</tbody></table>`;
            
            // Add row button
            html += `<div style="padding:10px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:center;">
                <button type="button" onclick="addVariantRow()" style="background:none; border:none; color:#0f766e; font-size:13px; font-weight:600; cursor:pointer;"><i class="bi bi-plus-circle"></i> Thêm cấu hình biến thể</button>
            </div>`;
            
            html += `</div>`;
            tableContainer.innerHTML = html;
            
            // Re-bind price formatters
            tableContainer.querySelectorAll('.js-price-input').forEach(input => {
                input.addEventListener('input', function(e) {
                    let val = this.value.replace(/\\D/g, '');
                    if (val === '') { this.value = ''; return; }
                    this.value = parseInt(val, 10).toLocaleString('vi-VN');
                });
            });
        }

        function generateCartesianVariants() {
            if (attributeGroups.length === 0) { variantsList = []; return; }
            
            let valArrays = attributeGroups.map(g => g.values.length > 0 ? g.values : ['']);
            let cartesian = valArrays.reduce((a, b) => a.reduce((r, v) => r.concat(b.map(w => [].concat(v, w))), []));
            if (!Array.isArray(cartesian[0])) cartesian = cartesian.map(v => [v]);
            
            let newVariants = cartesian.map(combo => {
                let key = combo.join('-');
                let existing = variantsList.find(v => v.key === key);
                if (existing) return existing;
                
                let basePrice = document.getElementById('hidden_base_price') ? document.getElementById('hidden_base_price').value : '';
                return { key: key, attrs: combo, price: basePrice, stock: '0', sku: '' };
            });
            variantsList = newVariants;
            serializeVariants();
        }
        
        window.removeVariantRow = function(vIdx) {
            variantsList.splice(vIdx, 1);
            serializeVariants();
            renderVariantTable();
        };

        window.addVariantRow = function() {
            let attrs = attributeGroups.map(g => g.values[0] || '');
            let basePrice = document.getElementById('hidden_base_price') ? document.getElementById('hidden_base_price').value : '';
            variantsList.push({ key: attrs.join('-'), attrs: attrs, price: basePrice, stock: '0', sku: '' });
            serializeVariants();
            renderVariantTable();
        };

        window.updateVariantRowAttr = function(vIdx, aIdx, val) {
            variantsList[vIdx].attrs[aIdx] = val;
            variantsList[vIdx].key = variantsList[vIdx].attrs.join('-');
            serializeVariants();
        };
        
        function serializeVariants() {
            let lines = variantsList.map(v => {
                let color = v.attrs[0] || '';
                let size = v.attrs[1] || '';
                let price = v.price || '';
                let stock = v.stock || '0';
                let sku = v.sku || '';
                return `${color}|${size}|${sku}|${price}|${stock}||`;
            });
            let textObj = document.querySelector('textarea[name="variants_text"]');
            if (textObj) textObj.value = lines.join('\n');
        }

        window.addGroup = function() {
            if (attributeGroups.length < 2) {
                attributeGroups.push({ name: attributeGroups.length === 0 ? 'Màu sắc' : 'Kích cỡ', values: [] });
                generateCartesianVariants();
                renderVariantApp();
            }
        };
        window.removeGroup = function(gIdx) {
            attributeGroups.splice(gIdx, 1);
            generateCartesianVariants();
            renderVariantApp();
        };
        window.updateGroupName = function(gIdx, val) {
            attributeGroups[gIdx].name = val;
            renderVariantApp(); // Only update name, table header changes
        };
        window.addValue = function(e, gIdx, inputEl) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let val = inputEl.value.trim();
                if (val && !attributeGroups[gIdx].values.includes(val)) {
                    attributeGroups[gIdx].values.push(val);
                    generateCartesianVariants();
                    renderVariantApp();
                } else if (val) {
                    inputEl.value = '';
                }
            }
        };
        window.removeValue = function(gIdx, vIdx) {
            attributeGroups[gIdx].values.splice(vIdx, 1);
            generateCartesianVariants();
            renderVariantApp();
        };
        window.updateVariantRow = function(vIdx, field, val) {
            variantsList[vIdx][field] = val;
            serializeVariants();
        };

        if (form) {
            form.addEventListener('submit', (e) => {
                if (variantsList.length > 0) {
                    let hasError = false;
                    variantsList.forEach(v => {
                        if (!v.price || v.stock === '' || parseInt(v.stock) < 0) hasError = true;
                    });
                    if (hasError) {
                        e.preventDefault();
                        alert('Vui lòng nhập đầy đủ Giá bán và Kho hàng hợp lệ cho tất cả biến thể.');
                        return false;
                    }
                }
            });
        }
        
        variantApp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                if (e.target.tagName !== 'TEXTAREA') e.preventDefault();
            }
        });

        renderVariantApp();
    }

    // Dimensions -> Volume calculation
    const inputL = document.getElementById('input-length');
    const inputW = document.getElementById('input-width');
    const inputH = document.getElementById('input-height');
    const inputVol = document.getElementById('input-volume');
    const inputVolUnit = document.getElementById('input-volume-unit');
    const calcPreview = document.getElementById('calculated-volume-preview');

    function calcVolume() {
        if (inputL && inputW && inputH && inputVol && calcPreview) {
            let l = parseFloat(inputL.value) || 0;
            let w = parseFloat(inputW.value) || 0;
            let h = parseFloat(inputH.value) || 0;
            if (l > 0 && w > 0 && h > 0) {
                let vol_m3 = (l * w * h) / 1000000;
                calcPreview.value = vol_m3.toFixed(6) + ' m3';
                if (!inputVol.value || inputVol.dataset.autoCalculated === 'true') {
                    inputVol.value = vol_m3.toFixed(6);
                    inputVol.dataset.autoCalculated = 'true';
                    if (inputVolUnit) inputVolUnit.value = 'm3';
                }
            } else {
                calcPreview.value = '0 m3';
            }
        }
    }
    if (inputL) inputL.addEventListener('input', () => { if (inputVol) inputVol.dataset.autoCalculated = 'false'; calcVolume(); });
    if (inputW) inputW.addEventListener('input', () => { if (inputVol) inputVol.dataset.autoCalculated = 'false'; calcVolume(); });
    if (inputH) inputH.addEventListener('input', () => { if (inputVol) inputVol.dataset.autoCalculated = 'false'; calcVolume(); });
    if (inputVol) inputVol.addEventListener('input', () => { inputVol.dataset.autoCalculated = 'false'; });

    // Smart Tag Input Logic
    const tagInput = document.getElementById('smart-tag-input');
    const tagDropdown = document.getElementById('smart-tag-dropdown');
    const tagChipsContainer = document.getElementById('smart-tag-chips');
    const hiddenInputsContainer = document.getElementById('smart-tag-hidden-inputs');

    if (tagInput && window.ALL_TAGS) {
        let allTags = window.ALL_TAGS;
        let selectedTagIds = new Set(window.SELECTED_TAG_IDS || []);
        let debounceTimer;

        function renderChips() {
            tagChipsContainer.innerHTML = '';
            hiddenInputsContainer.innerHTML = '';
            
            selectedTagIds.forEach(id => {
                let tag = allTags.find(t => parseInt(t.id) === parseInt(id));
                if (!tag) return;

                let hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'tag_ids[]';
                hiddenInput.value = tag.id;
                hiddenInputsContainer.appendChild(hiddenInput);

                let chip = document.createElement('div');
                chip.style.cssText = 'display: inline-flex; align-items: center; padding: 4px 10px; background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; border-radius: 20px; font-size: 13px; font-weight: 500;';
                chip.innerHTML = `
                    <span>#${tag.name}</span>
                    <i class="bi bi-x-circle-fill" style="margin-left: 6px; cursor: pointer; color: #38bdf8;" onclick="window.removeTag(${tag.id})"></i>
                `;
                tagChipsContainer.appendChild(chip);
            });
        }

        window.removeTag = function(id) {
            selectedTagIds.delete(parseInt(id));
            renderChips();
        };

        window.addTag = function(id) {
            selectedTagIds.add(parseInt(id));
            tagInput.value = '';
            tagDropdown.style.display = 'none';
            renderChips();
            tagInput.focus();
        };

        tagInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // If there's a visible dropdown with an exact match or first match, add it
                if (tagDropdown.style.display === 'block' && tagDropdown.children.length > 0) {
                    let firstMatch = tagDropdown.querySelector('div[onclick]');
                    if (firstMatch) firstMatch.click();
                }
            }
        });

        tagInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            let query = this.value.trim().toLowerCase();
            if (query.startsWith('#')) query = query.substring(1);

            if (query.length === 0) {
                tagDropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                let matches = allTags.filter(t => t.name.toLowerCase().includes(query) && !selectedTagIds.has(parseInt(t.id)));
                
                if (matches.length > 0) {
                    tagDropdown.innerHTML = matches.map(t => `
                        <div onclick="window.addTag(${t.id})" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <span style="color: #64748b;">#</span><strong>${t.name}</strong>
                        </div>
                    `).join('');
                    tagDropdown.style.display = 'block';
                } else {
                    tagDropdown.innerHTML = `<div style="padding: 8px 12px; font-size: 13px; color: #94a3b8; font-style: italic;">Không tìm thấy tag phù hợp.</div>`;
                    tagDropdown.style.display = 'block';
                }
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!tagInput.contains(e.target) && !tagDropdown.contains(e.target)) {
                tagDropdown.style.display = 'none';
            }
        });

        renderChips();
    }
});
