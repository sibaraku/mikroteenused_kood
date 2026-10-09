<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Laenutus\Auth\AuthException;
use Laenutus\Auth\AuthMiddleware;
use Laenutus\Auth\AuthService;
use Laenutus\Clients\ItemsHttpClient;
use Laenutus\Clients\ReservationsHttpClient;
use Laenutus\Config;
use Laenutus\HttpClient;
use Laenutus\Loans\LoansService;
use Laenutus\LoanView\LoanViewService;
use Laenutus\Request;
use Laenutus\Response;
use Laenutus\Router;

$request = Request::fromGlobals();
$router = new Router();

$authService = new AuthService();
$itemsClient = new ItemsHttpClient();
$reservationsClient = new ReservationsHttpClient();
$loansService = new LoansService();
$loanViewService = new LoanViewService($loansService, $itemsClient);

$handleException = static function (Throwable $e) use ($request): void {
    if ($e instanceof AuthException) {
        Response::error($e->errorCode, $e->getMessage(), $e->httpStatus, $request->requestId);
        return;
    }

    laenutus_log('error', $e->getMessage(), $request->requestId);
    Response::error('INTERNAL_ERROR', 'Sisemine viga', 500, $request->requestId);
};

$proxyItems = static function (Request $req, string $method, string $path) use ($handleException, $reservationsClient): void {
    try {
        $client = new HttpClient(
            Config::get('ITEMS_SERVICE_URL', 'http://items-service'),
            $req->bearerToken(),
            $req->requestId,
        );
        $result = $client->request($method, $path, $req->body !== [] ? $req->body : null, $req->query);

        if ($result['status'] >= 200 && $result['status'] < 300 && $method === 'PATCH' && ($req->body['status'] ?? null) === 'available' && preg_match('~^/items/([^/]+)$~', $path, $matches)) {
            try {
                $reservationsClient->notifyNext(rawurldecode($matches[1]), $req->requestId);
            } catch (Throwable $e) {
                laenutus_log('error', 'Could not notify next reservation', $req->requestId, ['error' => $e->getMessage()]);
            }
        }

        if ($result['status'] === 204) {
            Response::noContent($req->requestId);
            return;
        }

        Response::json($result['body'] ?? [], $result['status'], $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
};

$router->get('/health', static function (Request $req) {
    Response::json(['status' => 'ok', 'service' => 'loans'], 200, $req->requestId);
});

$router->post('/auth/login', static function (Request $req) use ($authService, $handleException) {
    try {
        $email = (string) ($req->body['email'] ?? '');
        $password = (string) ($req->body['password'] ?? '');
        $result = $authService->login($email, $password);
        Response::json($result, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/auth/me', static function (Request $req) use ($authService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $user = $authService->me($auth['userId']);
        Response::json($user, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/items', static function (Request $req) use ($proxyItems, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $proxyItems($req, 'GET', '/items');
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/items/{id}', static function (Request $req, array $params) use ($proxyItems, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $proxyItems($req, 'GET', '/items/' . $params['id']);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->patch('/items/{id}', static function (Request $req, array $params) use ($proxyItems, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        AuthMiddleware::requireAdmin($auth);
        $proxyItems($req, 'PATCH', '/items/' . $params['id']);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/loans', static function (Request $req) use ($loansService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $loans = $loansService->list($auth['userId'], $auth['role'] === 'admin');
        Response::json($loans, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/reservations', static function (Request $req) use ($reservationsClient, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        Response::json($reservationsClient->list($req->bearerToken(), $req->requestId), 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/reservations', static function (Request $req) use ($reservationsClient, $itemsClient, $authService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $itemId = (string) ($req->body['itemId'] ?? '');
        if ($itemId === '') {
            throw new AuthException('INVALID_INPUT', 'Vahendi ID on kohustuslik', 400);
        }
        $item = $itemsClient->get($itemId, $req->bearerToken(), $req->requestId);
        if ($item['status'] !== 'reserved') {
            throw new AuthException('ITEM_NOT_ON_LOAN', 'Ootejärjekorda saab lisada ainult juba laenutatud vahendile', 409);
        }
        $reservation = $reservationsClient->create([
            'itemId' => $itemId,
            'itemName' => $item['name'],
            'userId' => $auth['userId'],
            'email' => $authService->getUserEmail($auth['userId']),
        ], $req->bearerToken(), $req->requestId);
        Response::json($reservation, 201, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->delete('/reservations/{id}', static function (Request $req, array $params) use ($reservationsClient, $handleException) {
    try {
        AuthMiddleware::requireAuth($req);
        $reservationsClient->cancel($params['id'], $req->bearerToken(), $req->requestId);
        Response::noContent($req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/loans/{id}', static function (Request $req, array $params) use ($loansService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $loan = $loansService->get($params['id']);
        AuthMiddleware::canAccessLoan($auth, $loan['userId']);
        Response::json($loan, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->post('/loans', static function (Request $req) use ($loansService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        if ($auth['role'] !== 'admin' && ($req->body['userId'] ?? $auth['userId']) !== $auth['userId']) {
            throw new AuthException('FORBIDDEN', 'Te ei saa luua laenutust teise kasutaja nimel', 403);
        }
        $body = $req->body;
        if (!isset($body['userId'])) {
            $body['userId'] = $auth['userId'];
        }
        $loan = $loansService->create($body, $req->requestId, $req->bearerToken());
        Response::json($loan, 201, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->patch('/loans/{id}', static function (Request $req, array $params) use ($loansService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        AuthMiddleware::requireAdmin($auth);
        $loan = $loansService->update($params['id'], $req->body, $req->bearerToken(), $req->requestId);
        Response::json($loan, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->delete('/loans/{id}', static function (Request $req, array $params) use ($loansService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $loan = $loansService->get($params['id']);
        if ($auth['role'] !== 'admin' && $loan['userId'] !== $auth['userId']) {
            throw new AuthException('FORBIDDEN', 'Teil pole õigust seda laenutust tühistada', 403);
        }
        $loansService->delete($params['id'], $req->bearerToken(), $req->requestId);
        Response::noContent($req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/loan-view/{id}', static function (Request $req, array $params) use ($loanViewService, $handleException) {
    try {
        $auth = AuthMiddleware::requireAuth($req);
        $view = $loanViewService->get($params['id'], $auth, $req->bearerToken(), $req->requestId);
        Response::json($view, 200, $req->requestId);
    } catch (Throwable $e) {
        $handleException($e);
    }
});

$router->get('/', static function () {
    laenutus_render('login', ['title' => 'Sisselogimine']);
});

$router->get('/login', static function () {
    laenutus_render('login', ['title' => 'Sisselogimine']);
});

$router->get('/items-page', static function () {
    laenutus_render('items', ['title' => 'Vahendid']);
});

$router->get('/loans-page', static function () {
    laenutus_render('loans', ['title' => 'Minu laenutused']);
});

$router->get('/loan/{id}', static function (Request $req, array $params) {
    laenutus_render('loan-detail', ['title' => 'Laenutuse detail', 'loanId' => $params['id']]);
});

$router->dispatch($request);
