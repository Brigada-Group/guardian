{{--
  Guardian browser error reporter.

  Include as EARLY as possible after <meta name="csrf-token"> (start of <body> or end of <head>).
  Do not use defer here: deferred scripts run after the full document is parsed, so errors in
  normal inline or sync scripts earlier in the page would fire before these listeners exist.
--}}
@if (config('guardian.client_errors.enabled', true))
<script>
  window.GuardianClientErrors = {
    url: @json(route('guardian.client-errors')),
    captureConsoleError: @json(config('guardian.client_errors.capture_console_error', false)),
  };
</script>
<script src="{{ asset('vendor/guardian/guardian-client.js') }}"></script>
@endif
