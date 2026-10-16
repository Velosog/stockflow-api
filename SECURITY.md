# Security Policy

StockFlow API is a portfolio project. It is not deployed as a public service,
but security reports are welcome and taken seriously.

## Supported versions

Only the latest release on the `main` branch receives fixes.

## Reporting a vulnerability

Please **do not** open a public issue or pull request for a security problem.

Report it privately to the repository owner, either through GitHub's
"Report a vulnerability" button (Security tab) or through the owner's GitHub
profile: <https://github.com/Velosog>

Include:

- the affected endpoint, file or commit;
- steps to reproduce (a minimal request is ideal);
- the impact you expect (data exposure, privilege escalation, overselling...).

Never include real credentials or personal data in a report.

## What to expect

This is a solo-maintained repository. Reports are acknowledged on a
best-effort basis, usually within a week. Confirmed issues are fixed on
`main`, noted in `CHANGELOG.md`, and credited if you wish.

## Security practices in this repository

- Secrets are read from environment variables only; `.env` is never committed.
- `gitleaks` runs as a pre-commit hook and in CI on the full git history.
- Dependabot monitors Composer packages, GitHub Actions and Docker images.
- `composer audit` runs in CI.
- Production deployments must set `APP_DEBUG=false` and `APP_ENV=production`.
