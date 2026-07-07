import './bootstrap';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Inicializar todos los dropdowns
document.addEventListener('DOMContentLoaded', () => {
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    })
});