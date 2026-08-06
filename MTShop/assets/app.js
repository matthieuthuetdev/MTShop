import './stimulus_bootstrap.js';
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
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
                case 'az':
                default:
                    return a.dataset.name.localeCompare(b.dataset.name);
            }
        });

        products.forEach(product => {
            productsContainer.appendChild(product);
        });
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

        modalForm.action = card.dataset.addToCartUrl || '#';
        modalCartToken.value = csrfToken;

        productModal.show();
    };

    productsContainer.addEventListener('click', event => {
        if (event.target.closest('[data-no-modal="true"]')) {
            return;
        }

        const card = event.target.closest('.product-card');
        if (card) {
            openProductModal(card);
        }
    });

    productsContainer.addEventListener('keydown', event => {
        const card = event.target.closest('.product-card');
        if (!card || event.target !== card) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openProductModal(card);
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
});