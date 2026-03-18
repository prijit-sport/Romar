document.addEventListener('DOMContentLoaded', function () {
    const createModal = document.getElementById('createModal');
    const editModal = document.getElementById('editModal');
    const deleteForm = document.getElementById('deleteForm');

    function toggleModal(modalEl, open) {
        if (!modalEl) return;
        modalEl.classList.toggle('show', open);
        document.body.classList.toggle('modal-open', open);
    }

    window.openCreateModal = function () {
        toggleModal(createModal, true);
    };

    window.closeCreateModal = function () {
        toggleModal(createModal, false);
    };

    window.openEditModal = function () {
        toggleModal(editModal, true);
    };

    window.closeEditModal = function () {
        toggleModal(editModal, false);
    };

    window.editUser = function (user) {
        if (!user) return;
        const fields = {
            edit_user_id: user.user_id ?? '',
            edit_full_name: user.full_name ?? '',
            edit_email: user.email ?? '',
            edit_phone: user.phone ?? '',
            edit_department: user.department ?? '',
            edit_position: user.position ?? '',
            edit_role: user.role ?? '',
            edit_status: user.status ?? 'inactive',
        };
        Object.entries(fields).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.value = value;
        });
        toggleModal(editModal, true);
    };

    window.deleteUser = function (userId, name) {
        if (!userId) return;
        const confirmText = name
            ? `ต้องการลบผู้ใช้งาน "${name}" ใช่หรือไม่?`
            : 'ต้องการลบผู้ใช้งานใช่หรือไม่?';
        if (confirm(confirmText)) {
            document.getElementById('delete_user_id').value = userId;
            deleteForm.submit();
        }
    };

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (createModal?.classList.contains('show')) toggleModal(createModal, false);
            if (editModal?.classList.contains('show')) toggleModal(editModal, false);
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.classList?.contains('modal')) {
            toggleModal(event.target, false);
        }
    });

    const forms = [createModal?.querySelector('form'), editModal?.querySelector('form')]
        .filter(Boolean);
    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const requiredFields = form.querySelectorAll('[required]');
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
                event.preventDefault();
                showToast('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วนก่อนบันทึก', 'error');
            }
        });
    });

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>${message}`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, 3000);
    }
});
