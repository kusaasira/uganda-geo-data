# Continuous integration contract

Pull requests to `main` or `next/2.x` and pushes to those branches must pass required checks before integration.

The quality matrix tests PHP 8.2, 8.3 and 8.4 with highest compatible dependencies. PHP 8.2 additionally tests the lowest dependency set. Every matrix job runs PHPUnit, coding standards, PHPStan level 9 and a consumer-style package smoke test.

A separate job audits production dependencies only when at least one third-party production package is installed. When the library has no third-party runtime packages, the audit reports this fact and succeeds without incorrectly flagging an empty dependency set. Any actual package vulnerabilities still fail the job. CI has read-only repository permissions and cancels obsolete runs for the same pull request.

The source remains a Composer library rather than a Laravel-dependent application. PHP version policy is defined in package metadata and must be verified by its release candidate before stable publication.

When the dependency solver reports incompatible minimum versions, update only the minimum-version job's constraints with evidence. Do not suppress genuine failures or skip test suites.
