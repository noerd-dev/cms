import { Extension } from '@tiptap/core';
import { PluginKey, TextSelection } from '@tiptap/pm/state';
import Suggestion from '@tiptap/suggestion';
import { computePosition, flip, offset, shift } from '@floating-ui/dom';
import { newUid } from './normalize.js';

/**
 * `//` opens the insert menu: text blocks (headings, lists, quote, divider)
 * and the elements of the content type. Not inside `https://` — the trigger
 * needs a space or the start of a line in front of it.
 */
export const InsertMenu = Extension.create({
    name: 'cmsInsertMenu',

    addOptions() {
        return { host: null };
    },

    addProseMirrorPlugins() {
        const host = this.options.host;

        return [
            Suggestion({
                editor: this.editor,
                pluginKey: new PluginKey('cmsInsertMenu'),
                char: '//',
                allowedPrefixes: [' '],
                allow: () => host.editable,
                items: ({ query }) => menuItems(host, query),
                command: ({ editor, range, props }) => props.run(editor, range),
                render: () => renderMenu(host),
            }),
        ];
    },
});

function textBlocks(host) {
    const s = host.strings;
    return [
        { group: s.textGroup, title: s.heading1, keywords: 'h1 heading überschrift', run: (editor, range) => editor.chain().focus().deleteRange(range).setNode('heading', { level: 1 }).run() },
        { group: s.textGroup, title: s.heading2, keywords: 'h2 heading überschrift', run: (editor, range) => editor.chain().focus().deleteRange(range).setNode('heading', { level: 2 }).run() },
        { group: s.textGroup, title: s.heading3, keywords: 'h3 heading überschrift', run: (editor, range) => editor.chain().focus().deleteRange(range).setNode('heading', { level: 3 }).run() },
        { group: s.textGroup, title: s.paragraph, keywords: 'text paragraph absatz', run: (editor, range) => editor.chain().focus().deleteRange(range).setParagraph().run() },
        { group: s.textGroup, title: s.bulletList, keywords: 'list liste ul', run: (editor, range) => editor.chain().focus().deleteRange(range).toggleBulletList().run() },
        { group: s.textGroup, title: s.orderedList, keywords: 'list liste ol nummer', run: (editor, range) => editor.chain().focus().deleteRange(range).toggleOrderedList().run() },
        { group: s.textGroup, title: s.quote, keywords: 'quote zitat', run: (editor, range) => editor.chain().focus().deleteRange(range).toggleBlockquote().run() },
        { group: s.textGroup, title: s.divider, keywords: 'hr divider trenner linie', run: (editor, range) => editor.chain().focus().deleteRange(range).setHorizontalRule().run() },
    ];
}

function menuItems(host, query) {
    const needle = (query || '').toLowerCase();
    const elements = host.catalog.map((entry) => ({
        group: entry.group,
        title: entry.title,
        description: entry.description,
        keywords: `${entry.key} ${entry.group}`,
        run: (editor, range) => insertElement(editor, range, entry.key, host),
    }));

    return [...textBlocks(host), ...elements]
        .filter((item) => !needle || `${item.title} ${item.keywords} ${item.description || ''}`.toLowerCase().includes(needle))
        .slice(0, 40);
}

/**
 * Replace the `//query` with an element. The element sits between two text
 * sections, so the section around the cursor is split there; a line that only
 * held the trigger disappears. The normalizer gives the second half a uid of
 * its own — the first half keeps the texts of the other languages.
 */
export function insertElement(editor, range, elementKey, host) {
    const { schema } = editor.state;
    const node = schema.nodes.elementBlock.create({
        uid: newUid(),
        elementKey,
        data: host.defaultData(elementKey),
        saved: false,
    });

    let tr = editor.state.tr.delete(range.from, range.to);
    let $pos = tr.doc.resolve(range.from);

    // An empty line that only held the trigger is removed first, unless it is
    // the only block of its section.
    const block = $pos.parent;
    if (block.isTextblock && block.content.size === 0 && $pos.depth >= 2 && $pos.node($pos.depth - 1).childCount > 1) {
        const before = $pos.before($pos.depth);
        tr = tr.delete(before, before + block.nodeSize);
        $pos = tr.doc.resolve(Math.min(before, tr.doc.content.size));
    }

    let insertAt;
    if ($pos.depth === 0) {
        insertAt = $pos.pos;
    } else if ($pos.depth === 1) {
        // Between two blocks of a section (the trigger line was removed): a
        // split at its very start or end would leave an empty section, which
        // the schema does not allow — the element goes next to the section.
        if ($pos.index() === 0) {
            insertAt = $pos.before(1);
        } else if ($pos.index() === $pos.parent.childCount) {
            insertAt = $pos.after(1);
        } else {
            tr = tr.split($pos.pos, 1);
            insertAt = $pos.pos + 1;
        }
    } else if ($pos.parentOffset === 0 && $pos.index(1) === 0 && $pos.depth === 2) {
        // At the very start of a section: put the element in front of it.
        insertAt = $pos.before(1);
    } else {
        tr = tr.split($pos.pos, $pos.depth);
        insertAt = $pos.pos + $pos.depth;
    }

    tr = tr.insert(insertAt, node);

    const after = Math.min(insertAt + node.nodeSize + 1, tr.doc.content.size);
    tr = tr.setSelection(TextSelection.near(tr.doc.resolve(after)));
    editor.view.dispatch(tr.scrollIntoView());
    editor.view.focus();
}

function renderMenu(host) {
    let popup = null;
    let items = [];
    let selected = 0;
    let command = null;
    let clientRect = null;

    const place = () => {
        if (!popup || !clientRect) {
            return;
        }
        const reference = { getBoundingClientRect: () => clientRect() ?? new DOMRect() };
        computePosition(reference, popup, { placement: 'bottom-start', middleware: [offset(6), flip(), shift({ padding: 8 })] })
            .then(({ x, y }) => Object.assign(popup.style, { left: `${x}px`, top: `${y}px` }));
    };

    const draw = () => {
        popup.replaceChildren();

        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'cms-doc-menu-empty';
            empty.textContent = host.strings.nothingFound;
            popup.append(empty);
            return;
        }

        let group = null;
        items.forEach((item, index) => {
            if (item.group !== group) {
                group = item.group;
                const heading = document.createElement('div');
                heading.className = 'cms-doc-menu-group';
                heading.textContent = group;
                popup.append(heading);
            }

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'cms-doc-menu-item' + (index === selected ? ' cms-doc-menu-item--active' : '');
            const title = document.createElement('span');
            title.className = 'cms-doc-menu-title';
            title.textContent = item.title;
            row.append(title);
            if (item.description) {
                const description = document.createElement('span');
                description.className = 'cms-doc-menu-description';
                description.textContent = item.description;
                row.append(description);
            }
            row.addEventListener('mousedown', (event) => {
                event.preventDefault();
                command(item);
            });
            popup.append(row);

            if (index === selected) {
                requestAnimationFrame(() => row.scrollIntoView({ block: 'nearest' }));
            }
        });
    };

    return {
        onStart: (props) => {
            popup = document.createElement('div');
            popup.className = 'cms-doc-menu';
            document.body.append(popup);
            items = props.items;
            command = props.command;
            clientRect = props.clientRect;
            selected = 0;
            draw();
            place();
        },
        onUpdate: (props) => {
            items = props.items;
            command = props.command;
            clientRect = props.clientRect;
            selected = Math.min(selected, Math.max(items.length - 1, 0));
            draw();
            place();
        },
        onKeyDown: ({ event }) => {
            if (event.key === 'ArrowDown') {
                selected = items.length ? (selected + 1) % items.length : 0;
                draw();
                return true;
            }
            if (event.key === 'ArrowUp') {
                selected = items.length ? (selected - 1 + items.length) % items.length : 0;
                draw();
                return true;
            }
            if (event.key === 'Enter') {
                if (items[selected]) {
                    command(items[selected]);
                }
                return true;
            }
            if (event.key === 'Escape') {
                popup?.remove();
                popup = null;
                return true;
            }
            return false;
        },
        onExit: () => {
            popup?.remove();
            popup = null;
        },
    };
}
