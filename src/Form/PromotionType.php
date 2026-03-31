<?php

namespace App\Form;

use App\Entity\Promotion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PromotionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter description']
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Percentage' => 'percentage',
                    'Cash' => 'cash',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('value', NumberType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter value']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Promotion::class,
        ]);
    }
}
