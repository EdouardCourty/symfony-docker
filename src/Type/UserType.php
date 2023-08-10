<?php

namespace App\Type;

use App\Entity\User;
use App\Validation\Constraint\Password;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'constraints' => [
                    new Length(max: 50),
                    new NotNull()
                ]
            ])
            ->add('email', EmailAddressType::class, [
                'constraints' => [
                    new Email(),
                    new NotNull()
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'constraints' => [
                    new Password(),
                    new Length(min: 10)
                ],
                'first_options' => [
                    'label' => 'Password'
                ],
                'second_options' => [
                    'label' => 'Password confirmation'
                ]
            ])
            ->add('submit', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'trim' => true,
            'required' => true,
            'data_class' => User::class,
            'mapped' => false,
            'empty_data' => new User()
        ]);
    }
}
