import { Node, mergeAttributes } from '@tiptap/core';
import { ElementBlockView } from './element-block-view.js';

/**
 * The document of the content editor holds exactly two kinds of top-level
 * blocks: running text (textSection) and elements (elementBlock). Each one is
 * a `content_elements` row on the server — the running text is the element
 * `rich_text`.
 */
export const DocumentNode = Node.create({
    name: 'doc',
    topNode: true,
    content: '(textSection | elementBlock)+',
});

/**
 * Running text between two elements. Invisible as a frame: it reads as one
 * continuous text. `uid` identifies it across round trips to the server,
 * `absorbed` lists the sections merged into it (their texts in the other
 * languages are appended on the server).
 */
export const TextSection = Node.create({
    name: 'textSection',
    content: 'block+',

    addAttributes() {
        return {
            uid: { default: null, rendered: false },
            absorbed: { default: [], rendered: false },
        };
    },

    parseHTML() {
        return [{ tag: 'section[data-text-section]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['section', mergeAttributes(HTMLAttributes, { 'data-text-section': '', class: 'cms-doc-text' }), 0];
    },
});

/**
 * An element (image, button, slider, …) inside the document. Its fields are
 * edited inline in the node view; `data` holds every field value in every
 * language, exactly as the row stores it.
 */
export const ElementBlock = Node.create({
    name: 'elementBlock',
    atom: true,
    draggable: true,
    selectable: true,

    addOptions() {
        return { host: null };
    },

    addAttributes() {
        return {
            uid: { default: null, rendered: false },
            elementKey: { default: null, rendered: false },
            data: { default: {}, rendered: false },
            saved: { default: false, rendered: false },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-element-block]',
            getAttrs: (element) => {
                try {
                    const attrs = JSON.parse(element.getAttribute('data-element-block') || '{}');

                    // A pasted copy is a NEW element — never the saved original.
                    return { ...attrs, uid: null, saved: false };
                } catch {
                    return false;
                }
            },
        }];
    },

    renderHTML({ node }) {
        return ['div', { 'data-element-block': JSON.stringify({ elementKey: node.attrs.elementKey, data: node.attrs.data }) }];
    },

    addNodeView() {
        return (props) => new ElementBlockView({ ...props, host: this.options.host });
    },
});
