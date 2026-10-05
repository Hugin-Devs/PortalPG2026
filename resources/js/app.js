import './bootstrap';
import * as bootstrap from 'bootstrap';
import '@fontsource/georama/700.css';
import '@fontsource/instrument-sans/400.css';
import '@fontsource/instrument-sans/500.css';
import '@fontsource/instrument-sans/600.css';
import '@fontsource/instrument-sans/700.css';
import '@fortawesome/fontawesome-free/css/all.min.css';

window.bootstrap = bootstrap;

// Inicializar todos los dropdowns
document.addEventListener('DOMContentLoaded', () => {
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    })
});