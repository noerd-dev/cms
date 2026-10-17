import '../css/cms-editor.css';
import { documentEditor } from './document/document-editor.js';

/*
 | The CMS document editor bundle. Loaded by <x-cms::editor-assets> through
 | Livewire's @assets — possibly after Alpine started (a detail opened as a
 | modal), so the component is registered right away when Alpine exists and
 | on alpine:init otherwise.
 */
function register() {
    if (!window.Alpine || window.__cmsDocumentEditorRegistered) {
        return;
    }
    window.__cmsDocumentEditorRegistered = true;
    window.Alpine.data('cmsDocumentEditor', documentEditor);
}

register();
document.addEventListener('alpine:init', register);
