# LM Studio as a Local LLM API Server

You can serve local LLMs from LM Studio's Developer tab, either on localhost or on the network.

LM Studio's APIs can be used through an OpenAI compatibility mode, ehanced REST API, or through a client library like lmstudio.js.

## API options

- OpenAI Compatibility endpoints
- LM Studio REST API (new, in beta)
- TypeScript SDK - lmstudio.js

## Load and server LLMs from LM Studio

### Run LM Studio as a service (headless)

Starting in v0.3.5, LM Studio can be run as a service without the GUI. This is useful for running LM Studio on a server or in the background on your local machine.

Running LM Studio as a service consists of several new features intended to make it more efficient to use LM Studio as a developer tool.

- The ability to run LM Studio without the GUI
- The ability to start the LM Studio LLM server on machine login, headlessly
- On-demand model loading
- Run the LLM service on machine login

To enable this, head to app settings (Cmd / Ctrl + ,) and check the box to run the LLM server on login.

When this setting is enabled, exiting the app will minimize it to the system tray, and the LLM server will continue to run in the background.

### Just-In-Time (JIT) model loading

Useful when utilizing LM Studio as an LLM service with other frontends or applications.

When JIT loading is ON:

- Call to /v1/models will return all downloaded models, not only the ones loaded into memory
- Calls to inference endpoints will load the model into memory if it's not already loaded

When JIT loading is OFF:

- Call to /v1/models will return only the models loaded into memory
- You have to first load the model into memory before being able to use it

### Idle TTL and Auto-Evict

The counterpart to loading models on-demand is unloading them from memory at the right time.

LM Studio 0.3.9 introduces TTL (unload after some time) and Auto-Evict (limit how many JIT models are loaded at one time).

Read about Idle TTL and Auto-Evict.

### Auto Server Start

Your last server state will be saved and restored on app or service launch.

To achieve this programmatically, you can use the following command:

lms server start

**Pro Tip**

If you haven't already, bootstrap lms on your machine by following the instructions here.

## Community

Chat with other LM Studio developers, discuss LLMs, hardware, and more on the LM Studio Discord server.

Please report bugs and issues in the lmstudio-bug-tracker GitHub repository.

## Idle TTL and Auto-Evict

LM Studio 0.3.9 introduced the ability to set a time-to-live (TTL) for API models, and optionally auto-evict previously loaded models before loading new ones.

These features complement LM Studio's on-demand model loading (JIT) to automate efficient memory management and reduce the need for manual intervention.

Background
JIT loading makes it easy to use your LM Studio models in other apps: you don't need to manually load the model first before being able to use it. However, this also means that models can stay loaded in memory even when they're not being used. [Default: enabled]

(New) Idle TTL (technically: Time-To-Live) defines how long a model can stay loaded in memory without receiving any requests. When the TTL expires, the model is automatically unloaded from memory. You can set a TTL using the ttl field in your request payload. [Default: 60 minutes]

(New) Auto-Evict is a feature that unloads previously JIT loaded models before loading new ones. This enables easy switching between models from client apps without having to manually unload them first. You can enable or disable this feature in Developer tab > Server Settings. [Default: enabled]

### Idle TTL

Use case: imagine you're using an app like Zed, Cline, or Continue.dev to interact with LLMs served by LM Studio. These apps leverage JIT to load models on-demand the first time you use them.

Problem: When you're not actively using a model, you might don't want it to remain loaded in memory.

Solution: Set a TTL for models loaded via API requests. The idle timer resets every time the model receives a request, so it won't disappear while you use it. A model is considered idle if it's not doing any work. When the idle TTL expires, the model is automatically unloaded from memory.

Set App-default Idle TTL
By default, JIT-loaded models have a TTL of 60 minutes. You can configure a default TTL value for any model loaded via JIT like so:

Set a default TTL value. Will be used for all JIT loaded models unless specified otherwise in the request payload

Set per-model TTL-model in API requests
When JIT loading is enabled, the first request to a model will load it into memory. You can specify a TTL for that model in the request payload.

This works for requests targeting both the OpenAI compatibility API and the LM Studio's REST API:

```
curl http://localhost:1234/api/v0/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "deepseek-r1-distill-qwen-7b",
+   "ttl": 300,
    "messages": [ ... ]
}'
```

This will set a TTL of 5 minutes (300 seconds) for this model if it is JIT loaded.
Set TTL for models loaded with lms
By default, models loaded with lms load do not have a TTL, and will remain loaded in memory until you manually unload them.

You can set a TTL for a model loaded with lms like so:

lms load <model> --ttl 3600

Load a <model> with a TTL of 1 hour (3600 seconds)
Specify TTL when loading models in the server tab
You can also set a TTL when loading a model in the server tab like so

    ```
lms load <model> --ttl 3600
```

Set a TTL value when loading a model in the server tab

Configure Auto-Evict for JIT loaded models
With this setting, you can ensure new models loaded via JIT automatically unload previously loaded models first.

This is useful when you want to switch between models from another app without worrying about memory building up with unused models.

Enable or disable Auto-Evict for JIT loaded models in the Developer tab > Server Settings

When Auto-Evict is ON (default):

- At most 1 model is kept loaded in memory at a time (when loaded via JIT)
- Non-JIT loaded models are not affected

When Auto-Evict is OFF:

- Switching models from an external app will keep previous models loaded in memory
- Models will remain loaded until either:
  - Their TTL expires
  - You manually unload them

This feature works in tandem with TTL to provide better memory management for your workflow.

Nomenclature
TTL: Time-To-Live, is a term borrowed from networking protocols and cache systems. It defines how long a resource can remain allocated before it's considered stale and evicted.

## Structured Output

You can enforce a particular response format from an LLM by providing a JSON schema to the /v1/chat/completions endpoint, via LM Studio's REST API (or via any OpenAI client).

Start LM Studio as a server
To use LM Studio programatically from your own code, run LM Studio as a local server.

You can turn on the server from the "Developer" tab in LM Studio, or via the lms CLI:

lms server start

Install lms by running npx lmstudio install-cli
This will allow you to interact with LM Studio via an OpenAI-like REST API. For an intro to LM Studio's OpenAI-like API, see Running LM Studio as a server.


### Structured Output

The API supports structured JSON outputs through the /v1/chat/completions endpoint when given a JSON schema. Doing this will cause the LLM to respond in valid JSON conforming to the schema provided.

It follows the same format as OpenAI's recently announced Structured Output API and is expected to work via the OpenAI client SDKs.

Example using curl

This example demonstrates a structured output request using the curl utility.

To run this example on Mac or Linux, use any terminal. On Windows, use Git Bash.

```bash
curl http://{{hostname}}:{{port}}/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "{{model}}",
    "messages": [
      {
        "role": "system",
        "content": "You are a helpful jokester."
      },
      {
        "role": "user",
        "content": "Tell me a joke."
      }
    ],
    "response_format": {
      "type": "json_schema",
      "json_schema": {
        "name": "joke_response",
        "strict": "true",
        "schema": {
          "type": "object",
          "properties": {
            "joke": {
              "type": "string"
            }
          },
        "required": ["joke"]
        }
      }
    },
    "temperature": 0.7,
    "max_tokens": 50,
    "stream": false
  }'
```

All parameters recognized by /v1/chat/completions will be honored, and the JSON schema should be provided in the json_schema field of response_format.

The JSON object will be provided in string form in the typical response field, choices[0].message.content, and will need to be parsed into a JSON object.

Example using python

```python
from openai import OpenAI
import json

# Initialize OpenAI client that points to the local LM Studio server
client = OpenAI(
    base_url="http://localhost:1234/v1",
    api_key="lm-studio"
)

# Define the conversation with the AI
messages = [
    {"role": "system", "content": "You are a helpful AI assistant."},
    {"role": "user", "content": "Create 1-3 fictional characters"}
]

# Define the expected response structure
character_schema = {
    "type": "json_schema",
    "json_schema": {
        "name": "characters",
        "schema": {
            "type": "object",
            "properties": {
                "characters": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "name": {"type": "string"},
                            "occupation": {"type": "string"},
                            "personality": {"type": "string"},
                            "background": {"type": "string"}
                        },
                        "required": ["name", "occupation", "personality", "background"]
                    },
                    "minItems": 1,
                }
            },
            "required": ["characters"]
        },
    }
}

# Get response from AI
response = client.chat.completions.create(
    model="your-model",
    messages=messages,
    response_format=character_schema,
)

# Parse and display the results
results = json.loads(response.choices[0].message.content)
print(json.dumps(results, indent=2))
```

Important: Not all models are capable of structured output, particularly LLMs below 7B parameters.

Check the model card README if you are unsure if the model supports structured output.

Structured output engine
For GGUF models: utilize llama.cpp's grammar-based sampling APIs.
For MLX models: using Outlines.
The MLX implementation is available on Github: lmstudio-ai/mlx-engine.

Tool Use

Tool use enables LLMs to request calls to external functions and APIs through the /v1/chat/completions endpoint, via LM Studio's REST API (or via any OpenAI client). This expands their functionality far beyond text output.

🔔 Tool use requires LM Studio 0.3.6 or newer, get it here

Quick Start
1. Start LM Studio as a server
To use LM Studio programmatically from your own code, run LM Studio as a local server.

You can turn on the server from the "Developer" tab in LM Studio, or via the lms CLI:

lms server start

Install lms by running npx lmstudio install-cli
This will allow you to interact with LM Studio via an OpenAI-like REST API. For an intro to LM Studio's OpenAI-like API, see Running LM Studio as a server.

2. Load a Model
You can load a model from the "Chat" or "Developer" tabs in LM Studio, or via the lms CLI:

lms load

3. Copy, Paste, and Run an Example!
Curl
Single Turn Tool Call Request
Python
Single Turn Tool Call + Tool Use
Multi-Turn Example
Advanced Agent Example

Tool Use
What really is "Tool Use"?
Tool use describes:

LLMs output text requesting functions to be called (LLMs cannot directly execute code)
Your code executes those functions
Your code feeds the results back to the LLM.
High-level flow
┌──────────────────────────┐
│ SETUP: LLM + Tool list   │
└──────────┬───────────────┘
           ▼
┌──────────────────────────┐
│    Get user input        │◄────┐
└──────────┬───────────────┘     │
           ▼                     │
┌──────────────────────────┐     │
│ LLM prompted w/messages  │     │
└──────────┬───────────────┘     │
           ▼                     │
     Needs tools?                │
      │         │                │
    Yes         No               │
      │         │                │
      ▼         └────────────┐   │
┌─────────────┐              │   │
│Tool Response│              │   │
└──────┬──────┘              │   │
       ▼                     │   │
┌─────────────┐              │   │
│Execute tools│              │   │
└──────┬──────┘              │   │
       ▼                     ▼   │
┌─────────────┐          ┌───────────┐
│Add results  │          │  Normal   │
│to messages  │          │ response  │
└──────┬──────┘          └─────┬─────┘
       │                       ▲
       └───────────────────────┘

In-depth flow
LM Studio supports tool use through the /v1/chat/completions endpoint when given function definitions in the tools parameter of the request body. Tools are specified as an array of function definitions that describe their parameters and usage, like:

It follows the same format as OpenAI's Function Calling API and is expected to work via the OpenAI client SDKs.

We will use lmstudio-community/Qwen2.5-7B-Instruct-GGUF as the model in this example flow.

You provide a list of tools to an LLM. These are the tools that the model can request calls to. For example:

// the list of tools is model-agnostic
[
  {
    "type": "function",
    "function": {
      "name": "get_delivery_date",
      "description": "Get the delivery date for a customer's order",
      "parameters": {
        "type": "object",
        "properties": {
          "order_id": {
            "type": "string"
          }
        },
        "required": ["order_id"]
      }
    }
  }
]

This list will be injected into the system prompt of the model depending on the model's chat template. For Qwen2.5-Instruct, this looks like:

<|im_start|>system
You are Qwen, created by Alibaba Cloud. You are a helpful assistant.

# Tools

You may call one or more functions to assist with the user query.

You are provided with function signatures within <tools></tools> XML tags:
<tools>
{"type": "function", "function": {"name": "get_delivery_date", "description": "Get the delivery date for a customer's order", "parameters": {"type": "object", "properties": {"order_id": {"type": "string"}}, "required": ["order_id"]}}}
</tools>

For each function call, return a json object with function name and arguments within <tool_call></tool_call> XML tags:
<tool_call>
{"name": <function-name>, "arguments": <args-json-object>}
</tool_call><|im_end|>

Important: The model can only request calls to these tools because LLMs cannot directly call functions, APIs, or any other tools. They can only output text, which can then be parsed to programmatically call the functions.

When prompted, the LLM can then decide to either:

(a) Call one or more tools
User: Get me the delivery date for order 123
Model: <tool_call>
{"name": "get_delivery_date", "arguments": {"order_id": "123"}}
</tool_call>

(b) Respond normally
User: Hi
Model: Hello! How can I assist you today?

LM Studio parses the text output from the model into an OpenAI-compliant chat.completion response object.

If the model was given access to tools, LM Studio will attempt to parse the tool calls into the response.choices[0].message.tool_calls field of the chat.completion response object.
If LM Studio cannot parse any correctly formatted tool calls, it will simply return the response to the standard response.choices[0].message.content field.
Note: Smaller models and models that were not trained for tool use may output improperly formatted tool calls, resulting in LM Studio being unable to parse them into the tool_calls field. This is useful for troubleshooting when you do not receive tool_calls as expected. Example of an improperly formatting Qwen2.5-Instruct tool call:
<tool_call>
["name": "get_delivery_date", function: "date"]
</tool_call>

Note that the brackets are incorrect, and the call does not follow the name, argument format.

Your code parses the chat.completion response to check for tool calls from the model, then calls the appropriate tools with the parameters specified by the model. Your code then adds both:

The model's tool call message
The result of the tool call
To the messages array to send back to the model

# pseudocode, see examples for copy-paste snippets
if response.has_tool_calls:
    for each tool_call:
        # Extract function name & args
        function_to_call = tool_call.name     # e.g. "get_delivery_date"
        args = tool_call.arguments            # e.g. {"order_id": "123"}

        # Execute the function
        result = execute_function(function_to_call, args)

        # Add result to conversation
        add_to_messages([
            ASSISTANT_TOOL_CALL_MESSAGE,      # The request to use the tool
            TOOL_RESULT_MESSAGE               # The tool's response
        ])
else:
    # Normal response without tools
    add_to_messages(response.content)

The LLM is then prompted again with the updated messages array, but without access to tools. This is because:

The LLM already has the tool results in the conversation history
We want the LLM to provide a final response to the user, not call more tools
# Example messages
messages = [
    {"role": "user", "content": "When will order 123 be delivered?"},
    {"role": "assistant", "function_call": {
        "name": "get_delivery_date",
        "arguments": {"order_id": "123"}
    }},
    {"role": "tool", "content": "2024-03-15"},
]
response = client.chat.completions.create(
    model="lmstudio-community/qwen2.5-7b-instruct",
    messages=messages
)

The response.choices[0].message.content field after this call may be something like:

Your order #123 will be delivered on March 15th, 2024

The loop continues back at step 2 of the flow

Note: This is the pedantic flow for tool use. However, you can certainly experiment with this flow to best fit your use case.


Supported Models
Through LM Studio, all models support at least some degree of tool use.

However, there are currently two levels of support that may impact the quality of the experience: Native and Default.

Models with Native tool use support will have a hammer badge in the app, and generally perform better in tool use scenarios.

Native tool use support
"Native" tool use support means that both:

The model has a chat template that supports tool use (usually means the model has been trained for tool use)
This is what will be used to format the tools array into the system prompt and tell them model how to format tool calls
Example: Qwen2.5-Instruct chat template
LM Studio supports that model's tool use format
Required for LM Studio to properly input the chat history into the chat template, and parse the tool calls the model outputs into the chat.completion object
Models that currently have native tool use support in LM Studio (subject to change):

Qwen
GGUF lmstudio-community/Qwen2.5-7B-Instruct-GGUF (4.68 GB)
MLX mlx-community/Qwen2.5-7B-Instruct-4bit (4.30 GB)
Llama-3.1, Llama-3.2
GGUF lmstudio-community/Meta-Llama-3.1-8B-Instruct-GGUF (4.92 GB)
MLX mlx-community/Meta-Llama-3.1-8B-Instruct-8bit (8.54 GB)
Mistral
GGUF bartowski/Ministral-8B-Instruct-2410-GGUF (4.67 GB)
MLX mlx-community/Ministral-8B-Instruct-2410-4bit (4.67 GB GB)
Default tool use support
"Default" tool use support means that either:

The model does not have chat template that supports tool use (usually means the model has not been trained for tool use)
LM Studio does not currently support that model's tool use format
Under the hood, default tool use works by:

Giving models a custom system prompt and a default tool call format to use
Converting tool role messages to the user role so that chat templates without the tool role are compatible
Converting assistant role tool_calls into the default tool call format
Results will vary by model.

You can see the default format by running lms log stream in your terminal, then sending a chat completion request with tools to a model that doesn't have Native tool use support. The default format is subject to change.

Expand to see example of default tool use format
All models that don't have native tool use support will have default tool use support.


Example using curl
This example demonstrates a model requesting a tool call using the curl utility.

To run this example on Mac or Linux, use any terminal. On Windows, use Git Bash.

curl http://localhost:1234/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "lmstudio-community/qwen2.5-7b-instruct",
    "messages": [{"role": "user", "content": "What dell products do you have under $50 in electronics?"}],
    "tools": [
      {
        "type": "function",
        "function": {
          "name": "search_products",
          "description": "Search the product catalog by various criteria. Use this whenever a customer asks about product availability, pricing, or specifications.",
          "parameters": {
            "type": "object",
            "properties": {
              "query": {
                "type": "string",
                "description": "Search terms or product name"
              },
              "category": {
                "type": "string",
                "description": "Product category to filter by",
                "enum": ["electronics", "clothing", "home", "outdoor"]
              },
              "max_price": {
                "type": "number",
                "description": "Maximum price in dollars"
              }
            },
            "required": ["query"],
            "additionalProperties": false
          }
        }
      }
    ]
  }'

All parameters recognized by /v1/chat/completions will be honored, and the array of available tools should be provided in the tools field.

If the model decides that the user message would be best fulfilled with a tool call, an array of tool call request objects will be provided in the response field, choices[0].message.tool_calls.

The finish_reason field of the top-level response object will also be populated with "tool_calls".

An example response to the above curl request will look like:

```json
{
  "id": "chatcmpl-gb1t1uqzefudice8ntxd9i",
  "object": "chat.completion",
  "created": 1730913210,
  "model": "lmstudio-community/qwen2.5-7b-instruct",
  "choices": [
    {
      "index": 0,
      "logprobs": null,
      "finish_reason": "tool_calls",
      "message": {
        "role": "assistant",
        "tool_calls": [
          {
            "id": "365174485",
            "type": "function",
            "function": {
              "name": "search_products",
              "arguments": "{\"query\":\"dell\",\"category\":\"electronics\",\"max_price\":50}"
            }
          }
        ]
      }
    }
  ],
  "usage": {
    "prompt_tokens": 263,
    "completion_tokens": 34,
    "total_tokens": 297
  },
  "system_fingerprint": "lmstudio-community/qwen2.5-7b-instruct"
}
```

In plain english, the above response can be thought of as the model saying:

"Please call the search_products function, with arguments:

'dell' for the query parameter,
'electronics' for the category parameter
'50' for the max_price parameter
and give me back the results"

The tool_calls field will need to be parsed to call actual functions/APIs. The below examples demonstrate how.


Examples using python
Tool use shines when paired with program languages like python, where you can implement the functions specified in the tools field to programmatically call them when the model requests.

Single-turn example
Below is a simple single-turn (model is only called once) example of enabling a model to call a function called say_hello that prints a hello greeting to the console:

single-turn-example.py

```python
from openai import OpenAI

# Connect to LM Studio
client = OpenAI(base_url="http://localhost:1234/v1", api_key="lm-studio")

# Define a simple function
def say_hello(name: str) -> str:
    print(f"Hello, {name}!")

# Tell the AI about our function
tools = [
    {
        "type": "function",
        "function": {
            "name": "say_hello",
            "description": "Says hello to someone",
            "parameters": {
                "type": "object",
                "properties": {
                    "name": {
                        "type": "string",
                        "description": "The person's name"
                    }
                },
                "required": ["name"]
            }
        }
    }
]

# Ask the AI to use our function
response = client.chat.completions.create(
    model="lmstudio-community/qwen2.5-7b-instruct",
    messages=[{"role": "user", "content": "Can you say hello to Bob the Builder?"}],
    tools=tools
)

# Get the name the AI wants to use a tool to say hello to
# (Assumes the AI has requested a tool call and that tool call is say_hello)
tool_call = response.choices[0].message.tool_calls[0]
name = eval(tool_call.function.arguments)["name"]

# Actually call the say_hello function
say_hello(name) # Prints: Hello, Bob the Builder!
```

Running this script from the console should yield results like:
```bash
-> % python single-turn-example.py
Hello, Bob the Builder!

Play around with the name in

messages=[{"role": "user", "content": "Can you say hello to Bob the Builder?"}]
```

to see the model call the say_hello function with different names.

### Multi-turn example

Now for a slightly more complex example.

In this example, we'll:

Enable the model to call a get_delivery_date function
Hand the result of calling that function back to the model, so that it can fulfill the user's request in plain text
multi-turn-example.py (click to expand)
Running this script from the console should yield results like:
```bash
-> % python multi-turn-example.py

Model response requesting tool call:

ChatCompletion(id='chatcmpl-wwpstqqu94go4hvclqnpwn', choices=[Choice(finish_reason='tool_calls', index=0, logprobs=None, message=ChatCompletionMessage(content=None, refusal=None, role='assistant', function_call=None, tool_calls=[ChatCompletionMessageToolCall(id='377278620', function=Function(arguments='{"order_id":"1017"}', name='get_delivery_date'), type='function')]))], created=1730916196, model='lmstudio-community/qwen2.5-7b-instruct', object='chat.completion', service_tier=None, system_fingerprint='lmstudio-community/qwen2.5-7b-instruct', usage=CompletionUsage(completion_tokens=24, prompt_tokens=223, total_tokens=247, completion_tokens_details=None, prompt_tokens_details=None))

get_delivery_date function returns delivery date:

2024-11-19 13:03:17.773298

Final model response with knowledge of the tool call result:

Your order number 1017 is scheduled for delivery on November 19, 2024, at 13:03 PM.
```

### Advanced agent example

Building upon the principles above, we can combine LM Studio models with locally defined functions to create an "agent" - a system that pairs a language model with custom functions to understand requests and perform actions beyond basic text generation.

The agent in the below example can:

Open safe urls in your default browser
Check the current time
Analyze directories in your file system
agent-chat-example.py (click to expand)
Running this script from the console will allow you to chat with the agent:

```bash
-> % python agent-example.py
Assistant: Hello! I can help you open safe web links, tell you the current time, and analyze directory contents. What would you like me to do?
(Type 'quit' to exit)

You: What time is it?

Assistant: The current time is 14:11:40 (EST) as of November 6, 2024.

You: What time is it now?

Assistant: The current time is 14:13:59 (EST) as of November 6, 2024.

You: Open lmstudio.ai

Assistant: The link to lmstudio.ai has been opened in your default web browser.

You: What's in my current directory?

Assistant: Your current directory at `/Users/matt/project` contains a total of 14 files and 8 directories. Here's the breakdown:

- Files without an extension: 3
- `.mjs` files: 2
- `.ts` (TypeScript) files: 3
- Markdown (`md`) file: 1
- JSON files: 4
- TOML file: 1

The total size of these items is 1,566,990,604 bytes.

You: Thank you!

Assistant: You're welcome! If you have any other questions or need further assistance, feel free to ask.

You:

Streaming
When streaming through /v1/chat/completions (stream=true), tool calls are sent in chunks. Function names and arguments are sent in pieces via chunk.choices[0].delta.tool_calls.function.name and chunk.choices[0].delta.tool_calls.function.arguments.

For example, to call get_current_weather(location="San Francisco"), the streamed ChoiceDeltaToolCall in each chunk.choices[0].delta.tool_calls[0] object will look like:

ChoiceDeltaToolCall(index=0, id='814890118', function=ChoiceDeltaToolCallFunction(arguments='', name='get_current_weather'), type='function')
ChoiceDeltaToolCall(index=0, id=None, function=ChoiceDeltaToolCallFunction(arguments='{"', name=None), type=None)
ChoiceDeltaToolCall(index=0, id=None, function=ChoiceDeltaToolCallFunction(arguments='location', name=None), type=None)
ChoiceDeltaToolCall(index=0, id=None, function=ChoiceDeltaToolCallFunction(arguments='":"', name=None), type=None)
ChoiceDeltaToolCall(index=0, id=None, function=ChoiceDeltaToolCallFunction(arguments='San Francisco', name=None), type=None)
ChoiceDeltaToolCall(index=0, id=None, function=ChoiceDeltaToolCallFunction(arguments='"}', name=None), type=None)
```
These chunks must be accumulated throughout the stream to form the complete function signature for execution.

The below example shows how to create a simple tool-enhanced chatbot through the /v1/chat/completions streaming endpoint (stream=true).

tool-streaming-chatbot.py (click to expand)
You can chat with the bot by running this script from the console:
```bash
-> % python tool-streaming-chatbot.py
Assistant: Hi! I am an AI agent empowered with the ability to tell the current time (Type 'quit' to exit)

You: Tell me a joke, then tell me the current time

Assistant: Sure! Here's a light joke for you: Why don't scientists trust atoms? Because they make up everything.

Now, let me get the current time for you.

**Calling Tool: get_current_time**

The current time is 18:49:31. Enjoy your day!

You:


```

## OpenAI Compatibility API

Send requests to Chat Completions (text and images), Completions, and Embeddings endpoints.

### OpenAI-like API endpoints

LM Studio accepts requests on several OpenAI endpoints and returns OpenAI-like response objects.

Supported endpoints

- GET  /v1/models
- POST /v1/chat/completions
- POST /v1/embeddings
- POST /v1/completions

See below for more info about each endpoint
Re-using an existing OpenAI client
Pro Tip
You can reuse existing OpenAI clients (in Python, JS, C#, etc) by switching up the "base URL" property to point to your LM Studio instead of OpenAI's servers.

Switching up the base url to point to LM Studio
Note: The following examples assume the server port is 1234
Python
```python
from openai import OpenAI

client = OpenAI(
+    base_url="http://localhost:1234/v1"
)

# ... the rest of your code ...

Typescript
import OpenAI from 'openai';

const client = new OpenAI({
+  baseUrl: "http://localhost:1234/v1"
});

// ... the rest of your code ...

cURL
- curl https://api.openai.com/v1/chat/completions \
+ curl http://localhost:1234/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
-     "model": "gpt-4o-mini",
+     "model": "use the model identifier from LM Studio here",
     "messages": [{"role": "user", "content": "Say this is a test!"}],
     "temperature": 0.7
   }'
```

### Endpoints overview

#### /v1/models

GET request
Lists the currently loaded models.

cURL example
```bash
curl http://localhost:1234/v1/models
```

#### /v1/chat/completions

POST request
Send a chat history and receive the assistant's response
Prompt template is applied automatically
You can provide inference parameters such as temperature in the payload. See supported parameters
See OpenAI's documentation for more information
As always, keep a terminal window open with lms log stream to see what input the model receives
Python example
# Example: reuse your existing OpenAI setup
```python
from openai import OpenAI

# Point to the local server
client = OpenAI(base_url="http://localhost:1234/v1", api_key="lm-studio")

completion = client.chat.completions.create(
  model="model-identifier",
  messages=[
    {"role": "system", "content": "Always answer in rhymes."},
    {"role": "user", "content": "Introduce yourself."}
  ],
  temperature=0.7,
)

print(completion.choices[0].message)
```

#### /v1/embeddings

POST request
Send a string or array of strings and get an array of text embeddings (integer token IDs)
See OpenAI's documentation for more information
Python example
```python
# Make sure to `pip install openai` first
from openai import OpenAI
client = OpenAI(base_url="http://localhost:1234/v1", api_key="lm-studio")

def get_embedding(text, model="model-identifier"):
   text = text.replace("\n", " ")
   return client.embeddings.create(input = [text], model=model).data[0].embedding

print(get_embedding("Once upon a time, there was a cat."))
```

#### /v1/completions

**Heads Up**

This OpenAI-like endpoint is no longer supported by OpenAI. LM Studio continues to support it.

Using this endpoint with chat-tuned models might result in unexpected behavior such as extraneous role tokens being emitted by the model.

For best results, utilize a base model.

POST request
Send a string and get the model's continuation of that string
See supported payload parameters
Prompt template will NOT be applied, even if the model has one
See OpenAI's documentation for more information
As always, keep a terminal window open with lms log stream to see what input the model receives
Supported payload parameters
For an explanation for each parameter, see https://platform.openai.com/docs/api-reference/chat/create.

- model
- top_p
- top_k
- messages
- temperature
- max_tokens
- stream
- stop
- presence_penalty
- frequency_penalty
- logit_bias
- repeat_penalty
- seed

### LM Studio REST API (beta)

Experimental

Requires LM Studio 0.3.6 or newer. Still WIP, endpoints may change.
LM Studio now has its own REST API, in addition to OpenAI compatibility mode (learn more).

The REST API includes enhanced stats such as Token / Second and Time To First Token (TTFT), as well as rich information about models such as loaded vs unloaded, max context, quantization, and more.

Supported API Endpoints

- GET /api/v0/models - List available models
- GET /api/v0/models/{model} - Get info about a specific model
- POST /api/v0/chat/completions - Chat Completions (messages -> assistant response)
- POST /api/v0/completions - Text Completions (prompt -> completion)
- POST /api/v0/embeddings - Text Embeddings (text -> embedding)

🚧 We are in the process of developing this interface. Let us know what's important to you on Github or by email.

### Start the REST API server

To start the server, run the following command:

lms server start

**Pro Tip**

You can run LM Studio as a service and get the server to auto-start on boot without launching the GUI. Learn about Headless Mode.

### Endpoints

#### GET /api/v0/models

List all loaded and downloaded models

Example request

```bash
curl http://localhost:1234/api/v0/models
```

Response format

```json
{
  "object": "list",
  "data": [
    {
      "id": "qwen2-vl-7b-instruct",
      "object": "model",
      "type": "vlm",
      "publisher": "mlx-community",
      "arch": "qwen2_vl",
      "compatibility_type": "mlx",
      "quantization": "4bit",
      "state": "not-loaded",
      "max_context_length": 32768
    },
    {
      "id": "meta-llama-3.1-8b-instruct",
      "object": "model",
      "type": "llm",
      "publisher": "lmstudio-community",
      "arch": "llama",
      "compatibility_type": "gguf",
      "quantization": "Q4_K_M",
      "state": "not-loaded",
      "max_context_length": 131072
    },
    {
      "id": "text-embedding-nomic-embed-text-v1.5",
      "object": "model",
      "type": "embeddings",
      "publisher": "nomic-ai",
      "arch": "nomic-bert",
      "compatibility_type": "gguf",
      "quantization": "Q4_0",
      "state": "not-loaded",
      "max_context_length": 2048
    }
  ]
}
```

#### GET /api/v0/models/{model}

Get info about one specific model

Example request

```bash
curl http://localhost:1234/api/v0/models/qwen2-vl-7b-instruct
```

Response format

```json
{
  "id": "qwen2-vl-7b-instruct",
  "object": "model",
  "type": "vlm",
  "publisher": "mlx-community",
  "arch": "qwen2_vl",
  "compatibility_type": "mlx",
  "quantization": "4bit",
  "state": "not-loaded",
  "max_context_length": 32768
}
```

#### POST /api/v0/chat/completions

Chat Completions API. You provide a messages array and receive the next assistant response in the chat.

Example request

```bash
curl http://localhost:1234/api/v0/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "granite-3.0-2b-instruct",
    "messages": [
      { "role": "system", "content": "Always answer in rhymes." },
      { "role": "user", "content": "Introduce yourself." }
    ],
    "temperature": 0.7,
    "max_tokens": -1,
    "stream": false
  }'
```

Response format

```json
{
  "id": "chatcmpl-i3gkjwthhw96whukek9tz",
  "object": "chat.completion",
  "created": 1731990317,
  "model": "granite-3.0-2b-instruct",
  "choices": [
    {
      "index": 0,
      "logprobs": null,
      "finish_reason": "stop",
      "message": {
        "role": "assistant",
        "content": "Greetings, I'm a helpful AI, here to assist,\nIn providing answers, with no distress.\nI'll keep it short and sweet, in rhyme you'll find,\nA friendly companion, all day long you'll bind."
      }
    }
  ],
  "usage": {
    "prompt_tokens": 24,
    "completion_tokens": 53,
    "total_tokens": 77
  },
  "stats": {
    "tokens_per_second": 51.43709529007664,
    "time_to_first_token": 0.111,
    "generation_time": 0.954,
    "stop_reason": "eosFound"
  },
  "model_info": {
    "arch": "granite",
    "quant": "Q4_K_M",
    "format": "gguf",
    "context_length": 4096
  },
  "runtime": {
    "name": "llama.cpp-mac-arm64-apple-metal-advsimd",
    "version": "1.3.0",
    "supported_formats": ["gguf"]
  }
}
```

#### POST /api/v0/completions

Text Completions API. You provide a prompt and receive a completion.

Example request

```bash
curl http://localhost:1234/api/v0/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "granite-3.0-2b-instruct",
    "prompt": "the meaning of life is",
    "temperature": 0.7,
    "max_tokens": 10,
    "stream": false,
    "stop": "\n"
  }'
```

Response format

```json
{
  "id": "cmpl-p9rtxv6fky2v9k8jrd8cc",
  "object": "text_completion",
  "created": 1731990488,
  "model": "granite-3.0-2b-instruct",
  "choices": [
    {
      "index": 0,
      "text": " to find your purpose, and once you have",
      "logprobs": null,
      "finish_reason": "length"
    }
  ],
  "usage": {
    "prompt_tokens": 5,
    "completion_tokens": 9,
    "total_tokens": 14
  },
  "stats": {
    "tokens_per_second": 57.69230769230769,
    "time_to_first_token": 0.299,
    "generation_time": 0.156,
    "stop_reason": "maxPredictedTokensReached"
  },
  "model_info": {
    "arch": "granite",
    "quant": "Q4_K_M",
    "format": "gguf",
    "context_length": 4096
  },
  "runtime": {
    "name": "llama.cpp-mac-arm64-apple-metal-advsimd",
    "version": "1.3.0",
    "supported_formats": ["gguf"]
  }
}
```

#### POST /api/v0/embeddings

Text Embeddings API. You provide a text and a representation of the text as an embedding vector is returned.

Example request

```bash
curl http://127.0.0.1:1234/api/v0/embeddings \
  -H "Content-Type: application/json" \
  -d '{
    "model": "text-embedding-nomic-embed-text-v1.5",
    "input": "Some text to embed"
  }
```

Example response

```json
{
  "object": "list",
  "data": [
    {
      "object": "embedding",
      "embedding": [
        -0.016731496900320053,
        0.028460891917347908,
        -0.1407836228609085,
        ... (truncated for brevity) ...,
        0.02505224384367466,
        -0.0037634256295859814,
        -0.04341062530875206
      ],
      "index": 0
    }
  ],
  "model": "text-embedding-nomic-embed-text-v1.5@q4_k_m",
  "usage": {
    "prompt_tokens": 0,
    "total_tokens": 0
  }
}
```

Please report bugs by opening an issue on Github.

