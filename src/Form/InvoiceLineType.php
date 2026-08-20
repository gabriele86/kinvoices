<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\InvoiceLineDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Goods or services supplied',
                ],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                ],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount',
                'scale' => InvoiceLineDto::SCALE,
                'html5' => true,
                // Keeps the value a decimal string all the way to Doctrine.
                'input' => 'string',
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'data-invoice-line-target' => 'amount',
                ],
            ])
            ->add('vatAmount', NumberType::class, [
                'label' => 'VAT amount',
                'scale' => InvoiceLineDto::SCALE,
                'html5' => true,
                'input' => 'string',
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'data-invoice-line-target' => 'vat',
                ],
            ])
            ->add('totalWithVat', NumberType::class, [
                'label' => 'Total with VAT',
                'scale' => InvoiceLineDto::SCALE,
                'html5' => true,
                'input' => 'string',
                // Always derived from amount + VAT, never trusted from the request.
                'disabled' => true,
                'required' => false,
                'attr' => [
                    'data-invoice-line-target' => 'total',
                    'tabindex' => -1,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceLineDto::class,
        ]);
    }
}
