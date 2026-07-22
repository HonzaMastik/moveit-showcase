<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\SmartObject;

final class RecipesForm
{
    use SmartObject;

    public function __construct(private readonly Translator $translator) {}

    public function create(bool $isEdit = false): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);

        $form->addHidden('id');

        // Kategorie receptu
        $form->addSelect('category', 'frontend.recipes.category', [
            'breakfasts' => 'frontend.recipes.breakfasts',
            'lunches' => 'frontend.recipes.lunches',
            'snacks' => 'frontend.recipes.snacks',
            'dinners' => 'frontend.recipes.dinners',
            'deserts' => 'frontend.recipes.deserts',
        ])
            ->setHtmlAttribute('required', true)
            ->setPrompt('frontend.recipes.selectCategory');

        // Název receptu
        $form->addText('title', 'frontend.recipes.createRecipeTitle')
            ->setHtmlType('text')
            ->setHtmlAttribute('required', true)
            ->addRule(
                Form::MAX_LENGTH,
                'frontend.recipes.messages.maxLength',
                50
            );

        // Obsah receptu – s WYSIWYG editor
        $form->addTextArea('content', 'frontend.recipes.content')
            ->setHtmlAttribute('class', 'wysiwyg')
            ->setHtmlAttribute('required', true);

        // Obrázek
        $form->addUpload('image_path', 'frontend.recipes.uploadPhoto');

        $form->addHidden('remove_image')->setDefaultValue('0');

        $form->addSubmit(
            'submit',
            $this->translator->translate('frontend.recipes.submit.label')
        );

        $form->addProtection();

        return $form;
    }
}
