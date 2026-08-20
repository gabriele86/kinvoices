<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\InvoiceNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Translates the domain exception raised by the service layer into a 404, so
 * that controllers do not have to catch it themselves.
 */
class InvoiceNotFoundSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof InvoiceNotFoundException) {
            $event->setThrowable(new NotFoundHttpException($exception->getMessage(), $exception));
        }
    }
}
