import { createField, isTranslatable } from './fields.js';
import { newUid } from './normalize.js';

/**
 * Node view of an element inside the document: a card with a header (drag
 * handle, title, duplicate, delete) and the element's fields as an inline
 * form. Field changes are written into the node's `data` attribute, so undo,
 * copy and the draft sent to the server see them like any other edit.
 */
export class ElementBlockView {
    constructor({ node, getPos, editor, host }) {
        this.node = node;
        this.getPos = getPos;
        this.editor = editor;
        this.host = host;
        this.fields = [];

        this.dom = document.createElement('div');
        this.dom.className = 'cms-doc-element';
        this.dom.contentEditable = 'false';

        const header = document.createElement('div');
        header.className = 'cms-doc-element-header';

        const handle = document.createElement('span');
        handle.className = 'cms-doc-handle';
        handle.draggable = true;
        handle.dataset.dragHandle = '';
        handle.title = host.strings.drag;
        handle.textContent = '⠿';

        this.title = document.createElement('span');
        this.title.className = 'cms-doc-element-title';

        const actions = document.createElement('span');
        actions.className = 'cms-doc-element-actions';
        actions.append(
            this.button(host.strings.duplicate, '⧉', () => this.duplicate()),
            this.button(host.strings.delete, '✕', () => this.remove(), 'cms-doc-icon-button--danger'),
        );

        header.append(handle, this.title, actions);

        this.body = document.createElement('div');
        this.body.className = 'cms-doc-grid';

        this.dom.append(header, this.body);
        this.render();
    }

    button(title, label, onClick, extraClass = '') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cms-doc-icon-button ' + extraClass;
        button.title = title;
        button.setAttribute('aria-label', title);
        button.textContent = label;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            onClick();
        });
        return button;
    }

    render() {
        this.fields.forEach((field) => field.destroy());
        this.fields = [];
        this.body.replaceChildren();

        const definition = this.host.definition(this.node.attrs.elementKey);
        this.title.textContent = definition?.title || this.node.attrs.elementKey;

        if (!definition || !definition.known) {
            const notice = document.createElement('div');
            notice.className = 'cms-doc-notice';
            notice.textContent = this.host.strings.unknownElement.replace(':key', this.node.attrs.elementKey);
            this.body.append(notice);
            return;
        }

        for (const field of definition.fields) {
            const rendered = createField(field, this.context(field));
            this.fields.push(rendered);
            this.body.append(rendered.element);
        }
    }

    context(field) {
        const translatable = isTranslatable(field.type);

        return {
            host: this.host,
            uid: () => this.node.attrs.uid,
            saved: () => this.node.attrs.saved,
            value: () => {
                const value = (this.node.attrs.data || {})[field.name];
                if (!translatable) {
                    return value;
                }
                return value && typeof value === 'object' ? value[this.host.language] ?? '' : '';
            },
            set: (value) => {
                if (!this.host.editable) {
                    return;
                }
                const data = { ...(this.node.attrs.data || {}) };
                if (translatable) {
                    const current = data[field.name] && typeof data[field.name] === 'object' && !Array.isArray(data[field.name]) ? data[field.name] : {};
                    data[field.name] = { ...current, [this.host.language]: value };
                } else {
                    data[field.name] = value;
                }
                this.updateAttrs({ data });
            },
        };
    }

    updateAttrs(attrs) {
        const pos = this.getPos();
        if (typeof pos !== 'number') {
            return;
        }
        const { tr } = this.editor.state;
        tr.setNodeMarkup(pos, undefined, { ...this.node.attrs, ...attrs });
        this.editor.view.dispatch(tr);
    }

    duplicate() {
        const pos = this.getPos();
        if (typeof pos !== 'number' || !this.host.editable) {
            return;
        }
        const copy = this.node.type.create({ ...this.node.attrs, uid: newUid(), saved: false });
        this.editor.view.dispatch(this.editor.state.tr.insert(pos + this.node.nodeSize, copy));
    }

    /**
     * No confirmation: a removed element comes back with undo (Cmd+Z), and
     * nothing is written before the record is saved.
     */
    remove() {
        const pos = this.getPos();
        if (typeof pos !== 'number' || !this.host.editable) {
            return;
        }
        this.editor.view.dispatch(this.editor.state.tr.delete(pos, pos + this.node.nodeSize));
    }

    update(node) {
        if (node.type !== this.node.type || node.attrs.elementKey !== this.node.attrs.elementKey) {
            return false;
        }

        this.node = node;
        this.fields.forEach((field) => field.sync());

        return true;
    }

    selectNode() {
        this.dom.classList.add('cms-doc-element--selected');
    }

    deselectNode() {
        this.dom.classList.remove('cms-doc-element--selected');
    }

    /**
     * The form is the node view's own business — ProseMirror must not treat a
     * keystroke in an input as typing into the document. Only a drag that
     * starts on the handle is ProseMirror's (it moves the element).
     */
    stopEvent(event) {
        const handle = event.target instanceof Element && event.target.closest('[data-drag-handle]');
        if (handle && event.type.startsWith('drag')) {
            return false;
        }
        if (handle && event.type === 'mousedown') {
            return false;
        }
        return true;
    }

    ignoreMutation() {
        return true;
    }

    destroy() {
        this.fields.forEach((field) => field.destroy());
    }
}
