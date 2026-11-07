/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

/**
 * A fake page small enough to drive the widgets in Node: elements answer the selectors the scripts use (`tag`,
 * `[name]`, `[name="value"]`, `.class` and a tag followed by attribute tests), keep their attributes, children,
 * value, disabled and checked state, honour a listener's `signal`, and let an event bubble to their ancestors
 */

/**
 * @param {string} selector
 * @returns {function(Object): boolean}
 */
function compile(selector) {
    const parsed = /^([a-z]*)((?:\[[\w-]+(?:="[^"]*")?\])*)((?:\.[\w-]+)*)$/.exec(selector);

    if (!parsed) {
        throw new Error(`The fake page does not answer ${selector}`);
    }

    const tag = parsed[1];
    const tests = [...parsed[2].matchAll(/\[([\w-]+)(?:="([^"]*)")?\]/g)].map((match) => [match[1], match[2]]);
    const classes = parsed[3].split('.').filter(Boolean);

    return (node) => (tag === '' || node.tagName === tag)
        && tests.every(([name, value]) => (value === undefined ? name in node.attributes : node.attributes[name] === value))
        && classes.every((name) => node.classList.contains(name));
}

/**
 * @param {Object} element
 * @returns {Array<Object>}
 */
function descendants(element) {
    return element.children.flatMap((child) => [child, ...descendants(child)]);
}

/**
 * An element of the fake page
 *
 * @param {string} tagName
 * @param {Object<string, string>} [attributes]
 * @param {Array<Object>} [children]
 * @param {string} [text]
 * @returns {Object}
 */
function el(tagName, attributes = {}, children = [], text = '') {
    const listeners = {};
    const element = {
        tagName,
        attributes: {...attributes},
        children: [],
        parent: null,
        ownerDocument: null,
        textContent: text,
        value: attributes.value || '',
        disabled: 'disabled' in attributes,
        checked: 'checked' in attributes,
        focused: 0,
        classList: {
            contains: (name) => (element.attributes.class || '').split(' ').includes(name)
        },
        getAttribute: (name) => (name in element.attributes ? element.attributes[name] : null),
        setAttribute: (name, value) => {
            element.attributes[name] = String(value);
        },
        removeAttribute: (name) => {
            delete element.attributes[name];
        },
        hasAttribute: (name) => name in element.attributes,
        focus: () => {
            element.focused += 1;
        },
        appendChild: (child) => {
            child.remove();
            child.parent = element;
            element.children.push(child);

            return child;
        },
        remove: () => {
            if (element.parent) {
                element.parent.children.splice(element.parent.children.indexOf(element), 1);
                element.parent = null;
            }
        },
        cloneNode: () => el(
            tagName,
            element.attributes,
            element.children.map((child) => child.cloneNode(true)),
            element.textContent
        ),
        matches: (selector) => compile(selector)(element),
        querySelectorAll: (selector) => descendants(element).filter(compile(selector)),
        querySelector: (selector) => element.querySelectorAll(selector)[0] || null,
        closest: (selector) => {
            const test = compile(selector);

            for (let node = element; node; node = node.parent) {
                if (test(node)) {
                    return node;
                }
            }

            return null;
        },
        contains: (other) => {
            for (let node = other; node; node = node.parent) {
                if (node === element) {
                    return true;
                }
            }

            return false;
        },
        addEventListener: (type, listener, options) => {
            const signal = options && options.signal;

            if (signal && signal.aborted) {
                return;
            }
            (listeners[type] = listeners[type] || []).push(listener);
            if (signal) {
                signal.addEventListener('abort', () => element.removeEventListener(type, listener));
            }
        },
        removeEventListener: (type, listener) => {
            listeners[type] = (listeners[type] || []).filter((known) => known !== listener);
        },
        listenerCount: (type) => (listeners[type] || []).length,
        listenersOf: (type) => (listeners[type] || []).slice()
    };

    children.forEach((child) => element.appendChild(child));

    return element;
}

/**
 * Fire an event at an element; it bubbles to every ancestor
 *
 * @param {Object} target
 * @param {string} type
 * @param {Object} [init] e.g. `{key: 'Enter'}` or `{clipboardData: …}`
 * @returns {Object} The event, to read `defaultPrevented`
 */
function fire(target, type, init = {}) {
    const event = {
        type,
        target,
        defaultPrevented: false,
        preventDefault: () => {
            event.defaultPrevented = true;
        },
        ...init
    };

    for (let node = target; node; node = node.parent) {
        node.listenersOf(type).forEach((listener) => listener(event));
    }

    return event;
}

/**
 * A document holding the given elements, which can find them by id and owns them
 *
 * @param {Array<Object>} elements
 * @param {Object} [view] What `defaultView` answers
 * @returns {Object}
 */
function documentOf(elements, view = {}) {
    const document = {
        defaultView: view,
        getElementById: (id) => elements
            .flatMap((element) => [element, ...descendants(element)])
            .find((element) => element.attributes.id === id) || null
    };

    elements.flatMap((element) => [element, ...descendants(element)]).forEach((element) => {
        element.ownerDocument = document;
    });

    return document;
}

/**
 * A copy made in this realm: the module's objects come from the AMD loader's context, whose prototypes differ
 *
 * @param {*} value
 * @returns {*}
 */
const plain = (value) => JSON.parse(JSON.stringify(value));

module.exports = {el, fire, documentOf, plain};
