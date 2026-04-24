(function () {
    const opts = window.GuardianClientErrors || {};
    const endpoint = opts.url || '/guardian/client-errors';
    const csrfSelector = 'meta[name="csrf-token"]';
    const captureConsoleError = Boolean(opts.captureConsoleError);
  
    function csrfToken() {
      const el = document.querySelector(csrfSelector);
      return el ? el.getAttribute('content') : '';
    }
  
    function normalizeError(reason) {
      if (reason instanceof Error) return reason;
      return new Error(typeof reason === 'string' ? reason : JSON.stringify(reason));
    }
  
    function send(payload) {
      const token = csrfToken();
      if (!token) return;
  
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
  
    function captureException(reason, extra) {
      extra = extra || {};
      const err = normalizeError(reason);
  
      send({
        message: err.message || 'Error',
        stack: err.stack || null,
        name: extra.name || err.name || 'Error',
        filename: err.fileName || null,
        lineno: typeof err.lineNumber === 'number' ? err.lineNumber : null,
        colno: typeof err.columnNumber === 'number' ? err.columnNumber : null,
        page_url: window.location.href,
        component_stack: extra.component_stack || null,
        framework_info: extra.framework_info || null,
      });
    }
  
    window.Guardian = window.Guardian || {};
    window.Guardian.captureException = captureException;
  
    window.addEventListener('error', function (event) {
      if (event.defaultPrevented) return;
      send({
        message: event.message || 'Script error',
        stack: event.error && event.error.stack ? event.error.stack : null,
        name: event.error && event.error.name ? event.error.name : 'Error',
        filename: event.filename || null,
        lineno: event.lineno ?? null,
        colno: event.colno ?? null,
        page_url: window.location.href,
      });
    });
  
    window.addEventListener('unhandledrejection', function (event) {
      const err = normalizeError(event.reason);
      send({
        message: err.message,
        stack: err.stack || null,
        name: err.name || 'UnhandledRejection',
        page_url: window.location.href,
      });
    });
  
    if (captureConsoleError && typeof console !== 'undefined' && console.error) {
      var originalConsoleError = console.error.bind(console);
      console.error = function () {
        originalConsoleError.apply(console, arguments);
        var first = arguments[0];
        if (first instanceof Error) {
          captureException(first, { name: 'ConsoleError' });
        }
      };
    }
  })();