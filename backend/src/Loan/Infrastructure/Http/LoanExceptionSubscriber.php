<?php

declare(strict_types=1);

namespace App\Loan\Infrastructure\Http;

use App\Loan\Application\ChangeLoanRequest\LoanActionForbidden;
use App\Loan\Application\ChangeLoanRequest\LoanRequestNotFound;
use App\Loan\Application\CreateLoanRequest\LoanTargetNotFound;
use App\Shared\Application\UniqueConstraintConflict;
use App\Shared\Infrastructure\Http\ApiResponse;
use DomainException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LoanExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 10]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/loan/')) {
            return;
        }
        $exception = $event->getThrowable();
        $response = match (true) {
            $exception instanceof LoanTargetNotFound => ApiResponse::error('book_copy_not_found', 'Book copy not available.', 404),
            $exception instanceof LoanRequestNotFound => ApiResponse::error('loan_request_not_found', 'Request not available.', 404),
            $exception instanceof LoanActionForbidden => ApiResponse::error('loan_action_forbidden', 'Action not allowed.', 403),
            $exception instanceof UniqueConstraintConflict => ApiResponse::error('loan_conflict', 'La richiesta o la copia non è più disponibile per questa operazione.', 409),
            $exception instanceof DomainException => ApiResponse::error('loan_conflict', 'La richiesta o la copia non è più disponibile per questa operazione.', 409),
            default => null,
        };
        if (null !== $response) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $event->setResponse($response);
        }
    }
}
