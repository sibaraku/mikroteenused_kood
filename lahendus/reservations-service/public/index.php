<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Laenutus\Auth\AuthException;
use Laenutus\Auth\AuthMiddleware;
use Laenutus\Config;
use Laenutus\Request;
use Laenutus\Reservations\ReservationsService;
use Laenutus\Response;
use Laenutus\Router;

$request = Request::fromGlobals();
$router = new Router();
$reservationsService = new ReservationsService();

$handleException = static function (Throwable $e) use ($request): void {
    if ($e instanceof AuthException) {
        Response::error($e->errorCode, $e->getMessage(), $e->httpStatus, $request->requestId);
        return;
    }

    laenutus_log('error', $e->getMessage(), $request->requestId);
    Response::error('INTERNAL_ERROR', 'Sisemine viga', 500, $request->requestId);
};

$requireInternalKey = static function (Request $req): void {
    $expected = Config::get('INTERNAL_API_KEY');
    if ($expected === null || $expected === '' || ($req->headers['X-Internal-Api-Key'] ?? null) !== $expected) {
        throw new AuthException('UNAUTHORIZED', 'Puudub volitus', 401);
    }
};

$router->get('/health', static function (Request $req) {
    Response::json(['status' => 'ok', 'service' => 'reservations'], 200, $req->requestId);
});

$router->get('/reservations', static function (Request $req) use ($reservationsService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        Response::json($reservationsService->list($auth['userId']), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/reservations', static function (Request $req) use ($reservationsService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $body = $req->body;
        $body['userId'] = $auth['userId'];
        $body['email'] = $auth['email'] ?? ($body['email'] ?? '');
        Response::json($reservationsService->create($body), 201, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->delete('/reservations/{id}', static function (Request $req, array $params) use ($reservationsService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $reservationsService->cancel($params['id'], $auth['userId']);
        Response::noContent($req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/reservations/notify-next', static function (Request $req) use ($reservationsService, $handleException, $requireInternalKey) {
    try {
        $requireInternalKey($req);
        $itemId = (string) ($req->body['itemId'] ?? '');
        if ($itemId === '') {
            throw new AuthException('INVALID_INPUT', 'Vahendi ID on kohustuslik', 400);
        }
        $reservation = $reservationsService->notifyNext($itemId, $req->requestId);
        Response::json(['notified' => $reservation !== null, 'reservation' => $reservation], 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->dispatch($request);