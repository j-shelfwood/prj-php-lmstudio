# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-03-01

### Added

- Comprehensive documentation in README.md with examples for all features
- Added TTL support for model loading
- Added support for auto-evict configuration
- Improved error handling in streaming responses
- Added Laravel 12 compatibility

### Changed

- Enhanced documentation with more detailed examples
- Improved code organization and reduced duplication
- Updated configuration examples with all available options
- Updated dependencies to support both Laravel 10 and Laravel 12
- Updated Symfony Console dependency to support both v6.4 and v7.0

## [1.1.0] - 2024-03-01

### Added

- New `ConfigAwareInterface` for accessing client configuration
- New `BaseCommand` class to reduce code duplication in commands
- Interactive `chat` command for chatting with language models
- Added version number to composer.json
- Added CHANGELOG.md file
- Enhanced streaming support with retries, timeouts, and better error handling
- Added `StreamingException` class for better error diagnostics
- Added health check functionality to verify LMStudio server connection
- Added new configuration options for connection timeouts and retries
- Improved tool call handling with better JSON parsing
- Added `DebugLogger` class for structured and configurable logging
- Added support for file-based logging with configurable verbosity
- Added PSR-3 logger compatibility for integration with existing logging systems

### Changed

- Refactored `Chat` and `Sequence` commands to extend `BaseCommand`
- Removed reflection usage in commands in favor of proper interfaces
- Improved code organization and reduced duplication
- Enhanced error handling in commands
- Updated configuration file with new options for streaming and health checks
- Replaced direct debug echo statements with structured logging
- Improved streaming response handling with better error recovery
- Enhanced tool call handling with incremental JSON parsing

### Fixed

- Fixed type inconsistencies in client implementations
- Improved error handling in streaming responses
- Fixed JSON parsing issues in tool call arguments
- Added retry mechanism for failed streaming requests
- Fixed potential memory leaks in streaming response handling
- Improved handling of idle connections during streaming

## [1.0.0] - 2024-02-22

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
