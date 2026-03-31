<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Promotion;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sku', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter SKU']
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => ['class' => 'form-control']
            ])
            ->add('price', NumberType::class, [
                'attr' => ['class' => 'form-control']
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('reserved', IntegerType::class, [
                'attr' => ['class' => 'form-control']
            ])
            ->add('promotions', EntityType::class, [
                'class' => Promotion::class,
                'choice_label' => 'description',
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
