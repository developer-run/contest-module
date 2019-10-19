<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    ModuleListener.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Listeners;

use Devrun\CmsModule\Entities\PackageEntity;
use Devrun\CmsModule\Entities\RouteEntity;
use Devrun\CmsModule\Facades\PackageFacade;
use Devrun\CmsModule\Repositories\PackageRepository;
use Devrun\Module\ModuleFacade;
use Kdyby\Events\Subscriber;
use Kdyby\Translation\Translator;

class ModuleListener implements Subscriber
{

    const DEFAULT_PACKAGE = 'Default';

    /** @var PackageRepository */
    private $packageRepository;

    /** @var PackageFacade */
    private $packageFacade;

    /** @var Translator */
    private $translator;

    /**
     * ModuleListener constructor.
     *
     * @param PackageFacade     $packageFacade
     * @param PackageRepository $packageRepository
     * @param Translator        $translator
     */
    public function __construct(PackageFacade $packageFacade, PackageRepository $packageRepository, Translator $translator)
    {
        $this->translator        = $translator;
        $this->packageFacade     = $packageFacade;
        $this->packageRepository = $packageRepository;
    }


    /**
     *
     *
     * @param ModuleFacade $moduleFacade
     * @param string $moduleName
     * @throws \Exception
     */
    public function onUpdate(ModuleFacade $moduleFacade, $moduleName)
    {
        $module = $moduleFacade->getModules()[$moduleName];

        if ($module->hasPackagePages()) {
            $em = $this->packageRepository->getEntityManager();

            if (!$packageEntity = $this->packageRepository->findOneBy(['module' => $moduleName, 'name' => self::DEFAULT_PACKAGE])) {
                $packageEntity = new PackageEntity(self::DEFAULT_PACKAGE, $moduleName, $this->translator);
                $em->persist($packageEntity)->flush();
            }

            /** @var RouteEntity[] $routeEntities */
            if ($routeEntities = $this->packageRepository->getSourceRoutes($packageEntity)) {
                foreach ($routeEntities as $routeEntity) {
                    $routeEntity->setPackage($packageEntity);

                    $params = $this->packageFacade->mergeRouteParameters($routeEntity, [
                        'package' => $packageEntity->getId()
                    ]);

                    $routeEntity->setParams($params);
                    $em->persist($routeEntity);
                };

                $em->flush();
            }
        }
    }


    function getSubscribedEvents()
    {
        /**
         * @todo deprecated
         */
        return [
//            'Devrun\Module\ModuleFacade::onUpdate'
        ];
    }
}