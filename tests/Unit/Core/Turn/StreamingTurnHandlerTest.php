<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Turn;

// Project Imports needed by this test file AND the helper function
use Shelfwood\LMStudio\Api\Enum\FinishReason;
use Shelfwood\LMStudio\Api\Enum\Role;
use Shelfwood\LMStudio\Api\Model\ChatCompletionChunk;
use Shelfwood\LMStudio\Api\Model\Choice;
use Shelfwood\LMStudio\Api\Model\Message;
use Shelfwood\LMStudio\Api\Model\Tool\ToolCall;
use Shelfwood\LMStudio\Api\Model\Usage;
use Shelfwood\LMStudio\Api\Response\ChatCompletionResponse;
use Shelfwood\LMStudio\Api\Service\ChatService;
use Shelfwood\LMStudio\Core\Conversation\ConversationState;
use Shelfwood\LMStudio\Core\Event\EventHandler;
use Shelfwood\LMStudio\Core\Streaming\StreamingHandler;
use Shelfwood\LMStudio\Core\Tool\ToolExecutor;
use Shelfwood\LMStudio\Core\Tool\ToolRegistry;
use Shelfwood\LMStudio\Core\Turn\StreamingTurnHandler;

// Helper to create simple ChatCompletionChunk for content
function createChunk(string $id, string $contentDelta): ChatCompletionChunk
{
    return ChatCompletionChunk::fromArray([
        'id' => $id,
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => 'test-model',
        'choices' => [
            [
                'index' => 0,
                'delta' => ['role' => Role::ASSISTANT->value, 'content' => $contentDelta],
                'finish_reason' => null,
            ],
        ],
    ]);
}

// Helper to create a final ChatCompletionChunk (finish reason)
function createFinalChunk(string $id, ?string $finishReason = 'stop', ?array $toolCalls = null): ChatCompletionChunk
{
    $delta = ['role' => Role::ASSISTANT->value]; // Usually empty delta in final chunk

    if ($toolCalls) {
        // Simulate how tool calls might appear in chunks (implementation varies)
        // This is simplified; real chunks might build tool calls incrementally.
        $delta['tool_calls'] = array_map(fn ($tc) => $tc->toArray(), $toolCalls);
    }

    return ChatCompletionChunk::fromArray([
        'id' => $id,
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => 'test-model',
        'choices' => [
            [
                'index' => 0,
                'delta' => $delta,
                'finish_reason' => $finishReason,
            ],
        ],
    ]);
}

// Helper function defined in the test namespace scope
function createMockResponse(?string $content, ?array $toolCalls = null): ChatCompletionResponse
{
    $finishReasonEnum = $toolCalls ? FinishReason::TOOL_CALLS : FinishReason::STOP;

    return new ChatCompletionResponse(
        id: 'chatcmpl-mock'.uniqid(),
        object: 'chat.completion',
        created: time(),
        model: 'test-model',
        choices: [
            new Choice(
                index: 0,
                logprobs: null,
                message: new Message(
                    role: Role::ASSISTANT,
                    content: $content ?? '',
                    toolCalls: $toolCalls
                ),
                finishReason: $finishReasonEnum
            ),
        ],
        usage: new Usage(
            promptTokens: 0,
            completionTokens: 0,
            totalTokens: 0
        )
    );
}

// describe('StreamingTurnHandler', function (): void {
//     beforeEach(function (): void {
//         // Use global Mockery namespace is safer
//         $this->chatService = \Mockery::mock(ChatService::class);
//         $this->toolRegistry = \Mockery::mock(ToolRegistry::class);
//         $this->toolExecutor = \Mockery::mock(ToolExecutor::class);
//         $this->streamingHandler = \Mockery::mock(StreamingHandler::class);
//         $this->eventHandler = \Mockery::mock(EventHandler::class)->makePartial();
//         /** @var MockInterface&EventHandler $eventHandlerMock */
//         $eventHandlerMock = $this->eventHandler;

//         // Use statements should handle these class names now
//         $this->handler = new StreamingTurnHandler(
//             $this->chatService,
//             $this->toolRegistry,
//             $this->toolExecutor,
//             $eventHandlerMock,
//             $this->streamingHandler
//         );

//         $this->state = new ConversationState('test-model', ['temperature' => 0.5]);
//         $this->state->addUserMessage('Tell me a story');
//     });

//     // --- Refined Simplified Mocking Approach V2 ---

//     test('[Simplified V2] handle simple streaming response without tools', function (): void {
//         $chunk1 = createChunk('chunk1', 'Once ');
//         $chunk2 = createChunk('chunk2', 'upon a time...');
//         $finalChunk = createFinalChunk('chunk3', FinishReason::STOP->value);
//         $allChunks = [$chunk1, $chunk2, $finalChunk];

//         $realEndListener = null;
//         $realErrorListener = null;
//         $realContentListener = null;

//         $this->toolRegistry->shouldReceive('hasTools')->once()->andReturn(false);

//         // Mock StreamingHandler: Capture listeners, handleChunk calls content listener
//         $this->streamingHandler->shouldReceive('reset')->once();
//         $this->streamingHandler->shouldReceive('on')->with('stream_end', \Mockery::capture($realEndListener));
//         $this->streamingHandler->shouldReceive('on')->with('stream_error', \Mockery::capture($realErrorListener));
//         $this->streamingHandler->shouldReceive('on')->with('stream_content', \Mockery::capture($realContentListener));

//         // Expect handleChunk to be called AND invoke the captured content listener
//         $this->streamingHandler->shouldReceive('handleChunk')
//             ->with(\Mockery::type(ChatCompletionChunk::class))
//             ->times(count($allChunks))
//             ->andReturnUsing(function (ChatCompletionChunk $chunk) use (&$realContentListener): void {
//                 // Ensure listener is captured before use
//                 expect($realContentListener)->toBeCallable('Real stream_content listener not captured by handleChunk mock');

//                 if ($chunk->choices[0]->delta->content !== null && is_callable($realContentListener)) {
//                     // Manually trigger the captured content listener
//                     ($realContentListener)($chunk->choices[0]->delta->content);
//                 }
//                 // DO NOT call end/error listeners here
//             });

//         // Mock ChatService: Immediately process chunks AND call the end listener
//         $this->chatService->shouldReceive('createCompletionStream')
//             ->once()
//             ->with(\Mockery::any(), \Mockery::any(), \Mockery::type('callable'), null, null, \Mockery::any())
//             ->andReturnUsing(function ($model, $messages, $callback) use ($allChunks, &$realEndListener): void {
//                 expect($realEndListener)->toBeCallable('Real stream_end listener not captured before createCompletionStream mock execution');

//                 // Immediately invoke the callback with all chunks
//                 foreach ($allChunks as $chunk) {
//                     call_user_func($callback, $chunk); // This now triggers the handleChunk mock above, which calls content listener
//                 }
//                 // Manually call the captured end listener to signal completion
//                 ($realEndListener)([]); // No tools
//             });

//         $this->eventHandler->shouldReceive('trigger')->never()->with('error', \Mockery::any());

//         // --- Execute & Assert ---
//         $result = $this->handler->handle($this->state);
//         expect($result)->toBe('Once upon a time...');
//         $messages = $this->state->getMessages();
//         expect($messages)->toHaveCount(2);
//         expect($messages[1]->role)->toBe(Role::ASSISTANT);
//         expect($messages[1]->content)->toBe('Once upon a time...');
//     });

//     test('[Simplified V2] handle streaming response with tool call', function (): void {
//         $toolCallId = 'call_stream1';
//         $toolName = 'search';
//         $toolArgs = ['query' => 'weather'];
//         $toolResult = ['results' => 'Sunny, 20C'];
//         $finalReply = 'The weather is Sunny, 20C.';

//         $chunk1 = createChunk('c1', 'Okay ');
//         $toolCall = ToolCall::fromArray(['id' => $toolCallId, 'type' => 'function', 'function' => ['name' => $toolName, 'arguments' => json_encode($toolArgs)]]);
//         $finalChunk = createFinalChunk('c2', FinishReason::TOOL_CALLS->value);
//         $streamChunks = [$chunk1, $finalChunk];
//         $mockResponse2 = createMockResponse($finalReply);

//         $realEndListener = null;
//         $realErrorListener = null;
//         $realContentListener = null;

//         $this->toolRegistry->shouldReceive('hasTools')->once()->andReturn(true);
//         $this->toolRegistry->shouldReceive('getTools')->once()->andReturn([/* dummy tool */]);

//         // Mock StreamingHandler: Capture listeners, handleChunk calls content listener
//         $this->streamingHandler->shouldReceive('reset')->once();
//         $this->streamingHandler->shouldReceive('on')->with('stream_end', \Mockery::capture($realEndListener));
//         $this->streamingHandler->shouldReceive('on')->with('stream_error', \Mockery::capture($realErrorListener));
//         $this->streamingHandler->shouldReceive('on')->with('stream_content', \Mockery::capture($realContentListener));
//         $this->streamingHandler->shouldReceive('handleChunk')
//             ->with(\Mockery::type(ChatCompletionChunk::class))
//             ->times(count($streamChunks))
//             ->andReturnUsing(function (ChatCompletionChunk $chunk) use (&$realContentListener): void {
//                 expect($realContentListener)->toBeCallable('Real stream_content listener not captured by handleChunk mock');

//                 if ($chunk->choices[0]->delta->content !== null && is_callable($realContentListener)) {
//                     ($realContentListener)($chunk->choices[0]->delta->content);
//                 }
//             });

//         // Mock FIRST ChatService call (stream): Process chunks and call END listener
//         $this->chatService->shouldReceive('createCompletionStream')
//             ->once()
//             ->with(\Mockery::any(), \Mockery::any(), \Mockery::type('callable'), \Mockery::any(), null, \Mockery::any())
//             ->andReturnUsing(function ($model, $messages, $callback) use ($streamChunks, &$realEndListener, $toolCall): void {
//                 expect($realEndListener)->toBeCallable('Real stream_end listener not captured');

//                 foreach ($streamChunks as $chunk) {
//                     call_user_func($callback, $chunk); // Triggers handleChunk mock -> content listener
//                 }
//                 // Manually call end listener with the tool call
//                 ($realEndListener)([$toolCall]);
//             });

//         // Mock Tool Execution & SECOND ChatService call (non-stream)
//         $this->toolExecutor->shouldReceive('executeMany')->once()->with([$toolCall])->andReturn([$toolCallId => $toolResult]);
//         $this->chatService->shouldReceive('createCompletion')
//             ->once()
//             ->with(\Mockery::any(), \Mockery::any(), null, null, \Mockery::any())
//             ->andReturn($mockResponse2);
//         $this->eventHandler->shouldReceive('trigger')->once()->with('response', $mockResponse2);
//         $this->eventHandler->shouldReceive('trigger')->never()->with('error', \Mockery::any());

//         // --- Execute & Assert ---
//         $result = $this->handler->handle($this->state);
//         expect($result)->toBe($finalReply);
//         $messages = $this->state->getMessages();
//         expect($messages)->toHaveCount(4);
//         expect($messages[1]->content)->toBe('Okay ');
//         expect($messages[1]->toolCalls[0]->id)->toBe($toolCallId);
//         expect($messages[2]->role)->toBe(Role::TOOL);
//         expect($messages[2]->toolCallId)->toBe($toolCallId);
//         expect($messages[2]->content)->toBe(json_encode($toolResult));
//         expect($messages[3]->role)->toBe(Role::ASSISTANT);
//         expect($messages[3]->content)->toBe($finalReply);
//     });
// });
