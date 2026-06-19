<?php

namespace App\Notes\Presentation\Form;

use App\Notes\Domain\Entity\Note;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'A useful title',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'help' => 'Markdown is supported. Use `inline code` or fenced blocks with ```.',
                'attr' => [
                    'rows' => 14,
                    'placeholder' => "Write the note...\n\nUse `inline code` or:\n\n```sh\nmake migrate\n```",
                ],
            ])
            ->add('isPinned', CheckboxType::class, [
                'label' => 'Pin this note',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Note::class,
        ]);
    }
}
