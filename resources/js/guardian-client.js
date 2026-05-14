(function () {
    const opts = window.GuardianClientErrors || {};
    const endpoint = opts.url || '/guardian/client-errors';
    const csrfSelector = 'meta[name="csrf-token"]';
    const captureConsoleError = Boolean(opts.captureConsoleError);

    function csrfToken() {
        const el = document.querySelector(csrfSelector);
        return el ? el.getAttribute('content') : '';
    }

    /** Best-effort: identify Webpack vs Vite (and similar) without build-time hooks. */
    function detectBundler() {
        try {
            if (typeof window.__webpack_require__ === 'function') {
                return 'webpack';
            }
            if (typeof window.webpackChunk === 'object' && window.webpackChunk) {
                return 'webpack';
            }
            const keys = Object.keys(window);
            for (let i = 0; i < keys.length; i++) {
                if (keys[i].indexOf('webpackChunk') === 0) {
                    return 'webpack';
                }
            }
        } catch (e) {
            /* ignore */
        }
        try {
            const scripts = document.scripts || [];
            for (let i = 0; i < scripts.length; i++) {
                const s = scripts[i].src || '';
                if (s.indexOf('/@vite/') !== -1 || s.indexOf('@vite/client') !== -1) {
                    return 'vite';
                }
            }
            const firstMod = document.querySelector('script[type="module"]');
            if (firstMod && firstMod.src && firstMod.src.indexOf('/@fs/') !== -1) {
                return 'vite';
            }
        } catch (e) {
            /* ignore */
        }
        if (document.querySelector('script[type="module"]')) {
            return 'esm';
        }
        return 'unknown';
    }

    /** Classify common Webpack/Vite production failures for search/filter in Nightwatch. */
    function classifyBundleCategory(message, errName) {
        const m = (message || '').toLowerCase();
        const n = (errName || '').toLowerCase();
        if (n.indexOf('chunkload') !== -1 || m.indexOf('loading chunk') !== -1) {
            return 'chunk-load';
        }
        if (m.indexOf('failed to fetch dynamically imported module') !== -1) {
            return 'vite-dynamic-import';
        }
        if (m.indexOf('importing a module script failed') !== -1) {
            return 'module-script-import';
        }
        if (m.indexOf('loading css chunk') !== -1) {
            return 'css-chunk';
        }
        if (m.indexOf('dynamic import') !== -1 && m.indexOf('failed') !== -1) {
            return 'dynamic-import';
        }
        return 'javascript';
    }

    function appendCauseChain(err, lines, depth) {
        if (depth > 8 || !err) {
            return;
        }
        let c = null;
        try {
            c = err.cause;
        } catch (e) {
            return;
        }
        if (!c) {
            return;
        }
        lines.push('Caused by: ' + (c && c.message ? c.message : String(c)));
        if (c instanceof Error && c.stack) {
            lines.push(c.stack);
        }
        appendCauseChain(c, lines, depth + 1);
    }

    function stringifyReason(reason) {
        if (reason instanceof Error) {
            return reason.message || String(reason);
        }
        if (reason && typeof reason === 'object') {
            try {
                return JSON.stringify(reason);
            } catch (e) {
                return String(reason);
            }
        }
        return typeof reason === 'string' ? reason : String(reason);
    }

    /**
     * Normalize any thrown / rejected value into message, stack, name — including
     * Error.cause, AggregateError (modern browsers), and plain objects.
     */
    function normalizeToParts(reason) {
        const parts = [];
        let message = '';
        let stack = null;
        let name = 'Error';

        if (typeof AggregateError !== 'undefined' && reason instanceof AggregateError) {
            name = reason.name || 'AggregateError';
            message = reason.message || stringifyReason(reason);
            const inner = reason.errors;
            if (inner && inner.length) {
                const sub = [];
                for (let i = 0; i < inner.length; i++) {
                    sub.push(
                        '(' +
                            (i + 1) +
                            ') ' +
                            (inner[i] instanceof Error ? inner[i].message : stringifyReason(inner[i])),
                    );
                }
                parts.push('Aggregate: ' + sub.join('; '));
            }
            if (reason.stack) {
                parts.unshift(reason.stack);
            }
            appendCauseChain(reason, parts, 0);
            stack = parts.filter(Boolean).join('\n\n');
            return { message: message, stack: stack, name: name };
        }

        if (reason instanceof Error) {
            name = reason.name || 'Error';
            message = reason.message || '';
            stack = reason.stack || null;
            appendCauseChain(reason, parts, 0);
            if (parts.length) {
                stack = (stack ? stack + '\n\n' : '') + parts.join('\n\n');
            }
            return { message: message || stringifyReason(reason), stack: stack, name: name };
        }

        message = stringifyReason(reason);
        return { message: message, stack: null, name: 'UnhandledRejection' };
    }

    function buildFrameworkInfo(category, extra) {
        const payload = { b: detectBundler(), c: category || 'javascript' };
        if (extra && typeof extra === 'object') {
            for (const k in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, k)) {
                    payload[k] = extra[k];
                }
            }
        }
        try {
            return JSON.stringify(payload);
        } catch (e) {
            return '{"b":"unknown","c":"javascript"}';
        }
    }

    function send(payload) {
        const token = csrfToken();
        if (!token) {
            return;
        }

        const body = JSON.stringify(payload);

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            keepalive: true,
            body,
        }).catch(function () {});
    }

    function normalizeError(reason) {
        if (reason instanceof Error) {
            return reason;
        }
        return new Error(typeof reason === 'string' ? reason : JSON.stringify(reason));
    }

    function captureException(reason, extra) {
        extra = extra || {};
        const err = reason instanceof Error ? reason : normalizeError(reason);
        const parts = normalizeToParts(err);
        const category =
            extra.category ||
            classifyBundleCategory(parts.message, parts.name || err.name);
        const finfo =
            extra.framework_info != null
                ? extra.framework_info
                : buildFrameworkInfo(category, extra.framework_extra);

        send({
            message: parts.message || err.message || 'Error',
            stack: extra.stack != null ? extra.stack : parts.stack || err.stack || null,
            name: extra.name || parts.name || err.name || 'Error',
            filename: err.fileName || null,
            lineno: typeof err.lineNumber === 'number' ? err.lineNumber : null,
            colno: typeof err.columnNumber === 'number' ? err.columnNumber : null,
            page_url: window.location.href,
            component_stack: extra.component_stack || null,
            framework_info: finfo,
        });
    }

    function captureResourceFailure(tagName, url) {
        const category = 'resource-load';
        const msg =
            'Failed to load ' +
            (tagName || 'resource').toLowerCase() +
            (url ? ': ' + url : '');
        send({
            message: msg,
            stack: null,
            name: 'ResourceError',
            filename: url || null,
            lineno: null,
            colno: null,
            page_url: window.location.href,
            framework_info: buildFrameworkInfo(category, {
                tag: tagName || null,
                url: url || null,
            }),
        });
    }

    window.Guardian = window.Guardian || {};
    window.Guardian.captureException = captureException;

    window.addEventListener(
        'error',
        function (event) {
            if (event.defaultPrevented) {
                return;
            }

            const t = event.target;
            if (
                t &&
                t !== window &&
                t !== document &&
                (t.src || t.href) &&
                typeof t.tagName === 'string' &&
                !event.error
            ) {
                const url = t.src || t.href || '';
                captureResourceFailure(t.tagName, url);
                return;
            }

            const msg = event.message || '';
            const errObj = event.error;
            const execName =
                errObj && errObj.name ? errObj.name : 'Error';
            const category = classifyBundleCategory(msg, execName);

            const baseStack =
                errObj && errObj.stack
                    ? errObj.stack
                    : null;
            let stackOut = baseStack;
            if (errObj instanceof Error) {
                const p = normalizeToParts(errObj);
                stackOut = p.stack || baseStack;
            }

            send({
                message: msg || 'Script error',
                stack: stackOut,
                name: execName,
                filename: event.filename || null,
                lineno: typeof event.lineno === 'number' ? event.lineno : null,
                colno: typeof event.colno === 'number' ? event.colno : null,
                page_url: window.location.href,
                framework_info: buildFrameworkInfo(category),
            });
        },
        true,
    );

    window.addEventListener('unhandledrejection', function (event) {
        const parts = normalizeToParts(event.reason);
        const category = classifyBundleCategory(parts.message, parts.name);
        send({
            message: parts.message,
            stack: parts.stack,
            name: parts.name || 'UnhandledRejection',
            filename: null,
            lineno: null,
            colno: null,
            page_url: window.location.href,
            framework_info: buildFrameworkInfo(category),
        });
    });

    if (captureConsoleError && typeof console !== 'undefined' && console.error) {
        const originalConsoleError = console.error.bind(console);
        console.error = function () {
            originalConsoleError.apply(console, arguments);
            const first = arguments[0];
            if (first instanceof Error) {
                captureException(first, { name: 'ConsoleError', category: 'console' });
            }
        };
    }
})();