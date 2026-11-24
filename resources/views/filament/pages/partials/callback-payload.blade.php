<div class="space-y-2 text-sm">
    <p><strong>SHA-256:</strong> <code>{{ $entry->payload_sha256 }}</code></p>
    <p class="text-gray-500">{{ __('Secrets are redacted before storage.') }}</p>
    <pre class="overflow-auto rounded bg-gray-950 p-3 text-xs text-gray-100">{{ json_encode($entry->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
