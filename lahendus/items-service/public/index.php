<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Laenutus\Auth\AuthException;
use Laenutus\Auth\AuthMiddleware;
use Laenutus\Items\ItemsService;
use Laenutus\Request;
use Laenutus\Response;
use Laenutus\Router;

$request = Request::fromGlobals();
$router = new Router();
$itemsService = new ItemsService();

$handleException = static function (Throwable $e) use ($request): void {
    if ($e instanceof AuthException) {
        Response::error($e->errorCode, $e->getMessage(), $e->httpStatus, $request->requestId);
        return;
    }

    laenutus_log('error', $e->getMessage(), $request->requestId);
    Response::error('INTERNAL_ERROR', 'Sisemine viga', 500, $request->requestId);
};

$router->get('/health', static function (Request $req) {
    Response::json(['status' => 'ok', 'service' => 'items'], 200, $req->requestId);
});

$router->get('/items', static function (Request $req) use ($itemsService, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $status = isset($req->query['status']) ? (string) $req->query['status'] : null;
        Response::json($itemsService->list($status), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/items/{id}', static function (Request $req, array $params) use ($itemsService, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        Response::json($itemsService->get($params['id']), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->patch('/items/{id}', static function (Request $req, array $params) use ($itemsService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        AuthMiddleware::requireAdmin($auth);
        $updated = $itemsService->update($params['id'], $req->body);
        Response::json($updated, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/items/{id}/reserve', static function (Request $req, array $params) use ($itemsService, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $itemsService->reserve($params['id']);
        Response::json($itemsService->get($params['id']), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/items/{id}/release', static function (Request $req, array $params) use ($itemsService, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $itemsService->release($params['id']);
        Response::json($itemsService->get($params['id']), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->dispatch($request);
