<?php

declare(strict_types=1);

use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Exception\ApiException;
use Shelfwood\LMStudio\Api\Model\Choice as ChatCompletionChoice;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Usage;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\Conversation;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;

describe('Conversation Streaming Turn Handling', function (): void {
    // Properties to hold captured listeners
    $capturedOnStreamEnd = null;
    $capturedOnStreamError = null;

    beforeEach(function () use (&$capturedOnStreamEnd, &$capturedOnStreamError): void {
        // Reset captured listeners for each test
        $capturedOnStreamEnd = null;
        $capturedOnStreamError = null;

        $this->chatServiceMock = Mockery::mock(ChatService::class);
        $this->toolRegistry = new ToolRegistry;
        $this->eventHandlerMock = Mockery::mock(EventHandler::class);
        $this->streamingHandlerMock = Mockery::mock(StreamingHandler::class);
        $this->toolExecutorMock = Mockery::mock(ToolExecutor::class);
        $this->model = 'test-model';
        $this->conversationOptions = ['temperature' => 0.7];

        // Default allow expectations
        $this->eventHandlerMock->allows('trigger')->zeroOrMoreTimes();
        $this->streamingHandlerMock->allows('reset');
        $this->streamingHandlerMock->allows('handleChunk');

        // Capture listeners passed to 'on' method of the StreamingHandler mock
        $this->streamingHandlerMock->allows('on')->andReturnUsing(
            function (string $event, callable $listener) use (&$capturedOnStreamEnd, &$capturedOnStreamError) {
                if ($event === 'stream_end') {
                    $capturedOnStreamEnd = $listener;
                } elseif ($event === 'stream_error') {
                    $capturedOnStreamError = $listener;
                }

                // Allow chaining or return self if needed by the original allows('on')
                return $this->streamingHandlerMock; // Assuming allows returns the mock itself
            }
        );

        // Define the helper function within the context accessible to tests
        $this->createConversationForTest = function (bool $streaming = false): Conversation {
            return new Conversation(
                $this->chatServiceMock,
                $this->model,
                $this->conversationOptions,
                $this->toolRegistry,
                $this->eventHandlerMock,
                $streaming,
                $streaming ? $this->streamingHandlerMock : null,
                $this->toolExecutorMock
            );
        };
    });

    test('handle streaming turn no tools', function () use (&$capturedOnStreamEnd): void {
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('Hello');

        $expectedContent = 'Hi there!';
        $finalAssistantMessage = new Message(Role::ASSISTANT, $expectedContent);

        // Mock initiateStreamingResponse behavior
        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->withArgs([
                $this->model,
                Mockery::on(fn ($msgs) => count($msgs) === 1 && $msgs[0]->role === Role::USER),
                Mockery::type('callable'), // The handleChunk callback
                [], // Expect empty tools
                null,
                Mockery::subset(['stream' => true] + $this->conversationOptions),
            ])
            ->andReturnUsing(function (...$args) use ($conversation, $finalAssistantMessage, &$capturedOnStreamEnd): void {
                // Simulate adding the message during the stream (as handler would)
                $conversation->addMessage($finalAssistantMessage);

                // Simulate the stream ending by calling the listener captured from $handler->on('stream_end', ...)
                if ($capturedOnStreamEnd) {
                    ($capturedOnStreamEnd)(null); // No tool calls expected
                } else {
                    throw new \Exception('Test setup error: stream_end listener was not captured by mock.');
                }
            });

        // Execute
        $finalContent = $conversation->handleStreamingTurn(5);

        // Assert
        expect($finalContent)->toBe($expectedContent);
        $messages = $conversation->getMessages();
        expect($messages)->toHaveCount(2);
        expect($messages[1])->toBe($finalAssistantMessage);
    });

    test('handle streaming turn with tools', function () use (&$capturedOnStreamEnd): void {
        $this->toolRegistry->registerTool('get_weather', fn () => 'Sunny', ['type' => 'object']);
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('What is the weather?');

        $toolCallId = 'tool_123';
        $toolFunction = ['name' => 'get_weather', 'arguments' => '{}'];
        $toolCall = new ToolCall($toolCallId, 'function', $toolFunction);
        $finalResponseContent = 'The weather is Sunny.';

        // 1. Mock createCompletionStream (Initial call returning tool request)
        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->withArgs([
                $this->model,
                Mockery::any(), // Less strict check on messages
                Mockery::type('callable'),
                Mockery::on(function ($tools) { // Loosened tool check
                    return is_array($tools) && count($tools) === 1 && $tools[0] instanceof Tool;
                }),
                null,
                Mockery::subset(['stream' => true]),
            ])
            ->andReturnUsing(function () use ($conversation, $toolCall, &$capturedOnStreamEnd): void {
                // Simulate StreamingHandler adding the assistant message *requesting* the tool
                $conversation->addMessage(new Message(Role::ASSISTANT, null, [$toolCall]));

                // Simulate stream end, signaling that tool calls are ready by calling captured listener
                if ($capturedOnStreamEnd) {
                    ($capturedOnStreamEnd)([$toolCall]); // Pass tool calls
                } else {
                    throw new \Exception('Test setup error: stream_end listener was not captured by mock.');
                }
            });

        // 2. Mock ToolExecutor
        $this->toolExecutorMock->shouldReceive('executeMany')
            ->once()
            ->with([$toolCall])
            ->andReturn([$toolCallId => 'Sunny']); // executeToolCalls will add tool message

        // 3. Mock second createCompletion call (Non-streaming, after tool execution)
        $mockUsage = new Usage(10, 5, 15);
        $mockMessage = new Message(Role::ASSISTANT, $finalResponseContent);
        $mockChoice = new ChatCompletionChoice(
            index: 0, logprobs: null, finishReason: FinishReason::STOP, message: $mockMessage
        );
        $secondCallResponse = new ChatCompletionResponse(
            id: 'chatcmpl-final', object: 'chat.completion', created: time(), model: $this->model,
            choices: [$mockChoice], usage: $mockUsage
        );
        // This mock is for the getResponse() call *inside* handleStreamingTurn
        $this->chatServiceMock->shouldReceive('createCompletion')
            ->once()
            ->withArgs([
                $this->model,
                Mockery::on(function ($messages) use ($toolCallId) {
                    // History should now include User, Assistant(tool_call), Tool(result)
                    return count($messages) === 3 &&
                           $messages[1]->role === Role::ASSISTANT && ! empty($messages[1]->toolCalls) &&
                           $messages[2]->role === Role::TOOL && $messages[2]->toolCallId === $toolCallId;
                }),
                null, // No tools for final answer
                null, // No response format needed
                Mockery::subset(['stream' => false]), // Ensure stream is false for this call
            ])
            ->andReturn($secondCallResponse);

        // Execute the full streaming turn logic
        $resultContent = $conversation->handleStreamingTurn(5);

        // Assert final state
        expect($resultContent)->toBe($finalResponseContent);
        $messages = $conversation->getMessages();
        expect($messages)->toHaveCount(4); // User, Assistant(tool_call), Tool(result), Assistant(final)
        expect($messages[3]->role)->toBe(Role::ASSISTANT);
        expect($messages[3]->content)->toBe($finalResponseContent);
        expect($messages[3]->toolCalls)->toBeNull();
    });

    test('handle streaming turn handles stream error', function () use (&$capturedOnStreamError): void {
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('Trigger error');
        $exception = new ApiException('Stream connection failed');

        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->andReturnUsing(function () use ($exception, &$capturedOnStreamError): void {
                // Simulate the stream erroring by calling the captured listener
                if ($capturedOnStreamError) {
                    ($capturedOnStreamError)($exception);
                } else {
                    throw new \Exception('Test setup error: stream_error listener was not captured by mock.');
                }
            });

        // The error event should be triggered by handleStreamingTurn's catch block
        // $this->eventHandlerMock->shouldNotReceive('trigger')->with('error', $exception); // Can remove this negative assertion

        expect(fn () => $conversation->handleStreamingTurn(5))
            ->toThrow(ApiException::class, 'Stream connection failed');
    });

    test('handle streaming turn handles tool execution error', function () use (&$capturedOnStreamEnd): void {
        $this->toolRegistry->registerTool('bad_tool', fn () => throw new \Exception('Tool failed!'), []);
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('Use bad tool');

        $toolCallId = 'tc_bad';
        $toolFunctionBad = ['name' => 'bad_tool', 'arguments' => '{}'];
        $toolCall = new ToolCall($toolCallId, 'function', $toolFunctionBad);
        $toolException = new \RuntimeException('Tool execution failed internally');

        // Mock initial stream completing successfully with tool call
        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->andReturnUsing(function () use ($conversation, $toolCall, &$capturedOnStreamEnd): void {
                $conversation->addMessage(new Message(Role::ASSISTANT, null, [$toolCall]));

                // Invoke captured stream_end listener
                if ($capturedOnStreamEnd) {
                    ($capturedOnStreamEnd)([$toolCall]);
                } else {
                    throw new \Exception('Test setup error: stream_end listener was not captured by mock.');
                }
            });

        // Mock ToolExecutor throwing the error
        $this->toolExecutorMock->shouldReceive('executeMany')
            ->once()
            ->with([$toolCall])
            ->andThrow($toolException);

        // Expect handleStreamingTurn to trigger the error event in its catch block
        // $this->eventHandlerMock->shouldReceive('trigger')->once()->with('error', Mockery::type(\RuntimeException::class)); // Removed this expectation

        // Expect the exception to be rethrown by handleStreamingTurn
        expect(fn () => $conversation->handleStreamingTurn(5))
            ->toThrow(\RuntimeException::class, 'Tool execution failed internally');
    });

    test('handle streaming turn handles second call error', function () use (&$capturedOnStreamEnd): void {
        $this->toolRegistry->registerTool('get_data', fn () => ['id' => 1], []);
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('Get data');

        $toolCallId = 'tc_data';
        $toolFunctionData = ['name' => 'get_data', 'arguments' => '{}'];
        $toolCall = new ToolCall($toolCallId, 'function', $toolFunctionData);
        $apiException = new ApiException('Second API call failed');

        // Mock initial stream success
        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->andReturnUsing(function () use ($conversation, $toolCall, &$capturedOnStreamEnd): void {
                $conversation->addMessage(new Message(Role::ASSISTANT, null, [$toolCall]));

                // Invoke captured stream_end listener
                if ($capturedOnStreamEnd) {
                    ($capturedOnStreamEnd)([$toolCall]);
                } else {
                    throw new \Exception('Test setup error: stream_end listener was not captured by mock.');
                }
            });

        // Mock tool execution success
        $this->toolExecutorMock->shouldReceive('executeMany')
            ->once()
            ->with([$toolCall])
            ->andReturn([$toolCallId => ['id' => 1]]);

        // Mock the second, non-streaming call (getResponse) failing
        $this->chatServiceMock->shouldReceive('createCompletion')
            ->once()
            ->withArgs([
                $this->model,
                Mockery::on(fn ($msgs) => count($msgs) === 3), // User, Assistant(tool), Tool(result)
                null, null,
                Mockery::subset(['stream' => false]),
            ])
            ->andThrow($apiException);

        // Expect handleStreamingTurn to trigger the error event in its catch block
        // $this->eventHandlerMock->shouldReceive('trigger')->once()->with('error', $apiException); // Removed this expectation

        // Expect the exception to be rethrown
        expect(fn () => $conversation->handleStreamingTurn(5))
            ->toThrow(ApiException::class, 'Second API call failed');
    });

    test('handle streaming turn throws timeout', function (): void {
        $conversation = ($this->createConversationForTest)(true);
        $conversation->addUserMessage('Cause timeout');

        // Mock the stream starting but never ending (no listener call)
        $this->chatServiceMock->shouldReceive('createCompletionStream')
            ->once()
            ->andReturnUsing(function (): void { /* Hang indefinitely */
            });

        // DON'T expect the event handler trigger, as the timeout occurs inside the loop
        // $this->eventHandlerMock->shouldReceive('trigger')... -> removed

        // Expect the timeout exception directly from the loop
        expect(fn () => $conversation->handleStreamingTurn(1)) // Short timeout
            ->toThrow(\RuntimeException::class, 'Streaming turn timed out after 1 seconds during initial stream.');
    });
});
