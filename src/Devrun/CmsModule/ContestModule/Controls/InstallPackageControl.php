<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    InstallPackageControl.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\CmsModule\ContestModule\Controls;

use Devrun\Application\UI\Control\Control;
use Devrun\Application\UI\Presenter\TImgStoragePipe;
use Devrun\CmsModule\Controls\FlashMessageControl;
use Devrun\CmsModule\Entities\ImagesEntity;
use Devrun\CmsModule\Entities\PackageEntity;
use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\CmsModule\Entities\RouteTranslationEntity;
use Devrun\CmsModule\Facades\PackageFacade;
use Devrun\CmsModule\Forms\DevrunForm;
use Devrun\CmsModule\Forms\IDevrunForm;
use Devrun\CmsModule\NotFoundResourceException;
use Devrun\CmsModule\Presenters\AdminPresenter;
use Devrun\CmsModule\Repositories\PackageRepository;
use Devrun\CmsModule\Repositories\RouteRepository;
use Devrun\ContestModule\Repositories\PageCaptureRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Forms\Controls\TextInput;
use Tracy\Debugger;

interface IInstallPackageControlFactory {

    /**
     * @return InstallPackageControl
     */
    public function create();
}

class InstallPackageControl extends Control
{

    use TImgStoragePipe;

    /** @var IDevrunForm @inject */
    public $devrunFormFactory;

    /** @var PackageRepository @inject */
    public $packageRepository;

    /** @var PackageFacade @inject */
    public $packageFacade;

    /** @var RouteRepository @inject */
    public $routeRepository;

    /** @var PageCaptureRepository @inject */
    public $pageCaptureRepository;



    /** @var array DI parameters */
    private $packages = [];


    public function handleUninstall($package)
    {
        $imageEntity = $this->packageRepository->getEntityManager()->getRepository(ImagesEntity::class)->findOneBy([
            'namespace' => 'benefits',
            'systemName' => 'Q',
        ]);

        dump($imageEntity);


        if ($imageEntity) {
            $this->packageRepository->getEntityManager()->remove($imageEntity)->flush();
        }


        die("deleted");



        if ($newPackage = $this->packageRepository->findOneBy(['name' => 'Marama3'])) {
            $this->packageRepository->getEntityManager()->remove($newPackage)->flush();
        }

        /** @var AdminPresenter $presenter */
        $presenter = $this->presenter;

        $message = "balíček $package odinstalován";
        $presenter->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Package add", FlashMessageControl::TOAST_SUCCESS);


        $presenter->ajaxRedirect('this', null, ['flash']);

    }



    public function handleInstall($package)
    {
        /** @var AdminPresenter $presenter */
        $presenter = $this->presenter;
        $em = $this->packageRepository->getEntityManager();


    }




    public function render($params = array())
    {
//        dump($params);
//        dump($this->packages);


        $template = $this->createTemplate();
        $template->packages = $this->packages;
        $template->params = $params;

        $template->render();
    }


    public function getImage($module, $package)
    {
        if ($route = $this->routeRepository->findOneBy(['package.module' => $module, 'package.name' => $package])) {

            $image = $this->pageCaptureRepository->getRouteImage($route);
            return $image;
        }

        throw new NotFoundResourceException("module $module, package $package not found");
    }


    protected function createComponentPackagesInstallForm($name)
    {

        $self = $this;

        return new Multiplier(function ($id) use ($self, $name) {

//            dump($self->packages);

            $packageInfo = $self->packages[$id];

            $package = $packageInfo['package'];


//            dump($id);
//            dump($package);


            $form = $this->devrunFormFactory->create();
            $form->addHidden('module');

            $form->addText('name', 'Název balíčku')
                ->addRule(Form::FILLED)
                ->addRule(Form::MIN_LENGTH, null, 3)
                ->addRule(Form::MAX_LENGTH, null, 64)
                ->addRule(Form::PATTERN, 'Zadejte prosím povolené znaky a-z0-9', '[a-zA-Z0-9]+')
                ->addRule(function (TextInput $textInput, $val) use ($id) {

                    $value = $textInput->value;

                    if ($testPackage = $this->packageRepository->findOneBy(['name' => $value, 'module' => $id])) {
                        return false;
                    }

                    return true;

                }, 'Tento balíček se již používá');

            $form->addSubmit('send', 'Vytvořit');
//            $form->addFormClass(['ajax']);

            $form->bindEntity(new PackageEntity('', $id, $this->translator));  // '' => $package
            $form->bootstrap3Render();
            $form->onSuccess[] = function (DevrunForm $form, $values) use ($id, $package) {

                /** @var AdminPresenter $presenter */
                $presenter = $this->presenter;

                /** @var PackageEntity $newPackage */
                $newPackage = $form->getEntity();


                /**
                 * custom modify after translate url
                 *
                 * @param $module
                 * @param RouteEntity $route
                 */
                $this->packageFacade->onAfterCopyPackageRoute[] = function ($module, $route) {

                    /** @var RouteTranslationEntity $translationEntity */
                    foreach ($route->getTranslations() as $translationEntity) {

                    }
                };


                try {
                    if (!$oldPackage = $this->packageRepository->findOneBy(['name' => $package, 'module' => $id])) {
                        $message = "balíček `$newPackage` se nepodařilo přidat, chybí originál {$oldPackage} <br> přidejte prosím default $id";
                        $presenter->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Package add", FlashMessageControl::TOAST_CRITICAL);

                        $presenter->ajaxRedirect('this', null, ['flash']);
                    }
                    $this->packageFacade->copyPackage($newPackage, $oldPackage);

                } catch (UniqueConstraintViolationException $exception) {

//                    dump($exception);
//                    die();

                    $message = "balíček `$newPackage` se nepodařilo přidat, tento název již existuje";
                    $presenter->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Package add", FlashMessageControl::TOAST_WARNING);

                    $presenter->ajaxRedirect('this', null, ['flash']);
                }


                /*
                 * custom debug stop add package
                 */

                $message = "balíček $newPackage přidán";
                $presenter->flashMessage($message, FlashMessageControl::TOAST_TYPE, "Package add", FlashMessageControl::TOAST_SUCCESS);

                $presenter->ajaxRedirect('this', null, ['flash']);
            };


            return $form;
        });

    }


    public function success()
    {

    }



    /**
     * @param array $packages
     */
    public function setPackages($packages)
    {
        $this->setPackageInstanceCount($packages);
        $this->packages = $packages;
    }

    private function setPackageInstanceCount(array & $packages)
    {
        foreach ($packages as $module => $package) {
            $packages[$module]['instances'] = 0;
        }

        foreach ($modules = $this->packageRepository->getPackageInstancesCount() as $module => $count) {
            if (isset($packages[$module])) {
                $packages[$module]['instances'] = $count;
            }
        }
    }




}