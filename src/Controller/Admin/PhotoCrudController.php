<?php

namespace App\Controller\Admin;

use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Photo::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $isNew = Crud::PAGE_NEW === $pageName;

        return [
            IdField::new('id')->hideOnForm(),

            Field::new('imageFile')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => $isNew,
                ])
                ->onlyOnForms(),

            ImageField::new('path')
                ->onlyOnIndex(),

            TextField::new('prestationReference')->setRequired(false),
            TextField::new('internalOrder')->setRequired(false),
            IntegerField::new('interventionId')->setRequired(false),
            DateTimeField::new('dateTaken')->setRequired(false),
            TextField::new('location')->setRequired(false),
            TextField::new('cameraModel')->setRequired(false),

            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('prestationReference'))
            ->add(TextFilter::new('internalOrder'))
            ->add(NumericFilter::new('interventionId'))
            ->add(TextFilter::new('filename'))
            ->add(DateTimeFilter::new('dateTaken'))
            ->add(DateTimeFilter::new('createdAt'));
    }


    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Photo) {
            return;
        }

        $request = $this->getContext()->getRequest();
        $file = $request->files->get('Photo')['imageFile'] ?? null;

        if (!$file instanceof UploadedFile) {
            throw new \RuntimeException('Le fichier image est obligatoire.');
        }

        $this->replacePhotoFile($entityInstance, $file);
        $entityInstance->setCreatedAt(new \DateTimeImmutable());

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Photo) {
            return;
        }

        $request = $this->getContext()->getRequest();
        $file = $request->files->get('Photo')['imageFile'] ?? null;

        if ($file instanceof UploadedFile) {
            $this->replacePhotoFile($entityInstance, $file);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Photo) {
            $path = $entityInstance->getPath();
            if ($path) {
                $absolutePath = $this->getParameter('kernel.project_dir') . '/public' . $path;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private function replacePhotoFile(Photo $photo, UploadedFile $file): void
    {
        $mimeType = $file->getClientMimeType();
        $size = $file->getSize() ?? 0;
        $allowedMimeTypes = ['image/jpeg', 'image/png'];

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \RuntimeException('Format invalide. Formats autorises: JPEG, PNG.');
        }

        if ($size > 5 * 1024 * 1024) {
            throw new \RuntimeException('Fichier trop volumineux (max 5MB).');
        }

        $oldPath = $photo->getPath();
        $newFilename = uniqid('', true) . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

        $file->move($uploadDir, $newFilename);

        $photo->setFilename($newFilename);
        $photo->setOriginalName($file->getClientOriginalName());
        $photo->setSize((int) $size);
        $photo->setMimeType((string) $mimeType);
        $photo->setPath('/uploads/' . $newFilename);

        if ($oldPath) {
            $oldAbsolutePath = $this->getParameter('kernel.project_dir') . '/public' . $oldPath;
            if (is_file($oldAbsolutePath)) {
                @unlink($oldAbsolutePath);
            }
        }
    }
}