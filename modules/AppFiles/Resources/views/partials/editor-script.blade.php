<script>
    (() => {
        const EDITOR_SELECTOR = '[data-image-editor]';
        const CUSTOM_PRESET_STORAGE_KEY = 'appfiles-image-editor-custom-presets';
        const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
        const round = (value) => Math.round(Number(value) || 0);
        const uid = (prefix) => `${prefix}-${Math.random().toString(36).slice(2, 10)}`;
        const deepClone = (value) => JSON.parse(JSON.stringify(value));

        const DEFAULT_ADJUST = Object.freeze({
            brightness: 100,
            contrast: 100,
            saturation: 100,
            grayscale: 0,
            sepia: 0,
            blur: 0,
        });

        const FILTER_PRESETS = {
            'cross-process': { brightness: 108, contrast: 122, saturation: 134, grayscale: 0, sepia: 24, blur: 0 },
            'glow-sun': { brightness: 116, contrast: 108, saturation: 142, grayscale: 0, sepia: 16, blur: 0 },
            'jarques': { brightness: 96, contrast: 136, saturation: 122, grayscale: 0, sepia: 8, blur: 0 },
            'love': { brightness: 112, contrast: 104, saturation: 128, grayscale: 0, sepia: 34, blur: 0 },
            'old-boot': { brightness: 92, contrast: 118, saturation: 84, grayscale: 0, sepia: 42, blur: 0 },
            'orange-peel': { brightness: 108, contrast: 118, saturation: 138, grayscale: 0, sepia: 28, blur: 0 },
            'pin-hole': { brightness: 94, contrast: 132, saturation: 76, grayscale: 42, sepia: 18, blur: 1 },
            'sepia': { brightness: 102, contrast: 104, saturation: 86, grayscale: 0, sepia: 86, blur: 0 },
            'sun-rise': { brightness: 118, contrast: 104, saturation: 120, grayscale: 0, sepia: 22, blur: 0 },
            'vintage': { brightness: 98, contrast: 90, saturation: 74, grayscale: 12, sepia: 48, blur: 0 },
        };

        const PRESET_PREVIEW_OVERLAYS = {};

        const normalizeExtension = (extension) => {
            const normalized = String(extension || '').toLowerCase().trim();

            return normalized === 'jpeg' ? 'jpg' : normalized;
        };

        const extensionToMime = (extension, fallback = 'image/png') => {
            switch (normalizeExtension(extension)) {
                case 'jpg':
                    return 'image/jpeg';
                case 'png':
                    return 'image/png';
                case 'webp':
                    return 'image/webp';
                default:
                    return fallback;
            }
        };

        const downloadBlob = (blob, name) => {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        };

        const createDefaultTextLayer = (count = 1) => ({
            id: uid('text'),
            name: count === 1 ? 'Text 1' : `Text ${count}`,
            enabled: true,
            value: '',
            fontFamily: 'Arial',
            size: 48,
            color: '#ffffff',
            opacity: 100,
            strokeColor: '#000000',
            strokeWidth: 0,
            x: 50,
            y: 88,
            bold: true,
            italic: false,
            align: 'center',
            shadowColor: '#0f172a',
            shadowBlur: 12,
            letterSpacing: 0,
            lineHeight: 116,
        });

        const createDefaultWatermark = (count = 1) => ({
            id: uid('image'),
            name: count === 1 ? 'Image 1' : `Image ${count}`,
            enabled: false,
            src: '',
            opacity: 72,
            scale: 24,
            x: 78,
            y: 78,
            naturalWidth: 0,
            naturalHeight: 0,
        });

        const buildFilter = (adjust) => [
            `brightness(${adjust.brightness}%)`,
            `contrast(${adjust.contrast}%)`,
            `saturate(${adjust.saturation}%)`,
            `grayscale(${adjust.grayscale}%)`,
            `sepia(${adjust.sepia}%)`,
            `blur(${adjust.blur}px)`,
        ].join(' ');

        const loadCustomPresets = () => {
            try {
                const raw = window.localStorage.getItem(CUSTOM_PRESET_STORAGE_KEY);
                const parsed = JSON.parse(raw || '[]');

                return Array.isArray(parsed)
                    ? parsed.filter((item) => item && item.key && item.name && item.adjust)
                    : [];
            } catch {
                return [];
            }
        };

        const saveCustomPresets = (presets) => {
            window.localStorage.setItem(CUSTOM_PRESET_STORAGE_KEY, JSON.stringify(presets.slice(0, 12)));
        };

        const initEditor = (root) => {
            if (!root || root.dataset.editorReady === 'true') {
                return;
            }

            root.dataset.editorReady = 'true';

            const canvas = root.querySelector('[data-editor-canvas]');
            const context = canvas.getContext('2d');
            const source = root.querySelector('[data-editor-source-image]') || new Image();
            const watermarkImage = new Image();
            let sourceObjectUrl = null;
            if ('decoding' in source) {
                source.decoding = 'async';
            }
            watermarkImage.decoding = 'async';

            const status = root.querySelector('[data-editor-status]');
            const dimensions = root.querySelector('[data-editor-dimensions]');
            const rotationBadge = root.querySelector('[data-editor-rotation]');
            const saveButton = root.querySelector('[data-editor-save]');
            const downloadButton = root.querySelector('[data-editor-download]');
            const undoButton = root.querySelector('[data-editor-undo]');
            const redoButton = root.querySelector('[data-editor-redo]');
            const lockInput = root.querySelector('[data-editor-lock]');
            const sliderInputs = [...root.querySelectorAll('[data-editor-slider]')];
            const presetCards = [...root.querySelectorAll('[data-editor-preset-card]')];
            const presetOverlays = [...root.querySelectorAll('[data-editor-preset-overlay]')];
            const cropInputs = {
                x: root.querySelector('[data-editor-input="crop-x"]'),
                y: root.querySelector('[data-editor-input="crop-y"]'),
                width: root.querySelector('[data-editor-input="crop-width"]'),
                height: root.querySelector('[data-editor-input="crop-height"]'),
            };
            const outputInputs = {
                width: root.querySelector('[data-editor-input="output-width"]'),
                height: root.querySelector('[data-editor-input="output-height"]'),
            };
            const customPresetName = root.querySelector('[data-editor-custom-preset-name]');
            const customPresetList = root.querySelector('[data-editor-custom-presets]');
            const textLayerSelect = root.querySelector('[data-editor-text-layer-select]');
            const textEnabled = root.querySelector('[data-editor-text-enabled]');
            const textInputs = [...root.querySelectorAll('[data-editor-text]')];
            const textStyleButtons = [...root.querySelectorAll('[data-editor-text-style]')];
            const colorPreview = root.querySelector('[data-editor-color-preview]');
            const colorLabel = colorPreview?.nextElementSibling || null;
            const strokePreview = root.querySelector('[data-editor-stroke-preview]');
            const strokeLabel = strokePreview?.nextElementSibling || null;
            const shadowPreview = root.querySelector('[data-editor-shadow-preview]');
            const shadowLabel = shadowPreview?.nextElementSibling || null;
            const watermarkEnabled = root.querySelector('[data-editor-watermark-enabled]');
            const watermarkFile = root.querySelector('[data-editor-watermark-file]');
            const watermarkName = root.querySelector('[data-editor-watermark-name]');
            const watermarkInputs = [...root.querySelectorAll('[data-editor-watermark]')];
            const imageLayerSelect = root.querySelector('[data-editor-image-layer-select]');
            const compareButton = root.querySelector('[data-editor-compare]');
            const exportName = root.querySelector('[data-editor-export-name]');
            const exportFormat = root.querySelector('[data-editor-export-format]');
            const exportQuality = root.querySelector('[data-editor-export-quality]');
            const exportQualityValue = root.querySelector('[data-editor-value="export-quality"]');
            const shortcutHint = root.querySelector('[data-editor-shortcuts]');

            const state = {
                sourceWidth: Number(root.dataset.originalWidth || 0),
                sourceHeight: Number(root.dataset.originalHeight || 0),
                fileName: root.dataset.fileName || 'image',
                fileExtension: normalizeExtension(root.dataset.fileExtension),
                saveUrl: root.dataset.saveUrl,
                backUrl: root.dataset.backUrl,
                csrf: root.dataset.csrf,
                saving: false,
                loaded: false,
                zoom: 1,
                rotation: 0,
                flipX: 1,
                flipY: 1,
                crop: { x: 0, y: 0, width: 0, height: 0 },
                output: { width: 0, height: 0, lockRatio: true },
                adjust: deepClone(DEFAULT_ADJUST),
                activeFilterPreset: '',
                previewAdjust: null,
                previewFilterPreset: '',
                compareOriginal: false,
                customPresets: loadCustomPresets(),
                textLayers: [],
                selectedTextLayerId: null,
                imageLayers: [],
                selectedImageLayerId: null,
                activeObject: { type: null, id: null },
                watermark: createDefaultWatermark(),
                export: {
                    name: (root.dataset.fileName || 'image').replace(/\.[^.]+$/, ''),
                    format: normalizeExtension(root.dataset.fileExtension) || 'png',
                    quality: 92,
                },
                guides: [],
                drag: {
                    active: false,
                    pointerId: null,
                    type: null,
                    id: null,
                    offsetX: 0,
                    offsetY: 0,
                    handle: null,
                    originScale: 0,
                    originCenterX: 0,
                    originCenterY: 0,
                    originDistance: 0,
                },
                cropTool: {
                    active: false,
                    pointerId: null,
                    rect: null,
                    interaction: null,
                    handle: null,
                    offsetX: 0,
                    offsetY: 0,
                },
                history: {
                    undo: [],
                    redo: [],
                    lastSerialized: '',
                    restoring: false,
                },
            };

            const setStatus = (message, tone = 'default') => {
                if (!status) return;
                status.textContent = message;
                status.classList.remove('text-emerald-300', 'text-rose-300', 'text-slate-300');
                status.classList.add(tone === 'success' ? 'text-emerald-300' : tone === 'error' ? 'text-rose-300' : 'text-slate-300');
            };

            const getSelectedTextLayer = () => state.textLayers.find((layer) => layer.id === state.selectedTextLayerId) || state.textLayers[0] || null;
            const getSelectedImageLayer = () => state.imageLayers.find((layer) => layer.id === state.selectedImageLayerId) || state.imageLayers[0] || null;
            const syncSelectedImageAlias = () => {
                state.watermark = getSelectedImageLayer() || createDefaultWatermark(1);
            };

            const setActiveObject = (type = null, id = null) => {
                state.activeObject = { type, id };
                if (type === 'text' && id) {
                    state.selectedTextLayerId = id;
                }
                if (type === 'image' && id) {
                    state.selectedImageLayerId = id;
                    syncSelectedImageAlias();
                }
            };

            const getSerializableState = () => ({
                zoom: state.zoom,
                rotation: state.rotation,
                flipX: state.flipX,
                flipY: state.flipY,
                crop: deepClone(state.crop),
                output: deepClone(state.output),
                adjust: deepClone(state.adjust),
                activeFilterPreset: state.activeFilterPreset,
                previewAdjust: null,
                previewFilterPreset: '',
                compareOriginal: false,
                textLayers: deepClone(state.textLayers),
                selectedTextLayerId: state.selectedTextLayerId,
                imageLayers: state.imageLayers.map(({ image, ...layer }) => deepClone(layer)),
                selectedImageLayerId: state.selectedImageLayerId,
                activeObject: deepClone(state.activeObject),
                watermark: deepClone((({ image, ...layer }) => layer)(state.watermark || createDefaultWatermark())),
                export: deepClone(state.export),
            });

            const restoreWatermarkImage = () => {
                state.imageLayers.forEach((layer) => {
                    if (!layer?.src) {
                        return;
                    }
                    if (layer.image?.src === layer.src) {
                        return;
                    }
                    const image = new Image();
                    image.decoding = 'async';
                    image.onload = () => {
                        layer.naturalWidth = image.naturalWidth;
                        layer.naturalHeight = image.naturalHeight;
                        layer.image = image;
                        renderPreview();
                    };
                    image.src = layer.src;
                    layer.image = image;
                });
            };

            const applySnapshot = (snapshot, { skipRender = false } = {}) => {
                state.history.restoring = true;
                state.zoom = snapshot.zoom;
                state.rotation = snapshot.rotation;
                state.flipX = snapshot.flipX;
                state.flipY = snapshot.flipY;
                state.crop = deepClone(snapshot.crop);
                state.output = deepClone(snapshot.output);
                state.adjust = deepClone(snapshot.adjust);
                state.activeFilterPreset = snapshot.activeFilterPreset || '';
                state.previewAdjust = null;
                state.previewFilterPreset = '';
                state.compareOriginal = false;
                state.textLayers = deepClone(snapshot.textLayers || []);
                if (state.textLayers.length === 0) {
                    state.textLayers = [createDefaultTextLayer(1)];
                }
                state.selectedTextLayerId = snapshot.selectedTextLayerId || state.textLayers[0].id;
                state.imageLayers = deepClone(snapshot.imageLayers || []);
                state.selectedImageLayerId = snapshot.selectedImageLayerId || state.imageLayers[0]?.id || null;
                state.activeObject = deepClone(snapshot.activeObject || { type: null, id: null });
                state.export = {
                    name: snapshot.export?.name || state.export.name,
                    format: snapshot.export?.format || state.export.format,
                    quality: snapshot.export?.quality || state.export.quality,
                };
                syncSelectedImageAlias();
                restoreWatermarkImage();
                state.history.lastSerialized = JSON.stringify(getSerializableState());
                state.history.restoring = false;

                if (!skipRender) {
                    renderPreview();
                }
            };

            const pushHistory = () => {
                if (state.history.restoring || !state.loaded) return;
                const snapshot = getSerializableState();
                const serialized = JSON.stringify(snapshot);

                if (serialized === state.history.lastSerialized) {
                    return;
                }

                state.history.undo.push(JSON.parse(state.history.lastSerialized || serialized));
                if (state.history.undo.length > 50) {
                    state.history.undo.shift();
                }

                state.history.redo = [];
                state.history.lastSerialized = serialized;
                syncHistoryButtons();
            };

            const syncHistoryButtons = () => {
                if (undoButton) undoButton.disabled = state.history.undo.length === 0;
                if (redoButton) redoButton.disabled = state.history.redo.length === 0;
            };

            const undo = () => {
                if (state.history.undo.length === 0) return;
                const previous = state.history.undo.pop();
                state.history.redo.push(getSerializableState());
                applySnapshot(previous);
                syncHistoryButtons();
                setStatus('Undid the latest change.');
            };

            const redo = () => {
                if (state.history.redo.length === 0) return;
                const next = state.history.redo.pop();
                state.history.undo.push(getSerializableState());
                applySnapshot(next);
                syncHistoryButtons();
                setStatus('Redid the latest change.');
            };

            const updateBadges = () => {
                if (dimensions) dimensions.textContent = `Output: ${state.output.width} x ${state.output.height}`;
                if (rotationBadge) rotationBadge.textContent = `Rotation: ${state.rotation}°`;
            };

            const syncPresetCards = () => {
                presetCards.forEach((card) => {
                    card.dataset.active = card.dataset.editorPresetCard === state.activeFilterPreset ? 'true' : 'false';
                    card.dataset.preview = card.dataset.editorPresetCard === state.previewFilterPreset ? 'true' : 'false';
                });
            };

            const renderPresetThumbnails = () => {
                root.querySelectorAll('[data-editor-preset-preview]').forEach((preview) => {
                    const presetKey = preview.dataset.editorPresetPreview;
                    const adjust = FILTER_PRESETS[presetKey] || DEFAULT_ADJUST;

                    preview.style.filter = buildFilter(adjust);
                });
            };

            const actualCanvasSize = () => {
                const rotated = Math.abs(state.rotation) % 180 !== 0;
                return rotated
                    ? { width: state.output.height, height: state.output.width }
                    : { width: state.output.width, height: state.output.height };
            };

            const currentDisplayMetrics = () => {
                const rect = canvas.getBoundingClientRect();
                const logicalWidth = Number(canvas.width || 1);
                const logicalHeight = Number(canvas.height || 1);

                return {
                    rect,
                    scaleX: logicalWidth / Math.max(rect.width, 1),
                    scaleY: logicalHeight / Math.max(rect.height, 1),
                    logicalWidth,
                    logicalHeight,
                };
            };

            const pointerToCanvas = (event) => {
                const metrics = currentDisplayMetrics();
                const x = (event.clientX - metrics.rect.left) * metrics.scaleX;
                const y = (event.clientY - metrics.rect.top) * metrics.scaleY;

                return { ...metrics, x, y };
            };

            const clampCrop = () => {
                state.crop.x = clamp(round(state.crop.x), 0, Math.max(0, state.sourceWidth - 1));
                state.crop.y = clamp(round(state.crop.y), 0, Math.max(0, state.sourceHeight - 1));
                state.crop.width = clamp(round(state.crop.width), 1, state.sourceWidth - state.crop.x);
                state.crop.height = clamp(round(state.crop.height), 1, state.sourceHeight - state.crop.y);
            };

            const clampSelectionRect = (rect, logicalWidth, logicalHeight) => {
                const x1 = clamp(rect.x, 0, logicalWidth);
                const y1 = clamp(rect.y, 0, logicalHeight);
                const x2 = clamp(rect.x + rect.width, 0, logicalWidth);
                const y2 = clamp(rect.y + rect.height, 0, logicalHeight);

                return {
                    x: Math.min(x1, x2),
                    y: Math.min(y1, y2),
                    width: Math.abs(x2 - x1),
                    height: Math.abs(y2 - y1),
                };
            };

            const updateWatermarkBounds = (layer = getSelectedImageLayer()) => {
                if (!layer?.src || !layer.naturalWidth || !layer.naturalHeight) {
                    return null;
                }

                const width = clamp((layer.scale / 100) * canvas.width, 24, canvas.width * 0.9);
                const height = width * (layer.naturalHeight / layer.naturalWidth);
                const x = (layer.x / 100) * canvas.width;
                const y = (layer.y / 100) * canvas.height;

                return {
                    x: clamp(x - width / 2, 0, Math.max(0, canvas.width - width)),
                    y: clamp(y - height / 2, 0, Math.max(0, canvas.height - height)),
                    width,
                    height,
                };
            };

            const buildFont = (layer) => {
                const style = layer.italic ? 'italic' : 'normal';
                const weight = layer.bold ? '700' : '400';

                return `${style} ${weight} ${clamp(round(layer.size), 8, Math.max(8, Math.floor(Math.min(canvas.width, canvas.height) * 0.5)))}px "${layer.fontFamily || 'Arial'}", sans-serif`;
            };

            const measureSpacedText = (ctx, text, spacing) => {
                if (!text) return 0;
                if (!spacing) return ctx.measureText(text).width;

                return text.split('').reduce((width, character, index) => {
                    return width + ctx.measureText(character).width + (index < text.length - 1 ? spacing : 0);
                }, 0);
            };

            const drawSpacedText = (ctx, text, startX, y, spacing, mode) => {
                if (!spacing) {
                    if (mode === 'stroke') ctx.strokeText(text, startX, y);
                    else ctx.fillText(text, startX, y);
                    return;
                }

                let x = startX;
                for (const character of text) {
                    if (mode === 'stroke') ctx.strokeText(character, x, y);
                    else ctx.fillText(character, x, y);
                    x += ctx.measureText(character).width + spacing;
                }
            };

            const getTextLayerBounds = (ctx, layer, width, height) => {
                if (!layer.enabled || !String(layer.value || '').trim()) {
                    return null;
                }

                const lines = String(layer.value).split(/\r?\n/);
                const fontSize = clamp(round(layer.size), 8, Math.max(8, Math.floor(Math.min(width, height) * 0.5)));
                const lineHeight = Math.max(12, Math.round(fontSize * ((Number(layer.lineHeight) || 100) / 100)));
                const letterSpacing = Number(layer.letterSpacing) || 0;
                ctx.save();
                ctx.font = buildFont(layer);

                const widths = lines.map((line) => measureSpacedText(ctx, line, letterSpacing));
                const maxWidth = Math.max(1, ...widths);
                const totalHeight = Math.max(lineHeight, lineHeight * lines.length);
                let x = (clamp(Number(layer.x) || 0, 0, 100) / 100) * width;
                const y = (clamp(Number(layer.y) || 0, 0, 100) / 100) * height;
                if (layer.align === 'center') x -= maxWidth / 2;
                if (layer.align === 'right') x -= maxWidth;
                const top = y - totalHeight / 2;
                ctx.restore();

                return {
                    x,
                    y: top,
                    width: maxWidth,
                    height: totalHeight,
                    fontSize,
                    lineHeight,
                    letterSpacing,
                    lines,
                };
            };

            const drawTextLayer = (ctx, layer, width, height) => {
                const bounds = getTextLayerBounds(ctx, layer, width, height);
                if (!bounds) return null;

                const opacity = clamp(Number(layer.opacity) || 0, 0, 100) / 100;
                const strokeWidth = clamp(round(layer.strokeWidth), 0, Math.max(0, Math.round(bounds.fontSize * 0.3)));

                ctx.save();
                ctx.font = buildFont(layer);
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = layer.color || '#ffffff';
                ctx.globalAlpha = opacity;
                ctx.shadowColor = layer.shadowColor || 'rgba(15, 23, 42, 0.42)';
                ctx.shadowBlur = Math.max(0, Number(layer.shadowBlur) || 0);
                ctx.lineWidth = strokeWidth;
                ctx.strokeStyle = layer.strokeColor || '#000000';

                bounds.lines.forEach((line, index) => {
                    const lineY = bounds.y + (bounds.lineHeight * index) + (bounds.lineHeight / 2);
                    const lineWidth = measureSpacedText(ctx, line, bounds.letterSpacing);
                    let drawX = bounds.x;
                    if (layer.align === 'center') drawX = bounds.x + (bounds.width - lineWidth) / 2;
                    if (layer.align === 'right') drawX = bounds.x + (bounds.width - lineWidth);
                    if (strokeWidth > 0) {
                        drawSpacedText(ctx, line, drawX, lineY, bounds.letterSpacing, 'stroke');
                    }
                    drawSpacedText(ctx, line, drawX, lineY, bounds.letterSpacing, 'fill');
                });
                ctx.restore();

                return bounds;
            };

            const drawWatermark = (ctx) => {
                let selectedBounds = null;

                state.imageLayers.forEach((layer) => {
                    const bounds = updateWatermarkBounds(layer);
                    if (!layer.enabled || !bounds || !layer.image?.complete || !layer.image?.naturalWidth) {
                        return;
                    }

                    ctx.save();
                    ctx.globalAlpha = clamp(Number(layer.opacity) || 0, 0, 100) / 100;
                    ctx.drawImage(layer.image, bounds.x, bounds.y, bounds.width, bounds.height);
                    ctx.restore();

                    if (layer.id === state.selectedImageLayerId) {
                        selectedBounds = bounds;
                    }
                });

                return selectedBounds;
            };

            const drawSelectionBox = (ctx, bounds, tone = 'rgba(56, 189, 248, 0.95)') => {
                if (!bounds) return;
                ctx.save();
                ctx.strokeStyle = tone;
                ctx.lineWidth = 2;
                ctx.setLineDash([8, 6]);
                ctx.strokeRect(bounds.x, bounds.y, bounds.width, bounds.height);
                ctx.setLineDash([]);
                ctx.fillStyle = tone;
                [[bounds.x, bounds.y], [bounds.x + bounds.width, bounds.y], [bounds.x, bounds.y + bounds.height], [bounds.x + bounds.width, bounds.y + bounds.height]].forEach(([x, y]) => {
                    ctx.fillRect(x - 4, y - 4, 8, 8);
                });
                ctx.restore();
            };

            const getCropHandles = (rect) => ({
                nw: { x: rect.x, y: rect.y },
                n: { x: rect.x + rect.width / 2, y: rect.y },
                ne: { x: rect.x + rect.width, y: rect.y },
                e: { x: rect.x + rect.width, y: rect.y + rect.height / 2 },
                se: { x: rect.x + rect.width, y: rect.y + rect.height },
                s: { x: rect.x + rect.width / 2, y: rect.y + rect.height },
                sw: { x: rect.x, y: rect.y + rect.height },
                w: { x: rect.x, y: rect.y + rect.height / 2 },
            });

            const getImageHandles = (rect) => ({
                nw: { x: rect.x, y: rect.y },
                ne: { x: rect.x + rect.width, y: rect.y },
                se: { x: rect.x + rect.width, y: rect.y + rect.height },
                sw: { x: rect.x, y: rect.y + rect.height },
            });

            const drawCropOverlay = (ctx, width, height) => {
                if (!state.cropTool.active || !state.cropTool.rect) return;

                const rect = clampSelectionRect(state.cropTool.rect, width, height);
                if (rect.width < 2 || rect.height < 2) return;

                ctx.save();
                ctx.fillStyle = 'rgba(15, 23, 42, 0.45)';
                ctx.fillRect(0, 0, width, rect.y);
                ctx.fillRect(0, rect.y, rect.x, rect.height);
                ctx.fillRect(rect.x + rect.width, rect.y, width - (rect.x + rect.width), rect.height);
                ctx.fillRect(0, rect.y + rect.height, width, height - (rect.y + rect.height));
                ctx.strokeStyle = 'rgba(56, 189, 248, 0.95)';
                ctx.lineWidth = 2;
                ctx.setLineDash([10, 8]);
                ctx.strokeRect(rect.x, rect.y, rect.width, rect.height);
                ctx.setLineDash([]);
                ctx.strokeStyle = 'rgba(255,255,255,0.22)';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(rect.x + rect.width / 3, rect.y);
                ctx.lineTo(rect.x + rect.width / 3, rect.y + rect.height);
                ctx.moveTo(rect.x + (rect.width * 2) / 3, rect.y);
                ctx.lineTo(rect.x + (rect.width * 2) / 3, rect.y + rect.height);
                ctx.moveTo(rect.x, rect.y + rect.height / 3);
                ctx.lineTo(rect.x + rect.width, rect.y + rect.height / 3);
                ctx.moveTo(rect.x, rect.y + (rect.height * 2) / 3);
                ctx.lineTo(rect.x + rect.width, rect.y + (rect.height * 2) / 3);
                ctx.stroke();
                ctx.fillStyle = 'rgba(56, 189, 248, 1)';
                Object.values(getCropHandles(rect)).forEach((handle) => ctx.fillRect(handle.x - 5, handle.y - 5, 10, 10));
                ctx.restore();
            };

            const detectCropHandle = (point) => {
                if (!state.cropTool.rect) return null;
                const rect = clampSelectionRect(state.cropTool.rect, canvas.width, canvas.height);
                const handles = getCropHandles(rect);
                const threshold = 14;

                for (const [key, handle] of Object.entries(handles)) {
                    if (Math.abs(point.x - handle.x) <= threshold && Math.abs(point.y - handle.y) <= threshold) {
                        return { key, rect };
                    }
                }

                if (point.x >= rect.x && point.x <= rect.x + rect.width && point.y >= rect.y && point.y <= rect.y + rect.height) {
                    return { key: 'move', rect };
                }

                return { key: 'new', rect };
            };

            const updateCropRectFromInteraction = (point) => {
                const rect = clampSelectionRect(state.cropTool.rect || { x: 0, y: 0, width: canvas.width, height: canvas.height }, canvas.width, canvas.height);
                const minSize = 24;
                let next = { ...rect };

                switch (state.cropTool.handle) {
                    case 'move':
                        next.x = clamp(point.x - state.cropTool.offsetX, 0, canvas.width - rect.width);
                        next.y = clamp(point.y - state.cropTool.offsetY, 0, canvas.height - rect.height);
                        break;
                    case 'nw':
                        next.width += next.x - point.x;
                        next.height += next.y - point.y;
                        next.x = point.x;
                        next.y = point.y;
                        break;
                    case 'n':
                        next.height += next.y - point.y;
                        next.y = point.y;
                        break;
                    case 'ne':
                        next.width = point.x - next.x;
                        next.height += next.y - point.y;
                        next.y = point.y;
                        break;
                    case 'e':
                        next.width = point.x - next.x;
                        break;
                    case 'se':
                        next.width = point.x - next.x;
                        next.height = point.y - next.y;
                        break;
                    case 's':
                        next.height = point.y - next.y;
                        break;
                    case 'sw':
                        next.width += next.x - point.x;
                        next.height = point.y - next.y;
                        next.x = point.x;
                        break;
                    case 'w':
                        next.width += next.x - point.x;
                        next.x = point.x;
                        break;
                    default:
                        next = {
                            x: state.cropTool.startX,
                            y: state.cropTool.startY,
                            width: point.x - state.cropTool.startX,
                            height: point.y - state.cropTool.startY,
                        };
                }

                next = clampSelectionRect(next, canvas.width, canvas.height);
                next.width = Math.max(minSize, next.width);
                next.height = Math.max(minSize, next.height);
                next.x = clamp(next.x, 0, Math.max(0, canvas.width - next.width));
                next.y = clamp(next.y, 0, Math.max(0, canvas.height - next.height));
                state.cropTool.rect = next;
            };

            const drawWatermarkBoundsOnly = () => updateWatermarkBounds(getSelectedImageLayer());

            const getInteractiveObjects = () => {
                const objects = [];

                state.textLayers.forEach((layer) => {
                    const bounds = getTextLayerBounds(context, layer, canvas.width, canvas.height);
                    if (bounds) {
                        objects.push({ type: 'text', id: layer.id, bounds });
                    }
                });

                state.imageLayers.forEach((layer) => {
                    const bounds = updateWatermarkBounds(layer);
                    if (bounds && layer.enabled) {
                        objects.push({ type: 'image', id: layer.id, bounds });
                    }
                });

                return objects;
            };

            const hitTestObject = (event) => {
                const point = pointerToCanvas(event);
                const objects = getInteractiveObjects().reverse();

                return objects.find((object) => (
                    point.x >= object.bounds.x
                    && point.x <= object.bounds.x + object.bounds.width
                    && point.y >= object.bounds.y
                    && point.y <= object.bounds.y + object.bounds.height
                )) || null;
            };

            const detectImageHandle = (point, bounds) => {
                const threshold = 14;
                const handles = getImageHandles(bounds);

                for (const [key, handle] of Object.entries(handles)) {
                    if (Math.abs(point.x - handle.x) <= threshold && Math.abs(point.y - handle.y) <= threshold) {
                        return key;
                    }
                }

                return 'move';
            };

            const syncCustomPresetList = () => {
                if (!customPresetList) return;
                customPresetList.innerHTML = '';

                if (state.customPresets.length === 0) {
                    customPresetList.innerHTML = '<p class="text-xs leading-6 text-slate-500">No saved presets yet.</p>';
                    return;
                }

                state.customPresets.forEach((preset) => {
                    const row = document.createElement('div');
                    row.className = 'flex items-center gap-2';
                    row.innerHTML = `
                        <button type="button" data-editor-custom-preset="${preset.key}" class="flex-1 rounded-[0.95rem] border border-white/10 bg-white/5 px-3 py-2 text-left text-xs font-medium text-slate-200 transition hover:bg-white/10">${preset.name}</button>
                        <button type="button" data-editor-custom-preset-delete="${preset.key}" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.95rem] border border-white/10 bg-white/5 text-slate-400 transition hover:bg-white/10"><i class="fa-light fa-trash text-xs"></i></button>
                    `;
                    customPresetList.appendChild(row);
                });
            };

            const syncTextLayerSelect = () => {
                if (!textLayerSelect) return;
                textLayerSelect.innerHTML = '';

                state.textLayers.forEach((layer) => {
                    const option = document.createElement('option');
                    option.value = layer.id;
                    option.textContent = layer.name || 'Text layer';
                    textLayerSelect.appendChild(option);
                });

                textLayerSelect.value = getSelectedTextLayer()?.id || '';
            };

            const syncImageLayerSelect = () => {
                if (!imageLayerSelect) return;
                imageLayerSelect.innerHTML = '';

                state.imageLayers.forEach((layer) => {
                    const option = document.createElement('option');
                    option.value = layer.id;
                    option.textContent = layer.name || 'Image layer';
                    imageLayerSelect.appendChild(option);
                });

                imageLayerSelect.value = getSelectedImageLayer()?.id || '';
            };

            const syncForm = () => {
                const selected = getSelectedTextLayer();
                const selectedImage = getSelectedImageLayer();
                cropInputs.x.value = state.crop.x;
                cropInputs.y.value = state.crop.y;
                cropInputs.width.value = state.crop.width;
                cropInputs.height.value = state.crop.height;
                outputInputs.width.value = state.output.width;
                outputInputs.height.value = state.output.height;
                lockInput.checked = state.output.lockRatio;

                sliderInputs.forEach((input) => {
                    const key = input.dataset.editorSlider;
                    input.value = state.adjust[key];
                    const target = root.querySelector(`[data-editor-value="${key}"]`);
                    if (target) target.textContent = state.adjust[key];
                });

                syncTextLayerSelect();
                syncImageLayerSelect();

                if (textEnabled) {
                    textEnabled.checked = selected?.enabled ?? false;
                }

                textInputs.forEach((input) => {
                    const key = input.dataset.editorText;
                    if (selected && key in selected) {
                        input.value = selected[key];
                    }
                    input.disabled = !selected;
                });

                textStyleButtons.forEach((button) => {
                    const key = button.dataset.editorTextStyle;
                    let active = false;
                    if (selected) {
                        if (key === 'bold' || key === 'italic') active = Boolean(selected[key]);
                        if (key.startsWith('align-')) active = selected.align === key.replace('align-', '');
                    }
                    button.dataset.active = active ? 'true' : 'false';
                });

                if (colorPreview) colorPreview.style.backgroundColor = selected?.color || '#ffffff';
                if (colorLabel) colorLabel.textContent = String(selected?.color || '#FFFFFF').toUpperCase();
                if (strokePreview) strokePreview.style.backgroundColor = selected?.strokeColor || '#000000';
                if (strokeLabel) strokeLabel.textContent = String(selected?.strokeColor || '#000000').toUpperCase();
                if (shadowPreview) shadowPreview.style.backgroundColor = selected?.shadowColor || '#0F172A';
                if (shadowLabel) shadowLabel.textContent = String(selected?.shadowColor || '#0F172A').toUpperCase();

                const opacityValue = root.querySelector('[data-editor-value="text-opacity"]');
                if (opacityValue) opacityValue.textContent = selected?.opacity ?? 100;

                if (watermarkEnabled) {
                    watermarkEnabled.checked = selectedImage?.enabled ?? false;
                }

                watermarkInputs.forEach((input) => {
                    const key = input.dataset.editorWatermark;
                    if (selectedImage && key in selectedImage) {
                        input.value = selectedImage[key];
                    }
                    input.disabled = !selectedImage?.src;
                });

                if (watermarkName) {
                    watermarkName.textContent = selectedImage?.src ? 'Overlay ready. Drag it on the canvas to reposition.' : 'No image overlay selected.';
                }

                if (exportName) exportName.value = state.export.name;
                if (exportFormat) exportFormat.value = state.export.format;
                if (exportQuality) exportQuality.value = state.export.quality;
                if (exportQualityValue) exportQualityValue.textContent = state.export.quality;

                syncPresetCards();
                syncCustomPresetList();
                updateBadges();
                syncHistoryButtons();

                if (shortcutHint) {
                    shortcutHint.textContent = 'Shortcuts: Ctrl/Cmd+Z undo, Ctrl/Cmd+Shift+Z redo, Delete remove layer, Esc cancel crop, arrows move selection.';
                }
            };

            const renderTo = (targetCanvas) => {
                const drawContext = targetCanvas.getContext('2d');
                const size = actualCanvasSize();
                const renderAdjust = state.previewAdjust || state.adjust;
                targetCanvas.width = Math.max(1, round(size.width));
                targetCanvas.height = Math.max(1, round(size.height));

                drawContext.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
                drawContext.save();
                drawContext.translate(targetCanvas.width / 2, targetCanvas.height / 2);
                drawContext.rotate((state.rotation * Math.PI) / 180);
                drawContext.scale(state.flipX, state.flipY);
                drawContext.imageSmoothingEnabled = true;
                drawContext.imageSmoothingQuality = 'high';
                drawContext.filter = buildFilter(renderAdjust);
                drawContext.drawImage(
                    source,
                    state.crop.x,
                    state.crop.y,
                    state.crop.width,
                    state.crop.height,
                    -state.output.width / 2,
                    -state.output.height / 2,
                    state.output.width,
                    state.output.height,
                );
                drawContext.filter = 'none';
                drawContext.restore();

                state.textLayers.forEach((layer) => drawTextLayer(drawContext, layer, targetCanvas.width, targetCanvas.height));
                drawWatermark(drawContext);
            };

            const drawGuides = (ctx) => {
                if (!state.guides.length) return;
                ctx.save();
                ctx.strokeStyle = 'rgba(59, 130, 246, 0.85)';
                ctx.lineWidth = 1;
                ctx.setLineDash([6, 6]);
                state.guides.forEach((guide) => {
                    ctx.beginPath();
                    if (guide.axis === 'x') {
                        ctx.moveTo(guide.value, 0);
                        ctx.lineTo(guide.value, canvas.height);
                    } else {
                        ctx.moveTo(0, guide.value);
                        ctx.lineTo(canvas.width, guide.value);
                    }
                    ctx.stroke();
                });
                ctx.restore();
            };

            const renderPreview = () => {
                if (!state.loaded) return;
                if (state.compareOriginal) {
                    canvas.width = Math.max(1, round(actualCanvasSize().width));
                    canvas.height = Math.max(1, round(actualCanvasSize().height));
                    context.clearRect(0, 0, canvas.width, canvas.height);
                    context.drawImage(source, 0, 0, canvas.width, canvas.height);
                } else {
                    renderTo(canvas);
                }

                const selected = getSelectedTextLayer();
                if (!state.compareOriginal && state.activeObject.type === 'text' && selected && state.activeObject.id === selected.id) {
                    drawSelectionBox(context, getTextLayerBounds(context, selected, canvas.width, canvas.height));
                }

                if (!state.compareOriginal && state.activeObject.type === 'image' && getSelectedImageLayer()?.src) {
                    drawSelectionBox(context, drawWatermarkBoundsOnly(), 'rgba(34, 197, 94, 0.95)');
                }

                if (!state.compareOriginal) {
                    drawCropOverlay(context, canvas.width, canvas.height);
                    drawGuides(context);
                }

                const size = actualCanvasSize();
                const availableWidth = Math.max(420, canvas.parentElement?.clientWidth || size.width);
                const availableHeight = Math.max(520, canvas.parentElement?.clientHeight || size.height);
                const fitScale = Math.min(availableWidth / size.width, availableHeight / size.height, 1) * 0.7;
                const finalScale = Math.max(0.1, fitScale * state.zoom);

                canvas.style.width = `${Math.max(160, Math.round(size.width * finalScale))}px`;
                canvas.style.height = `${Math.max(160, Math.round(size.height * finalScale))}px`;
                syncForm();
            };

            const resetAll = () => {
                state.zoom = 1;
                state.rotation = 0;
                state.flipX = 1;
                state.flipY = 1;
                state.compareOriginal = false;
                state.crop = { x: 0, y: 0, width: state.sourceWidth, height: state.sourceHeight };
                state.output = { width: state.sourceWidth, height: state.sourceHeight, lockRatio: true };
                state.adjust = deepClone(DEFAULT_ADJUST);
                state.activeFilterPreset = '';
                state.previewAdjust = null;
                state.previewFilterPreset = '';
                state.textLayers = [createDefaultTextLayer(1)];
                state.selectedTextLayerId = state.textLayers[0].id;
                setActiveObject('text', state.textLayers[0].id);
                state.imageLayers = [];
                state.selectedImageLayerId = null;
                state.watermark = createDefaultWatermark();
                state.export = {
                    name: (root.dataset.fileName || 'image').replace(/\.[^.]+$/, ''),
                    format: normalizeExtension(root.dataset.fileExtension) || 'png',
                    quality: 92,
                };
                state.guides = [];
                restoreWatermarkImage();
                state.cropTool = { active: false, pointerId: null, rect: null, interaction: null, handle: null, offsetX: 0, offsetY: 0 };
                state.drag = { active: false, pointerId: null, type: null, id: null, offsetX: 0, offsetY: 0, handle: null, originScale: 0, originCenterX: 0, originCenterY: 0, originDistance: 0 };
                state.history.undo = [];
                state.history.redo = [];
                state.history.lastSerialized = JSON.stringify(getSerializableState());
                renderPreview();
                setStatus('Editor reset to the original image.');
            };

            const moveLayerInArray = (layers, id, direction) => {
                const index = layers.findIndex((layer) => layer.id === id);
                if (index < 0) return false;
                const targetIndex = direction === 'front'
                    ? Math.min(layers.length - 1, index + 1)
                    : Math.max(0, index - 1);
                if (targetIndex === index) return false;
                const [layer] = layers.splice(index, 1);
                layers.splice(targetIndex, 0, layer);
                return true;
            };

            const applySnap = (x, y, width, height) => {
                const snapThreshold = 10;
                const guides = [];
                let snappedX = x;
                let snappedY = y;
                const centerX = x + width / 2;
                const centerY = y + height / 2;
                const targetsX = [0, canvas.width / 2, canvas.width];
                const targetsY = [0, canvas.height / 2, canvas.height];

                targetsX.forEach((target) => {
                    const candidates = [
                        { value: x, shift: target - x },
                        { value: centerX, shift: target - centerX },
                        { value: x + width, shift: target - (x + width) },
                    ];
                    const match = candidates.find((candidate) => Math.abs(candidate.shift) <= snapThreshold);
                    if (match) {
                        snappedX += match.shift;
                        guides.push({ axis: 'x', value: target });
                    }
                });

                targetsY.forEach((target) => {
                    const candidates = [
                        { value: y, shift: target - y },
                        { value: centerY, shift: target - centerY },
                        { value: y + height, shift: target - (y + height) },
                    ];
                    const match = candidates.find((candidate) => Math.abs(candidate.shift) <= snapThreshold);
                    if (match) {
                        snappedY += match.shift;
                        guides.push({ axis: 'y', value: target });
                    }
                });

                state.guides = guides;

                return { x: snappedX, y: snappedY };
            };

            const saveCurrentCustomPreset = () => {
                const name = String(customPresetName?.value || '').trim();
                if (!name) {
                    setStatus('Preset name is required.', 'error');
                    return;
                }

                state.customPresets.unshift({
                    key: uid('preset'),
                    name,
                    adjust: deepClone(state.adjust),
                });
                state.customPresets = state.customPresets.slice(0, 12);
                saveCustomPresets(state.customPresets);
                if (customPresetName) customPresetName.value = '';
                renderPreview();
                setStatus('Preset saved for this browser.', 'success');
            };

            const applyFilterPreset = (preset, fromCustom = false) => {
                const values = fromCustom
                    ? state.customPresets.find((item) => item.key === preset)?.adjust
                    : FILTER_PRESETS[preset];

                if (!values) return;

                state.previewAdjust = null;
                state.previewFilterPreset = '';
                state.adjust = deepClone(values);
                state.activeFilterPreset = preset;
                renderPreview();
                pushHistory();
                setStatus('Filter preset applied.', 'success');
            };

            const clearFilterPreset = () => {
                state.previewAdjust = null;
                state.previewFilterPreset = '';
                state.adjust = deepClone(DEFAULT_ADJUST);
                state.activeFilterPreset = '';
                renderPreview();
                pushHistory();
                setStatus('Filter preset cleared.');
            };

            const previewFilterPreset = (preset, fromCustom = false) => {
                const values = fromCustom
                    ? state.customPresets.find((item) => item.key === preset)?.adjust
                    : FILTER_PRESETS[preset];

                if (!values) return;

                state.previewAdjust = deepClone(values);
                state.previewFilterPreset = preset;
                renderPreview();
                setStatus('Previewing filter preset.');
            };

            const clearFilterPreview = () => {
                if (!state.previewAdjust && !state.previewFilterPreset) return;
                state.previewAdjust = null;
                state.previewFilterPreset = '';
                renderPreview();
                setStatus(state.activeFilterPreset ? 'Showing selected filter preset.' : 'Ready to edit.');
            };

            const addTextLayer = () => {
                const layer = createDefaultTextLayer(state.textLayers.length + 1);
                state.textLayers.push(layer);
                state.selectedTextLayerId = layer.id;
                setActiveObject('text', layer.id);
                renderPreview();
                pushHistory();
                setStatus('Added a new text layer.', 'success');
            };

            const duplicateTextLayer = () => {
                const selected = getSelectedTextLayer();
                if (!selected) return;
                const copy = deepClone(selected);
                copy.id = uid('text');
                copy.name = `${selected.name || 'Text'} copy`;
                copy.x = clamp((Number(copy.x) || 50) + 4, 0, 100);
                copy.y = clamp((Number(copy.y) || 50) + 4, 0, 100);
                state.textLayers.push(copy);
                state.selectedTextLayerId = copy.id;
                setActiveObject('text', copy.id);
                renderPreview();
                pushHistory();
                setStatus('Duplicated the selected text layer.', 'success');
            };

            const deleteSelectedTextLayer = () => {
                const selected = getSelectedTextLayer();
                if (!selected) return;
                if (state.textLayers.length === 1) {
                    state.textLayers = [createDefaultTextLayer(1)];
                } else {
                    state.textLayers = state.textLayers.filter((layer) => layer.id !== selected.id);
                }
                state.selectedTextLayerId = state.textLayers[0].id;
                setActiveObject('text', state.selectedTextLayerId);
                renderPreview();
                pushHistory();
                setStatus('Removed the selected text layer.');
            };

            const removeWatermark = () => {
                const selected = getSelectedImageLayer();
                if (!selected) return;
                state.imageLayers = state.imageLayers.filter((layer) => layer.id !== selected.id);
                state.selectedImageLayerId = state.imageLayers[0]?.id || null;
                syncSelectedImageAlias();
                restoreWatermarkImage();
                if (state.activeObject.type === 'image') {
                    setActiveObject(null, null);
                }
                renderPreview();
                pushHistory();
                setStatus('Removed the image overlay.');
            };

            const duplicateImageLayer = () => {
                const selected = getSelectedImageLayer();
                if (!selected?.src) return;
                const copy = deepClone(selected);
                copy.id = uid('image');
                copy.name = `${selected.name || 'Image'} copy`;
                copy.x = clamp((Number(copy.x) || 50) + 4, 0, 100);
                copy.y = clamp((Number(copy.y) || 50) + 4, 0, 100);
                copy.image = new Image();
                copy.image.decoding = 'async';
                copy.image.src = copy.src;
                state.imageLayers.push(copy);
                state.selectedImageLayerId = copy.id;
                syncSelectedImageAlias();
                setActiveObject('image', copy.id);
                renderPreview();
                pushHistory();
                setStatus('Duplicated the image overlay.', 'success');
            };

            const moveActiveLayer = (direction) => {
                let changed = false;
                if (state.activeObject.type === 'text') {
                    changed = moveLayerInArray(state.textLayers, state.activeObject.id, direction);
                } else if (state.activeObject.type === 'image') {
                    changed = moveLayerInArray(state.imageLayers, state.activeObject.id, direction);
                }
                if (!changed) return;
                renderPreview();
                pushHistory();
                setStatus(direction === 'front' ? 'Layer moved forward.' : 'Layer moved backward.');
            };

            const applyCropPreset = (preset) => {
                if (!state.loaded) return;

                if (preset === 'free') {
                    state.crop = { x: 0, y: 0, width: state.sourceWidth, height: state.sourceHeight };
                } else {
                    const ratio = preset === 'square' ? 1 : preset === 'portrait' ? 4 / 5 : 16 / 9;
                    let width = state.sourceWidth;
                    let height = Math.round(width / ratio);

                    if (height > state.sourceHeight) {
                        height = state.sourceHeight;
                        width = Math.round(height * ratio);
                    }

                    state.crop = {
                        x: Math.max(0, Math.round((state.sourceWidth - width) / 2)),
                        y: Math.max(0, Math.round((state.sourceHeight - height) / 2)),
                        width,
                        height,
                    };
                }

                if (state.output.lockRatio) {
                    state.output.width = state.crop.width;
                    state.output.height = state.crop.height;
                }

                renderPreview();
                pushHistory();
            };

            const enableCropTool = () => {
                state.cropTool.active = true;
                state.cropTool.pointerId = null;
                state.cropTool.rect = state.cropTool.rect || {
                    x: 0,
                    y: 0,
                    width: Math.max(1, Number(canvas.width || state.output.width || 1)),
                    height: Math.max(1, Number(canvas.height || state.output.height || 1)),
                };
                canvas.style.cursor = 'crosshair';
                setStatus('Advanced crop enabled. Resize, move, or redraw the crop frame, then click Apply crop.');
                renderPreview();
            };

            const disableCropTool = (message = 'Manual crop cancelled.') => {
                state.cropTool.active = false;
                state.cropTool.pointerId = null;
                state.cropTool.rect = null;
                state.cropTool.interaction = null;
                state.cropTool.handle = null;
                canvas.style.cursor = 'default';
                setStatus(message);
                renderPreview();
            };

            const applyCropSelection = () => {
                if (!state.cropTool.rect) return;

                const metrics = currentDisplayMetrics();
                const rect = clampSelectionRect(state.cropTool.rect, metrics.logicalWidth, metrics.logicalHeight);
                if (rect.width < 6 || rect.height < 6) {
                    setStatus('Crop area is too small.', 'error');
                    return;
                }

                const currentCrop = { ...state.crop };
                state.crop = {
                    x: round(currentCrop.x + (rect.x / Math.max(metrics.logicalWidth, 1)) * currentCrop.width),
                    y: round(currentCrop.y + (rect.y / Math.max(metrics.logicalHeight, 1)) * currentCrop.height),
                    width: round((rect.width / Math.max(metrics.logicalWidth, 1)) * currentCrop.width),
                    height: round((rect.height / Math.max(metrics.logicalHeight, 1)) * currentCrop.height),
                };
                clampCrop();

                if (state.output.lockRatio) {
                    state.output.width = state.crop.width;
                    state.output.height = state.crop.height;
                }

                state.cropTool.active = false;
                state.cropTool.pointerId = null;
                state.cropTool.rect = null;
                canvas.style.cursor = 'default';
                renderPreview();
                pushHistory();
                setStatus('Crop updated from manual selection.', 'success');
            };

            const exportBlob = async () => {
                const exportCanvas = document.createElement('canvas');
                renderTo(exportCanvas);
                const mime = extensionToMime(state.export.format || state.fileExtension, root.dataset.fileMime || 'image/png');
                const quality = clamp(Number(state.export.quality) || 92, 10, 100) / 100;

                return new Promise((resolve, reject) => {
                    exportCanvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error('Could not export the edited image.'));
                            return;
                        }

                        resolve(blob);
                    }, mime, mime === 'image/png' ? undefined : quality);
                });
            };

            const saveImage = async () => {
                if (state.saving || !state.loaded) return;

                state.saving = true;
                saveButton.disabled = true;
                setStatus('Saving edited image...');

                try {
                    const blob = await exportBlob();
                    const formData = new FormData();
                    const extension = (state.export.format || state.fileExtension) === 'jpg' ? 'jpeg' : (state.export.format || state.fileExtension);
                    formData.append('image', blob, `${(state.export.name || 'edited').trim() || 'edited'}.${extension}`);
                    formData.append('return', state.backUrl);

                    const response = await fetch(state.saveUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': state.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = payload?.message || Object.values(payload?.errors || {})?.flat?.()?.[0] || 'Could not save the edited image.';
                        throw new Error(message);
                    }

                    setStatus(payload?.message || 'Image updated successfully.', 'success');
                    window.location.assign(`${state.backUrl}${state.backUrl.includes('?') ? '&' : '?'}editor_updated=${Date.now()}`);
                } catch (error) {
                    setStatus(error instanceof Error ? error.message : 'Could not save the edited image.', 'error');
                } finally {
                    state.saving = false;
                    saveButton.disabled = false;
                }
            };

            sliderInputs.forEach((input) => {
                input.addEventListener('input', () => {
                    state.adjust[input.dataset.editorSlider] = Number(input.value || 0);
                    state.activeFilterPreset = '';
                    renderPreview();
                });
                input.addEventListener('change', pushHistory);
            });

            textLayerSelect?.addEventListener('change', () => {
                state.selectedTextLayerId = textLayerSelect.value;
                setActiveObject('text', textLayerSelect.value);
                renderPreview();
            });

            imageLayerSelect?.addEventListener('change', () => {
                state.selectedImageLayerId = imageLayerSelect.value;
                syncSelectedImageAlias();
                setActiveObject('image', imageLayerSelect.value);
                renderPreview();
            });

            textEnabled?.addEventListener('change', () => {
                const layer = getSelectedTextLayer();
                if (!layer) return;
                layer.enabled = textEnabled.checked;
                renderPreview();
                pushHistory();
            });

            textInputs.forEach((input) => {
                const key = input.dataset.editorText;
                const applyInput = () => {
                    const layer = getSelectedTextLayer();
                    if (!layer) return;
                    layer[key] = ['size', 'x', 'y', 'opacity', 'strokeWidth', 'shadowBlur', 'letterSpacing', 'lineHeight'].includes(key)
                        ? Number(input.value || 0)
                        : input.value;
                    renderPreview();
                };
                input.addEventListener('input', applyInput);
                input.addEventListener('change', pushHistory);
            });

            textStyleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const layer = getSelectedTextLayer();
                    if (!layer) return;
                    const style = button.dataset.editorTextStyle;
                    if (style === 'bold' || style === 'italic') {
                        layer[style] = !layer[style];
                    } else if (style.startsWith('align-')) {
                        layer.align = style.replace('align-', '');
                    }
                    renderPreview();
                    pushHistory();
                });
            });

            watermarkEnabled?.addEventListener('change', () => {
                const selected = getSelectedImageLayer();
                if (!selected) return;
                selected.enabled = watermarkEnabled.checked;
                if (selected.enabled) setActiveObject('image', selected.id);
                renderPreview();
                pushHistory();
            });

            watermarkInputs.forEach((input) => {
                const key = input.dataset.editorWatermark;
                input.addEventListener('input', () => {
                    const selected = getSelectedImageLayer();
                    if (!selected) return;
                    selected[key] = Number(input.value || 0);
                    if (selected.enabled) setActiveObject('image', selected.id);
                    renderPreview();
                });
                input.addEventListener('change', pushHistory);
            });

            watermarkFile?.addEventListener('change', () => {
                const file = watermarkFile.files?.[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => {
                    const layer = createDefaultWatermark(state.imageLayers.length + 1);
                    layer.src = String(reader.result || '');
                    layer.name = file.name;
                    layer.enabled = true;
                    const image = new Image();
                    image.decoding = 'async';
                    image.onload = () => {
                        layer.naturalWidth = image.naturalWidth;
                        layer.naturalHeight = image.naturalHeight;
                        layer.image = image;
                        renderPreview();
                        pushHistory();
                        setStatus('Image overlay loaded. Drag it on the canvas to position it.', 'success');
                    };
                    image.src = layer.src;
                    layer.image = image;
                    state.imageLayers.push(layer);
                    state.selectedImageLayerId = layer.id;
                    syncSelectedImageAlias();
                    renderPreview();
                };
                reader.readAsDataURL(file);
                watermarkFile.value = '';
            });

            exportName?.addEventListener('input', () => {
                state.export.name = exportName.value;
            });
            exportFormat?.addEventListener('change', () => {
                state.export.format = exportFormat.value;
            });
            exportQuality?.addEventListener('input', () => {
                state.export.quality = Number(exportQuality.value || 92);
                if (exportQualityValue) exportQualityValue.textContent = state.export.quality;
            });

            const startCompare = () => {
                state.compareOriginal = true;
                setStatus('Showing original image.');
                renderPreview();
            };
            const stopCompare = () => {
                state.compareOriginal = false;
                setStatus('Showing edited image.');
                renderPreview();
            };
            ['pointerdown', 'mousedown', 'touchstart'].forEach((eventName) => compareButton?.addEventListener(eventName, startCompare));
            ['pointerup', 'pointerleave', 'mouseup', 'touchend', 'touchcancel'].forEach((eventName) => compareButton?.addEventListener(eventName, stopCompare));

            cropInputs.x?.addEventListener('input', () => { state.crop.x = cropInputs.x.value; clampCrop(); renderPreview(); });
            cropInputs.y?.addEventListener('input', () => { state.crop.y = cropInputs.y.value; clampCrop(); renderPreview(); });
            cropInputs.width?.addEventListener('input', () => {
                state.crop.width = cropInputs.width.value;
                clampCrop();
                if (state.output.lockRatio) {
                    state.output.width = state.crop.width;
                    state.output.height = state.crop.height;
                }
                renderPreview();
            });
            cropInputs.height?.addEventListener('input', () => {
                state.crop.height = cropInputs.height.value;
                clampCrop();
                if (state.output.lockRatio) {
                    state.output.width = state.crop.width;
                    state.output.height = state.crop.height;
                }
                renderPreview();
            });
            Object.values(cropInputs).forEach((input) => input?.addEventListener('change', pushHistory));

            outputInputs.width?.addEventListener('input', () => {
                const width = clamp(round(outputInputs.width.value), 1, 8000);
                state.output.width = width;
                if (state.output.lockRatio) {
                    const ratio = state.crop.height / Math.max(1, state.crop.width);
                    state.output.height = clamp(round(width * ratio), 1, 8000);
                }
                renderPreview();
            });
            outputInputs.height?.addEventListener('input', () => {
                const height = clamp(round(outputInputs.height.value), 1, 8000);
                state.output.height = height;
                if (state.output.lockRatio) {
                    const ratio = state.crop.width / Math.max(1, state.crop.height);
                    state.output.width = clamp(round(height * ratio), 1, 8000);
                }
                renderPreview();
            });
            Object.values(outputInputs).forEach((input) => input?.addEventListener('change', pushHistory));

            lockInput?.addEventListener('change', () => {
                state.output.lockRatio = lockInput.checked;
                if (state.output.lockRatio) {
                    state.output.width = state.crop.width;
                    state.output.height = state.crop.height;
                }
                renderPreview();
                pushHistory();
            });

            root.querySelectorAll('[data-editor-action]').forEach((button) => {
                button.addEventListener('click', () => {
                    switch (button.dataset.editorAction) {
                        case 'rotate-left':
                            state.rotation = (state.rotation - 90 + 360) % 360;
                            renderPreview();
                            pushHistory();
                            return;
                        case 'rotate-right':
                            state.rotation = (state.rotation + 90) % 360;
                            renderPreview();
                            pushHistory();
                            return;
                        case 'flip-x':
                            state.flipX *= -1;
                            renderPreview();
                            pushHistory();
                            return;
                        case 'flip-y':
                            state.flipY *= -1;
                            renderPreview();
                            pushHistory();
                            return;
                        case 'reset-crop':
                            state.crop = { x: 0, y: 0, width: state.sourceWidth, height: state.sourceHeight };
                            state.cropTool.rect = null;
                            if (state.output.lockRatio) {
                                state.output.width = state.crop.width;
                                state.output.height = state.crop.height;
                            }
                            renderPreview();
                            pushHistory();
                            return;
                        case 'toggle-crop-mode':
                            state.cropTool.active ? disableCropTool() : enableCropTool();
                            return;
                        case 'apply-crop-selection':
                            applyCropSelection();
                            return;
                        case 'cancel-crop-mode':
                            disableCropTool();
                            return;
                        case 'reset-all':
                            resetAll();
                            return;
                        case 'clear-preset':
                            clearFilterPreset();
                            return;
                        case 'save-custom-preset':
                            saveCurrentCustomPreset();
                            return;
                        case 'text-add':
                            addTextLayer();
                            return;
                        case 'text-duplicate':
                            duplicateTextLayer();
                            return;
                        case 'text-delete':
                            deleteSelectedTextLayer();
                            return;
                        case 'layer-forward':
                            moveActiveLayer('front');
                            return;
                        case 'layer-backward':
                            moveActiveLayer('back');
                            return;
                        case 'watermark-duplicate':
                            duplicateImageLayer();
                            return;
                        case 'watermark-remove':
                            removeWatermark();
                            return;
                        default:
                            return;
                    }
                });
            });

            root.addEventListener('click', (event) => {
                const applyButton = event.target.closest('[data-editor-custom-preset]');
                if (applyButton) {
                    applyFilterPreset(applyButton.dataset.editorCustomPreset, true);
                    return;
                }

                const deleteButton = event.target.closest('[data-editor-custom-preset-delete]');
                if (deleteButton) {
                    state.customPresets = state.customPresets.filter((item) => item.key !== deleteButton.dataset.editorCustomPresetDelete);
                    saveCustomPresets(state.customPresets);
                    renderPreview();
                    setStatus('Removed saved preset.');
                }
            });

            root.addEventListener('mouseover', (event) => {
                const previewButton = event.target.closest('[data-editor-custom-preset]');
                if (previewButton) {
                    previewFilterPreset(previewButton.dataset.editorCustomPreset, true);
                }
            });

            root.addEventListener('mouseout', (event) => {
                const previewButton = event.target.closest('[data-editor-custom-preset]');
                if (previewButton && (!event.relatedTarget || !previewButton.contains(event.relatedTarget))) {
                    clearFilterPreview();
                }
            });

            root.querySelectorAll('[data-editor-preset]').forEach((button) => {
                button.addEventListener('click', () => applyCropPreset(button.dataset.editorPreset));
            });

            presetCards.forEach((button) => {
                button.addEventListener('click', () => applyFilterPreset(button.dataset.editorPresetCard));
                button.addEventListener('mouseenter', () => previewFilterPreset(button.dataset.editorPresetCard));
                button.addEventListener('mouseleave', clearFilterPreview);
                button.addEventListener('focus', () => previewFilterPreset(button.dataset.editorPresetCard));
                button.addEventListener('blur', clearFilterPreview);
            });

            presetOverlays.forEach((overlay) => {
                const background = PRESET_PREVIEW_OVERLAYS[overlay.dataset.editorPresetOverlay];
                if (background) overlay.style.background = background;
            });

            root.querySelectorAll('[data-editor-zoom]').forEach((button) => {
                button.addEventListener('click', () => {
                    const action = button.dataset.editorZoom;
                    if (action === 'in') state.zoom = clamp(state.zoom + 0.1, 0.2, 3);
                    else if (action === 'out') state.zoom = clamp(state.zoom - 0.1, 0.2, 3);
                    else state.zoom = 1;
                    renderPreview();
                });
            });

            undoButton?.addEventListener('click', undo);
            redoButton?.addEventListener('click', redo);

            canvas.addEventListener('pointerdown', (event) => {
                if (state.cropTool.active) {
                    const point = pointerToCanvas(event);
                    const target = detectCropHandle(point);
                    state.cropTool.pointerId = event.pointerId;
                    state.cropTool.startX = point.x;
                    state.cropTool.startY = point.y;
                    state.cropTool.handle = target?.key || 'new';
                    if (target?.key === 'move') {
                        state.cropTool.offsetX = point.x - target.rect.x;
                        state.cropTool.offsetY = point.y - target.rect.y;
                    }
                    if (target?.key === 'new') {
                        state.cropTool.rect = { x: point.x, y: point.y, width: 0, height: 0 };
                    } else {
                        state.cropTool.rect = target.rect;
                    }
                    canvas.setPointerCapture?.(event.pointerId);
                    canvas.style.cursor = 'crosshair';
                    event.preventDefault();
                    return;
                }

                const hit = hitTestObject(event);
                if (!hit) {
                    setActiveObject(null, null);
                    renderPreview();
                    return;
                }

                state.drag.active = true;
                state.drag.pointerId = event.pointerId;
                state.drag.type = hit.type;
                state.drag.id = hit.id;
                state.drag.offsetX = pointerToCanvas(event).x - hit.bounds.x;
                state.drag.offsetY = pointerToCanvas(event).y - hit.bounds.y;
                if (hit.type === 'image') {
                    const point = pointerToCanvas(event);
                    const handle = detectImageHandle(point, hit.bounds);
                    state.drag.handle = handle;
                    state.drag.originCenterX = hit.bounds.x + hit.bounds.width / 2;
                    state.drag.originCenterY = hit.bounds.y + hit.bounds.height / 2;
                    state.drag.originDistance = Math.max(1, Math.hypot(point.x - state.drag.originCenterX, point.y - state.drag.originCenterY));
                    const layer = state.imageLayers.find((item) => item.id === hit.id);
                    state.drag.originScale = layer?.scale || 24;
                } else {
                    state.drag.handle = null;
                }
                canvas.setPointerCapture?.(event.pointerId);
                canvas.style.cursor = 'grabbing';
                setActiveObject(hit.type, hit.id);
                renderPreview();
                event.preventDefault();
            });

            canvas.addEventListener('pointermove', (event) => {
                if (state.cropTool.active && state.cropTool.pointerId === event.pointerId) {
                    updateCropRectFromInteraction(pointerToCanvas(event));
                    renderPreview();
                    event.preventDefault();
                    return;
                }

                if (state.drag.active && state.drag.pointerId === event.pointerId) {
                    const point = pointerToCanvas(event);
                    if (state.drag.type === 'text') {
                        const layer = state.textLayers.find((item) => item.id === state.drag.id);
                        const bounds = layer ? getTextLayerBounds(context, layer, canvas.width, canvas.height) : null;
                        if (layer && bounds) {
                            const snapped = applySnap(point.x - state.drag.offsetX, point.y - state.drag.offsetY, bounds.width, bounds.height);
                            const newX = snapped.x;
                            const newY = snapped.y;
                            let anchorX = newX;
                            if (layer.align === 'center') anchorX += bounds.width / 2;
                            if (layer.align === 'right') anchorX += bounds.width;
                            layer.x = clamp(Math.round((anchorX / canvas.width) * 100), 0, 100);
                            layer.y = clamp(Math.round(((newY + bounds.height / 2) / canvas.height) * 100), 0, 100);
                        }
                    } else if (state.drag.type === 'image') {
                        const layer = state.imageLayers.find((item) => item.id === state.drag.id);
                        const bounds = layer ? updateWatermarkBounds(layer) : null;
                        if (layer && bounds) {
                            if (state.drag.handle && state.drag.handle !== 'move') {
                                const currentDistance = Math.max(1, Math.hypot(point.x - state.drag.originCenterX, point.y - state.drag.originCenterY));
                                const nextScale = clamp(Math.round(state.drag.originScale * (currentDistance / state.drag.originDistance)), 5, 90);
                                layer.scale = nextScale;
                                state.guides = [];
                            } else {
                                const snapped = applySnap(point.x - state.drag.offsetX, point.y - state.drag.offsetY, bounds.width, bounds.height);
                                const newX = snapped.x;
                                const newY = snapped.y;
                                layer.x = clamp(Math.round(((newX + bounds.width / 2) / canvas.width) * 100), 0, 100);
                                layer.y = clamp(Math.round(((newY + bounds.height / 2) / canvas.height) * 100), 0, 100);
                            }
                        }
                    }
                    renderPreview();
                    event.preventDefault();
                    return;
                }

                const hit = hitTestObject(event);
                state.guides = [];
                canvas.style.cursor = state.cropTool.active ? 'crosshair' : (hit ? 'grab' : 'default');
            });

            const finishPointer = (event) => {
                if (state.drag.active && (!event || state.drag.pointerId === event.pointerId)) {
                    if (state.drag.pointerId !== null) canvas.releasePointerCapture?.(state.drag.pointerId);
                    state.drag = { active: false, pointerId: null, type: null, id: null, offsetX: 0, offsetY: 0, handle: null, originScale: 0, originCenterX: 0, originCenterY: 0, originDistance: 0 };
                    state.guides = [];
                    canvas.style.cursor = 'default';
                    pushHistory();
                }

                if (state.cropTool.active && state.cropTool.pointerId !== null && (!event || state.cropTool.pointerId === event.pointerId)) {
                    canvas.releasePointerCapture?.(state.cropTool.pointerId);
                    state.cropTool.pointerId = null;
                    state.cropTool.handle = null;
                    canvas.style.cursor = 'crosshair';
                    renderPreview();
                }
            };

            canvas.addEventListener('pointerup', finishPointer);
            canvas.addEventListener('pointercancel', finishPointer);
            canvas.addEventListener('pointerleave', (event) => {
                if (!state.drag.active && !state.cropTool.pointerId) {
                    canvas.style.cursor = 'default';
                    return;
                }
                finishPointer(event);
            });

            downloadButton?.addEventListener('click', async () => {
                try {
                    const blob = await exportBlob();
                    const downloadName = `${(state.export.name || state.fileName.replace(/\.[^.]+$/, '')).trim() || 'image'}.${state.export.format || state.fileExtension}`;
                    downloadBlob(blob, downloadName);
                    setStatus('Downloaded the edited image.', 'success');
                } catch (error) {
                    setStatus(error instanceof Error ? error.message : 'Could not download the edited image.', 'error');
                }
            });

            saveButton?.addEventListener('click', saveImage);

            const shouldIgnoreKeyboard = (target) => {
                return target instanceof HTMLElement
                    && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
            };

            window.addEventListener('keydown', (event) => {
                if (!root.isConnected) return;

                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
                    event.preventDefault();
                    if (event.shiftKey) redo();
                    else undo();
                    return;
                }

                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') {
                    event.preventDefault();
                    redo();
                    return;
                }

                if (event.key === 'Escape' && state.cropTool.active) {
                    event.preventDefault();
                    disableCropTool();
                    return;
                }

                if (shouldIgnoreKeyboard(event.target)) {
                    return;
                }

                if (event.key === 'Delete' || event.key === 'Backspace') {
                    if (state.activeObject.type === 'text') {
                        event.preventDefault();
                        deleteSelectedTextLayer();
                    } else if (state.activeObject.type === 'image') {
                        event.preventDefault();
                        removeWatermark();
                    }
                    return;
                }

                const step = event.shiftKey ? 10 : 1;
                if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                    return;
                }

                if (state.activeObject.type === 'text') {
                    const layer = getSelectedTextLayer();
                    if (!layer) return;
                    event.preventDefault();
                    if (event.key === 'ArrowLeft') layer.x = clamp(layer.x - step, 0, 100);
                    if (event.key === 'ArrowRight') layer.x = clamp(layer.x + step, 0, 100);
                    if (event.key === 'ArrowUp') layer.y = clamp(layer.y - step, 0, 100);
                    if (event.key === 'ArrowDown') layer.y = clamp(layer.y + step, 0, 100);
                    renderPreview();
                    pushHistory();
                    return;
                }

                const selectedImage = getSelectedImageLayer();
                if (state.activeObject.type === 'image' && selectedImage?.src) {
                    event.preventDefault();
                    if (event.key === 'ArrowLeft') selectedImage.x = clamp(selectedImage.x - step, 0, 100);
                    if (event.key === 'ArrowRight') selectedImage.x = clamp(selectedImage.x + step, 0, 100);
                    if (event.key === 'ArrowUp') selectedImage.y = clamp(selectedImage.y - step, 0, 100);
                    if (event.key === 'ArrowDown') selectedImage.y = clamp(selectedImage.y + step, 0, 100);
                    renderPreview();
                    pushHistory();
                }
            });

            const handleLoad = () => {
                state.sourceWidth = source.naturalWidth;
                state.sourceHeight = source.naturalHeight;
                state.loaded = true;
                resetAll();
                renderPresetThumbnails();
                setStatus('Image loaded. Adjust, crop, layer, and save when ready.');
            };

            const revokeSourceObjectUrl = () => {
                if (!sourceObjectUrl) {
                    return;
                }

                URL.revokeObjectURL(sourceObjectUrl);
                sourceObjectUrl = null;
            };

            const loadSourceImage = async () => {
                const previewUrl = root.dataset.previewUrl;

                if (!previewUrl) {
                    setStatus('Could not load the source image.', 'error');
                    return;
                }

                try {
                    const response = await fetch(previewUrl, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'image/*',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
                    if (contentType !== '' && !contentType.startsWith('image/')) {
                        throw new Error(`Unexpected content type: ${contentType}`);
                    }

                    const blob = await response.blob();
                    if (!blob.size) {
                        throw new Error('Empty image payload');
                    }

                    revokeSourceObjectUrl();
                    sourceObjectUrl = URL.createObjectURL(blob);
                    source.src = sourceObjectUrl;
                } catch (error) {
                    console.error('Failed to fetch editor source image.', error);
                    revokeSourceObjectUrl();
                    source.src = previewUrl;
                }
            };

            source.addEventListener('load', handleLoad);
            source.addEventListener('error', () => setStatus('Could not load the source image.', 'error'));

            if (source.getAttribute && source.getAttribute('src')) {
                if (source.complete) {
                    if (source.naturalWidth > 0) {
                        handleLoad();
                    } else {
                        setStatus('Could not load the source image.', 'error');
                    }
                }
            } else {
                loadSourceImage();
            }

            window.addEventListener('resize', () => renderPreview(), { passive: true });
            window.addEventListener('beforeunload', revokeSourceObjectUrl, { once: true });
        };

        const bootEditors = () => {
            document.querySelectorAll(EDITOR_SELECTOR).forEach(initEditor);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootEditors, { once: true });
        } else {
            bootEditors();
        }

        document.addEventListener('livewire:navigated', bootEditors);
    })();
</script>
