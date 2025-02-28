# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-06-01

### Added

- New `ConfigAwareInterface` for accessing client configuration
- New `BaseCommand` class to reduce code duplication in commands
- Interactive `chat` command for chatting with language models
- Added version number to composer.json
- Added CHANGELOG.md file

### Changed

- Refactored `Chat` and `Sequence` commands to extend `BaseCommand`
- Removed reflection usage in commands in favor of proper interfaces
- Improved code organization and reduced duplication
- Enhanced error handling in commands

### Fixed

- Fixed type inconsistencies in client implementations
- Improved error handling in streaming responses

## [1.0.0] - 2024-05-01

### Added

- Initial release
- Support for LM Studio API (v0)
- Support for OpenAI compatibility API (v1)
- Chat completion requests
- Text completion requests
- Embedding requests
- Streaming support
- Tool functions support
- Command-line interface with `sequence` command
