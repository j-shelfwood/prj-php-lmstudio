# LMStudio PHP

A PHP package for integrating with LMStudio's local API. This library allows you to interact with LMStudio via both OpenAI‑compatible endpoints (/v1) and the new LM Studio REST API endpoints (/api/v0). It supports chat and text completions, embeddings, tool calls (including streaming responses), and is designed with dependency injection in mind.

- PHP 8.2

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

- **Dependency Injection & Extensibility:**

  - Designed to work seamlessly with DI containers (e.g., Laravel's service container).
  - Interfaces for the API client and streaming response handler for easier customization.

- **Laravel Integration:**
  - Provides a Laravel service provider and facade for effortless integration.
  - Artisan commands available for interactive usage (e.g., Chat, Models, Tools, ToolResponse).

## Contributing

Contributions are welcome!

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
