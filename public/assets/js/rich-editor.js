(function () {
    'use strict';

    function normalizeEditorHtml(surface) {
        if (surface.textContent.replace(/\u00a0/g, ' ').trim() === '') {
            return '';
        }

        surface.querySelectorAll('font[size]').forEach(function (node) {
            var span = document.createElement('span');
            span.style.fontSize = node.getAttribute('data-font-size') || '20px';
            span.innerHTML = node.innerHTML;
            node.replaceWith(span);
        });

        surface.querySelectorAll('a[href]').forEach(function (link) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });

        return surface.innerHTML
            .replace(/<!--[\s\S]*?-->/g, '')
            .replace(/\sdata-font-size="[^"]*"/g, '')
            .trim();
    }

    function setFontSize(surface, size) {
        if (!size) {
            return;
        }

        surface.focus();
        document.execCommand('fontSize', false, '7');
        surface.querySelectorAll('font[size="7"]').forEach(function (font) {
            font.setAttribute('data-font-size', size);
        });
        normalizeEditorHtml(surface);
    }

    function createLink(surface) {
        var url = window.prompt('Nhập link cần gắn:');
        if (!url) {
            return;
        }

        var normalized = url.trim();
        if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(normalized)) {
            normalized = 'https://' + normalized;
        }

        surface.focus();
        var selection = window.getSelection();
        if (!selection || selection.rangeCount === 0 || selection.toString().trim() === '') {
            document.execCommand('insertHTML', false, '<a href="' + normalized.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + normalized + '</a>');
            return;
        }

        document.execCommand('createLink', false, normalized);
        surface.querySelectorAll('a[href]').forEach(function (link) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }

    function initEditor(editor) {
        var surface = editor.querySelector('[data-rich-surface]');
        var input = editor.querySelector('[data-rich-input]');
        var form = editor.closest('form');

        if (!surface || !input) {
            return;
        }

        function sync() {
            input.value = normalizeEditorHtml(surface);
        }

        editor.querySelectorAll('[data-rich-command]').forEach(function (button) {
            button.addEventListener('click', function () {
                surface.focus();
                document.execCommand(button.getAttribute('data-rich-command'), false, null);
                sync();
            });
        });

        var blockSelect = editor.querySelector('[data-rich-block]');
        if (blockSelect) {
            blockSelect.addEventListener('change', function () {
                surface.focus();
                document.execCommand('formatBlock', false, '<' + blockSelect.value + '>');
                blockSelect.value = 'p';
                sync();
            });
        }

        var sizeSelect = editor.querySelector('[data-rich-size]');
        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                setFontSize(surface, sizeSelect.value);
                sizeSelect.value = '';
                sync();
            });
        }

        var linkButton = editor.querySelector('[data-rich-link]');
        if (linkButton) {
            linkButton.addEventListener('click', function () {
                createLink(surface);
                sync();
            });
        }

        surface.addEventListener('input', sync);
        surface.addEventListener('blur', sync);
        if (form) {
            form.addEventListener('submit', sync);
        }

        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rich-editor]').forEach(initEditor);
    });
}());
