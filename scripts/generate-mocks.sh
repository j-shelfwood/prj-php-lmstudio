#!/bin/bash

# Configurable variables
DEFAULT_MODEL="test-model"
BASE_URL="http://localhost:1234"
MOCKS_DIR="tests/mocks"

# Use first argument as model or default
MODEL=${1:-$DEFAULT_MODEL}

# Generate for both API versions with correct base paths
for API_VERSION in v0 v1; do
  # Set correct API path for each version
  if [ "$API_VERSION" = "v0" ]; then
    API_PATH="api/v0"
  else
    API_PATH="v1"
  fi

  # Create version-specific directories
  mkdir -p "$MOCKS_DIR/$API_VERSION"/{models,chat,completions,streaming,embeddings}

  # Get model list with correct API path
  MODEL_LIST=$(curl -s "$BASE_URL/$API_PATH/models")

  # Check if we got a valid response
  if ! echo "$MODEL_LIST" | jq -e . >/dev/null 2>&1; then
    echo "Error: Failed to get models for $API_VERSION at $API_PATH/models"
    echo "Response received:"
    echo "$MODEL_LIST"
    continue
  fi

  echo "$MODEL_LIST" | jq . > "$MOCKS_DIR/$API_VERSION/models/list.json"

  # Validate model exists
  if ! echo "$MODEL_LIST" | jq -e ".data[] | select(.id == \"$MODEL\")" > /dev/null; then
    echo "Error: Model $MODEL not found in $API_VERSION available models."
    echo "Available $API_VERSION models:"
    echo "$MODEL_LIST" | jq -r '.data[].id'
    continue  # Skip this API version but continue with others
  fi

  # Generate mock responses
  generate_mock() {
    local endpoint=$1
    local data=$2
    local output_file=$3

    curl -s "$BASE_URL/$API_PATH/$endpoint" \
      -H "Content-Type: application/json" \
      -d "$data" | jq . > "$output_file"
  }

  # Chat completion
  generate_mock "chat/completions" "$(cat <<EOF
{
  "model": "$MODEL",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "temperature": 0.7
}
EOF
  )" "$MOCKS_DIR/$API_VERSION/chat/standard-response.json"

  # Chat completion with tools
  generate_mock "chat/completions" "$(cat <<EOF
{
  "model": "$MODEL",
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
}
EOF
  )" "$MOCKS_DIR/$API_VERSION/chat/tool-response.json"

  # Streaming chat completion
  curl -s "$BASE_URL/$API_PATH/chat/completions" \
    -H "Content-Type: application/json" \
    -d "$(cat <<EOF
{
  "model": "$MODEL",
  "messages": [
    {"role": "user", "content": "What is the weather in London?"}
  ],
  "stream": true,
  "temperature": 0.7
}
EOF
  )" > "$MOCKS_DIR/$API_VERSION/streaming/chat-stream.txt"

  # Text completion
  generate_mock "completions" "$(cat <<EOF
{
  "model": "$MODEL",
  "prompt": "Once upon a time",
  "max_tokens": 50,
  "temperature": 0.7
}
EOF
  )" "$MOCKS_DIR/$API_VERSION/completions/standard-response.json"

  # Embeddings
  generate_mock "embeddings" "$(cat <<EOF
{
  "model": "$MODEL",
  "input": "The food was delicious and the waiter..."
}
EOF
  )" "$MOCKS_DIR/$API_VERSION/embeddings/standard-response.json"

  echo "Generated $API_VERSION mocks for model '$MODEL' in: $MOCKS_DIR/$API_VERSION"
done

echo "Mock generation complete for both API versions"