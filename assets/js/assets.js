document.addEventListener('DOMContentLoaded', function() {
    const catToggle = document.getElementById('categoryToggle');
    const catPanel = document.getElementById('categoryPanel');
    const catBackdrop = document.getElementById('categoryBackdrop');
    const catCloseBtn = document.querySelector('.category-panel-close');
    const iconEl = catToggle ? catToggle.querySelector('i') : null;
    const hasPanel = catPanel && catBackdrop;

    function updatePanelState(isOpen) {
        if (!hasPanel) {
            return;
        }
        catPanel.classList.toggle('show', isOpen);
        catBackdrop.classList.toggle('show', isOpen);
        catPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        catBackdrop.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('category-panel-open', isOpen);
        if (catToggle) {
            catToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (iconEl) {
                iconEl.classList.toggle('fa-chevron-right', !isOpen);
                iconEl.classList.toggle('fa-chevron-left', isOpen);
            }
        }
    }

    function toggleCategoryPanel(forceState) {
        if (!hasPanel) {
            return;
        }
        const shouldOpen = typeof forceState === 'boolean' ? forceState : !catPanel.classList.contains('show');
        updatePanelState(shouldOpen);
    }

    if (catToggle) {
        catToggle.addEventListener('click', function(event) {
            event.preventDefault();
            toggleCategoryPanel();
        });
    }

    if (catBackdrop) {
        catBackdrop.addEventListener('click', function() {
            toggleCategoryPanel(false);
        });
    }

    if (catCloseBtn) {
        catCloseBtn.addEventListener('click', function() {
            toggleCategoryPanel(false);
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && catPanel && catPanel.classList.contains('show')) {
            toggleCategoryPanel(false);
        }
    });

    document.querySelectorAll('.category-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const cat = this.dataset.cat;
            const url = new URL(window.location);
            url.searchParams.set('cat', cat);
            window.location.href = url.toString();
        });
    });
});

const modalSections = ['basic', 'os', 'hw', 'net', 'purchase'];

function switchModalTab(prefix, tab) {
    modalSections.forEach(section => {
        const el = document.getElementById(`${prefix}_section_${section}`);
        const btn = document.getElementById(`${prefix}_tab_${section}`);
        if (el) {
            el.style.display = section === tab ? '' : 'none';
        }
        if (btn) {
            btn.classList.toggle('active-tab', section === tab);
        }
    });
}

window.openCreateModal = function () {
    switchModalTab('c', 'basic');
    const modal = document.getElementById('createModal');
    if (modal) modal.classList.add('show');
};

window.closeCreateModal = function () {
    const modal = document.getElementById('createModal');
    if (modal) modal.classList.remove('show');
};

window.closeEditModal = function () {
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.remove('show');
};



window.deleteAsset = function (assetId, name) {
    if (!assetId) return;
    if (confirm(`ต้องการลบทรัพย์สิน "${name}" ใช่หรือไม่?`)) {
        const input = document.getElementById('delete_asset_id');
        if (input) input.value = assetId;
        const form = document.getElementById('deleteForm');
        if (form) form.submit();
    }
};

window.switchView = function (mode) {
    const tableDiv = document.getElementById('viewTable');
    const userDiv = document.getElementById('viewUser');
    const btnTable = document.getElementById('btnTableView');
    const btnUser = document.getElementById('btnUserView');
    if (mode === 'table') {
        if (tableDiv) tableDiv.style.display = 'block';
        if (userDiv) userDiv.style.display = 'none';
        if (btnTable) {
            btnTable.style.background = 'linear-gradient(180deg, #10ce30 0%, #000000)';
            btnTable.style.color = 'white';
        }
        if (btnUser) {
            btnUser.style.background = '#e2e8f0';
            btnUser.style.color = '#4a5568';
        }
    } else {
        if (tableDiv) tableDiv.style.display = 'none';
        if (userDiv) userDiv.style.display = 'block';
        if (btnUser) {
            btnUser.style.background = 'linear-gradient(180deg, #10ce30 0%, #000000)';
            btnUser.style.color = 'white';
        }
        if (btnTable) {
            btnTable.style.background = '#e2e8f0';
            btnTable.style.color = '#4a5568';
        }
    }
};

window.toggleWarrantyAlert = function () {
    const body = document.getElementById('warrantyAlertBody');
    const icon = document.getElementById('warrantyToggleIcon');
    if (!body || !icon) {
        return;
    }
    if (body.style.display === 'none' || !body.style.display) {
        body.style.display = 'block';
        icon.innerHTML = '<i class="fas fa-chevron-up"></i> ซ่อน';
    } else {
        body.style.display = 'none';
        icon.innerHTML = '<i class="fas fa-chevron-down"></i> แสดง';
    }
};

document.addEventListener('click', function(event) {
    if (event.target.classList?.contains('modal') && !event.target.classList.contains('bootstrap-modal')) {
        event.target.classList.remove('show');
    }
});
