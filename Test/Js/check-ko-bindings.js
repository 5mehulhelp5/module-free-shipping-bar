#!/usr/bin/env node
/**
 * Guards the Knockout templates against a binding string that cannot be parsed.
 *
 * Magento_Ui/js/lib/knockout/template/renderer rewrites attribute-syntax bindings into a single
 * binding string, and wrapArgs() wraps a value in curly braces when it contains a colon and no
 * closing brace. An inline ternary in css="" therefore becomes css: {expr}, which is not valid
 * object literal syntax and takes down every binding on the page — including the minicart that
 * hosts the bar. Knockout reports it only at runtime, so check it here instead.
 */
'use strict';

const fs = require('fs');
const path = require('path');

// Magento_Ui/js/lib/knockout/template/renderer.js — preset.attributes and preset.nodes.
const BINDINGS = new Set([
    'css', 'attr', 'html', 'with', 'text', 'click', 'event', 'submit', 'enable', 'disable',
    'options', 'visible', 'template', 'hasFocus', 'textInput', 'component', 'uniqueName',
    'optionsText', 'optionsValue', 'checkedValue', 'selectedOptions',
    'if', 'scope', 'ifnot', 'foreach'
]);

/** Mirrors renderer.wrapArgs(). */
function wrapArgs(args) {
    if (args.indexOf('\\:') !== -1) {
        return args.replace(/\\:/g, ':');
    }

    if (args.indexOf(':') !== -1 && args.indexOf('}') === -1) {
        return '{' + args + '}';
    }

    return args;
}

const dir = path.join(__dirname, '..', '..', 'view', 'frontend', 'web', 'template');
let failures = 0;
let checked = 0;

for (const file of fs.readdirSync(dir).filter((f) => f.endsWith('.html'))) {
    const html = fs.readFileSync(path.join(dir, file), 'utf8');
    const tags = html.match(/<[a-z][^>]*>/gi) || [];

    for (const tag of tags) {
        const pairs = [];
        const attr = /([a-zA-Z-]+)\s*=\s*"([^"]*)"/g;
        let m;

        while ((m = attr.exec(tag)) !== null) {
            if (m[1] === 'data-bind') {
                pairs.push(m[2]);
            } else if (BINDINGS.has(m[1])) {
                pairs.push(m[1] + ': ' + wrapArgs(m[2]));
            }
        }

        if (!pairs.length) {
            continue;
        }

        checked++;
        const bindings = pairs.join(', ');

        try {
            // How Knockout builds its evaluator, minus the runtime context.
            new Function('$context', '$data', 'with($context){with($data){return{' + bindings + '}}}');
        } catch (e) {
            failures++;
            console.error('FAIL %s\n  %s\n  %s\n', file, bindings, e.message);
        }
    }
}

console.log('%d binding string(s) checked, %d failure(s)', checked, failures);
process.exit(failures ? 1 : 0);
