import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';

export function newUid() {
    return 'n' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);
}

/**
 * Keeps the document in the shape the server expects:
 * - there is running text before, between and after the elements, so the
 *   author can always write there;
 * - neighbouring text sections are merged (an element between them was
 *   removed) — the merged uid is remembered in `absorbed`;
 * - every section has a uid of its own (a split or a paste copies attributes).
 *
 * Every fix is a step of its own, so the selection is mapped correctly.
 */
export const DocumentNormalizer = Extension.create({
    name: 'cmsDocumentNormalizer',

    addProseMirrorPlugins() {
        return [
            new Plugin({
                key: new PluginKey('cmsDocumentNormalizer'),
                appendTransaction: (transactions, oldState, newState) => {
                    if (!transactions.some((tr) => tr.docChanged)) {
                        return null;
                    }

                    const tr = newState.tr;
                    let changed = false;

                    for (let guard = 0; guard < 500; guard++) {
                        if (!fixOne(tr, newState.schema)) {
                            break;
                        }
                        changed = true;
                    }

                    return changed ? tr.setMeta('addToHistory', false) : null;
                },
            }),
        ];
    },
});

function emptySection(schema) {
    return schema.nodes.textSection.create({ uid: newUid(), absorbed: [] }, schema.nodes.paragraph.create());
}

/**
 * Apply the first fix the document needs; false when it needs none.
 */
function fixOne(tr, schema) {
    const doc = tr.doc;
    const seen = new Set();
    let pos = 0;

    for (let index = 0; index < doc.childCount; index++) {
        const child = doc.child(index);
        const previous = index > 0 ? doc.child(index - 1) : null;
        const isElement = child.type.name === 'elementBlock';

        if (index === 0 && isElement) {
            tr.insert(0, emptySection(schema));
            return true;
        }

        if (previous && isElement && previous.type.name === 'elementBlock') {
            tr.insert(pos, emptySection(schema));
            return true;
        }

        if (previous && !isElement && previous.type.name === 'textSection') {
            const previousPos = pos - previous.nodeSize;
            const absorbed = [...(previous.attrs.absorbed || []), child.attrs.uid, ...(child.attrs.absorbed || [])].filter(Boolean);
            tr.setNodeMarkup(previousPos, undefined, { ...previous.attrs, absorbed });
            tr.join(pos);
            return true;
        }

        if (!child.attrs.uid || seen.has(child.attrs.uid)) {
            const attrs = { ...child.attrs, uid: newUid() };
            if (isElement) {
                attrs.saved = false;
            } else {
                attrs.absorbed = [];
            }
            tr.setNodeMarkup(pos, undefined, attrs);
            return true;
        }

        seen.add(child.attrs.uid);
        pos += child.nodeSize;
    }

    const last = doc.lastChild;
    if (last && last.type.name === 'elementBlock') {
        tr.insert(doc.content.size, emptySection(schema));
        return true;
    }

    return false;
}
