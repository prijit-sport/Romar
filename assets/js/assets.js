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

window.editAsset = function (asset) {
    if (!asset) return;
    switchModalTab('e', 'basic');
    const setValue = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.value = value ?? '';
    };

    setValue('edit_asset_id', asset.asset_id || '');
    setValue('edit_asset_tag', asset.asset_tag);
    setValue('edit_inventory_number', asset.inventory_number);
    setValue('edit_asset_name', asset.asset_name);
    setValue('edit_asset_type', asset.asset_type);
    setValue('edit_brand', asset.brand);
    setValue('edit_model', asset.model);
    setValue('edit_serial_number', asset.serial_number);
    setValue('edit_status', asset.status || 'active');
    setValue('edit_location', asset.location);
    setValue('edit_department', asset.department);
    setValue('edit_assigned_to', asset.assigned_to);
    setValue('edit_tech_in_charge', asset.tech_in_charge);
    setValue('edit_alternate_user', asset.alternate_user);
    setValue('edit_asset_group', asset.asset_group);
    setValue('edit_condition', asset.condition_status || 'good');
    setValue('edit_last_inventory_date', asset.last_inventory_date);
    setValue('edit_notes', asset.notes);

    setValue('edit_os_name', asset.os_name);
    setValue('edit_os_version', asset.os_version);
    setValue('edit_os_architecture', asset.os_architecture);
    setValue('edit_os_service_pack', asset.os_service_pack);
    setValue('edit_os_product_key', asset.os_product_key);

    setValue('edit_cpu', asset.cpu);
    setValue('edit_cpu_cores', asset.cpu_cores);
    setValue('edit_ram_gb', asset.ram_gb);
    setValue('edit_storage', asset.storage);
    setValue('edit_gpu', asset.gpu);
    setValue('edit_monitor', asset.monitor);

    setValue('edit_ip_address', asset.ip_address);
    setValue('edit_mac_address', asset.mac_address);
    setValue('edit_network_domain', asset.network_domain);
    setValue('edit_gateway', asset.gateway);
    setValue('edit_dns_server', asset.dns_server);

    setValue('edit_purchase_date', asset.purchase_date);
    setValue('edit_warranty_expiry', asset.warranty_expiry);
    setValue('edit_purchase_price', asset.purchase_price);
    setValue('edit_salvage_value', asset.salvage_value ?? 0);
    setValue('edit_useful_life', asset.useful_life_years ?? 5);
    setValue('edit_supplier', asset.supplier);

    const modal = document.getElementById('editModal');
    if (modal) modal.classList.add('show');
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
    if (event.target.classList?.contains('modal')) {
        event.target.classList.remove('show');
    }
});

