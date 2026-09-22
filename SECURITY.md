# Security Policy

## Supported Versions

| Version | Security Fixes |
|---------|---------------|
| 1.x     | ✅ Active      |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Report security issues by email to **usman@zentiqlabs.com** with the subject line:

```
[fast-pdf] Security Vulnerability Report
```

Include:

- A description of the vulnerability and the conditions required to trigger it.
- The affected version(s).
- Steps to reproduce or a proof-of-concept (where safe to share).
- Your assessment of impact and severity.

You will receive an acknowledgement within **48 hours** and a resolution timeline within **7 days**. We follow responsible disclosure: a CVE and public advisory are issued after a fix is released, crediting the reporter unless they prefer anonymity.

---

## Security Architecture

### How the package isolates Chromium

`fast-pdf` renders HTML by invoking a local headless Chromium binary through OS process pipes. Chromium runs as a **child process** of the PHP web worker with the following hardened defaults:

| Flag | Purpose |
|------|---------|
| `--disable-local-file-access` | Prevents the rendered page from reading arbitrary `file://` paths on the server (e.g. via `<iframe src="file:///etc/passwd">`) |
| `--allow-file-access-from-files=false` | Disallows cross-origin `file://` requests originating from the document itself |
| `--disable-web-security=false` | Ensures same-origin policy remains enforced |

These flags are **on by default** and can only be relaxed by explicitly setting `allow_local_file_access => true` in the configuration — a deliberate opt-in that should never be applied when rendering user-supplied HTML.

---

## Production Security Recommendations

### 1. Run Chromium under a dedicated non-root system account

Never execute Chromium as `root`. Create a dedicated system user with no login shell and no write access to application directories:

```bash
useradd --system --no-create-home --shell /sbin/nologin chromium-renderer
```

Configure PHP-FPM (or your application server) to spawn Chromium under this account, or use a separate queue worker that runs under it.

### 2. Escape all user-supplied content before rendering

`fast-pdf` makes no attempt to sanitize HTML. Any string passed to `fromHtml()` or rendered by `fromFile()` is forwarded verbatim to Chromium. If the HTML contains user input, sanitize it first:

```php
// Strip disallowed tags and attributes before generating the PDF
$safeHtml = strip_tags($userInput, ['p', 'b', 'i', 'ul', 'li', 'br']);

$pdf->fromHtml($safeHtml)->save('/var/output/doc.pdf');
```

For richer HTML, use a purpose-built HTML sanitizer library rather than relying on `strip_tags`.

### 3. Never enable `allow_local_file_access` for user-supplied content

The `allow_local_file_access` configuration key exists to support trusted internal use cases (e.g. rendering templates that embed local fonts). It **must not** be enabled when:

- The HTML originates from a user, an API call, or any external system.
- The HTML is assembled from user-controlled data without full sanitization.

### 4. Disable outbound network access for untrusted renders

When rendering untrusted HTML, prevent Chromium from making outbound HTTP requests that could exfiltrate data or load remote payloads:

```php
// In Docker: use network namespaces or iptables rules to block egress.
// In config: add the flag to restrict network access at the OS level.
$pdf = new FastPdf([
    'chromium_flags' => ['--disable-dev-shm-usage', '--disable-background-networking'],
]);
```

For the strongest isolation, run the renderer in a network-namespace-isolated container with no internet egress.

### 5. Use the `containerized` flag correctly

The `containerized` config key adds `--no-sandbox` and `--disable-setuid-sandbox` to the Chromium invocation. These flags **disable the OS user-namespace sandbox**. Only set this to `true` when:

- Chromium is running inside a Docker container where the Linux sandbox cannot be used.
- The container itself provides equivalent isolation (network policies, seccomp profiles, read-only filesystem mounts).

**Never** set `containerized => true` on bare-metal or VM deployments — the sandbox provides meaningful defence in depth against renderer exploits.

### 6. Restrict the temporary directory

The package writes intermediate HTML and PDF files to `temp_dir` (default: `sys_get_temp_dir()`). In production, point this to a dedicated directory with permissions restricted to the rendering process:

```bash
mkdir -p /var/run/fast-pdf-tmp
chown chromium-renderer:chromium-renderer /var/run/fast-pdf-tmp
chmod 700 /var/run/fast-pdf-tmp
```

```php
$pdf = new FastPdf(['temp_dir' => '/var/run/fast-pdf-tmp']);
```

---

## Changelog of Security-Relevant Changes

| Version | Change |
|---------|--------|
| 1.0.0   | Initial release with `--disable-local-file-access` hardening enabled by default; `containerized` flag required to add sandbox-bypass flags |
