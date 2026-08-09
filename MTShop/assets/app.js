import './stimulus_bootstrap.js';
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    initProductPage();
    initCartActions();
});

function initProductPage() {
    const searchInput = document.getElementById('productSearch');
    const sortSelect = document.getElementById('productSort');
    const productsContainer = document.getElementById('productsContainer');

    if (!productsContainer) {
        return;
    }

    const allProducts = [...productsContainer.querySelectorAll('.product-card')];

    const filterProducts = () => {
        const search = searchInput.value.trim().toLowerCase();
        allProducts.forEach(product => {
            const name = (product.dataset.name || '').toLowerCase();
            product.style.display = name.includes(search) ? '' : 'none';
        });
    };

    const sortProducts = () => {
        const products = [...productsContainer.querySelectorAll('.product-card')];
        products.sort((a, b) => {
            switch (sortSelect.value) {
                case 'za':
                    return b.dataset.name.localeCompare(a.dataset.name);
                case 'priceAsc':
                    return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                case 'priceDesc':
                    return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                case 'bestSellers':
                    return (parseInt(b.dataset.orderCount || '0', 10) || 0) - (parseInt(a.dataset.orderCount || '0', 10) || 0);
                case 'az':
                default:
                    return a.dataset.name.localeCompare(b.dataset.name);
            }
        });
        products.forEach(product => productsContainer.appendChild(product));
    };

    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                filterProducts();
            }
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            sortProducts();
            filterProducts();
        });
    }

    if (searchInput && sortSelect) {
        sortProducts();
        filterProducts();
    }

    const productModalElement = document.getElementById('productModal');
    const productModal = productModalElement ? new bootstrap.Modal(productModalElement) : null;
    const modalTitle = document.getElementById('modalTitle');
    const modalProductDescription = document.getElementById('modalProductDescription');
    const modalProductPriceContainer = document.getElementById('modalProductPriceContainer');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalForm = document.getElementById('modalAddToCartForm');
    const modalCartToken = document.getElementById('modalCartToken');

    let lastFocusedElement = null;

    const openProductModal = (card) => {
        if (!productModal || !modalTitle || !modalProductDescription || !modalProductPriceContainer || !modalProductImage || !modalForm || !modalCartToken) {
            return;
        }

        lastFocusedElement = document.activeElement;

        const name = card.dataset.name || '';
        const description = card.dataset.description || '';
        const price = parseFloat(card.dataset.price) || 0;
        const discountedPrice = parseFloat(card.dataset.discountedPrice) || price;
        const promotion = parseInt(card.dataset.promotion, 10) || 0;
        const csrfToken = card.dataset.csrfToken || '';
        const imageUrl = card.dataset.imageUrl || '';

        modalTitle.textContent = name;
        modalProductDescription.textContent = description;

        if (promotion > 0) {
            modalProductPriceContainer.innerHTML = `
                <div class="fw-semibold text-decoration-line-through text-secondary">
                    ${price.toFixed(0)} €
                </div>
                <strong class="fs-4">${discountedPrice.toFixed(0)} €</strong>
            `;
        } else {
            modalProductPriceContainer.innerHTML = `<strong class="fs-4">${price.toFixed(0)} €</strong>`;
        }

        modalProductImage.innerHTML = imageUrl
            ? `<img src="${imageUrl}" alt="${name}" class="img-fluid rounded-4 w-100" style="height:420px; object-fit:cover;">`
            : '<div class="bg-primary rounded-4" style="height:420px;"></div>';

        modalForm.dataset.url = card.dataset.addToCartUrl || '#';
        modalForm.dataset.decreaseUrl = card.dataset.decreaseUrl || '#';
        modalForm.dataset.productId = card.dataset.id || card.dataset.productId || '';
        modalForm.dataset.csrfToken = csrfToken;

        const modalAddBtn = document.getElementById('modalAddToCartButton');
        if (modalAddBtn) {
            modalAddBtn.dataset.productId = card.dataset.id || card.dataset.productId || '';
            modalAddBtn.dataset.csrfToken = csrfToken || '';
            modalAddBtn.dataset.url = card.dataset.addToCartUrl || '#';
        }

        const quantity = parseInt(card.dataset.quantity || '0', 10) || 0;
        if (window.mtshopUpdateCartButtons) {
            window.mtshopUpdateCartButtons(card.dataset.id || card.dataset.productId || '', quantity);
        }

        productModal.show();
    };

    productsContainer.addEventListener('click', event => {
        const trigger = event.target.closest('.js-product-modal-trigger');
        if (!trigger) {
            return;
        }

        const card = trigger.closest('.product-card');
        if (card) {
            openProductModal(card);
        }
    });

    productsContainer.addEventListener('keydown', event => {
        const trigger = event.target.closest('.js-product-modal-trigger');
        if (!trigger || event.target !== trigger) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            const card = trigger.closest('.product-card');
            if (card) {
                openProductModal(card);
            }
        }
    });

    const noModalTriggers = productsContainer.querySelectorAll('[data-no-modal="true"]');
    noModalTriggers.forEach(element => {
        element.addEventListener('click', event => {
            event.stopPropagation();
        });
    });

    if (productModalElement) {
        productModalElement.addEventListener('shown.bs.modal', () => {
            const focusTarget = modalForm.querySelector('button, input, textarea, select, [tabindex]:not([tabindex="-1"])');
            if (focusTarget) {
                focusTarget.focus();
            }
        });

        productModalElement.addEventListener('hidden.bs.modal', () => {
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        });
    }

    const deleteModalElement = document.getElementById('deleteModal');
    if (deleteModalElement) {
        deleteModalElement.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }

            const productId = button.getAttribute('data-product-id');
            const productName = button.getAttribute('data-product-name');
            const deleteToken = button.getAttribute('data-delete-token');
            const deleteForm = deleteModalElement.querySelector('#deleteProductForm');
            const deleteProductName = deleteModalElement.querySelector('#deleteProductName');
            const deleteProductToken = deleteModalElement.querySelector('#deleteProductToken');

            if (deleteForm && productId) {
                deleteForm.action = `/seller/product/${productId}/delete`;
            }

            if (deleteProductName && productName) {
                deleteProductName.textContent = productName;
            }

            if (deleteProductToken && deleteToken) {
                deleteProductToken.value = deleteToken;
            }
        });
    }
}

function initCartActions() {
    const buildHiddenToken = (token) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token;
        return input;
    };

    const renderAddButton = (container, productId, token, url) => {
        container.innerHTML = '';
        if (token) {
            container.appendChild(buildHiddenToken(token));
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary fw-semibold text-white js-cart-add';
        button.dataset.productId = productId;
        button.dataset.url = url || container.dataset.url || '';
        if (token) {
            button.dataset.csrfToken = token;
        }
        button.textContent = 'Ajouter au panier';

        container.appendChild(button);
    };

    const renderQuantityControls = (container, productId, token, url, decreaseUrl, quantity) => {
        container.innerHTML = '';
        if (token) {
            container.appendChild(buildHiddenToken(token));
        }

        const controls = document.createElement('div');
        controls.className = 'd-flex align-items-center gap-2';

        const decButton = document.createElement('button');
        decButton.type = 'button';
        decButton.className = 'btn btn-outline-secondary js-cart-decrease';
        decButton.dataset.productId = productId;
        if (decreaseUrl || container.dataset.decreaseUrl) {
            decButton.dataset.url = decreaseUrl || container.dataset.decreaseUrl;
        }
        if (token) {
            decButton.dataset.csrfToken = token;
        }
        decButton.textContent = '−';

        const qtySpan = document.createElement('span');
        qtySpan.className = 'fw-semibold js-cart-qty';
        qtySpan.dataset.productId = productId;
        qtySpan.textContent = quantity;

        const incButton = document.createElement('button');
        incButton.type = 'button';
        incButton.className = 'btn btn-primary js-cart-increase';
        incButton.dataset.productId = productId;
        if (url || container.dataset.url) {
            incButton.dataset.url = url || container.dataset.url;
        }
        if (token) {
            incButton.dataset.csrfToken = token;
        }
        incButton.textContent = '+';

        controls.appendChild(decButton);
        controls.appendChild(qtySpan);
        controls.appendChild(incButton);
        container.appendChild(controls);
    };

    const updateButtonGroup = (productId, quantity) => {
        const containers = document.querySelectorAll(`.js-cart-button-container[data-product-id="${productId}"]`);
        containers.forEach(container => {
            if (container.dataset.cartPage === 'true') {
                return;
            }
            const tokenValue = container.querySelector('input[name="_token"]')?.value || container.dataset.csrfToken || '';
            const addUrl = container.dataset.url || '';
            const decreaseUrl = container.dataset.decreaseUrl || '';
            if (quantity > 0) {
                renderQuantityControls(container, productId, tokenValue, addUrl, decreaseUrl, quantity);
            } else {
                renderAddButton(container, productId, tokenValue, addUrl);
            }
        });

        const modalButton = document.getElementById('modalAddToCartButton');
        const modalForm = modalButton?.closest('.js-cart-form');
        if (modalForm && modalButton?.dataset.productId === productId) {
            const tokenValue = modalForm.dataset.csrfToken || modalButton.dataset.csrfToken || '';
            const addUrl = modalForm.dataset.url || modalButton.dataset.url || '';
            const decreaseUrl = modalForm.dataset.decreaseUrl || '';
            if (quantity > 0) {
                renderQuantityControls(modalForm, productId, tokenValue, addUrl, decreaseUrl, quantity);
            } else {
                renderAddButton(modalForm, productId, tokenValue, addUrl);
            }
        }
    };

    window.mtshopUpdateCartButtons = updateButtonGroup;

    const setAddButton = (productId) => {
        updateButtonGroup(productId, 0);
    };

    const updateCartPageQuantity = (productId, quantity) => {
        const qtySpans = document.querySelectorAll(`.js-cart-qty[data-product-id="${productId}"]`);
        qtySpans.forEach(span => {
            span.textContent = quantity;
        });

        const article = document.querySelector(`article[data-product-id="${productId}"]`);
        if (article) {
            const unitPrice = parseFloat(article.dataset.linePrice || '0');
            const lineTotal = unitPrice * quantity;
            const lineTotalElements = article.querySelectorAll('.js-cart-line-total');
            lineTotalElements.forEach(el => {
                el.textContent = lineTotal.toFixed(2).replace('.', ',');
            });
        }

        if (quantity <= 0) {
            if (article) {
                article.remove();
            }
        }
    };

    const updateCartTotals = (totalItems, totalAmount) => {
        const totalItemsElement = document.getElementById('cartTotalItems');
        const totalAmountElement = document.getElementById('cartTotalAmount');
        if (totalItemsElement && typeof totalItems === 'number') {
            totalItemsElement.textContent = totalItems;
        }
        if (totalAmountElement && typeof totalAmount === 'number') {
            totalAmountElement.textContent = totalAmount.toFixed(2).replace('.', ',');
        }
    };

    const updateCartTotalsByDelta = (deltaItems, productId, deltaAmount = 0) => {
        const totalItemsElement = document.getElementById('cartTotalItems');
        const totalAmountElement = document.getElementById('cartTotalAmount');
        if (totalItemsElement) {
            const currentItems = parseInt(totalItemsElement.textContent.replace(/\D/g, '') || '0', 10);
            totalItemsElement.textContent = Math.max(0, currentItems + deltaItems);
        }
        if (totalAmountElement) {
            const currentAmount = parseFloat(totalAmountElement.textContent.replace(',', '.').replace(/[^0-9.\-]/g, '') || '0');
            totalAmountElement.textContent = Math.max(0, currentAmount + deltaAmount).toFixed(2).replace('.', ',');
        }
    };

    const fetchCartAction = async (url, token) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `_token=${encodeURIComponent(token)}`,
            redirect: 'manual'
        });

        if (response.status === 302 || response.status === 303 || response.redirected) {
            return null;
        }

        if (!response.ok) {
            return null;
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return null;
        }

        return response.json();
    };

    const handleCartUpdate = async (productId, action, token, url, currentQty = 0) => {
        if (!productId) {
            return;
        }

        let optimisticQty = currentQty;
        if (action === 'add' || action === 'increase') {
            optimisticQty = Math.max(1, currentQty + 1);
        } else if (action === 'decrease') {
            optimisticQty = Math.max(0, currentQty - 1);
        }

        if (optimisticQty > 0) {
            updateButtonGroup(productId, optimisticQty);
        } else {
            setAddButton(productId);
            updateCartPageQuantity(productId, 0);
        }

        if (action === 'increase' || action === 'decrease') {
            updateCartPageQuantity(productId, optimisticQty);
            updateCartTotalsByDelta(action === 'increase' ? 1 : -1, productId);
        }

        const result = await fetchCartAction(url, token);
        if (!result) {
            return;
        }

        if (typeof result.quantity === 'number') {
            if (result.quantity > 0) {
                updateButtonGroup(productId, result.quantity);
                updateCartPageQuantity(productId, result.quantity);
            } else {
                setAddButton(productId);
                updateCartPageQuantity(productId, 0);
            }
        }

        if (typeof result.totalItems === 'number' || typeof result.totalAmount === 'number') {
            updateCartTotals(result.totalItems, result.totalAmount);
        }
    };

    const handleCartRemove = async (productId, token, url) => {
        if (!productId) {
            return;
        }

        const article = document.querySelector(`article[data-product-id="${productId}"]`);
        const unitPrice = article ? parseFloat(article.dataset.linePrice || '0') : 0;
        const currentQty = parseInt(document.querySelector(`.js-cart-qty[data-product-id="${productId}"]`)?.textContent || '0', 10) || 0;

        setAddButton(productId);
        updateCartPageQuantity(productId, 0);
        updateCartTotalsByDelta(-currentQty, productId, unitPrice * currentQty);

        const result = await fetchCartAction(url, token);
        if (!result) {
            return;
        }

        if (typeof result.totalItems === 'number' || typeof result.totalAmount === 'number') {
            updateCartTotals(result.totalItems, result.totalAmount);
        }
    };

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('.js-cart-add');
        if (addButton) {
            event.preventDefault();
            const productId = addButton.dataset.productId;
            const container = addButton.closest('.js-cart-button-container');
            const token = addButton.dataset.csrfToken || container?.dataset.csrfToken || document.getElementById('modalCartToken')?.value || '';
            const actionUrl = addButton.dataset.url || container?.dataset.url || addButton.closest('form')?.getAttribute('action') || `/my-cart/add/${productId}`;
            const currentQty = parseInt(addButton.dataset.quantity || container?.querySelector('.js-cart-qty')?.textContent || '0', 10) || 0;
            handleCartUpdate(productId, 'add', token, actionUrl, currentQty);
            return;
        }

        const incButton = event.target.closest('.js-cart-increase');
        if (incButton) {
            event.preventDefault();
            const productId = incButton.dataset.productId;
            const token = incButton.dataset.csrfToken || incButton.closest('form')?.querySelector('input[name="_token"]')?.value || '';
            const actionUrl = incButton.dataset.url || incButton.closest('form')?.getAttribute('action') || `/my-cart/add/${productId}`;
            const currentQty = parseInt(document.querySelector(`.js-cart-qty[data-product-id="${productId}"]`)?.textContent || '0', 10) || 0;
            handleCartUpdate(productId, 'increase', token, actionUrl, currentQty);
            return;
        }

        const decButton = event.target.closest('.js-cart-decrease');
        if (decButton) {
            event.preventDefault();
            const productId = decButton.dataset.productId;
            const token = decButton.dataset.csrfToken || decButton.closest('.js-cart-button-container')?.dataset.csrfToken || decButton.closest('form')?.querySelector('input[name="_token"]')?.value || '';
            const actionUrl = decButton.dataset.url || decButton.closest('.js-cart-button-container')?.dataset.decreaseUrl || decButton.closest('form')?.getAttribute('action') || '#';
            const currentQty = parseInt(document.querySelector(`.js-cart-qty[data-product-id="${productId}"]`)?.textContent || '0', 10) || 0;
            handleCartUpdate(productId, 'decrease', token, actionUrl, currentQty);
            return;
        }

        const removeButton = event.target.closest('.js-cart-remove');
        if (removeButton) {
            event.preventDefault();
            const productId = removeButton.dataset.productId;
            const token = removeButton.dataset.csrfToken || removeButton.closest('form')?.querySelector('input[name="_token"]')?.value || '';
            const actionUrl = removeButton.dataset.url || removeButton.closest('form')?.getAttribute('action') || '#';
            handleCartRemove(productId, token, actionUrl);
            return;
        }
    });
}
