document.addEventListener('DOMContentLoaded', function () {
    const mainEl = document.querySelector('.main-content');
    const csrfToken = mainEl?.dataset?.csrfToken || '';
    const viewModal = document.getElementById('viewModal');
    const articleModal = document.getElementById('articleModal');
    const viewBody = document.getElementById('viewBody');
    const viewTitle = document.getElementById('viewTitle');
    const modalTitle = document.getElementById('modalTitle');
    const articleForm = document.getElementById('articleForm');
    const deleteForm = document.getElementById('deleteForm');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderMultiline(value) {
        return escapeHtml(value).replace(/\r?\n/g, '<br>');
    }

    function getEventCoordinates(event) {
        const scrollX = window.scrollX || window.pageXOffset;
        const scrollY = window.scrollY || window.pageYOffset;
        if (!event) {
            return {
                x: scrollX + window.innerWidth / 2,
                y: scrollY + window.innerHeight / 2
            };
        }
        const x = typeof event.pageX === 'number' ? event.pageX : (event.clientX + scrollX);
        const y = typeof event.pageY === 'number' ? event.pageY : (event.clientY + scrollY);
        return { x, y };
    }

    function positionFloatingModal(modalEl, coords) {
        if (!modalEl || !coords || !modalEl.classList.contains('floating-modal') || modalEl.id === 'viewModal') return;
        const contentEl = modalEl.querySelector('.modal-content');
        if (!contentEl) return;
        requestAnimationFrame(() => {
            const rect = contentEl.getBoundingClientRect();
            const modalWidth = rect.width;
            const modalHeight = rect.height;
            const scrollX = window.scrollX || window.pageXOffset;
            const scrollY = window.scrollY || window.pageYOffset;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const margin = 12;
            const fallbackX = scrollX + viewportWidth / 2;
            const fallbackY = scrollY + viewportHeight / 2;
            let left = Math.round((coords.x ?? fallbackX) + margin);
            let top = Math.round((coords.y ?? fallbackY) + margin);
            const minLeft = scrollX + margin;
            const maxLeft = scrollX + viewportWidth - modalWidth - margin;
            const minTop = scrollY + margin;
            const maxTop = scrollY + viewportHeight - modalHeight - margin;
            const boundedLeft = Math.min(Math.max(left, minLeft), Math.max(minLeft, maxLeft));
            const boundedTop = Math.min(Math.max(top, minTop), Math.max(minTop, maxTop));
            modalEl.style.position = 'absolute';
            modalEl.style.left = `${boundedLeft}px`;
            modalEl.style.top = `${boundedTop}px`;
        });
    }

    function toggleModal(modalEl, open, options = {}) {
        if (!modalEl) return;
        modalEl.classList.toggle('active', open);
        const shouldLockBody = options.lockBody !== false;
        if (shouldLockBody) {
            document.body.classList.toggle('modal-open', open);
        }
        if (!open) {
            modalEl.style.removeProperty('left');
            modalEl.style.removeProperty('top');
            modalEl.style.removeProperty('position');
        }
        if (open && options.coords) {
            positionFloatingModal(modalEl, options.coords);
        }
    }

    function updateViewModal(article) {
        if (!article) {
            viewBody.innerHTML = '';
            return;
        }

        viewTitle.textContent = article.title || 'บทความ';
        let html = '<div class="view-meta">';
        html += `<span><i class="fas fa-folder"></i> ${escapeHtml(article.category_name || 'ไม่ระบุหมวดหมู่')}</span>`;
        html += `<span><i class="fas fa-user"></i> ${escapeHtml(article.author_name || 'ไม่ระบุผู้เขียน')}</span>`;
        html += `<span><i class="fas fa-calendar"></i> ${escapeHtml(new Date(article.created_at).toLocaleDateString('th-TH'))}</span>`;
        html += `<span><i class="fas fa-eye"></i> ${Number(article.views || 0).toLocaleString()} วิว</span>`;
        html += `<span><i class="fas fa-thumbs-up"></i> ${Number(article.helpful_count || 0).toLocaleString()} ถูกใจ</span>`;
        html += '</div>';
    html += `<div class="view-content">${renderMultiline(article.content || '')}</div>`;

        const tags = (article.tags || '').split(',').map(tag => tag.trim()).filter(Boolean);
        if (tags.length) {
            html += '<div class="view-tags">';
            html += '<strong>แท็ก:</strong> ';
            html += tags.map(tag => `<span class="article-tag">#${escapeHtml(tag)}</span>`).join(' ');
            html += '</div>';
        }

        viewBody.innerHTML = html;
    }

window.viewArticle = function (maybeEventOrArticle, maybeArticle) {
        const article = maybeArticle ?? maybeEventOrArticle;
        const event = maybeArticle ? maybeEventOrArticle : undefined;
        if (article?.kb_id) {
            fetch('?view=' + article.kb_id, { cache: 'no-cache' });
        }
        updateViewModal(article);
        toggleModal(viewModal, true, { lockBody: true });
    };

    window.closeViewModal = function () {
        toggleModal(viewModal, false, { lockBody: false });
    };

    window.openCreateModal = function () {
        modalTitle.innerHTML = '<i class="fas fa-plus-circle"></i> เพิ่มบทความใหม่';
        document.getElementById('formAction').value = 'create';
        document.getElementById('kb_id').value = '';
        articleForm.reset();
        toggleModal(articleModal, true);
    };

    window.editArticle = function (article) {
        modalTitle.innerHTML = '<i class="fas fa-edit"></i> แก้ไขบทความ';
        document.getElementById('formAction').value = 'update';
        document.getElementById('kb_id').value = article.kb_id ?? '';
        document.getElementById('title').value = article.title ?? '';
        document.getElementById('category_id').value = article.category_id ?? '';
        document.getElementById('content').value = article.content ?? '';
        document.getElementById('tags').value = article.tags ?? '';
        toggleModal(articleModal, true);
    };

    window.closeModal = function () {
        toggleModal(articleModal, false);
    };

    window.deleteArticle = function (kbId, title) {
        if (!kbId) return;
        if (confirm(`ต้องการลบบทความ "${title}" ใช่หรือไม่?`)) {
            document.getElementById('delete_kb_id').value = kbId;
            deleteForm.submit();
        }
    };

    window.markHelpful = function (kbId) {
        if (!kbId || !csrfToken) return;
        const payload = new URLSearchParams({
            action: 'helpful',
            kb_id: kbId,
            csrf_token: csrfToken
        });

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload.toString()
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('ขอบคุณสำหรับคำติชม!', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            });
    };

    document.addEventListener('click', function (event) {
        if (event.target.classList.contains('modal')) {
            toggleModal(event.target, false, { lockBody: true });
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (viewModal.classList.contains('active')) toggleModal(viewModal, false, { lockBody: true });
            if (articleModal.classList.contains('active')) toggleModal(articleModal, false);
        }
    });

    if (articleForm) {
        articleForm.addEventListener('submit', function (event) {
            const requiredFields = articleForm.querySelectorAll('[required]');
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
                showToast('เธเธฃเธธเธ“เธฒเธเธฃเธญเธเธเนเธญเธกเธนเธฅเธ—เธตเนเธเธณเน€เธเนเธเธเธฃเธเธ–เนเธงเธ', 'error');
            }
        });
    }

    setTimeout(() => {
        document.querySelectorAll('.alert.show').forEach(alert => alert.classList.remove('show'));
    }, 4000);

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>${message}`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        }, 3500);
    }
});




