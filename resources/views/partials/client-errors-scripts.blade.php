{{-- Guardian browser error reporter: include before </body> in your app layout or Inertia root (e.g. resources/views/app.blade.php). --}}
@if (config('guardian.client_errors.enabled', true))
<script>
  window.GuardianClientErrors = {
    url: @json(route('guardian.client-errors')),
    captureConsoleError: @json(config('guardian.client_errors.capture_console_error', false)),
  };
</script>
<script src="{{ asset('vendor/guardian/guardian-client.js') }}" defer></script>
@endif
