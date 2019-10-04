<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    LoginForm.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Forms;

use Nette\Forms\Form;
use Nette\Security\IUserStorage;

interface ILoginFormFactory
{
    /** @return LoginForm */
    function create();
}

class LoginForm extends BaseForm
{

    /**
     * @return LoginForm
     */
    public function create()
    {
        $this->addText('username', 'Přihlašovací jméno')
            ->setAttribute('placeholder', "Zadejte prosím své přihlašovací jméno")
            ->addRule(Form::FILLED, 'Zadejte prosím své přihlašovací jméno')
            ->addRule(Form::MIN_LENGTH, 'Přihlašovací jméno musí mít minimálně %d znaky.', 4)
            ->addRule(Form::MAX_LENGTH, 'Přihlašovací jméno může mít maximálně %d znaků.', 32);

        $this->addPassword('password', 'Heslo')
            ->setAttribute('placeholder', "Zadejte heslo.")
            ->addRule(Form::FILLED, 'Zadejte heslo.');

        $this->addCheckbox('remember', 'Zapamatovat si')->getControl()->class[] = 'icheck';
        $reset = $this->addSubmit('reset', 'Nové údaje');
        $this->addSubmit('send', 'Přihlásit se')->getControlPrototype()->class = 'btn btn-primary btn-sm pull-right';
        $this->onSuccess[] = array($this, 'formSuccess');

        $reset->setValidationScope(false);
        $reset->getControlPrototype()->class = 'btn btn-info btn-sm pull-left';
        $reset->onClick[] = array($this, 'formReset');

        $this->getElementPrototype()->class = 'margin-bottom-0';

        return $this;
    }


    public function formReset()
    {
        $presenter = $this->getPresenter();


        $presenter->redirect('Form:');
    }


    public function formSuccess(LoginForm $form, $values)
    {
        $presenter = $this->getPresenter();

        try {
            $user = $presenter->getUser();

            $user->login($values['username'], $values['password']);
//            $this->onLoggedIn($this, $user);

            if ($values['remember']) {
                $user->setExpiration('14 days');

            } else {
                $user->setExpiration('14 hours', IUserStorage::CLEAR_IDENTITY);
            }


        } catch (\Nette\Security\AuthenticationException $e) {
            $form->addError($e->getMessage());
        }

    }



}