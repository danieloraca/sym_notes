<?php

declare(strict_types=1);

namespace App\Notes\Presentation\Form;

use App\Notes\Domain\Entity\Folder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FolderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => [
                    'maxlength' => 120,
                    'placeholder' => 'Projects',
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => Folder::class,
                'choices' => $options['folders'],
                'choice_label' => static fn (Folder $folder): string => $folder->getPath(),
                'label' => 'Parent folder',
                'help' => 'Leave empty to create a top-level folder.',
                'required' => false,
                'placeholder' => 'No parent (top level)',
            ])
            ->add('sortPosition', IntegerType::class, [
                'label' => 'Sort position',
                'required' => false,
                'empty_data' => '0',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Folder::class,
            'folders' => [],
        ]);

        $resolver->setAllowedTypes('folders', 'array');
    }
}
