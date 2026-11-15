<?php

declare(strict_types=1);

use App\Loan\Application\CreateLoanRequest\CreateLoanRequestCommand;
use App\Loan\Application\CreateLoanRequest\CreateLoanRequestHandler;
use App\Loan\Application\CreateLoanRequest\LoanTargetNotFound;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';
require dirname(__DIR__, 3).'/tests/bootstrap.php';

final class LoanWorkerKernel extends KernelTestCase
{
    public static function services(): \Psr\Container\ContainerInterface
    {
        self::bootKernel();
        return self::getContainer();
    }
}

$container = LoanWorkerKernel::services();
$db = $container->get(Connection::class);
fwrite(STDOUT, $db->fetchOne('SELECT pg_backend_pid()')."\n");
fflush(STDOUT);
try {
    $container->get(CreateLoanRequestHandler::class)->handle(new CreateLoanRequestCommand($argv[2], $argv[1]));
    fwrite(STDOUT, "CREATED\n");
    exit(0);
} catch (\DomainException|LoanTargetNotFound) {
    fwrite(STDOUT, "CONFLICT\n");
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
