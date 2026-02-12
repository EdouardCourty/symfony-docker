<?php

declare(strict_types=1);

namespace App\Controller\Admin\CRUD;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setSearchFields(['username'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield TextField::new('username', 'Username')
            ->setRequired(true);

        yield TextField::new('password', 'Password')
            ->onlyWhenCreating()
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->setHelp($pageName === CRUD::PAGE_EDIT ? 'Leave empty to keep current password' : '');

        yield ChoiceField::new('roles', 'Roles')
            ->setChoices(array_combine(User::ROLES, User::ROLES))
            ->allowMultipleChoices()
            ->onlyOnForms();

        yield ArrayField::new('roles', 'Roles')
            ->hideOnForm();

        yield BooleanField::new('enabled', 'Enabled')->hideWhenCreating();

        yield DateTimeField::new('createdAt', 'Created At')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Updated At')
            ->hideOnForm();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $plainPassword = $entityInstance->getPassword();
        $hashedPassword = $this->passwordHasher->hashPassword($entityInstance, $plainPassword);
        $entityInstance->setPassword($hashedPassword);

        parent::persistEntity($entityManager, $entityInstance);
    }
}
