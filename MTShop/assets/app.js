import './stimulus_bootstrap.js';
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('productSearch');
    const sortSelect = document.getElementById('productSort');
    const productsContainer = document.getElementById('productsContainer');

    if (!searchInput || !sortSelect || !productsContainer) {
        return;
    }

    const filterProducts = () => {
        const search = searchInput.value.trim().toLowerCase();
        const products = [...productsContainer.querySelectorAll('.product-card')];

        products.forEach(product => {
            const name = product.dataset.name;

            if (name.includes(search)) {
                product.style.display = '';
            } else {
                product.style.display = 'none';
            }
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

    searchInput.addEventListener('input', filterProducts);

    searchInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            filterProducts();
        }
    });

    sortSelect.addEventListener('change', () => {
        sortProducts();
        filterProducts();
    });

    sortProducts();
    filterProducts();
});