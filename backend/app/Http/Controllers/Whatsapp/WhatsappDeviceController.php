<?php

namespace App\Http\Controllers\Whatsapp;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whatsapp\LoginWithCodeRequest;
use App\Http\Requests\Whatsapp\RegisterDeviceRequest;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use App\Service\Whatsapp\Contracts\WhatsappGatewayService;
use App\Traits\ControllerHelpers;
use Exception;
use Illuminate\Http\Request;

class WhatsappDeviceController extends Controller
{
    use ControllerHelpers;

    public function __construct(
        protected WhatsappGatewayService $whatsappService
    ) {}

    /**
     * List all registered WhatsApp devices.
     */
    public function index(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $devices = $this->whatsappService->listDevices();

            return $this->respondWithData($devices, 'Successfully retrieved WhatsApp devices');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to fetch WhatsApp devices.');
        }
    }

    /**
     * Get connection and login status of WhatsApp device.
     */
    public function status(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $deviceId = $request->input('device_id');

        try {
            $status = $this->whatsappService->getStatus($deviceId);

            return $this->respondWithData($status, 'Successfully retrieved device status');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to fetch WhatsApp device status.');
        }
    }

    /**
     * Register a new WhatsApp gateway device.
     */
    public function register(RegisterDeviceRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $validated = $request->validated();

        try {
            $channel = $this->whatsappService->registerDevice(
                $validated['device_id'],
                $validated['name']
            );

            return $this->respondWithData($channel, 'Device successfully registered in GOWA', 201);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to register WhatsApp device.');
        }
    }

    /**
     * Get Login QR code with in-memory Base64 data URL.
     */
    public function loginQr(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $deviceId = $request->input('device_id') ?: config('services.gowa.default_device_id', 'default');

        try {
            $qrData = $this->whatsappService->getLoginQr($deviceId);

            return $this->respondWithData($qrData, 'Login QR code generated successfully');
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to generate WhatsApp login QR.');
        }
    }

    /**
     * Request 8-digit pairing code for login using phone number.
     */
    public function loginCode(LoginWithCodeRequest $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $validated = $request->validated();
        $deviceId = $validated['device_id'] ?? config('services.gowa.default_device_id', 'default');

        try {
            $codeData = $this->whatsappService->loginWithCode($deviceId, $validated['phone']);

            return $this->respondWithData($codeData, 'Pairing code generated successfully');
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to request pairing code.');
        }
    }

    /**
     * Disconnect and logout WhatsApp session.
     */
    public function logout(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $deviceId = $request->input('device_id') ?: config('services.gowa.default_device_id', 'default');

        try {
            $success = $this->whatsappService->logout($deviceId);

            return $this->respondWithData(
                ['device_id' => $deviceId, 'logged_out' => $success],
                'Device successfully logged out'
            );
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to logout device.');
        }
    }

    /**
     * Reconnect device to WhatsApp gateway.
     */
    public function reconnect(Request $request): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;
        $deviceId = $request->input('device_id') ?: config('services.gowa.default_device_id', 'default');

        try {
            $success = $this->whatsappService->reconnect($deviceId);

            return $this->respondWithData(
                ['device_id' => $deviceId, 'reconnected' => $success],
                'Device reconnect requested successfully'
            );
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to reconnect device.');
        }
    }

    /**
     * Delete a WhatsApp device from GOWA and database.
     */
    public function destroy(string $deviceId): DataResource|ErrorResource|InternalErrorResource
    {
        $ctx = __METHOD__;

        try {
            $success = $this->whatsappService->deleteDevice($deviceId);

            return $this->respondWithData(
                ['device_id' => $deviceId, 'deleted' => $success],
                'Device successfully deleted'
            );
        } catch (NotFoundException $e) {
            return $this->handleNotFound($e, $ctx);
        } catch (BadRequestException $e) {
            return $this->handleBadRequest($e, $ctx);
        } catch (InternalServerException $e) {
            return $this->handleInternal($e, $ctx);
        } catch (Exception $e) {
            return $this->handleUnexpected($e, $ctx, 'Failed to delete WhatsApp device.');
        }
    }
}
