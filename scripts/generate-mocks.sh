#!/bin/bash

# Configuration
DEFAULT_MODEL="qwen2.5-7b-instruct"
BASE_URL="http://localhost:1234"
MOCKS_DIR="tests/mocks"
API_PATH="api/v0"

# Use first argument as model or default
MODEL=${1:-$DEFAULT_MODEL}

# Check if server is running
if ! curl -s "$BASE_URL/api/v0/models" > /dev/null; then
    echo "Error: LM Studio server is not running at $BASE_URL"
    echo "Please start the server first"
    exit 1
fi

# Helper Functions
generate_mock() {
    local endpoint=$1
    local data=$2
    local output_file=$3
    local description=$4

    echo "Generating mock: $description"
    response=$(curl -s "$BASE_URL/$API_PATH/$endpoint" \
        -H "Content-Type: application/json" \
        -d "$data")

    if [ $? -eq 0 ] && echo "$response" | jq . >/dev/null 2>&1; then
        echo "$response" | jq . > "$output_file"
        echo "✓ Generated $output_file"
    else
        echo "✗ Failed to generate $output_file"
        echo "Response: $response"
    fi
}

generate_stream_mock() {
    local endpoint=$1
    local data=$2
    local output_file=$3
    local description=$4

    echo "Generating streaming mock: $description"
    curl -s "$BASE_URL/$API_PATH/$endpoint" \
        -H "Content-Type: application/json" \
        -d "$data" > "$output_file"

    if [ $? -eq 0 ] && [ -s "$output_file" ]; then
        echo "✓ Generated $output_file"
    else
        echo "✗ Failed to generate $output_file"
    fi
}

# Create mock directories (including completions and embeddings)
mkdir -p "$MOCKS_DIR"/{models,chat,completions,streaming,embeddings}

# Tool definitions as heredocs for better readability
read -r -d '' WEATHER_TOOL << 'EOF'
{
  "type": "function",
  "function": {
    "name": "get_current_weather",
    "description": "Get the current weather",
    "parameters": {
      "type": "object",
      "properties": {
        "location": {
          "type": "string",
          "description": "The location to get weather for"
        },
        "unit": {
          "type": "string",
          "enum": ["celsius", "fahrenheit"],
          "description": "Temperature unit"
        }
      },
      "required": ["location"]
    }
  }
}
EOF

read -r -d '' SEARCH_TOOL << 'EOF'
{
  "type": "function",
  "function": {
    "name": "search_products",
    "description": "Search the product catalog",
    "parameters": {
      "type": "object",
      "properties": {
        "query": {
          "type": "string",
          "description": "Search terms"
        },
        "category": {
          "type": "string",
          "enum": ["electronics", "books", "clothing"]
        },
        "max_price": {
          "type": "number"
        }
      },
      "required": ["query"]
    }
  }
}
EOF

# Models list
echo "Fetching models list..."
curl -s "$BASE_URL/$API_PATH/models" | jq . > "$MOCKS_DIR/models/list.json"

# [OPTIONAL] Model info - uncomment to generate mock for /api/v0/models/{model}
# echo "Generating model info mock..."
# generate_mock "models/$MODEL" '{}' "$MOCKS_DIR/models/info.json" "Model info request"

# Chat completions mocks
echo "Generating chat completions mocks..."

# Basic chat completion
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the capital of France?"}
  ],
  "temperature": 0.7
}' "$MOCKS_DIR/chat/standard-response.json" "Standard chat completion"

# Single tool call
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "tools": ['"$WEATHER_TOOL"'],
  "tool_choice": "auto",
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-single-response.json" "Single tool call"

# Multiple tools
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "Find me weather-appropriate clothing in London"}
  ],
  "tools": ['"$WEATHER_TOOL"','"$SEARCH_TOOL"'],
  "tool_choice": "auto",
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-multiple-response.json" "Multiple tools"

# Forced tool choice
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "tools": ['"$WEATHER_TOOL"'],
  "tool_choice": {"type": "function", "function": {"name": "get_current_weather"}},
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-forced-response.json" "Forced tool choice"

# Multi-turn tool conversation
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What should I wear in London today?"},
    {"role": "assistant", "tool_calls": [{
      "id": "call_1",
      "type": "function",
      "function": {
        "name": "get_current_weather",
        "arguments": "{\"location\":\"London\"}"
      }
    }]},
    {"role": "tool", "content": "{\"temperature\": 18, \"condition\": \"sunny\"}"},
    {"role": "assistant", "content": "Let me find appropriate clothing for sunny weather."},
    {"role": "assistant", "tool_calls": [{
      "id": "call_2",
      "type": "function",
      "function": {
        "name": "search_products",
        "arguments": "{\"query\":\"summer casual wear\",\"category\":\"clothing\"}"
      }
    }]}
  ],
  "tools": ['"$WEATHER_TOOL"','"$SEARCH_TOOL"'],
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-conversation-response.json" "Multi-turn tool conversation"

# Invalid tool parameters
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the weather?"}
  ],
  "tools": ['"$WEATHER_TOOL"'],
  "tool_choice": {"type": "function", "function": {"name": "get_current_weather"}},
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-error-missing-required.json" "Missing required parameter"

# Invalid tool name
generate_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "tools": ['"$WEATHER_TOOL"'],
  "tool_choice": {"type": "function", "function": {"name": "nonexistent_function"}},
  "temperature": 0.7
}' "$MOCKS_DIR/chat/tool-error-invalid-name.json" "Invalid tool name"

# Completions (Text Completions) mocks
echo "Generating completions mocks..."

# Standard text completion
generate_mock "completions" '{
  "model": "'$MODEL'",
  "prompt": "The meaning of life is",
  "max_tokens": 10
}' "$MOCKS_DIR/completions/standard-response.json" "Standard text completion"

# Completion with stop tokens
generate_mock "completions" '{
  "model": "'$MODEL'",
  "prompt": "Complete this: Once upon a time",
  "stop": ["\n", "."]
}' "$MOCKS_DIR/completions/stop-tokens-response.json" "Completion with stop tokens"

# Completion with temperature
generate_mock "completions" '{
  "model": "'$MODEL'",
  "prompt": "Write a very creative short story, starting with: In a world where",
  "temperature": 1.2
}' "$MOCKS_DIR/completions/temperature-response.json" "Completion with temperature"


# Embeddings mocks
echo "Generating embeddings mocks..."

# Standard embeddings request
generate_mock "embeddings" '{
  "model": "text-embedding-nomic-embed-text-v1.5", # <-- !!! REPLACE WITH YOUR EMBEDDING MODEL NAME !!!
  "input": "This is a sample text to embed."
}' "$MOCKS_DIR/embeddings/standard-response.json" "Standard embeddings request"

# [OPTIONAL] Embeddings request with multiple inputs - uncomment if your API supports it
# generate_mock "embeddings" '{
#   "model": "text-embedding-nomic-embed-text-v1.5", # <-- !!! REPLACE WITH YOUR EMBEDDING MODEL NAME !!!
#   "input": ["Text input 1", "Text input 2"]
# }' "$MOCKS_DIR/embeddings/multiple-inputs-response.json" "Embeddings with multiple inputs"


# Streaming chat mocks
echo "Generating streaming chat mocks..."

# Streaming chat
generate_stream_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "Write a short story"}
  ],
  "stream": true,
  "temperature": 0.7
}' "$MOCKS_DIR/streaming/chat-stream.txt" "Basic streaming"

# Streaming tool calls
generate_stream_mock "chat/completions" '{
  "model": "'$MODEL'",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "tools": ['"$WEATHER_TOOL"'],
  "tool_choice": "auto",
  "stream": true,
  "temperature": 0.7
}' "$MOCKS_DIR/streaming/tool-stream.txt" "Tool streaming"

echo "Mock generation complete. Generated mocks in: $MOCKS_DIR"