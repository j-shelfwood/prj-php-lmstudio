# LMStudio PHP

A PHP package for integrating with LMStudio's local API. This library allows you to interact with LMStudio via both OpenAI‑compatible endpoints (/v1) and the new LM Studio REST API endpoints (/api/v0). It supports chat and text completions, embeddings, tool calls (including streaming responses), and is designed with dependency injection in mind.

- PHP 8.2
- Version 1.1.0

## Features

- **Fully type hinted objects for building chats/requests and responses**

- **OpenAI Compatibility Endpoints (/v1):**

  - List models, get model information, chat completions, text completions, and embeddings.
  - Support for streaming responses and tool call handling.

- **LM Studio REST API Endpoints (/api/v0):**

  - List all available models and get detailed model info.
  - Create chat completions, text completions, and embeddings using the REST API.

- **Tool Calls & Structured Output:**

  - Parse and execute tool calls requested by LMStudio.
  - Accumulate partial streaming chunks for tool calls.
  - [Learn more about using tool functions](docs/tool-functions.md)

- **Dependency Injection & Extensibility:**

  - Designed to work seamlessly with DI containers (e.g., Laravel's service container).
  - Interfaces for the API client and streaming response handler for easier customization.

- **Laravel Integration:**

  - Provides a Laravel service provider and facade for effortless integration.
  - Artisan commands available for interactive usage (e.g., Chat, Models, Tools, ToolResponse).

- **Command Line Interface:**
  - Interactive chat command for chatting with language models
  - Sequence command for testing all API endpoints
  - Configurable model and API selection

## Installation

You can install the package via composer:

```bash
composer require shelfwood/lmstudio-php
```

## Basic Usage

```php
use Shelfwood\LMStudio\LMStudio;
use Shelfwood\LMStudio\ValueObjects\ChatHistory;
use Shelfwood\LMStudio\ValueObjects\Message;

// Create a new LMStudio instance
$lmstudio = new LMStudio();

// Create a chat history
$history = new ChatHistory([
    Message::system('You are a helpful assistant.'),
    Message::user('Hello, how are you?'),
]);

// Get a response using the OpenAI-compatible API
$response = $lmstudio->openai()->chat($history->toArray(), [
    'model' => 'granite-3.1-8b-instruct',
]);

echo $response->choices[0]->message->content;
```

## Command Line Interface

The package includes a command-line interface for interacting with LMStudio:

```bash
# Interactive chat with a language model
php bin/lmstudio chat

# Run a sequence of API calls to test all endpoints
php bin/lmstudio sequence
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome!

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
