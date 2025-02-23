# LMStudio PHP Integration

This document outlines our learnings and requirements for creating a robust PHP integration with LMStudio's local API.

## Background

LMStudio provides a local API that mimics OpenAI's API structure, allowing for running Large Language Models locally. While the API is similar to OpenAI's, there are some key differences and challenges that need to be addressed for robust integration.

## Key Learnings

### API Communication

1. **Connection Management**

   - LMStudio runs locally (default: `localhost:1234`)
   - Need robust connection handling with appropriate timeouts
   - Should handle cases where LMStudio is not running or inaccessible
   - Consider retry mechanisms for transient failures

2. **Streaming Responses**
   - LMStudio supports streaming responses similar to OpenAI
   - Responses come in chunks that need to be properly buffered
   - Tool calls can be split across multiple chunks
   - Need to handle both content and tool calls in the stream

### Tool Calls

1. **Format Differences**

   - LMStudio supports both native tool calls and embedded tool calls in content
   - Tool calls can come in two formats:

     ```json
     // Native format (in tool_calls field)
     {
       "id": "call_xyz",
       "type": "function",
       "function": {
         "name": "action_name",
         "arguments": "..."
       }
     }

     // Embedded format (in content)
     <tool_call>
     {
       "name": "action_name",
       "arguments": {
         "arg1": "value1"
       }
     }
     </tool_call>
     ```

2. **Streaming Challenges**
   - Tool calls can be split across multiple chunks
   - Need to buffer until complete JSON is received
   - Must handle both formats simultaneously
   - Should clean tool calls from content when embedded

### Content Structure

1. **Message Format**

   ```php
   [
     'content' => [
       'message' => 'The actual message content',
       'action' => [
         'name' => 'action_name',
         'args' => ['parsed', 'arguments']
       ]
     ]
   ]
   ```

2. **Content Processing**
   - Need to properly trim whitespace
   - Handle markdown formatting
   - Clean up tool call markers from content
   - Maintain proper content structure throughout

## Package Requirements

### Core Features

1. **Configuration**

   - Easy configuration of LMStudio connection details
   - Configurable timeouts and retry settings
   - Debug mode for detailed logging

2. **Laravel Integration**

   - Facade for easy access
   - Service Provider for configuration
   - Laravel-style configuration file
   - Event system integration

3. **Streaming Support**

   - Proper handling of streaming responses
   - Event-driven updates for real-time processing
   - Buffer management for tool calls
   - Error handling for incomplete streams

4. **Tool Call Management**
   - Support for registering available tools
   - Validation of tool arguments
   - Proper parsing and handling of both formats
   - Clean separation of tools from content

### API Design

```php
// Configuration
'lmstudio' => [
    'host' => 'localhost',
    'port' => 1234,
    'timeout' => 60,
    'retry_attempts' => 3,
    'retry_delay' => 100,
]

// Basic Usage
$response = LMStudio::chat()
    ->withMessages($messages)
    ->withTools($tools)
    ->stream();

// Streaming with Events
LMStudio::chat()
    ->withMessages($messages)
    ->stream(function($chunk) {
        // Handle each chunk
        if ($chunk->hasToolCall()) {
            // Process tool call
        }
        // Process content
    });

// Tool Registration
LMStudio::registerTool('action_name', [
    'description' => '...',
    'parameters' => [
        'arg1' => ['type' => 'string', 'description' => '...'],
    ],
    'required' => ['arg1'],
]);
```

### Error Handling

1. **Connection Errors**

   - Clear error messages for connection failures
   - Proper exception hierarchy
   - Retry mechanism for transient failures

2. **Parsing Errors**

   - Handle incomplete JSON gracefully
   - Proper logging of parsing failures
   - Recovery mechanisms for partial responses

3. **Tool Call Errors**
   - Validation of tool call format
   - Handling of malformed tool calls
   - Proper error propagation

## Testing Requirements

1. **Unit Tests**

   - Test all parsing logic
   - Test configuration handling
   - Test tool registration and validation

2. **Integration Tests**

   - Test actual LMStudio communication
   - Test streaming functionality
   - Test error conditions

3. **Mock Tests**
   - Provide mock responses for testing
   - Test error conditions
   - Test timeout scenarios

## Documentation Requirements

1. **Installation Guide**

   - Clear prerequisites
   - Step-by-step installation
   - Configuration options

2. **Usage Examples**

   - Basic usage patterns
   - Streaming examples
   - Tool registration and usage
   - Error handling examples

3. **API Reference**
   - Complete method documentation
   - Configuration options
   - Event documentation
   - Exception documentation

## Future Considerations

1. **Performance Optimization**

   - Connection pooling
   - Response caching
   - Batch processing

2. **Security**

   - Input validation
   - Rate limiting
   - Authentication support

3. **Monitoring**
   - Performance metrics
   - Error tracking
   - Usage statistics
