<?php

namespace App\Http\Controllers\Bot;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bot\ResolveTicketRequest;
use App\Http\Requests\Bot\ServeTicketRequest;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Service\Bot\Contracts\TicketService;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use ControllerHelpers;

    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * List all support tickets with filters (status, category, search) and queue summary.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $filters = [
                'status' => $request->input('status', 'all'),
                'category' => $request->input('category'),
                'search' => $request->input('search'),
                'page' => (int) $request->input('page', 1),
                'per_page' => (int) $request->input('per_page', 50),
            ];

            $result = $this->ticketService->listTickets($filters);

            return $this->respondWithData($result, 'Successfully retrieved support tickets');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve support tickets.');
        }
    }

    /**
     * Get real-time CS status: whether CS is currently serving an active ticket or idle, plus queue counts.
     */
    public function status(): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $status = $this->ticketService->getCsStatus();

            return $this->respondWithData($status, 'Successfully retrieved CS service status');
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to retrieve CS service status.');
        }
    }

    /**
     * Start serving a queued ticket: change status to in_progress and send greeting message to customer.
     */
    public function serve(string $id, ServeTicketRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $adminName = $request->input('admin_name', 'Admin Customer Care');
            $ticket = $this->ticketService->startServingTicket($id, $adminName);

            return $this->respondWithData(
                $this->ticketService->formatTicket($ticket),
                'Ticket is now in progress and being served'
            );
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to start serving ticket.');
        }
    }

    /**
     * Resolve an active ticket: change status to resolved, restore conversation to AI, and send closing message.
     */
    public function resolve(string $id, ResolveTicketRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $resolutionNotes = $request->input('resolution_notes');
            $ticket = $this->ticketService->resolveTicket($id, $resolutionNotes);

            return $this->respondWithData(
                $this->ticketService->formatTicket($ticket),
                'Ticket has been successfully resolved'
            );
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to resolve ticket.');
        }
    }
}
