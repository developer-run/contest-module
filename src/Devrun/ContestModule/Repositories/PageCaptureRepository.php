<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    PageCaptureRepository.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Repositories;

use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\CmsModule\Repositories\RouteRepository;
use Devrun\Module\ModuleFacade;
use Devrun\PhantomModule\Entities\ImageEntity;
use Devrun\PhantomModule\Facades\PhantomFacade;

class PageCaptureRepository
{

    /** @var PhantomFacade */
    private $phantomFacade;

    /** @var ModuleFacade */
    private $moduleFacade;

    /** @var RouteRepository */
    private $routeRepository;


    /**
     * PageCaptureRepository constructor.
     *
     * @param PhantomFacade $phantomFacade
     * @param ModuleFacade  $moduleFacade
     */
    public function __construct(PhantomFacade $phantomFacade, ModuleFacade $moduleFacade, RouteRepository $routeRepository)
    {
        $this->phantomFacade   = $phantomFacade;
        $this->moduleFacade    = $moduleFacade;
        $this->routeRepository = $routeRepository;
    }


    /**
     * @param array $criteria
     *
     * @return ImageEntity|null
     */
    public function findImageByRouteCriteria(array $criteria)
    {
        if ($route = $this->routeRepository->findOneBy($criteria)) {
            return $this->getRouteImage($route);
        }

        return null;
    }


    /**
     * @param RouteEntity $routeEntity
     *
     * @return ImageEntity
     */
    public function getRouteImage(RouteEntity $routeEntity): ImageEntity
    {
        $this->setPhantomOptions($routeEntity);

        $imageEntity = $this->phantomFacade->getIdentifierFromRoute($routeEntity);
        return $imageEntity;
    }


    private function setPhantomOptions(RouteEntity $routeEntity)
    {
        $module  = $routeEntity->getPage()->getModule();
        $modules = $this->moduleFacade->getModules();

        if (isset($modules[$module])) {
            if ($configuration = $modules[$module]->getConfiguration()) {
                if (isset($configuration['capturePageOptions'])) {
                    $capturePageOptions = $configuration['capturePageOptions'];
                    $this->phantomFacade->setCapturePageOptions($capturePageOptions);
                }
            }
        }
    }


}