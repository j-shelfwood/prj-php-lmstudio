# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Enhanced streaming support with the new `StreamBuilder` class
- Improved tool handling with the new `ToolRegistry` class
- Added `Conversation` class for managing chat conversations
- Added support for conversation serialization and deserialization
- Added support for conversation metadata
- Comprehensive examples for all features
- Refactored `Conversation` class to follow SOLID principles:
  - Added `ConversationBuilder` for fluent API
  - Added `ConversationSerializer` for serialization/deserialization
  - Added `ConversationToolHandler` for tool-related functionality
  - Added `ConversationStreamHandler` for streaming functionality
- Updated command files to use the `ConversationBuilder` pattern:
  - Refactored `Chat` command to use the builder pattern
  - Refactored `Sequence` command to use the builder pattern
  - Refactored `ToolTest` command to use the builder pattern
  - Updated `LMStudio::createConversation()` to use the builder pattern
- Added CLI chat application example
- Added tests for all new classes and features

### Changed

- Updated the Chat command to use the new streaming and tool APIs
- Improved error handling in streaming responses
- Enhanced documentation with examples of the new APIs

## [1.2.1] - 2023-03-15

### Added

- Support for structured output with JSON Schema
- Support for model options (TTL and JIT loading)
- Support for model information endpoint

### Fixed

- Fixed issue with streaming responses not being properly parsed
- Fixed issue with tool calls not being properly accumulated

## [1.2.0] - 2023-02-28

### Added

- Support for tool functions
- Support for streaming tool calls
- Support for accumulating tool calls from streaming responses

### Changed

- Improved error handling for streaming responses
- Enhanced documentation with examples of tool usage

## [1.1.0] - 2023-02-15

### Added

- Support for the LM Studio REST API (v0)
- Support for streaming responses
- Support for embeddings

### Changed

- Improved error handling
- Enhanced documentation

## [1.0.0] - 2023-02-01

### Added

- Initial release
- Support for the OpenAI-compatible API (v1)
- Support for chat completions
- Support for text completions
- Laravel integration
