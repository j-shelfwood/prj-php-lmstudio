# Contributing

Thank you for considering contributing to the LMStudio PHP package! This document outlines the process and guidelines for contributing.

## Development Setup

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:

```bash
composer install
```

## Development Workflow

1. Create a new branch for your feature/fix:

```bash
git checkout -b feature/your-feature-name
```

2. Make your changes, following our coding standards
3. Write or update tests as needed
4. Run tests:

```bash
composer test
```

5. Check code style:

```bash
composer check-style
```

6. Fix code style if needed:

```bash
composer fix-style
```

## Pull Request Process

1. Update the README.md with details of changes to the interface, if applicable
2. Update the CHANGELOG.md with a note describing your changes
3. The PR will be merged once you have the sign-off of at least one maintainer

## Coding Standards

This package follows the PSR-12 coding standard and the PSR-4 autoloading standard.

- Run `composer check-style` to check your code style
- Run `composer fix-style` to automatically fix code style issues

## Running Tests

The package uses Pest PHP for testing. To run tests:

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage
```

## Adding New Features

When adding new features:

1. Add tests for the new feature
2. Update documentation to reflect the changes
3. Add a note to CHANGELOG.md under the [Unreleased] section
4. Ensure all tests pass and code style checks pass

## Reporting Issues

When reporting issues:

1. Describe what you expected to happen
2. Describe what actually happened
3. Include steps to reproduce the issue
4. Include relevant details about your environment:
   - PHP version
   - Laravel version (if applicable)
   - Package version
   - LMStudio version

## Questions or Problems?

If you have questions or problems, please:

1. Check the README.md and existing documentation first
2. Search existing issues
3. If you can't find an answer, open a new issue

## License

By contributing to this project, you agree that your contributions will be licensed under its MIT License.
