<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\InvoiceType;
use App\Service\InvoiceManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HTTP and forms only.
 *
 * The controller depends on the service interface and exchanges DTOs with it:
 * no repository, no entity manager and no Doctrine entity ever reaches this
 * layer or the templates.
 */
#[Route('/invoices', name: 'invoice_')]
class InvoiceController extends AbstractController
{
    public function __construct(private readonly InvoiceManagerInterface $invoiceManager)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('invoice/index.html.twig', [
            'invoices' => $this->invoiceManager->getInvoices(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $invoice = $this->invoiceManager->createDraft();

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $created = $this->invoiceManager->create($invoice);
            $this->addFlash('success', sprintf('Invoice #%d has been created.', $created->invoiceNumber));

            return $this->redirectToRoute('invoice_show', ['id' => $created->id]);
        }

        return $this->render('invoice/new.html.twig', [
            'invoice' => $invoice,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $this->invoiceManager->getInvoice($id),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $invoice = $this->invoiceManager->getInvoice($id);

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updated = $this->invoiceManager->update($invoice);
            $this->addFlash('success', sprintf('Invoice #%d has been updated.', $updated->invoiceNumber));

            return $this->redirectToRoute('invoice_show', ['id' => $updated->id]);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $invoice = $this->invoiceManager->getInvoice($id);

        if ($this->isCsrfTokenValid('delete_invoice_'.$id, (string) $request->request->get('_token'))) {
            $this->invoiceManager->delete($id);
            $this->addFlash('success', sprintf('Invoice #%d has been deleted.', $invoice->invoiceNumber));
        }

        return $this->redirectToRoute('invoice_index');
    }
}
