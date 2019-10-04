<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    PageListener.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Listeners;

use Devrun\CmsModule\ContestModule\Controls\IPageTabPackageControlFactory;
use Devrun\CmsModule\Presenters\PagePresenter;
use Devrun\CmsModule\Repositories\RouteRepository;
use Kdyby\Events\Subscriber;
use Tracy\Debugger;

class PageListener implements Subscriber
{

    /** @var IPageTabPackageControlFactory @inject */
    public $pageTabPackageControlFactory;

    /** @var RouteRepository */
    private $routeRepository;

    /**
     * PageListener constructor.
     *
     * @param RouteRepository $routeRepository
     */
    public function __construct(RouteRepository $routeRepository)
    {
        $this->routeRepository = $routeRepository;
    }


    public function onPageEdit($page, $route, PagePresenter $presenter)
    {
//        dump($page);
//        dump($route);

//        $control = $presenter['cms_controls_pageTabPackageControl'];
        $control = $presenter['administrationItemControls']['cms_controls_pageTabPackageControl'];

//        dump($this->pageTabPackageControlFactory);

        $routeEntity = $this->routeRepository->find(138);

        $presenter->setRouteEntity($routeEntity);





//        $control = $presenter;

//        dump($control->routeSelect);
//        dump($control);



    }




    function getSubscribedEvents()
    {
        return [
//            "Devrun\CmsModule\Presenters\PagePresenter::onPageEdit",
        ];
    }
}