<?php
/**
 * This file is part of the devrun2018
 * Copyright (c) 2018
 *
 * @file    ContestExtension.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\DI;

use Devrun\Config\CompilerExtension;
use Devrun\ContestModule\Forms\ILoginFormFactory;
use Devrun\ContestModule\Forms\IRegistrationFormFactory;
use Flame\Modules\Providers\IRouterProvider;
use Kdyby\Doctrine\DI\IEntityProvider;
use Kdyby\Events\DI\EventsExtension;
use Nette\Application\Routers\RouteList;
use Nette\DI\ContainerBuilder;
use Nette\Environment;

class ContestExtension extends CompilerExtension implements IEntityProvider, IRouterProvider /* IPresenterMappingProvider */
{

    public $defaults = array(
        'installPackages' => [
        ]
    );


    public function loadConfiguration()
    {
        parent::loadConfiguration();

        /** @var ContainerBuilder $builder */
        $builder = $this->getContainerBuilder();
        $config  = $this->getConfig($this->defaults);

//        dump($builder);
//        dump($config);
//        die();


        /*
         * forms
         */
        $builder->addDefinition($this->prefix('form.registrationFormFactory'))
            ->setImplement(IRegistrationFormFactory::class)
            ->setInject(true);
//            ->addSetup('create')
//            ->addSetup('bootstrap3Render');

        $builder->addDefinition($this->prefix('form.loginFormFactory'))
            ->setImplement(ILoginFormFactory::class)
            ->addSetup('create')
            ->addSetup('bootstrap3Render');



        /*
         * repositories
         */
        $builder->addDefinition($this->prefix('repository.pageCapture'))
            ->setFactory('Devrun\ContestModule\Repositories\PageCaptureRepository');





        /*
         * facades
         */
        $builder->addDefinition($this->prefix('facade.resource'))
            ->setFactory('Devrun\ContestModule\Facades\ResourceManager');


        /*
         * controls
         */
        $builder->addDefinition($this->prefix('control.installPackageControl'))
            ->setImplement('Devrun\CmsModule\ContestModule\Controls\IInstallPackageControlFactory')
            ->addTag('devrun.control')
            ->addTag('administration', [
                'category'    => 'Dashboard',
                'name'        => 'Installations',
                'priority'    => 5,
            ])
            ->setInject(true)
            ->addSetup('setPackages', [$config['installPackages']]);

        $builder->addDefinition($this->prefix('control.userInstancePackageControl'))
            ->setImplement('Devrun\CmsModule\ContestModule\Controls\IInstancePackageControlFactory')
            ->addTag('devrun.control')
            ->addTag('administration', [
                'category'    => 'Instance',
                'name'        => 'Instances',
                'priority'    => 10,
            ])
            ->setInject(true);




/*
        cms.contest.controls.installPackageControl:
		implement: Devrun\CmsModule\ContestModule\Controls\IInstallPackageControlFactory
		setup:
			- setImages(%contest%)
		tags: [devrun.control, administration: [
        category: Dashboard
			name: Hra Pexeso - 1$
			description: Určitě to znáte. Stačí najít dvě karty se stejným symbolem a postupně otočit karty všechny.
    img: /images/pexeso/theme-default/pexeso-mini.png
			priority: 5
		]]
		inject: true
*/



        /*
         * subscribers
         */
        $builder->addDefinition($this->prefix('listener.pageListener'))
            ->setFactory('Devrun\ContestModule\Listeners\PageListener')
            ->addTag(EventsExtension::TAG_SUBSCRIBER);

        $builder->addDefinition($this->prefix('listener.moduleListener'))
            ->setFactory('Devrun\ContestModule\Listeners\ModuleListener')
            ->addTag(EventsExtension::TAG_SUBSCRIBER);



    }


    /**
     * Returns array of ClassNameMask => PresenterNameMask
     *
     * @example return array('*' => 'Booking\*Module\Presenters\*Presenter');
     * @return array
     */
    public function getPresenterMapping()
    {
        return array(
            'Contest' => 'Devrun\\ContestModule\*Module\Presenters\*Presenter',
        );
    }


    /**
     * Returns associative array of Namespace => mapping definition
     *
     * @return array
     */
    function getEntityMappings()
    {
        return array(
            'Devrun\ContestModule\Entities' => dirname(__DIR__) . '/Entities/',
        );
    }

    /**
     * Returns array of ServiceDefinition,
     * that will be appended to setup of router service
     *
     * @example https://github.com/nette/sandbox/blob/master/app/router/RouterFactory.php - createRouter()
     * @return \Nette\Application\IRouter
     */
    public function getRoutesDefinition()
    {
        $lang = Environment::getConfig('lang');

        $routeList     = new RouteList();
        $routeList[]   = $frontRouter = new RouteList();


        $defaultLocale    = 'cs';
        $availableLocales = 'cs';

        if ($translation = \Nette\Environment::getService('translation.default')) {
            $defaultLocale    = $translation->getDefaultLocale();
            if ($serviceAvailableLocales = $translation->getAvailableLocales()) {
                $availableLocales = implode('|', array_unique(preg_replace("/^(\w{2})_(.*)$/m", "$1", $serviceAvailableLocales)));
            }
        }

        //<slug .+>[/<module qwertzuiop>/<presenter qwertzuiop>]
        //[<module>]/[<locale=$defaultLocale $availableLocales>/]<presenter>/<action>[/<package .+>][/<id>]

        // CMS route
/*        $builder->addDefinition($this->prefix('pageRoute'))
            ->setClass('CmsModule\Content\Routes\PageRoute', array('@container', '@cacheStorage', '@doctrine.checkConnectionFactory', $prefix, $parameters, $config['website']['languages'], $config['website']['defaultLanguage'])
            )
            ->addTag('route', array('priority' => 100));

        if ($config['website']['oneWayRoutePrefix']) {
            $builder->addDefinition($this->prefix('oneWayPageRoute'))
                ->setClass('CmsModule\Content\Routes\PageRoute', array('@container', '@cacheStorage', '@doctrine.checkConnectionFactory', $config['website']['oneWayRoutePrefix'], $parameters, $config['website']['languages'], $config['website']['defaultLanguage'], TRUE)
                )
                ->addTag('route', array('priority' => 99));
        }*/


/*
        $frontRouter[] = new \ContestModule\Routes\Route("<slug .+>[/<module qwertzuiop>/<presenter qwertzuiop>]", array(
            'presenter' => 'Homepage',
            'action'    => 'default',
            'package'    => [
                Route::VALUE => null,
                Route::FILTER_IN => function($id) {

                    // IN = pro presenter
                    if (is_numeric($id)) {
//                        Debugger::barDump($id);
//                        Debugger::log(__FUNCTION__ . " " . __LINE__ . " " . $id, ILogger::DEBUG);
                        return $id;

                    } else {
//                        Debugger::barDump($id);
//                        Debugger::log(__FUNCTION__ . " " . __LINE__ . " " . $id, ILogger::DEBUG);

                        // 2. form
                        // 4. form
                        return $id == "ASD" ? 2 : null;
                    }

//                    dump($q);

//                    return $q;
                    return "Asdq";
                },
                Route::FILTER_OUT => function($id) {

                    if (!is_numeric($id)) {
//                        Debugger::barDump($id);
//                        Debugger::log(__FUNCTION__ . " " . __LINE__ . " " . $id, ILogger::DEBUG);

                        // 1. null
                        // 6. null
                        return $id;

                    } else {
//                        Debugger::barDump("urá $id");
//                        Debugger::log(__FUNCTION__ . " " . __LINE__ . " " . $id, ILogger::DEBUG);

                        return $id == 2 ? "ASD" : null;
                    }

//                    dump($q);

//                    return $q;

//                    dump($q);

//                    return "Asdq";
                    return 2;

                },
            ]
        ));
*/

//        $frontRouter[] = new Route("[<locale={$lang} sk|hu|cs>/]<presenter>/<action>[/<id>]", array(
//            'presenter' => array(
//                Route::VALUE        => 'Homepage',
//                Route::FILTER_TABLE => array(
//                    'testovaci' => 'Test',
////                    'presmerovano' => 'TestRedirect',
//                ),
//            ),
//            'action'    => array(
//                Route::VALUE        => 'default',
//                Route::FILTER_TABLE => array(
//                    'operace-ok' => 'operationSuccess',
//                ),
//            ),
//            'id'        => null,
//            'locale'    => [
//                Route::FILTER_TABLE => [
//                    'cz'  => 'cs',
//                    'sk'  => 'sk',
//                    'pl'  => 'pl',
//                    'com' => 'en'
//                ]]
//        ));
        return $routeList;

    }
}