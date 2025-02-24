# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security seriously at LMStudio PHP. If you discover a security vulnerability within LMStudio PHP, please follow these steps:

1. **DO NOT** open a public GitHub issue
2. Send an email to [security@example.com] with:
   - A description of the vulnerability
   - Steps to reproduce the issue
   - Possible impacts
   - Any suggested fixes (if you have them)

You should receive a response within 48 hours. If the issue is confirmed, we will:

1. Acknowledge your report
2. Work on a fix
3. Release a security patch
4. Credit you in the security advisory (unless you prefer to remain anonymous)

## Security Best Practices

When using LMStudio PHP in your projects:

1. Always use HTTPS when making API calls to LMStudio
2. Keep your dependencies up to date
3. Use environment variables for sensitive configuration
4. Follow the principle of least privilege when setting up API access
5. Monitor your application logs for suspicious activity

## Disclosure Policy

When we receive a security bug report, we will:

1. Confirm the problem and determine affected versions
2. Audit code to find any similar problems
3. Prepare fixes for all supported versions
4. Release new versions and update the CHANGELOG
5. Announce the problem and fixes in a security advisory

## Comments on Security

LMStudio PHP is designed to interact with local LMStudio instances. While this reduces some attack vectors, you should still:

1. Run LMStudio on a trusted network
2. Use appropriate firewall rules
3. Keep both LMStudio and this package updated
4. Monitor system resources and API usage

## Contact

For security-related inquiries, contact:

- Email: [security@example.com]
- GPG Key: [Your GPG key if available]
