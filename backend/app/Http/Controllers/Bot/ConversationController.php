<?php

namespace App\Http\Controllers\Bot;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bot\SendMessageRequest;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Service\Bot\Contracts\ConversationService;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ControllerHelpers;

    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * List all conversations with filtering and pagination.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $filters = [
                'search' => $request->input('search'),
                'status' => $request->input('status', 'all'),
                'device_id' => $request->input('device_id'),
            ];

            $result = $this->conversationService->list($filters);

            return $this->respondWithData($result, 'Successfully retrieved conversations');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve conversations.');
        }
    }

    /**
     * Get single conversation details.
     */
    public function show(string $id): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $conversation = $this->conversationService->show($id);

            return $this->respondWithData($conversation, 'Successfully retrieved conversation details');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve conversation details.');
        }
    }

    /**
     * Get message history for a conversation.
     */
    public function messages(string $id, Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $limit = (int) $request->input('limit', 50);
            $messages = $this->conversationService->getMessages($id, $limit);

            return $this->respondWithData($messages, 'Successfully retrieved conversation messages');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve conversation messages.');
        }
    }

    /**
     * Send an outgoing text message in a conversation.
     */
    public function send(string $id, SendMessageRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $text = $request->validated('text');
            $senderType = $request->validated('sender_type', 'human');

            $message = $this->conversationService->sendMessage($id, $text, $senderType);

            return $this->respondWithData($message, 'Message sent successfully');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to send message.');
        }
    }

    /**
     * Synchronize conversations and chat history from GOWA gateway.
     */
    public function sync(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $deviceId = $request->input('device_id');
            $result = $this->conversationService->syncFromGowa($deviceId);

            return $this->respondWithData($result, 'WhatsApp conversations synchronized successfully');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to synchronize conversations from WhatsApp gateway.');
        }
    }
}
