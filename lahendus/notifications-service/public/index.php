<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Laenutus\Auth\AuthException;
use Laenutus\Config;
use Laenutus\Notifications\NotificationsService;
use Laenutus\Request;
use Laenutus\Response;
use Laenutus\Router;

$requireInternalKey = static function (Request $req): void {
    $expected = Config::get('INTERNAL_API_KEY');
    if ($expected === null || $expected === '') {
        throw new AuthException('SERVICE_MISCONFIGURED', 'Sisemine API võti on seadistamata', 503);
    }

    $provided = $req->headers['X-Internal-Api-Key'] ?? null;
    if ($provided !== $expected) {
        throw new AuthException('UNAUTHORIZED', 'Puudub volitus', 401);
    }
};

$request = Request::fromGlobals();
$router = new Router();
$notificationsService = new NotificationsService();

$handleException = static function (Throwable $e) use ($request): void {
    if ($e instanceof AuthException) {
        Response::error($e->errorCode, $e->getMessage(), $e->httpStatus, $request->requestId);
        return;
    }

    laenutus_log('error', $e->getMessage(), $request->requestId);
    Response::error('INTERNAL_ERROR', 'Sisemine viga', 500, $request->requestId);
};

$router->get('/health', static function (Request $req) {
    Response::json(['status' => 'ok', 'service' => 'notifications'], 200, $req->requestId);
});

$router->get('/notifications', static function (Request $req) use ($notificationsService, $handleException, $requireInternalKey) {
    try {
        $requireInternalKey($req);
        Response::json($notificationsService->list(), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/notifications', static function (Request $req) use ($notificationsService, $handleException, $requireInternalKey) {
    try {
        $requireInternalKey($req);
        $notification = $notificationsService->create($req->body, $req->requestId);
        Response::json($notification, 201, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->dispatch($request);
