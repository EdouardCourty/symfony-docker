<?php

namespace App\Controller\Administration\CRUD;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Users')
            ->setAutofocusSearch();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm()->hideOnIndex();
        yield TextField::new('username', 'Username');
        yield EmailField::new('email', 'Email');
        yield BooleanField::new('enabled', 'Enabled');
        yield DateTimeField::new('lastLogin', 'Last Login')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Registration date')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Last update')->hideOnForm()->hideOnIndex();

        yield ChoiceField::new('roles', 'Roles')
            ->hideOnIndex()
            ->setChoices(array_combine(User::ROLES, User::ROLES))
            ->allowMultipleChoices();

        yield TextField::new('googleId', 'Google ID')->hideOnForm()->hideOnIndex();
    }
}
