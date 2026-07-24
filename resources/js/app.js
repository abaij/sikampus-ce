import './bootstrap';
import TomSelect from 'tom-select';

// Diekspos ke window supaya bisa dipanggil langsung dari x-init inline di komponen
// Blade <x-searchable-select> tanpa perlu import per-file.
window.TomSelect = TomSelect;
