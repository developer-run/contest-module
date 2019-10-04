<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2018
 *
 * @file    BaseAppPresenter.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Presenters;

use Devrun\Application\UI\Presenter\BasePresenter;
use Devrun\CmsModule\ArticleModule\Presenters\TArticlesPipe;
use Devrun\CmsModule\Entities\PackageEntity;
use Devrun\CmsModule\Presenters\TImageStoragePipe;
use Devrun\CmsModule\Repositories\PackageRepository;
use Devrun\CmsModule\Repositories\RouteRepository;
use Devrun\Doctrine\Entities\UserEntity;
use Devrun\Doctrine\Repositories\UserRepository;

class BaseContestPresenter extends BasePresenter
{
    use TArticlesPipe;
    use TImageStoragePipe;

    /** @var UserEntity */
    protected $userEntity;

    /** @var UserRepository @inject */
    public $userRepository;

    /** @var RouteRepository @inject */
    public $routeRepository;


    /** @var PackageRepository @inject */
    public $packageRepository;

    /** @var int @persistent */
    public $package;

    /** @var PackageEntity */
    private $packageEntity;

    /** @var boolean @persistent */
    public $appLayout = false;



    protected function startup()
    {
        parent::startup();

        if ($this->getUser()->isLoggedIn()) {
            if (!$this->userEntity = $this->userRepository->find($this->getUser()->id)) {
                $this->userEntity = new UserEntity();

                $this->getUser()->logout();
                // @todo log user logged not equal to user in database

            }

        } else {
            $this->userEntity = new UserEntity();
        }

    }

    protected function beforeRender()
    {
        parent::beforeRender();

        $themeName    = "theme-default";
        $themeVersion = 0;

        if ($packageEntity = $this->getPackageEntity()) {
            $themeName    = "theme-{$packageEntity}";
            $themeVersion = $packageEntity->getThemeVersion();
            $this->template->package = $packageEntity;
            $this->template->analyticCode = $packageEntity->getAnalyticCode();
        }

        if ($this->appLayout) {
            $appDir= $this->context->getParameters()['appDir'];
            $frontLayout = $appDir . "/modules/front-module/src/FrontModule/Presenters/templates/@layout.latte";
            $this->setLayout($frontLayout);
        }

        $this->template->appLayout = $this->appLayout;
        $this->template->themeName = $themeName;
        $this->template->themeVersion = $themeVersion;
        $this->template->pageClass = trim("$themeName {$this->template->pageClass}");

    }


    protected function afterRender()
    {
//        parent::afterRender();

        if ($routeId = $this->getRequest()->getParameter('routeId')) {
            if ($routeEntity = $this->routeRepository->find($routeId)) {
                $this->template->title = $routeEntity->title;
            }
        }
    }


    public function handleSwitchAppLayout()
    {
        $this->appLayout = !$this->appLayout;

        $this->ajaxRedirect();
    }


    public function getPackage()
    {
        return $this->getRequest()->getParameter('package');
    }


    /**
     * @return PackageEntity
     */
    public function getPackageEntity()
    {
        if ($this->packageEntity === null) {
            if ($package = $this->getPackage()) {
                if ($packageEntity = $this->packageRepository->find($package)) {
                    $this->packageEntity = $packageEntity;
                }
            }
        }

        return $this->packageEntity;
    }



    /**
     * @return UserEntity
     */
    public function getUserEntity()
    {
        return $this->userEntity;
    }


    public function handleLogout()
    {
        $this->getUser()->logout();
        $this->flashMessage('Byl jste odhlášen ze systému', 'info');
        $this->ajaxRedirect();
    }


}