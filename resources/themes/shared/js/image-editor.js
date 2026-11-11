const EDITOR_SELECTOR = '[data-image-editor]';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

const round = (value) => Math.max(1, Math.round(Number(value) || 0));

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

const buildFilter = (state) => [
    `brightness(${state.adjust.brightness}%)`,
    `contrast(${state.adjust.contrast}%)`,
    `saturate(${state.adjust.saturation}%)`,
    `grayscale(${state.adjust.grayscale}%)`,
    `sepia(${state.adjust.sepia}%)`,
    `blur(${state.adjust.blur}px)`,
].join(' ');

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

const initEditor = (root) => {
    if (!root || root.dataset.editorReady === 'true') {
        return;
    }

    root.dataset.editorReady = 'true';

    const canvas = root.querySelector('[data-editor-canvas]');
    const status = root.querySelector('[data-editor-status]');
    const dimensions = root.querySelector('[data-editor-dimensions]');
    const rotationBadge = root.querySelector('[data-editor-rotation]');
    const saveButton = root.querySelector('[data-editor-save]');
    const downloadButton = root.querySelector('[data-editor-download]');
    const lockInput = root.querySelector('[data-editor-lock]');
    const sliderInputs = [...root.querySelectorAll('[data-editor-slider]')];
    const textEnabled = root.querySelector('[data-editor-text-enabled]');
    const textInputs = [...root.querySelectorAll('[data-editor-text]')];
    const numberInputs = {
        cropX: root.querySelector('[data-editor-input="crop-x"]'),
        cropY: root.querySelector('[data-editor-input="crop-y"]'),
        cropWidth: root.querySelector('[data-editor-input="crop-width"]'),
        cropHeight: root.querySelector('[data-editor-input="crop-height"]'),
        outputWidth: root.querySelector('[data-editor-input="output-width"]'),
        outputHeight: root.querySelector('[data-editor-input="output-height"]'),
    };

    const source = new Image();
    source.decoding = 'async';

    const context = canvas.getContext('2d');

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
        adjust: {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            grayscale: 0,
            sepia: 0,
            blur: 0,
        },
        text: {
            enabled: false,
            value: '',
            size: 48,
            color: '#ffffff',
            x: 50,
            y: 88,
        },
    };

    const setStatus = (message, tone = 'default') => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.remove('text-emerald-300', 'text-rose-300', 'text-slate-300');
        status.classList.add(tone === 'success' ? 'text-emerald-300' : tone === 'error' ? 'text-rose-300' : 'text-slate-300');
    };

    const updateBadges = () => {
        if (dimensions) {
            dimensions.textContent = `Output: ${state.output.width} x ${state.output.height}`;
        }

        if (rotationBadge) {
            rotationBadge.textContent = `Rotation: ${state.rotation}°`;
        }
    };

    const syncForm = () => {
        numberInputs.cropX.value = state.crop.x;
        numberInputs.cropY.value = state.crop.y;
        numberInputs.cropWidth.value = state.crop.width;
        numberInputs.cropHeight.value = state.crop.height;
        numberInputs.outputWidth.value = state.output.width;
        numberInputs.outputHeight.value = state.output.height;
        lockInput.checked = state.output.lockRatio;
        textEnabled.checked = state.text.enabled;

        sliderInputs.forEach((input) => {
            const key = input.dataset.editorSlider;
            input.value = state.adjust[key];
            const target = root.querySelector(`[data-editor-value="${key}"]`);
            if (target) {
                target.textContent = state.adjust[key];
            }
        });

        textInputs.forEach((input) => {
            const key = input.dataset.editorText;
            if (key) {
                input.value = state.text[key];
            }
        });

        updateBadges();
    };

    const actualCanvasSize = () => {
        const rotated = Math.abs(state.rotation) % 180 !== 0;

        return rotated
            ? { width: state.output.height, height: state.output.width }
            : { width: state.output.width, height: state.output.height };
    };

    const clampCrop = () => {
        state.crop.x = clamp(round(state.crop.x), 0, Math.max(0, state.sourceWidth - 1));
        state.crop.y = clamp(round(state.crop.y), 0, Math.max(0, state.sourceHeight - 1));
        state.crop.width = clamp(round(state.crop.width), 1, state.sourceWidth - state.crop.x);
        state.crop.height = clamp(round(state.crop.height), 1, state.sourceHeight - state.crop.y);
    };

    const drawTextOverlay = (targetContext, width, height) => {
        if (!state.text.enabled || !String(state.text.value || '').trim()) {
            return;
        }

        const fontSize = clamp(round(state.text.size), 8, Math.max(8, Math.floor(Math.min(width, height) * 0.4)));
        const x = (clamp(Number(state.text.x) || 0, 0, 100) / 100) * width;
        const y = (clamp(Number(state.text.y) || 0, 0, 100) / 100) * height;

        targetContext.save();
        targetContext.font = `700 ${fontSize}px var(--theme-font-sans, sans-serif)`;
        targetContext.textAlign = 'center';
        targetContext.textBaseline = 'middle';
        targetContext.fillStyle = state.text.color || '#ffffff';
        targetContext.shadowColor = 'rgba(15, 23, 42, 0.42)';
        targetContext.shadowBlur = Math.max(6, Math.round(fontSize * 0.24));
        targetContext.lineWidth = Math.max(2, Math.round(fontSize * 0.08));
        targetContext.strokeStyle = 'rgba(15, 23, 42, 0.38)';

        const lines = String(state.text.value).split(/\r?\n/);
        const lineHeight = Math.round(fontSize * 1.16);
        const startY = y - ((lines.length - 1) * lineHeight) / 2;

        lines.forEach((line, index) => {
            const lineY = startY + index * lineHeight;
            targetContext.strokeText(line, x, lineY);
            targetContext.fillText(line, x, lineY);
        });

        targetContext.restore();
    };

    const renderTo = (targetCanvas) => {
        const drawContext = targetCanvas.getContext('2d');
        const size = actualCanvasSize();
        targetCanvas.width = Math.max(1, round(size.width));
        targetCanvas.height = Math.max(1, round(size.height));

        drawContext.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
        drawContext.save();
        drawContext.translate(targetCanvas.width / 2, targetCanvas.height / 2);
        drawContext.rotate((state.rotation * Math.PI) / 180);
        drawContext.scale(state.flipX, state.flipY);
        drawContext.filter = buildFilter(state);
        drawContext.imageSmoothingEnabled = true;
        drawContext.imageSmoothingQuality = 'high';
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
        drawContext.restore();

        drawTextOverlay(drawContext, targetCanvas.width, targetCanvas.height);
    };

    const renderPreview = () => {
        if (!state.loaded) {
            return;
        }

        renderTo(canvas);
        const size = actualCanvasSize();
        const availableWidth = Math.max(420, canvas.parentElement?.clientWidth || size.width);
        const availableHeight = Math.max(520, canvas.parentElement?.clientHeight || size.height);
        const fitScale = Math.min(availableWidth / size.width, availableHeight / size.height, 1);
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
        state.crop = {
            x: 0,
            y: 0,
            width: state.sourceWidth,
            height: state.sourceHeight,
        };
        state.output = {
            width: state.sourceWidth,
            height: state.sourceHeight,
            lockRatio: true,
        };
        state.adjust = {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            grayscale: 0,
            sepia: 0,
            blur: 0,
        };
        state.text = {
            enabled: false,
            value: '',
            size: 48,
            color: '#ffffff',
            x: 50,
            y: 88,
        };

        renderPreview();
        setStatus('Editor reset to the original image.');
    };

    const applyCropPreset = (preset) => {
        if (!state.loaded) {
            return;
        }

        if (preset === 'free') {
            state.crop = {
                x: 0,
                y: 0,
                width: state.sourceWidth,
                height: state.sourceHeight,
            };
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
    };

    const exportBlob = async () => {
        const exportCanvas = document.createElement('canvas');
        renderTo(exportCanvas);

        const mime = extensionToMime(state.fileExtension, root.dataset.fileMime || 'image/png');

        return new Promise((resolve, reject) => {
            exportCanvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Could not export the edited image.'));
                    return;
                }

                resolve(blob);
            }, mime, mime === 'image/png' ? undefined : 0.92);
        });
    };

    const saveImage = async () => {
        if (state.saving || !state.loaded) {
            return;
        }

        state.saving = true;
        saveButton.disabled = true;
        setStatus('Saving edited image...');

        try {
            const blob = await exportBlob();
            const formData = new FormData();
            const extension = state.fileExtension === 'jpg' ? 'jpeg' : state.fileExtension;
            formData.append('image', blob, `edited.${extension}`);
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

            source.src = `${root.dataset.previewUrl}${root.dataset.previewUrl.includes('?') ? '&' : '?'}updated=${Date.now()}`;
            setStatus(payload?.message || 'Image updated successfully.', 'success');
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
            renderPreview();
        });
    });

    textEnabled?.addEventListener('change', () => {
        state.text.enabled = textEnabled.checked;
        renderPreview();
    });

    textInputs.forEach((input) => {
        const key = input.dataset.editorText;
        input.addEventListener('input', () => {
            state.text[key] = ['size', 'x', 'y'].includes(key) ? Number(input.value || 0) : input.value;
            renderPreview();
        });
    });

    numberInputs.cropX?.addEventListener('input', () => {
        state.crop.x = numberInputs.cropX.value;
        clampCrop();
        renderPreview();
    });

    numberInputs.cropY?.addEventListener('input', () => {
        state.crop.y = numberInputs.cropY.value;
        clampCrop();
        renderPreview();
    });

    numberInputs.cropWidth?.addEventListener('input', () => {
        state.crop.width = numberInputs.cropWidth.value;
        clampCrop();
        if (state.output.lockRatio) {
            state.output.width = state.crop.width;
            state.output.height = state.crop.height;
        }
        renderPreview();
    });

    numberInputs.cropHeight?.addEventListener('input', () => {
        state.crop.height = numberInputs.cropHeight.value;
        clampCrop();
        if (state.output.lockRatio) {
            state.output.width = state.crop.width;
            state.output.height = state.crop.height;
        }
        renderPreview();
    });

    numberInputs.outputWidth?.addEventListener('input', () => {
        const width = clamp(round(numberInputs.outputWidth.value), 1, 8000);
        state.output.width = width;

        if (state.output.lockRatio) {
            const ratio = state.crop.height / Math.max(1, state.crop.width);
            state.output.height = clamp(round(width * ratio), 1, 8000);
        }

        renderPreview();
    });

    numberInputs.outputHeight?.addEventListener('input', () => {
        const height = clamp(round(numberInputs.outputHeight.value), 1, 8000);
        state.output.height = height;

        if (state.output.lockRatio) {
            const ratio = state.crop.width / Math.max(1, state.crop.height);
            state.output.width = clamp(round(height * ratio), 1, 8000);
        }

        renderPreview();
    });

    lockInput?.addEventListener('change', () => {
        state.output.lockRatio = lockInput.checked;
        if (state.output.lockRatio) {
            state.output.width = state.crop.width;
            state.output.height = state.crop.height;
        }
        renderPreview();
    });

    root.querySelectorAll('[data-editor-action]').forEach((button) => {
        button.addEventListener('click', () => {
            switch (button.dataset.editorAction) {
                case 'rotate-left':
                    state.rotation = (state.rotation - 90 + 360) % 360;
                    break;
                case 'rotate-right':
                    state.rotation = (state.rotation + 90) % 360;
                    break;
                case 'flip-x':
                    state.flipX *= -1;
                    break;
                case 'flip-y':
                    state.flipY *= -1;
                    break;
                case 'reset-crop':
                    state.crop = { x: 0, y: 0, width: state.sourceWidth, height: state.sourceHeight };
                    if (state.output.lockRatio) {
                        state.output.width = state.crop.width;
                        state.output.height = state.crop.height;
                    }
                    break;
                case 'reset-all':
                    resetAll();
                    return;
                default:
                    break;
            }

            renderPreview();
        });
    });

    root.querySelectorAll('[data-editor-preset]').forEach((button) => {
        button.addEventListener('click', () => applyCropPreset(button.dataset.editorPreset));
    });

    root.querySelectorAll('[data-editor-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.editorZoom;
            if (action === 'in') {
                state.zoom = clamp(state.zoom + 0.1, 0.2, 3);
            } else if (action === 'out') {
                state.zoom = clamp(state.zoom - 0.1, 0.2, 3);
            } else {
                state.zoom = 1;
            }

            renderPreview();
        });
    });

    downloadButton?.addEventListener('click', async () => {
        try {
            const blob = await exportBlob();
            downloadBlob(blob, state.fileName);
            setStatus('Downloaded the edited image.', 'success');
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'Could not download the edited image.', 'error');
        }
    });

    saveButton?.addEventListener('click', saveImage);

    const handleLoad = () => {
        state.sourceWidth = source.naturalWidth;
        state.sourceHeight = source.naturalHeight;
        state.loaded = true;
        resetAll();
        setStatus('Image loaded. Adjust, crop, and save when ready.');
    };

    const handleError = () => {
        setStatus('Could not load the source image.', 'error');
    };

    source.addEventListener('load', handleLoad);
    source.addEventListener('error', handleError);
    source.src = root.dataset.previewUrl;

    if (source.complete) {
        if (source.naturalWidth > 0) {
            handleLoad();
        } else {
            handleError();
        }
    }

    window.addEventListener('resize', () => renderPreview(), { passive: true });
};

const bootEditors = () => {
    document.querySelectorAll(EDITOR_SELECTOR).forEach(initEditor);
};

document.addEventListener('DOMContentLoaded', bootEditors);
document.addEventListener('livewire:navigated', bootEditors);
