import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

const TRANSLATABLE = ['translatableText', 'translatableTextarea', 'translatableRichText'];

function el(tag, className, attrs = {}) {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    for (const [key, value] of Object.entries(attrs)) {
        if (value !== null && value !== undefined) {
            node.setAttribute(key, value);
        }
    }
    return node;
}

/**
 * Field renderers of the inline element forms — one per YAML field type the
 * elements use. A renderer gets the field definition and a context:
 *
 *   ctx.value()      current value of the field (for translatable fields the
 *                    value in the editor's language)
 *   ctx.set(value)   write the value (translatable: only the editor's language)
 *   ctx.host         the editor host (strings, media picker, collections)
 *
 * and returns { element, sync(), destroy() } — sync() re-reads the value after
 * an external change (undo), without touching a field that has the focus.
 */
export function createField(field, ctx) {
    const wrapper = el('div', 'cms-doc-field');
    wrapper.style.gridColumn = `span ${field.colspan || 12} / span ${field.colspan || 12}`;

    if (field.type === 'spacer') {
        return { element: wrapper, sync() {}, destroy() {} };
    }

    if (field.label) {
        const label = el('div', 'cms-doc-label' + (TRANSLATABLE.includes(field.type) ? ' cms-doc-label--translatable' : ''));
        label.textContent = field.label;
        wrapper.append(label);
    }

    const renderer = (RENDERERS[field.type] || unsupported)(field, ctx);
    wrapper.append(renderer.element);

    return { element: wrapper, sync: renderer.sync, destroy: renderer.destroy || (() => {}) };
}

function textInput(field, ctx, multiline) {
    const input = multiline
        ? el('textarea', 'cms-doc-input cms-doc-textarea', { rows: 4, placeholder: field.placeholder })
        : el('input', 'cms-doc-input', { type: 'text', placeholder: field.placeholder });

    const sync = () => {
        const value = ctx.value() ?? '';
        if (document.activeElement !== input && input.value !== String(value)) {
            input.value = String(value);
        }
    };

    input.addEventListener('input', () => ctx.set(input.value));
    sync();

    return { element: input, sync };
}

function richText(field, ctx) {
    const box = el('div', 'cms-doc-richtext');
    const toolbar = el('div', 'cms-doc-richtext-toolbar');
    const area = el('div', 'cms-doc-richtext-area');
    box.append(toolbar, area);

    let current = ctx.value() ?? '';
    const editor = new Editor({
        element: area,
        extensions: [StarterKit.configure({ heading: { levels: [2, 3] }, link: { openOnClick: false } })],
        content: current,
        editable: ctx.host.editable,
        onUpdate: ({ editor: nested }) => {
            current = nested.isEmpty ? '' : nested.getHTML();
            ctx.set(current);
        },
    });

    const buttons = [
        ['B', 'bold', () => editor.chain().focus().toggleBold().run()],
        ['I', 'italic', () => editor.chain().focus().toggleItalic().run()],
        ['H2', 'heading', () => editor.chain().focus().toggleHeading({ level: 2 }).run()],
        ['H3', 'heading', () => editor.chain().focus().toggleHeading({ level: 3 }).run()],
        ['•', 'bulletList', () => editor.chain().focus().toggleBulletList().run()],
    ];

    for (const [label, , run] of buttons) {
        const button = el('button', 'cms-doc-mini-button', { type: 'button' });
        button.textContent = label;
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', run);
        toolbar.append(button);
    }

    return {
        element: box,
        sync() {
            const next = ctx.value() ?? '';
            if (!editor.isFocused && next !== current) {
                current = next;
                editor.commands.setContent(next, { emitUpdate: false });
            }
        },
        destroy() {
            editor.destroy();
        },
    };
}

function select(field, ctx) {
    const input = el('select', 'cms-doc-input');
    const empty = el('option', null, { value: '' });
    empty.textContent = field.placeholder || '—';
    input.append(empty);

    for (const option of field.options || []) {
        const item = el('option', null, { value: option.value });
        item.textContent = option.label;
        input.append(item);
    }

    const sync = () => {
        input.value = String(ctx.value() ?? '');
    };

    input.addEventListener('change', () => ctx.set(input.value));
    sync();

    return { element: input, sync };
}

function checkbox(field, ctx) {
    const input = el('input', 'cms-doc-checkbox', { type: 'checkbox' });
    const sync = () => {
        input.checked = Boolean(ctx.value());
    };

    input.addEventListener('change', () => ctx.set(input.checked));
    sync();

    return { element: input, sync };
}

function image(field, ctx) {
    const box = el('div', 'cms-doc-image');
    const preview = el('div', 'cms-doc-image-preview');
    const actions = el('div', 'cms-doc-image-actions');
    const choose = el('button', 'cms-doc-button', { type: 'button' });
    const remove = el('button', 'cms-doc-button cms-doc-button--danger', { type: 'button' });
    choose.textContent = ctx.host.strings.chooseImage;
    remove.textContent = ctx.host.strings.removeImage;
    actions.append(choose, remove);
    box.append(preview, actions);

    const sync = async () => {
        const value = ctx.value();
        preview.replaceChildren();
        remove.hidden = value === null || value === undefined || value === '';

        if (remove.hidden) {
            preview.textContent = ctx.host.strings.noImage;
            return;
        }

        const url = await ctx.host.mediaPreview(value);
        if (url && ctx.value() === value) {
            const img = el('img', null, { src: url, alt: '' });
            preview.replaceChildren(img);
        }
    };

    choose.addEventListener('click', () => ctx.host.pickMedia((mediaId) => ctx.set(mediaId)));
    remove.addEventListener('click', () => ctx.set(null));
    sync();

    return { element: box, sync };
}

function elementCollection(field, ctx) {
    const box = el('div', 'cms-doc-collection');
    const sync = () => {
        box.replaceChildren();

        if (!ctx.saved()) {
            box.textContent = ctx.host.strings.saveFirst;
            return;
        }

        const button = el('button', 'cms-doc-button', { type: 'button' });
        const count = ctx.host.collectionCount(ctx.uid(), field.name);
        button.textContent = `${count} ${count === 1 ? ctx.host.strings.entry : ctx.host.strings.entries} — ${ctx.host.strings.manage}`;
        button.addEventListener('click', () => ctx.host.manageCollection(ctx.uid(), field.name));
        box.append(button);
    };

    sync();

    return { element: box, sync };
}

function unsupported(field, ctx) {
    const box = el('div', 'cms-doc-notice');
    box.textContent = ctx.host.strings.unsupportedField.replace(':type', field.type);

    return { element: box, sync() {} };
}

const RENDERERS = {
    text: (field, ctx) => textInput(field, ctx, false),
    translatableText: (field, ctx) => textInput(field, ctx, false),
    textarea: (field, ctx) => textInput(field, ctx, true),
    translatableTextarea: (field, ctx) => textInput(field, ctx, true),
    richText,
    translatableRichText: richText,
    select,
    checkbox,
    image,
    'element-collection': elementCollection,
};

export function isTranslatable(type) {
    return TRANSLATABLE.includes(type);
}
