document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const fileUpload = document.querySelector('.file-upload');
    const fileUploadPlaceholder = fileUpload?.dataset?.placeholder?.trim() || '';
    const fileUploadNote = fileUpload ? fileUpload.querySelector('.form-note') : null;

    const modalState = {
        activeId: null,
    };

    function setBodyScroll(blocked) {
        document.body.style.overflow = blocked ? 'hidden' : '';
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('active');
        setBodyScroll(true);
        modalState.activeId = id;
    }

    function closeModal(id) {
        const modal = id ? document.getElementById(id) : document.querySelector('.modal.active');
        if (!modal) return;
        modal.classList.remove('active');
        setBodyScroll(false);
        modalState.activeId = null;
    }

    window.openCreateModal = function() {
        openModal('createModal');
    };

    window.closeModal = function(modalId) {
        closeModal(modalId);
    };

    window.openViewModal = function(ticketId) {
        window.location.href = 'ticket_view.php?id=' + ticketId;
    };

    window.openUpdateModal = function(ticketId) {
        window.location.href = 'ticket_update.php?id=' + ticketId;
    };

    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    function resetFileUploadText() {
        if (!fileUpload) return;
        const paragraph = fileUpload.querySelector('p');
        if (paragraph) {
            paragraph.textContent = fileUploadPlaceholder;
        }
        if (fileUploadNote) {
            fileUploadNote.style.display = '';
        }
    }

    if (fileUpload) {
        resetFileUploadText();

        fileUpload.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput?.click();
            }
        });
    }

    if (fileInput && fileUpload) {
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files || []);
            const preview = fileUpload.querySelector('p');
            const allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'application/zip'
            ];
            const maxSize = 10 * 1024 * 1024;
            const validFiles = files.filter(f => f.size <= maxSize && allowedTypes.includes(f.type));
            const invalidFiles = files.filter(f => f.size > maxSize || !allowedTypes.includes(f.type));

            if (preview) {
                if (validFiles.length === 0) {
                    preview.textContent = fileUploadPlaceholder;
                    if (fileUploadNote) {
                        fileUploadNote.style.display = '';
                    }
                } else {
                    const nameList = validFiles.map(f => f.name).join(', ');
                    preview.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${validFiles.length} file${validFiles.length > 1 ? 's' : ''} selected`;
                    if (nameList.length > 60) {
                        preview.innerHTML += `<br><small>${nameList.substring(0, 57)}...</small>`;
                    } else {
                        preview.innerHTML += `<br><small>${nameList}</small>`;
                    }
                    if (fileUploadNote) {
                        fileUploadNote.style.display = 'none';
                    }
                }

                if (invalidFiles.length > 0) {
                    preview.innerHTML += `<br><span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${invalidFiles.length} unsupported file${invalidFiles.length > 1 ? 's' : ''}</span>`;
                }
            }
        });
    }

    const ticketForm = document.querySelector('form[method="POST"]');
    if (ticketForm) {
        ticketForm.addEventListener('submit', function(e) {
            const requiredFields = ticketForm.querySelectorAll('[required]');
            let isValid = true;
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            if (!isValid) {
                e.preventDefault();
                showToast('Please fill all required fields', 'error');
            }
        });
    }

    setTimeout(function() {
        document.querySelectorAll('.alert.show').forEach(function(alert) {
            alert.classList.remove('show');
        });
    }, 5000);

    const cards = document.querySelectorAll('.ticket-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 120);
    });

    document.querySelectorAll('.badge').forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });

    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };
});
