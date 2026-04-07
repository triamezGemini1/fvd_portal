/**
 * File Preview Component
 * Sistema de vista previa de archivos para imágenes y PDFs
 * Versión: 1.0
 */

class FilePreview {
    constructor(options = {}) {
        this.maxFileSize = options.maxFileSize || 5 * 1024 * 1024; // 5MB por defecto
        this.allowedImageTypes = options.allowedImageTypes || ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        this.allowedPdfTypes = options.allowedPdfTypes || ['application/pdf'];
        this.previewSize = options.previewSize || 150;
    }

    /**
     * Inicializa la vista previa para un input file
     * @param {string} inputId - ID del input file
     * @param {string} previewId - ID del contenedor de vista previa
     * @param {string} fileType - Tipo de archivo esperado: 'image' o 'pdf'
     * @param {{ previewSize?: number }} [extra] - previewSize: ancho/alto máx. en px (p. ej. 220 para logos)
     */
    init(inputId, previewId, fileType = 'image', extra = {}) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);

        if (!input || !preview) {
            console.error(`No se encontró el elemento: ${inputId} o ${previewId}`);
            return;
        }

        const initialHtml = preview.innerHTML;
        const previewSizePx = typeof extra.previewSize === 'number' && extra.previewSize > 0
            ? extra.previewSize
            : null;

        input.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) {
                preview.innerHTML = initialHtml;
                return;
            }
            this.handleFileSelect(e, preview, fileType, previewSizePx);
        });
    }

    /**
     * Maneja la selección de archivo
     * @param {number|null} previewSizePx tamaño máximo en px (opcional)
     */
    handleFileSelect(event, previewContainer, fileType, previewSizePx = null) {
        const file = event.target.files[0];

        if (!file) {
            this.clearPreview(previewContainer);
            return;
        }

        // Validar tamaño
        if (file.size > this.maxFileSize) {
            this.showError(previewContainer, `El archivo es muy grande. Máximo ${this.formatBytes(this.maxFileSize)}`);
            event.target.value = '';
            return;
        }

        // Validar tipo
        const allowedTypes = fileType === 'image' ? this.allowedImageTypes : this.allowedPdfTypes;
        if (!allowedTypes.includes(file.type)) {
            this.showError(previewContainer, `Tipo de archivo no permitido. Use: ${allowedTypes.join(', ')}`);
            event.target.value = '';
            return;
        }

        // Mostrar vista previa
        if (fileType === 'image') {
            this.showImagePreview(file, previewContainer, previewSizePx);
        } else if (fileType === 'pdf') {
            this.showPdfPreview(file, previewContainer);
        }
    }

    /**
     * Muestra vista previa de imagen
     * @param {number|null} maxPx
     */
    showImagePreview(file, container, maxPx = null) {
        const px = maxPx !== null && maxPx > 0 ? maxPx : this.previewSize;
        const reader = new FileReader();

        reader.onload = (e) => {
            const result = (e.target && e.target.result) ? String(e.target.result) : '';
            const safeName = String(file.name || '').replace(/</g, '&lt;').replace(/&/g, '&amp;');
            container.innerHTML = `
                <div class="file-preview-wrapper">
                    <img src="${result}"
                         alt="Vista previa"
                         style="max-width: ${px}px; max-height: ${px}px; width: auto; height: auto; object-fit: contain; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                    <div class="file-info" style="margin-top:8px;font-size:12px;color:#94a3b8;line-height:1.4">
                        ${safeName}<br>${this.formatBytes(file.size)}
                    </div>
                </div>
            `;
        };

        reader.readAsDataURL(file);
    }

    /**
     * Muestra vista previa de PDF
     */
    showPdfPreview(file, container) {
        container.innerHTML = `
            <div class="file-preview-wrapper">
                <div class="pdf-preview" style="padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                    <i class="fas fa-file-pdf" style="font-size: 64px; color: #dc3545;"></i>
                    <div class="file-info mt-2">
                        <strong>${file.name}</strong><br>
                        <small class="text-muted">
                            <i class="fas fa-weight-hanging"></i> ${this.formatBytes(file.size)}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Muestra mensaje de error
     */
    showError(container, message) {
        container.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }

    /**
     * Limpia la vista previa
     */
    clearPreview(container) {
        container.innerHTML = '';
    }

    /**
     * Formatea bytes a formato legible
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
}

// Instancia global
window.filePreview = new FilePreview();

