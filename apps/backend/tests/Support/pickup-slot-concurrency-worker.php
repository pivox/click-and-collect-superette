<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/bootstrap.php';

[$script, $schema, $worker, $storeId, $action, $startsAt] = $argv;
$kernel = new Kernel('test', true);
$kernel->boot();
$connection = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class)->getConnection();
$connection->executeStatement('SET search_path TO '.$connection->quoteSingleIdentifier($schema));
$connection->executeQuery("SELECT set_config('application_name', ?, false)", [$worker]);
$path = '/api/merchant/stores/'.$storeId.'/pickup-slots';
$payload = [
    'starts_at' => $startsAt,
    'ends_at' => (new DateTimeImmutable($startsAt))->modify('+1 hour')->format(DateTimeInterface::ATOM),
    'capacity' => 9,
];
if ('generate' === $action) {
    $path = '/api/merchant/stores/'.$storeId.'/pickup-slot-rules/generate';
    $payload = ['horizon_months' => 1];
}
$request = Request::create($path, 'POST', server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_TEST_USER' => 'slot-concurrency@example.test',
], content: json_encode($payload, \JSON_THROW_ON_ERROR));
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent() ?: '{}', true)], \JSON_THROW_ON_ERROR);
$kernel->shutdown();
