// Assets Detail JS - CSP Compliant
// Nonce-enabled external script for modules/assetsdetail.php

const ALL_TABS = ["info", "os", "hardware", "network", "purchase", "repair", "borrow", "transfer", "depreciation", "tickets"];

function showTab(tabId) {
    ALL_TABS.forEach(id => {
        const tab = document.getElementById("tab-" + id);
        const link = document.getElementById("subnav_" + id);
        if (tab) tab.style.display = id === tabId ? "block" : "none";
        if (link) {
            link.classList.toggle("active", id === tabId);
            if (id === tabId) {
                link.style.background = 'linear-gradient(135deg,#10ce30,#276749)';
                link.style.color = 'white';
            } else {
                link.style.background = '';
                link.style.color = '#4a5568';
            }
        }
    });
    localStorage.setItem("assetsdetail_activeTab", tabId);
}

function returnAsset(borrowId) {
    document.getElementById("return_borrow_id").value = borrowId;
    document.getElementById("returnModal").classList.add("show");
}

window.onclick = (e) => {
    if (e.target.classList.contains("modal")) {
        e.target.classList.remove("show");
    }
};

window.addEventListener("load", () => {
    const activeTab = localStorage.getItem("assetsdetail_activeTab") || "repair";
    showTab(activeTab);
});

function toggleAssets(el) {
    el.classList.toggle('open');
    const submenu = el.nextElementSibling;
    submenu.classList.toggle('open');
}

