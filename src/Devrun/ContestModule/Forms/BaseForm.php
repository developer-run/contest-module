<?php
/**
 *
 * This file is part of the devrun-smart
 * Copyright (c) 2014 Pavel Paulík (http://www.pavelpaulik.cz)
 *
 */

namespace Devrun\ContestModule\Forms;

use Devrun\Doctrine\DoctrineForms\EntityFormTrait;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\CheckboxList;
use Nette\Forms\Controls\MultiSelectBox;
use Nette\Forms\Controls\RadioList;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\TextBase;
use Nette\Forms\Rendering\DefaultFormRenderer;

class BaseForm extends Form
{

    use EntityFormTrait;

    /** @var string */
    protected $redirect;

    /** @var string */
    protected $section = 'questions';


    /**
     * @param mixed $redirect
     */
    public function setRedirect($redirect)
    {
        $this->redirect = $redirect;
    }

    /**
     * @return string
     */
    public function getRedirect()
    {
        return $this->redirect ? $this->redirect : 'this';
    }

    public function bootstrap3Render()
    {
        /** @var $renderer DefaultFormRenderer */
        $renderer = $this->getRenderer();
        $renderer->wrappers['controls']['container']     = NULL;
        $renderer->wrappers['pair']['container']         = 'div class=form-group';
        $renderer->wrappers['pair']['.error']            = 'has-error';
        $renderer->wrappers['control']['container']      = 'div class=col-sm-9';
        $renderer->wrappers['label']['container']        = 'div class="col-sm-3 control-label"';
        $renderer->wrappers['control']['description']    = 'span class=help-block';
        $renderer->wrappers['control']['errorcontainer'] = 'span class=help-block';

        // make form and controls compatible with Twitter Bootstrap
        $this->getElementPrototype()->class('form-horizontal');

        foreach ($this->getControls() as $control) {
            if ($control instanceof Button) {
                $control->getControlPrototype()->addClass(empty($usedPrimary) ? 'btn btn-primary' : 'btn btn-default');
                $usedPrimary = TRUE;

            } /** @var $control BaseControl */
            elseif ($control instanceof TextBase ||
                $control instanceof SelectBox ||
                $control instanceof MultiSelectBox
            ) {
                $control->getControlPrototype()->addClass('form-control');

            } elseif ($control instanceof Checkbox ||
                $control instanceof CheckboxList ||
                $control instanceof RadioList
            ) {
                $control->getSeparatorPrototype()->setName('div')->addClass($control->getControlPrototype()->type);
            }
        }
    }


    protected function checkCompleteRegistration(UserManager $userManager)
    {
        $user = $this->presenter->getUser();
        if (!$user->isLoggedIn()) {
            return false;
        }

        /** @var $userData UserEntity */
        if ($userData = $userManager->getUserDao()->find($user->id)) {
            return $userData->isAllRequiderFill();
        }

        return false;
    }


}