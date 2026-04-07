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
     */
    init(inputId, previewId, fileType = 'image') {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);

        if (!input || !preview) {
            console.error(`No se encontró el elemento: ${inputId} o ${previewId}`);
            return;
        }

        input.addEventListener('change', (e) => {
            this.handleFileSelect(e, preview, fileType);
        });
    }

    /**
     * Maneja la selección de archivo
     */
    handleFileSelect(event, previewContainer, fileType) {
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
            this.showImagePreview(file, previewContainer);
        } else if (fileType === 'pdf') {
            this.showPdfPreview(file, previewContainer);
        }
    }

    /**
     * Muestra vista previa de imagen
     */
    showImagePreview(file, container) {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            container.innerHTML = `
                <div class="file-preview-wrapper">
                    <img src="${e.target.result}" 
                         alt="Vista previa" 
                         style="max-width: ${this.previewSize}px; max-height: ${this.previewSize}px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div class="file-info mt-2">
                        <small class="text-muted">
                            <i class="fas fa-file-image"></i> ${file.name} 
                            <br><i class="fas fa-weight-hanging"></i> ${this.formatBytes(file.size)}
                        </small>
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

