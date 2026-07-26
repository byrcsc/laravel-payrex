# Security Policy

## Reporting a vulnerability in this package

Please report privately, not in a public issue. Use GitHub's
[private vulnerability reporting](https://github.com/byrcsc/laravel-payrex/security/advisories/new)
on this repository; it opens a channel visible only to the maintainers.

Include the package version, the Laravel and PHP versions, and enough detail to
reproduce. If it helps, a failing test is the clearest possible report.

You can expect an acknowledgement within a week. Because this is a
single-maintainer package, please do not expect a same-day response; if the
issue is being actively exploited, say so in the title.

## Reporting a vulnerability in PayRex

**This package is unofficial and not affiliated with PayRex.** A vulnerability
in the PayRex API, dashboard, or hosted checkout pages belongs to PayRex, not
here. Contact them directly at <https://payrexhq.com>. Reporting it here only
delays it reaching the people who can fix it.

If you are unsure which one you have found, report it here and it will be
redirected.

## Supported versions

Security fixes are released for the latest package version and are not
backported. Keep your dependency constraint current.

## What this package does and does not protect

It **does**:

- verify inbound webhook signatures with `hash_equals()` (constant time),
  against the raw request body, in middleware that runs before your listeners;
- keep secrets out of exception messages; a `PayrexException` carries the
  method, URL, and status only;
- refuse to retry mutating requests, so an ambiguous timeout cannot become a
  duplicate charge.

It **does not**:

- provide replay protection beyond the freshness window. PayRex retries failed
  deliveries for up to three days, so a valid event can legitimately arrive more
  than once. Deduplicate on the PayRex event ID in your own listeners;
- validate amounts beyond the payment intent range PayRex documents outright
  (₱20 to ₱59,999,999.99, checked locally); every other limit is enforced
  server-side and surfaces as an `InvalidRequestException`;
- encrypt or otherwise guard your `PAYREX_SECRET_KEY` at rest. Treat it as you
  would any other production credential.
