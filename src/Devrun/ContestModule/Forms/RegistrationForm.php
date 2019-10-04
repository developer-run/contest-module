<?php

namespace Devrun\ContestModule\Forms;

use Devrun\CmsModule\InvalidStateException;
use Devrun\ContestModule\Presenters\BaseContestPresenter;
use Devrun\Doctrine\Entities\UserEntity;
use Devrun\Doctrine\Repositories\UserRepository;
use Doctrine\DBAL\LockMode;
use Kdyby\Monolog\Logger;
use Kdyby\Translation\ITranslator;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\User;

interface IRegistrationFormFactory
{
    /** @return RegistrationForm */
    function create();
}

/**
 * Class RegistrationFormFactory
 *
 * @package ContestModule\Forms
 */
class RegistrationForm extends BaseForm
{
    const MIN_YEARS = 16;

    const YEAR_VALIDATOR = 'ContestModule\Forms\RegistrationFormFactory::yearChecked';

    private $minYears = 16;

    /** @var string */
    private $locale;

    /** @var User @inject */
    public $user;

    /** @var Logger @inject */
    public $logger;


    /** @var ITranslator $translator @inject */
    public $translator;

    /** @var UserRepository @inject */
    public $userRepository;

//    /** @var QuestionRepository @inject */
    public $questionRepository;

    /** @var Nette\Forms\Container */
    private $userContainer;

    /** @var Nette\Http\SessionSection */
    private $sessionSection;

    /** @var bool sending email? (DI) */
    private $emailSending = true;

    /** @var bool */
    private $flush = true;

    /** @var string sending from email? (DI) */
    private $emailFrom;

    /** @var UserEntity */
    private $existUserEntity;


    /**
     * @var callable function ($entity, UserEntity $userEntity); Occurs when the form is submitted and need reload entity
     * reload form entity if needed, @see sendRegistrationForm.puml or onSuccess section loggedOut/userExist
     */
    public $callReloadEntity;


    /**
     * @param null $default
     *
     * @return $this
     */
    public function addGender($default = null)
    {
        $gender = $this->userContainer->addRadioList('gender', 'pohlaví', array(0 => 'žena', 1 => 'muž'))
            ->addRule(Form::FILLED, 'zvolte_pohlaví')
            ->setAttribute("tabindex", 2);

        if ($default) {
            $gender->setDefaultValue($default);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addBirthDay()
    {
        $days = array();
        foreach (range(1, 31) as $index) {
            $days[$index] = $index;
        }

        $this->userContainer->addSelect('day', 'den', $days)
            ->setPrompt($this->translator->translate('pexesosestka.registrationForm.den'))
            ->setTranslator(null)
            ->setAttribute("tabindex", $this->locale == 'hu' ? 11 : 10)
            ->setAttribute('placeholder', 'den')
            ->addRule(Form::FILLED, 'vyplňte_den_narození_správně')
            ->addCondition(Form::FILLED)
            ->addRule(Form::RANGE, 'vyplňte_den_narození_správně', array(1, 31));
        $this->userContainer['day']->controlPrototype->class = 'select-day';
        $this->userContainer['day']->controlPrototype->style = 'width: 100%';

        $month = array();
        foreach (range(1, 12) as $index) {
            $month[$index] = $index;
        }
        $this->userContainer->addSelect('month', 'měsíc', $month)
            ->setPrompt($this->translator->translate('pexesosestka.registrationForm.měsíc'))
            ->setTranslator(null)
            ->setAttribute("tabindex", $this->locale == 'hu' ? 10 : 11)
            ->addRule(Form::FILLED, 'vyplňte_měsíc_narození_správně')
            ->addCondition(Form::FILLED)
            ->addRule(Form::RANGE, 'vyplňte_měsíc_narození_správně', array(1, 12));
        $this->userContainer['month']->controlPrototype->class = 'select-month';
        $this->userContainer['month']->controlPrototype->style = 'width: 100%';

        $currentYear = intval(date('Y'));
        $years       = array();
        for ($index = $currentYear; $index >= 1900; $index--) {
            $years[$index] = $index;
        }


        $this->userContainer->addSelect('year', 'rok', $years)
            ->setPrompt($this->translator->translate('pexesosestka.registrationForm.rok'))
            ->setTranslator(null)
            ->setAttribute("tabindex", $this->locale == 'hu' ? 9 : 12)
            ->addRule(Form::FILLED, 'musíte_být_starší_x_let')
            ->addCondition(Form::FILLED)
//            ->addRule(Form::RANGE, 'musíte_být_starší_x_let', array(null, $currentYear - $this->minYears))
            ->addRule(self::YEAR_VALIDATOR, 'musíte_být_starší_x_let', [$this->userContainer['day'], $this->userContainer['month'], intval($this->minYears)]);

        $this->userContainer['year']->controlPrototype->class = 'select-year';
        $this->userContainer['year']->controlPrototype->style = 'width: 100%';

        $this->userContainer['day']->addConditionOn($this->userContainer['month'], Form::FILLED)->addRule(Form::RANGE, 'vyplňte_den_narození_správně', array(1, 31));
        $this->userContainer['day']->addConditionOn($this->userContainer['year'], Form::FILLED)->addRule(Form::RANGE, 'vyplňte_den_narození_správně', array(1, 31));
        $this->userContainer['month']->addConditionOn($this->userContainer['day'], Form::FILLED)->addRule(Form::RANGE, 'vyplňte_měsíc_narození_správně', array(1, 12));
        $this->userContainer['month']->addConditionOn($this->userContainer['year'], Form::FILLED)->addRule(Form::RANGE, 'vyplňte_měsíc_narození_správně', array(1, 12));
        $this->userContainer['year']->addConditionOn($this->userContainer['day'], Form::FILLED)->addRule(Form::RANGE, 'musíte_být_starší_x_let', array(null, $currentYear - $this->minYears));
        $this->userContainer['year']->addConditionOn($this->userContainer['month'], Form::FILLED)->addRule(Form::RANGE, 'musíte_být_starší_x_let', array(null, $currentYear - $this->minYears));


        return $this;
    }

    /**
     * @return $this
     */
    public function addAddress()
    {
        $this->userContainer->addText('street', 'ulice')
            ->addRule(Form::FILLED, 'vyplňte_vaši_ulici')
//            ->setAttribute('placeholder', 'ulice')
            ->setAttribute("tabindex", 15)
            ->controlPrototype->class = 'no-margin input-short';

        $this->userContainer->addText('strno', 'č.p.')
            ->addRule(Form::FILLED, 'vyplňte_číslo_popisné')
            // ->addRule(Form::PATTERN, 'vyplňte_číslo_popisné_správně', '[\/0-9]+[aA-zZ]*')
//            ->setAttribute('placeholder', 'č.p.')
            ->setAttribute("tabindex", 6)
            ->controlPrototype->class = 'input-shorter';


        $this->userContainer->addText('city', 'město')
            ->addRule(Form::FILLED, 'vyplňte_město')
//            ->setAttribute('placeholder', 'město')
            ->setAttribute("tabindex", 18)
            ->controlPrototype->class = 'input-short';

        $zip = $this->userContainer->addText('zip', 'PSČ')
            ->addRule(Form::FILLED, 'vyplňte_psč')
//            ->setAttribute('placeholder', 'PSČ')
            ->setAttribute("tabindex", 16);
        $zip->getControlPrototype()->class = 'no-margin input-shorter';

        if ($this->locale == 'hu') {
            $zip
                ->addRule(Form::INTEGER, 'vyplňte_psč_správně')
                ->addRule(Form::LENGTH, 'vyplňte_psč_správně', 4);

        } elseif ($this->locale == 'sk') {
            $zip
                ->addRule(Form::LENGTH, 'vyplňte_psč_správně', 5)
                ->addRule(Form::PATTERN, 'vyplňte_psč_správně', '([0-9]){5}');

        } else {
            $zip
                ->addRule(Form::LENGTH, 'vyplňte_psč_správně', 5)
                ->addRule(Form::PATTERN, 'vyplňte_psč_správně', '([1-9]{1})([0-9]){4}');
        }

        return $this;
    }


    /**
     * @return $this
     */
    public function addPrivacy()
    {
        $this->addCheckbox('privacy')
            ->setAttribute("tabindex", 23)
            ->addRule(Form::FILLED, 'potvrďte_souhlas_s_pravidly_soutěže')
            ->controlPrototype->class = 'id-privacy';

        return $this;
    }

    /**
     * @return $this
     */
    public function addNewsletter()
    {
        $this->addCheckbox('newsletter', 'posílat_newsletter')
            ->setAttribute("tabindex", $this->locale == 'hu' ? 24 : 24)
            ->controlPrototype->class = 'id-newsletter';

        return $this;
    }


    /**
     * @return $this
     */
    public function create()
    {
        $this->locale = $this->translator->getLocale();
        if ($this->locale == 'hu') $this->minYears = 18;

        $user = $this->addContainer('createdBy');

        $this->addHidden('originalEmail');

        $user->addText('firstName', 'jméno')
            ->addRule(Form::FILLED, 'vyplňte_vaše_křestní_jméno')
//            ->setAttribute('placeholder', 'jméno')
            ->setAttribute("tabindex", $this->locale == 'hu' ? 4 : 3)
            ->controlPrototype->class = 'id-firstname';

        $user->addText('lastName', 'příjmení')
            ->addRule(Form::FILLED, 'vyplňte_vaše_příjmení')
//            ->setAttribute('placeholder', 'příjmení')
            ->setAttribute("tabindex", $this->locale == 'hu' ? 3 : 4)
            ->controlPrototype->class = 'id-lastname';

        $user->addText('email', 'e-mail')
            ->addRule(Form::EMAIL, 'vyplňte_platný_e-mail')
            ->addRule(Form::FILLED, 'vyplňte_e-mail')
//            ->setAttribute('placeholder', 'e-mail')
            ->setAttribute("tabindex", 6)
            ->controlPrototype->class = 'no-margin';


        $btn = $this->addSubmit('send', 'odeslat')
            ->setAttribute('class', 'btn-md')
            ->setAttribute("tabindex", 26);
//            ->getControlPrototype();
//        $btn->setName("button")
//            ->setText($this->translator->translate('pexesosestka.registrationForm.odeslat'))
//            ->type = 'submit';
//        $btn->create('strong class="space"');

//        $this->getElementPrototype()->class[] = 'ajax';

        $this->userContainer = $user;
        $this->onValidate[] = array($this, 'validateAccount');
        $this->onSuccess[] = array($this, 'success');

        return $this;
    }


    /**
     * validate better birthday
     *
     * @param Nette\Forms\Controls\BaseControl $control
     * @param                                  $values
     *
     * @return bool
     * @throws \Exception
     */
    public static function yearChecked(Nette\Forms\Controls\BaseControl $control, $values)
    {
        $year     = $control->getValue();
        $day      = $values[0];
        $month    = $values[1];
        $minYears = $values[2];

        $date      = new Nette\Utils\DateTime("$year-$month-$day");
        $checkDate = new Nette\Utils\DateTime("-$minYears years");

        return $date < $checkDate;
    }



    protected function attached($presenter)
    {
        if ($presenter instanceof BaseContestPresenter) {
//            $this->setAction(new Nette\Application\UI\Link($presenter, 'this', ['package' => $presenter->getPackage()]));
        }

        if(!$this->user->loggedIn) {
            $this->refreshSessionSaveData();
        }

        parent::attached($presenter);
    }



    /**
     * @param Nette\Http\SessionSection $sessionSection
     */
    private function setSessionSection($sessionSection = null)
    {
        $session = $this->getPresenter()->getSession();
        $this->sessionSection = $session->getSection($this->getName());
    }

    /**
     * @return Nette\Http\SessionSection
     */
    public function getSessionSection()
    {
        if (null == $this->sessionSection) {
            $this->setSessionSection();
        }

        return $this->sessionSection;
    }

    public function refreshSessionSaveData()
    {
        $section = $this->getSessionSection();
        if (isset($section->saveValues)) {
            $this->setDefaults($section->saveValues);
        }

    }





    public function validateAccount(Form $form, $values)
    {
        $user = $this->user;
        $entity = $this->getEntity();

        /** @var $userEntity UserEntity */
        $userEntity = $entity->createdBy;


        /*
         * loggedIn
         */
        if ($user->isLoggedIn()) {

            /*
             * not admin
             */
            if (in_array($userEntity->getRole(), [UserEntity::ROLE_GUEST, UserEntity::ROLE_MEMBER])) {

                $originalEmail  = $values->originalEmail;

                /*
                 * change email?
                 */
                if ($originalEmail && $originalEmail != $values->createdBy->email) {

                    if ($existNewUser = $this->userRepository->findOneBy(['email' => $values->createdBy->email])) {
                        /*
                         * new user exist, must end
                         * @todo error hláška není dořešena
                         */
                        $form->addError("Zadaný e-mail již někomu patří");
                        return false;
                    }
                }
            }
        }

        return true;
    }



    public function success(Form $form, $values)
    {
        $presenter = $this->getPresenter();

        /** @var ResultEntity $entity */
        $entity = $this->getEntity();

        /** @var $userEntity UserEntity */
        $userEntity = $this->getEntity()->createdBy;

//        dump($values);
//        dump($entity);
//        die();


        /*
         * set birthday
         */
        if (isset($values->createdBy->day)) {
            $entity->getCreatedBy()->setBirthdayFromParts($values->createdBy->day, $values->createdBy->month, $values->createdBy->year);
        }


        /*
         * loggedIn
         */
        if ($this->user->isLoggedIn()) {

            /*
             * not admin
             */
            if (in_array($userEntity->getRole(), [UserEntity::ROLE_GUEST, UserEntity::ROLE_MEMBER])) {

                $originalEmail  = $values->originalEmail;

                /*
                 * change email?
                 */
                if ($originalEmail && $originalEmail != $userEntity->getEmail()) {
                    if ($existNewUser = $this->userRepository->findOneBy(['email' => $values->createdBy->email])) {
                        /*
                         * new user exist, must end
                         * @todo error hláška je zobrazena v onValidateAccount, zde již není potřeba šetřit
                         */
                        $form->addError("Zadaný e-mail již někomu patří");
                        $presenter->flashMessage("Zadaný e-mail již někomu patří", 'warning');
                        $presenter->redirect('this');

                    } else {
                        /*
                         * was change email, send mail info
                         * @todo send email není dořešen
                         */
                        if ($this->emailSending) {
//                            $this->sendMail($userEntity->getEmail());
                        }
                    }

                } else {


                }

            } else {
                /*
                 * is admin, reload original entity
                 */
                $userEntity = $this->userRepository->find($this->user->id, LockMode::PESSIMISTIC_READ);
            }


            /*
             * loggedOut
             */
        } else {

            /** @var UserEntity $existUser */
            if ($existUser = $this->getExistUserEntity($userEntity->getEmail())) {

                /*
                 * user has exist, reload form entity
                 */
                $userEntity = $existUser;
                if (!$this->callReloadEntity) {
                    throw new InvalidStateException("set callReloadEntity for reload entity");
                }

                static $rebind = false;

                if (!$rebind) {
                    $rebind = true;

                    $entity = call_user_func_array($this->callReloadEntity, ['userEntity' => $existUser, $entity]);
                }
                if (in_array($userEntity->getRole(), [UserEntity::ROLE_GUEST, UserEntity::ROLE_MEMBER])) {

                    /*
                     * not admin
                     * fill only new values
                     */
                    foreach ($values->createdBy as $key => $value) {
                        if (isset($userEntity->$key) && empty($userEntity->$key)) {
                            $userEntity->$key = $value;
                        }
                    }
                }

                /*
                 * fill all entity values
                 */
                $ignoreKeys = ['createdBy'];
                foreach ($values as $key => $value) {
                    if (isset($entity->$key) && !in_array($key, $ignoreKeys)) {
                        $entity->$key = $value;
                    }
                }


            } else {

                /*
                 * new user
                 */
                $userEntity
                    ->setRole(UserEntity::ROLE_GUEST)
                    ->setUsername($userEntity->getEmail())
                    ->setPassword($userEntity->getFirstName());

            }


        }


        try {
//        dump($values);
//            dump($userEntity);
//            dump($entity);
//            die();

            if ($this->flush) {

                $em = $this->getEntityMapper()->getEntityManager();
                $em
                    ->persist($entity)
                    ->persist($userEntity)
                    ->flush();
            }

            /*
             * po úspěšné registraci
             */
            $section = $this->getSessionSection();
            $section->saveValues = $values;
            $section->registrationSend = true;
//            $section->setExpiration('2 hours', 'registrationSend');

            $this->setEntity($entity);

            /*
             * login
             */
            /*
            if (!$this->user->isLoggedIn()) {
                $this->user->login($entity->getUsername(), $values->password);
            }
            */


        } catch (\Kdyby\Doctrine\DuplicateEntryException $exc) {
            if (Nette\Utils\Strings::contains($exc->getMessage(), "1062")) {
                $message = 'email_již_existuje';
                $form->getPresenter()->flashMessage($presenter->translator->translate($message));
                return;
            }

            throw new \Kdyby\Doctrine\DuplicateEntryException($exc);
        }

    }


    /**
     * send email
     *
     * @param $mail
     */
    private function sendMail($mail)
    {
        /** @var BaseContestPresenter $presenter */
        $presenter  = $this->getPresenter();
        $translator = $this->getTranslator();
        $title      = $translator->translate('userPage.management');
        $userEntity = $this->getExistUserEntity($mail);

        $latte  = new \Latte\Engine;
        $params = [
            'url'      => $presenter->link("//:Cms:Login:forgottenPassword"),
            'link' => $presenter->link("//:Cms:Login:changePassword", ['id' => $userEntity->getId(), 'code' => $userEntity->getNewPassword()]),
        ];

        $message = new Nette\Mail\Message();
        $message->setFrom($this->emailFrom)
            ->addTo($mail)
            ->setHtmlBody($latte->renderToString(__DIR__ . "/template/forgottenEmail.latte", $params));

        $this->mailer->send($message);
        $this->logger->info("{$userEntity->getRole()} {$userEntity->getUsername()} sendMail", ['type' => LogEntity::ACTION_ACCOUNT, 'target' => $userEntity, 'action' => 'email send ok']);

        $message = $translator->translate('user_has_been_send_email');
        $presenter->flashMessage($message, 'info');
    }


    /**
     * @param bool $emailSending
     *
     * @return $this
     */
    public function setEmailSending($emailSending)
    {
        $this->emailSending = (bool)$emailSending;
        return $this;
    }

    /**
     * @param string $emailFrom
     *
     * @return $this
     */
    public function setEmailFrom($emailFrom)
    {
        $this->emailFrom = $emailFrom;
        return $this;
    }




    /**
     * @param $email
     *
     * @return UserEntity|mixed|null|object
     */
    public function getExistUserEntity($email)
    {
        if (null === $this->existUserEntity) {
            $this->existUserEntity = $this->userRepository->findOneBy(['email' => $email]);
        }

        return $this->existUserEntity;
    }

    /**
     * @param bool $flush
     *
     * @return RegistrationForm
     */
    public function setFlush(bool $flush): RegistrationForm
    {
        $this->flush = $flush;
        return $this;
    }



}
