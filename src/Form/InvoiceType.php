<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\InvoiceDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('invoiceDate', DateType::class, [
                'label' => 'Invoice date',
                'widget' => 'single_text',
            ])
            ->add('invoiceNumber', IntegerType::class, [
                'label' => 'Invoice number',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                ],
            ])
            ->add('customerId', IntegerType::class, [
                'label' => 'Customer id',
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                ],
            ])
            ->add('lines', CollectionType::class, [
                'label' => 'Invoice lines',
                'entry_type' => InvoiceLineType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                // Forces addLine()/removeLine() to be called, so the owning side
                // of the association is kept in sync.
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__line__',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceDto::class,
        ]);
    }
}
