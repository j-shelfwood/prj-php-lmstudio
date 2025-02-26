#!/bin/bash

# Set base URL from your config (localhost:1234 as seen in tests)
BASE_URL="http://localhost:1234/v1"
MOCKS_DIR="tests/mocks"
MODEL="test-model"

# Create mock directory structure
mkdir -p "$MOCKS_DIR"/{models,chat,completions,streaming}

# Get available models
curl -s "$BASE_URL/models" \
  -H "Content-Type: application/json" \
  | jq . > "$MOCKS_DIR/models/list.json"

# Test regular chat completion
curl -s "$BASE_URL/chat/completions" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "'"$MODEL"'",
    "messages": [
      {"role": "user", "content": "What is the weather in London?"}
    ],
    "temperature": 0.7
  }' | jq . > "$MOCKS_DIR/chat/standard-response.json"

# Test chat completion with tools (weather example from your tests)
curl -s "$BASE_URL/chat/completions" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "'"$MODEL"'",
    "messages": [
      {"role": "user", "content": "What is the weather in London?"}
    ],
    "tools": [
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
              }
            },
            "required": ["location"]
          }
        }
      }
    ],
    "tool_choice": "auto",
    "temperature": 0.7
  }' | jq . > "$MOCKS_DIR/chat/tool-response.json"

# Test streaming chat completion
curl -s "$BASE_URL/chat/completions" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "'"$MODEL"'",
    "messages": [
      {"role": "user", "content": "What is the weather in London?"}
    ],
    "stream": true,
    "temperature": 0.7
  }' > "$MOCKS_DIR/streaming/chat-stream.txt"

# Test text completion
curl -s "$BASE_URL/completions" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "'"$MODEL"'",
    "prompt": "Once upon a time",
    "max_tokens": 50,
    "temperature": 0.7
  }' | jq . > "$MOCKS_DIR/completions/standard-response.json"

echo "Mocks generated in $MOCKS_DIR directory"