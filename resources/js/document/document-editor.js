import { Editor, generateJSON } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import BubbleMenu from '@tiptap/extension-bubble-menu';
import Placeholder from '@tiptap/extension-placeholder';
import { DOMSerializer } from '@tiptap/pm/model';
import { DocumentNode, ElementBlock, TextSection } from './nodes.js';
import { DocumentNormalizer, newUid } from './normalize.js';
import { InsertMenu } from './suggestion.js';

const TEXT_EXTENSIONS = [StarterKit.configure({ heading: { levels: [1, 2, 3] }, link: { openOnClick: false } })];

/**
 * Alpine component of the content editor (<x-cms::content-document>).
 *
 * The editor keeps its state in the browser and writes it — after every change,
 * without a round trip — into the Livewire property `contentDocumentDraft`
 * (`{ language, sections }`); the server merges it on save and on a language
 * switch (EditsContentDocument). The root is wire:ignore: the server only
 * talks to the editor through the browser events `content-document-load`
 * (language switch) and `content-document-saved`.
 */
export function documentEditor(config) {
    return {
        editor: null,
        language: config.language,
        editable: config.editable,
        catalog: config.catalog || [],
        definitions: config.definitions || {},
        previews: { ...(config.previews || {}) },
        collectionCounts: config.collectionCounts || {},
        strings: config.strings || {},
        pendingMedia: {},
        updatedAt: Date.now(),
        collapsed: false,
        linkUrl: '',
        showLinkInput: false,
        cleanups: [],

        init() {
            const host = this;

            this.editor = new Editor({
                element: this.$refs.editor,
                extensions: [
                    DocumentNode,
                    StarterKit.configure({
                        document: false,
                        heading: { levels: [1, 2, 3] },
                        link: { openOnClick: false },
                    }),
                    TextSection,
                    ElementBlock.configure({ host }),
                    DocumentNormalizer,
                    InsertMenu.configure({ host }),
                    Placeholder.configure({
                        includeChildren: true,
                        placeholder: ({ node }) => (node.type.name === 'paragraph' ? this.strings.placeholder : ''),
                    }),
                    BubbleMenu.configure({
                        element: this.$refs.bubble,
                        shouldShow: ({ editor, state }) => editor.isEditable
                            && !state.selection.empty
                            && !state.selection.node,
                    }),
                ],
                content: this.documentJson(config.sections),
                editable: this.editable,
                editorProps: {
                    attributes: { class: 'cms-doc-editor rich-text' },
                },
                onUpdate: () => {
                    this.updatedAt = Date.now();
                    this.sync();
                },
                onSelectionUpdate: () => {
                    this.updatedAt = Date.now();
                },
            });

            this.cleanups.push(window.Livewire.on('mediaSelected', (detail) => this.mediaSelected(detail)));
        },

        destroy() {
            this.cleanups.forEach((cleanup) => typeof cleanup === 'function' && cleanup());
            this.raw()?.destroy();
            this.editor = null;
        },

        raw() {
            return window.Alpine.raw(this.editor);
        },

        /**
         * Sections as the server sends them → the editor's JSON document.
         */
        documentJson(sections) {
            const content = (sections || []).map((section) => {
                if (section.kind === 'element') {
                    return {
                        type: 'elementBlock',
                        attrs: { uid: section.uid, elementKey: section.key, data: section.data || {}, saved: Boolean(section.saved) },
                    };
                }

                const blocks = section.html ? generateJSON(section.html, TEXT_EXTENSIONS).content : [];

                return {
                    type: 'textSection',
                    attrs: { uid: section.uid, absorbed: [] },
                    content: blocks && blocks.length ? blocks : [{ type: 'paragraph' }],
                };
            });

            return { type: 'doc', content: content.length ? content : [{ type: 'textSection', attrs: { uid: newUid(), absorbed: [] }, content: [{ type: 'paragraph' }] }] };
        },

        /**
         * The editor's state → the draft the server merges.
         */
        sync() {
            const editor = this.raw();
            const serializer = DOMSerializer.fromSchema(editor.schema);
            const sections = [];

            editor.state.doc.forEach((node) => {
                if (node.type.name === 'elementBlock') {
                    sections.push({ kind: 'element', uid: node.attrs.uid, key: node.attrs.elementKey, data: node.attrs.data });
                    return;
                }

                const container = document.createElement('div');
                container.append(serializer.serializeFragment(node.content));
                const empty = node.childCount === 1 && node.firstChild.isTextblock && node.firstChild.content.size === 0;
                sections.push({ kind: 'text', uid: node.attrs.uid, absorbed: node.attrs.absorbed || [], html: empty ? '' : container.innerHTML });
            });

            this.$wire.set('contentDocumentDraft', { language: this.language, sections }, false);
        },

        /**
         * Language switch: the server sends the document in the new language.
         */
        load(detail) {
            const next = detail?.config ?? detail;
            if (!next) {
                return;
            }
            this.language = next.language;
            this.catalog = next.catalog || this.catalog;
            this.definitions = next.definitions || this.definitions;
            this.previews = { ...this.previews, ...(next.previews || {}) };
            this.collectionCounts = next.collectionCounts || {};
            this.raw().commands.setContent(this.documentJson(next.sections), { emitUpdate: false });
        },

        /**
         * After a save: mark the elements that have a row now (their element
         * collections can be managed) — without touching text or cursor.
         */
        saved(detail) {
            const editor = this.raw();
            const saved = new Set(detail?.saved || []);
            this.collectionCounts = detail?.collectionCounts || this.collectionCounts;
            const tr = editor.state.tr;

            editor.state.doc.forEach((node, offset) => {
                if (node.type.name === 'elementBlock') {
                    tr.setNodeMarkup(offset, undefined, { ...node.attrs, saved: saved.has(node.attrs.uid) });
                }
            });

            tr.setMeta('addToHistory', false);
            editor.view.dispatch(tr);
        },

        definition(key) {
            return this.definitions[key] || null;
        },

        defaultData(key) {
            const data = {};
            for (const field of this.definition(key)?.fields || []) {
                if (['spacer', 'element-collection'].includes(field.type)) {
                    continue;
                }
                if (field.type.startsWith('translatable')) {
                    data[field.name] = {};
                } else if (field.type === 'checkbox') {
                    data[field.name] = Boolean(field.default);
                } else if (field.type === 'image') {
                    data[field.name] = null;
                } else {
                    data[field.name] = field.default ?? '';
                }
            }
            return data;
        },

        collectionCount(uid, field) {
            return this.collectionCounts?.[uid]?.[field] ?? 0;
        },

        manageCollection(uid, field) {
            this.$wire.manageContentDocumentCollection(uid, field);
        },

        pickMedia(callback) {
            const token = 'cmsdoc' + Math.random().toString(36).slice(2, 12);
            this.pendingMedia[token] = callback;
            this.$wire.pickContentDocumentMedia(token);
        },

        mediaSelected(detail) {
            const [mediaId, , token] = Array.isArray(detail) ? detail : [detail?.mediaId, detail?.context, detail?.token];
            const callback = this.pendingMedia[token];
            if (!callback) {
                return;
            }
            delete this.pendingMedia[token];
            callback(Number(mediaId));
        },

        async mediaPreview(value) {
            const key = String(value);
            if (this.previews[key]) {
                return this.previews[key];
            }
            if (!/^\d+$/.test(key)) {
                return key;
            }
            const url = await this.$wire.contentDocumentMediaPreview(Number(value));
            if (url) {
                this.previews[key] = url;
            }
            return url;
        },

        // Toolbar ---------------------------------------------------------

        command() {
            return this.raw().chain().focus();
        },

        isActive(type, attrs = {}) {
            return Boolean(this.updatedAt && this.raw()?.isActive(type, attrs));
        },

        insertMenu() {
            this.command().insertContent(' //').run();
        },

        setLink() {
            if (this.linkUrl) {
                this.command().extendMarkRange('link').setLink({ href: this.linkUrl }).run();
            }
            this.linkUrl = '';
            this.showLinkInput = false;
        },

        removeLink() {
            this.command().extendMarkRange('link').unsetLink().run();
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
        },
    };
}
