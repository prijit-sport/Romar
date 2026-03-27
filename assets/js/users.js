document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('createModal');
    const editModal = document.getElementById('editModal');
    const deleteForm = document.getElementById('deleteForm');

    const modalMap = {
        createModal,
        editModal,
    };

    function setBodyScroll(blocked) {
        document.body.style.overflow = blocked ? 'hidden' : '';
    }

    function getActiveModal() {
        return document.querySelector('.modal.active');
    }

    function openModal(modalId) {
        const modal = modalMap[modalId] || document.getElementById(modalId);
        if (!modal) return;

        const activeModal = getActiveModal();
        if (activeModal && activeModal !== modal) {
            activeModal.classList.remove('active');
        }

        modal.classList.add('active');
        setBodyScroll(true);
    }

    function closeModal(modalId) {
        const modal = modalId ? (modalMap[modalId] || document.getElementById(modalId)) : getActiveModal();
        if (!modal) return;

        modal.classList.remove('active');

        if (!getActiveModal()) {
            setBodyScroll(false);
        }
    }

    function resetCreateForm() {
        const form = createModal ? createModal.querySelector('form') : null;
        if (!form) return;

        form.reset();
        form.querySelectorAll('.is-invalid').forEach(function (field) {
            field.classList.remove('is-invalid');
        });
    }

    function populateEditForm(user) {
        if (!user || !editModal) return false;

        const fields = {
            edit_user_id: user.user_id ?? '',
            edit_username: user.username ?? '',
            edit_full_name: user.full_name ?? '',
            edit_email: user.email ?? '',
            edit_phone: user.phone ?? '',
            edit_department: user.department ?? '',
            edit_position: user.position ?? '',
            edit_role: user.role ?? 'user',
            edit_status: user.status ?? 'active',
        };

        Object.entries(fields).forEach(function ([fieldId, value]) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = value ?? '';
                field.classList.remove('is-invalid');
            }
        });

        return true;
    }

    function validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(function (field) {
            const value = typeof field.value === 'string' ? field.value.trim() : field.value;

            if (!value) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return isValid;
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';

        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="fas fa-' + icon + '"></i>' + message;

        document.body.appendChild(toast);

        setTimeout(function () {
            toast.classList.add('show');
        }, 100);

        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 4000);
    }

    window.deleteUser = function (userId, name) {
        if (!userId || !deleteForm) return;

        const confirmText = name
            ? 'ต้องการลบผู้ใช้งาน "' + name + '" ใช่หรือไม่?'
            : 'ต้องการลบผู้ใช้งานใช่หรือไม่?';

        if (!window.confirm(confirmText)) return;

        const deleteUserIdInput = document.getElementById('delete_user_id');
        if (deleteUserIdInput) {
            deleteUserIdInput.value = userId;
        }

        deleteForm.submit();
    };

    document.addEventListener('click', function (event) {
        const createTrigger = event.target.closest('[data-action="open-create-modal"]');
        if (createTrigger) {
            event.preventDefault();
            resetCreateForm();
            openModal('createModal');
            return;
        }

        const editTrigger = event.target.closest('[data-action="open-edit-modal"]');
        if (editTrigger) {
            event.preventDefault();

            const userData = editTrigger.getAttribute('data-user');
            if (!userData) {
                showToast('ไม่พบข้อมูลผู้ใช้งานสำหรับการแก้ไข', 'error');
                return;
            }

            try {
                const user = JSON.parse(userData);
                if (populateEditForm(user)) {
                    openModal('editModal');
                }
            } catch (error) {
                console.error('Unable to parse user data for edit modal:', error);
                showToast('ไม่สามารถโหลดข้อมูลผู้ใช้งานได้', 'error');
            }

            return;
        }

        const closeTrigger = event.target.closest('[data-close-modal]');
        if (closeTrigger) {
            event.preventDefault();
            closeModal(closeTrigger.getAttribute('data-close-modal'));
            return;
        }

        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    document.querySelectorAll('#createModal form, #editModal form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!validateForm(form)) {
                event.preventDefault();
                showToast('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วนก่อนบันทึก', 'error');
            }
        });
    });

    document.querySelectorAll('#createModal [required], #editModal [required]').forEach(function (field) {
        field.addEventListener('input', function () {
            if (field.value.trim()) {
                field.classList.remove('is-invalid');
            }
        });

        field.addEventListener('change', function () {
            if (field.value.trim()) {
                field.classList.remove('is-invalid');
            }
        });
    });
});
