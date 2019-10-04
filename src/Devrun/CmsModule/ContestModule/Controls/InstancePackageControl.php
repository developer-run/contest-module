<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    InstancePackageControl.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\CmsModule\ContestModule\Controls;

use Devrun\Application\UI\Control\Control;
use Devrun\Application\UI\Presenter\TImgStoragePipe;
use Devrun\CmsModule\Entities\PackageEntity;
use Devrun\CmsModule\NotFoundResourceException;
use Devrun\CmsModule\Repositories\PackageRepository;
use Devrun\CmsModule\Repositories\PageRepository;
use Devrun\CmsModule\Repositories\RouteRepository;
use Devrun\ContestModule\Repositories\PageCaptureRepository;
use Devrun\Security\LoggedUser;

interface IInstancePackageControlFactory {

    /**
     * @return InstancePackageControl
     */
    public function create();
}

class InstancePackageControl extends Control
{

    use TImgStoragePipe;

    /** @var LoggedUser @inject */
    public $loggedUser;

    /** @var PackageRepository @inject */
    public $packageRepository;

    /** @var PageRepository @inject */
    public $pageRepository;

    /** @var RouteRepository @inject */
    public $routeRepository;

    /** @var PageCaptureRepository @inject */
    public $pageCaptureRepository;



    public function render($params = array())
    {
        $packages = $this->packageRepository->findBy(['user' => $this->loggedUser->getUserEntity()]);

        $template = $this->createTemplate();
        $template->packages = $packages;
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



    public function getPreview(PackageEntity $package)
    {
        return $this->packageRepository->getPreview($package);
    }


    public function getEditLink(PackageEntity $package)
    {
        if ($pageEntity = $this->pageRepository->findOneBy(['module' => $package->getModule(), 'lvl' => 0])) {
            return $pageEntity->id;
        }

        return null;
    }


}