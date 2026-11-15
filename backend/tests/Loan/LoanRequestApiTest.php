<?php

declare(strict_types=1);

namespace App\Tests\Loan;

use App\Library\Application\PublicApi\LibraryForLoan;
use App\Loan\Application\ChangeLoanRequest\ChangeLoanRequestCommand;
use App\Loan\Application\ChangeLoanRequest\ChangeLoanRequestHandler;
use App\Loan\Application\CreateLoanRequest\CreateLoanRequestCommand;
use App\Loan\Application\CreateLoanRequest\CreateLoanRequestHandler;
use App\Loan\Domain\LoanRequestRepository;
use App\Shared\Application\AtomicOperation;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoanRequestApiTest extends WebTestCase
{
    private const string CATEGORY = '0199b001-0000-7000-8000-000000000001';
    private KernelBrowser $client;
    private Connection $db;
    private string $csrf;
    private string $ownerId;
    private string $copyId;
    private array $users = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->db = self::getContainer()->get(Connection::class);
        $this->db->executeStatement('TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE');
        $this->db->insert('library_category', ['id' => self::CATEGORY, 'name' => 'Narrativa', 'slug' => 'narrativa', 'active' => true]);
        $this->ownerId = $this->switchUser('owner');
        $this->setLocation($this->ownerId);
        $this->copyId = $this->createCopy(true);
    }

    public function testLoanLifecycleAndContactPrivacy(): void
    {
        $requesterId = $this->switchUser('requester');
        $this->setLocation($requesterId);
        $this->client->request('GET', '/api/library/book-copies/'.$this->copyId);
        self::assertTrue($this->payload()['bookCopy']['canRequestLoan']);
        $requestId = $this->requestLoan($this->copyId);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('AVAILABLE', $this->availability());
        self::assertSame('PENDING', $this->requestStatus($requestId));
        $this->client->request('GET', '/api/loan/requests');
        self::assertArrayNotHasKey('ownerEmail', $this->payload()['requests']['sent'][0]);
        $this->client->request('GET', '/api/discovery/book-copies');
        self::assertSame([], $this->payload()['results']);
        $this->client->request('GET', '/api/library/book-copies/'.$this->copyId);
        self::assertFalse($this->payload()['bookCopy']['canRequestLoan']);
        self::assertSame('PENDING', $this->payload()['bookCopy']['activeLoanStatus']);

        $this->switchUser('owner');
        $this->act($requestId, 'accept');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('AVAILABLE', $this->availability());
        $this->client->request('GET', '/api/loan/requests');
        self::assertArrayNotHasKey('ownerEmail', $this->payload()['requests']['received'][0]);
        $this->switchUser('requester');
        $this->client->request('GET', '/api/loan/requests');
        self::assertSame('owner@example.com', $this->payload()['requests']['sent'][0]['ownerEmail']);
        $this->client->request('GET', '/api/library/book-copies/'.$this->copyId);
        self::assertStringNotContainsString('owner@example.com', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/api/library/users/'.$this->ownerId.'/book-copies');
        self::assertStringNotContainsString('owner@example.com', (string) $this->client->getResponse()->getContent());
        $this->switchUser('stranger');
        $this->client->request('GET', '/api/loan/requests');
        self::assertSame([], $this->payload()['requests']['sent']);
        self::assertSame([], $this->payload()['requests']['received']);
        $this->act($requestId, 'accept');
        self::assertResponseStatusCodeSame(404);

        $this->switchUser('owner');
        $this->act($requestId, 'lend');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('LENT', $this->requestStatus($requestId));
        self::assertSame('UNAVAILABLE', $this->availability());
        $this->switchUser('requester');
        $this->client->request('GET', '/api/loan/requests');
        self::assertSame('owner@example.com', $this->payload()['requests']['sent'][0]['ownerEmail']);
        $this->act($requestId, 'cancel');
        self::assertResponseStatusCodeSame(409);
        $this->switchUser('owner');
        $this->act($requestId, 'return');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('RETURNED', $this->requestStatus($requestId));
        self::assertSame('AVAILABLE', $this->availability());
        $this->act($requestId, 'return');
        self::assertResponseStatusCodeSame(409);
        $this->switchUser('requester');
        $this->client->request('GET', '/api/loan/requests');
        self::assertArrayNotHasKey('ownerEmail', $this->payload()['requests']['sent'][0]);
        $this->client->request('GET', '/api/discovery/book-copies');
        self::assertSame([$this->copyId], array_column($this->payload()['results'], 'bookCopyId'));
    }

    public function testRejectingAndCancellingReleasesCopy(): void
    {
        $this->switchUser('requester');
        $first = $this->requestLoan($this->copyId);
        $this->requestLoan($this->copyId);
        self::assertResponseStatusCodeSame(409);
        $this->switchUser('another');
        $this->requestLoan($this->copyId);
        self::assertResponseStatusCodeSame(409);
        $this->switchUser('owner');
        $this->act($first, 'reject');
        self::assertResponseStatusCodeSame(204);
        $this->act($first, 'accept');
        self::assertResponseStatusCodeSame(409);
        $this->switchUser('another');
        $second = $this->requestLoan($this->copyId);
        self::assertResponseStatusCodeSame(201);
        $this->act($second, 'cancel');
        self::assertResponseStatusCodeSame(204);
        $this->switchUser('requester');
        $third = $this->requestLoan($this->copyId);
        $this->switchUser('owner');
        $this->act($third, 'accept');
        $this->act($third, 'cancel');
        self::assertResponseStatusCodeSame(204);
        $this->switchUser('requester');
        $this->client->request('GET', '/api/loan/requests');
        self::assertArrayNotHasKey('ownerEmail', $this->payload()['requests']['sent'][0]);
        $fourth = $this->requestLoan($this->copyId);
        $this->switchUser('owner');
        $this->act($fourth, 'accept');
        $this->switchUser('requester');
        $this->act($fourth, 'cancel');
        self::assertResponseStatusCodeSame(204);
        self::assertSame('AVAILABLE', $this->availability());
    }

    public function testConcurrentRequestsAllowOnlyOneLoan(): void
    {
        $firstRequester = $this->switchUser('requester');
        $secondRequester = $this->switchUser('another');
        $primary = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $primary->beginTransaction();
        $worker = null;
        $pipes = [];
        try {
            $primary->fetchOne('SELECT id FROM library_book_copy WHERE id = :id FOR UPDATE', ['id' => $this->copyId]);
            $worker = proc_open(
                [PHP_BINARY, '/app/tests/Loan/Fixtures/CreateRequestWorker.php', $this->copyId, $secondRequester],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                '/app',
            );
            self::assertIsResource($worker);
            fclose($pipes[0]);
            $workerPid = (int) trim((string) fgets($pipes[1]));
            self::assertGreaterThan(0, $workerPid);
            $waiting = false;
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                $waiting = 'Lock' === $primary->fetchOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = :pid', ['pid' => $workerPid]);
                if ($waiting) break;
                usleep(50_000);
            }
            self::assertTrue($waiting, 'The competing request must actually wait on the locked copy.');
            $first = self::getContainer()->get(CreateLoanRequestHandler::class)->handle(new CreateLoanRequestCommand($firstRequester, $this->copyId));
            $primary->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(2, proc_close($worker), (string) $error);
            $worker = null;
            self::assertStringContainsString('CONFLICT', (string) $output);
            self::assertSame(1, (int) $this->db->fetchOne("SELECT count(*) FROM loan_request WHERE book_copy_id = :id AND status IN ('PENDING', 'ACCEPTED', 'LENT')", ['id' => $this->copyId]));
            self::assertSame('PENDING', $this->requestStatus($first));
        } finally {
            if ($primary->isTransactionActive()) $primary->rollBack();
            if (is_resource($worker)) {
                proc_terminate($worker);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($worker);
            }
        }
    }

    public function testFailedReturnRollsBackLoanAndCopy(): void
    {
        $this->switchUser('requester');
        $requestId = $this->requestLoan($this->copyId);
        $this->switchUser('owner');
        $this->act($requestId, 'accept');
        $this->act($requestId, 'lend');
        self::assertSame('LENT', $this->requestStatus($requestId));
        self::assertSame('UNAVAILABLE', $this->availability());

        $real = self::getContainer()->get(LoanRequestRepository::class);
        $broken = $this->createStub(LoanRequestRepository::class);
        $broken->method('copyIdOf')->willReturnCallback($real->copyIdOf(...));
        $broken->method('findById')->willReturnCallback($real->findById(...));
        $broken->method('save')->willThrowException(new RuntimeException('Forced failure after Library flush.'));
        $handler = new ChangeLoanRequestHandler($broken, self::getContainer()->get(LibraryForLoan::class), self::getContainer()->get(AtomicOperation::class), self::getContainer()->get(Clock::class));

        try {
            $handler->handle(new ChangeLoanRequestCommand($requestId, $this->ownerId, 'return'));
            self::fail('Loan write must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Forced failure after Library flush.', $exception->getMessage());
        }
        self::assertSame('LENT', $this->requestStatus($requestId));
        self::assertSame('UNAVAILABLE', $this->availability());
    }

    public function testDisablingLocationPreservesCopiesAndLoans(): void
    {
        $freeCopy = $this->createCopy(true);
        $viewerId = $this->switchUser('requester');
        $this->setLocation($viewerId);
        $this->client->request('GET', '/api/discovery/book-copies');
        self::assertCount(2, $this->payload()['results']);
        $requestId = $this->requestLoan($this->copyId);
        self::assertResponseStatusCodeSame(201);
        $this->switchUser('owner');
        $this->act($requestId, 'accept');
        self::assertResponseStatusCodeSame(204);
        $copiesBefore = $this->db->fetchAllAssociative('SELECT * FROM library_book_copy ORDER BY id');
        $loansBefore = $this->db->fetchAllAssociative('SELECT * FROM loan_request ORDER BY id');

        $this->client->jsonRequest('DELETE', '/api/profile/discovery-location', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame($copiesBefore, $this->db->fetchAllAssociative('SELECT * FROM library_book_copy ORDER BY id'));
        self::assertSame($loansBefore, $this->db->fetchAllAssociative('SELECT * FROM loan_request ORDER BY id'));
        self::assertNull($this->db->fetchOne('SELECT discovery_location FROM identity_user WHERE id = ?', [$this->ownerId]));

        $this->switchUser('requester');
        $this->client->request('GET', '/api/discovery/book-copies');
        self::assertSame([], $this->payload()['results']);
        $this->client->request('GET', '/api/library/users/'.$this->ownerId.'/book-copies');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, $this->payload()['library']['sharedCount']);
        $this->client->request('GET', '/api/library/book-copies/'.$freeCopy);
        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->payload()['bookCopy']['canRequestLoan']);
        self::assertNull($this->payload()['bookCopy']['approximateDistance']);
        $this->client->request('GET', '/api/loan/requests');
        self::assertSame('ACCEPTED', $this->payload()['requests']['sent'][0]['status']);
        self::assertSame('owner@example.com', $this->payload()['requests']['sent'][0]['ownerEmail']);

        $this->switchUser('owner');
        $this->client->jsonRequest('PUT', '/api/profile/discovery-location', ['latitude' => 41.9, 'longitude' => 12.5], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(200);
        $this->switchUser('requester');
        $this->client->request('GET', '/api/discovery/book-copies');
        self::assertSame([$freeCopy], array_column($this->payload()['results'], 'bookCopyId'));
        $this->switchUser('owner');
        $this->client->jsonRequest('DELETE', '/api/profile/discovery-location', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(204);
        $this->switchUser('requester');
        self::assertNotSame('', $this->requestLoan($freeCopy));
        self::assertResponseStatusCodeSame(201);
    }

    private function requestLoan(string $copyId): string
    {
        $this->client->jsonRequest('POST', '/api/loan/requests', ['bookCopyId' => $copyId], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        return 201 === $this->client->getResponse()->getStatusCode() ? $this->payload()['id'] : '';
    }

    private function act(string $id, string $action): void
    {
        $this->client->jsonRequest('POST', '/api/loan/requests/'.$id.'/'.$action, [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
    }

    private function switchUser(string $name): string
    {
        if ($this->users !== []) {
            $this->client->jsonRequest('POST', '/api/auth/logout', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
            self::assertResponseStatusCodeSame(204);
        }
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        if (!isset($this->users[$name])) {
            $this->client->jsonRequest('POST', '/api/auth/register', ['email' => $name.'@example.com', 'password' => 'correct horse battery staple', 'displayName' => $name], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
            self::assertResponseStatusCodeSame(201);
            $this->users[$name] = $this->payload()['user']['id'];
        } else {
            $this->client->jsonRequest('POST', '/api/auth/login', ['email' => $name.'@example.com', 'password' => 'correct horse battery staple'], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
            self::assertResponseStatusCodeSame(200);
        }
        return $this->users[$name];
    }

    private function createCopy(bool $publish): string
    {
        $this->client->jsonRequest('POST', '/api/library/book-copies', [
            'newBook' => ['title' => 'Dune', 'authors' => ['Frank Herbert'], 'publicationYear' => null, 'categoryIds' => [self::CATEGORY], 'isbn' => null],
            'notes' => null, 'publish' => $publish,
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201);
        return $this->payload()['bookCopy']['id'];
    }

    private function setLocation(string $id): void
    {
        $this->db->executeStatement('UPDATE identity_user SET discovery_location = ST_SetSRID(ST_MakePoint(12.5, 41.9), 4326)::geography WHERE id = :id', ['id' => $id]);
    }

    private function availability(): string
    {
        return (string) $this->db->fetchOne('SELECT availability_status FROM library_book_copy WHERE id = :id', ['id' => $this->copyId]);
    }

    private function requestStatus(string $id): string
    {
        return (string) $this->db->fetchOne('SELECT status FROM loan_request WHERE id = :id', ['id' => $id]);
    }

    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
