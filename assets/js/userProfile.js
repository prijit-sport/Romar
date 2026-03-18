document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = Array.from(document.querySelectorAll('.tab-btn[data-tab]'));
    const tabPanels = Array.from(document.querySelectorAll('.tab-panel'));
    const editButton = document.querySelector('[data-profile]');
    const editModal = document.getElementById('editModal');
    const closeTriggers = Array.from(document.querySelectorAll('[data-edit-action="close-edit-modal"]'));

    function switchTab(name, activeBtn) {
        if (!name) return;
        tabPanels.forEach(panel => {
            panel.classList.toggle('active', panel.id === `tab-${name}`);
        });
        tabButtons.forEach(btn => {
            btn.classList.toggle('active', btn === activeBtn);
        });
    }

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            switchTab(this.dataset.tab, this);
        });
    });

    const initialActiveBtn = tabButtons.find(btn => btn.classList.contains('active'));
    if (initialActiveBtn) {
        switchTab(initialActiveBtn.dataset.tab, initialActiveBtn);
    } else if (tabButtons.length) {
        switchTab(tabButtons[0].dataset.tab, tabButtons[0]);
    }

    function showEditModal(user) {
        if (!editModal) return;
        if (user) {
            const mappings = {
                edit_user_id: user.user_id,
                edit_full_name: user.full_name,
                edit_email: user.email,
                edit_phone: user.phone,
                edit_department: user.department,
                edit_position: user.position,
                edit_role: user.role,
                edit_status: user.status || 'inactive'
            };
            Object.entries(mappings).forEach(([id, value]) => {
                const el = document.getElementById(id);
                if (el) {
                    el.value = value ?? '';
                }
            });
        }
        editModal.style.display = 'flex';
    }

    function closeEditModal() {
        if (!editModal) return;
        editModal.style.display = 'none';
    }

    if (editButton) {
        editButton.addEventListener('click', function () {
            const payload = this.dataset.profile;
            let user = null;
            if (payload) {
                try {
                    user = JSON.parse(payload);
                } catch (error) {
                    console.error('Failed to parse profile payload', error);
                }
            }
            showEditModal(user);
        });
    }

    closeTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            closeEditModal();
        });
    });

    document.addEventListener('click', event => {
        if (event.target === editModal) {
            closeEditModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeEditModal();
        }
    });
});
