<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    Route.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\ContestModule\Routes;


use Devrun\CmsModule\Repositories\RouteRepository;
use Nette;
use Tracy\Debugger;
use Tracy\ILogger;

class Route extends \Nette\Application\Routers\Route
{

    /** @var RouteRepository @inject */
    public $routeRepository;

    public static $request;



    public function match(Nette\Http\IRequest $httpRequest)
    {
        $appRequest = parent::match($httpRequest);

        self::$request = $httpRequest;

        return $appRequest;

        dump($appRequest);

        $packageId = $appRequest->getParameter('package');

        Debugger::barDump($packageId);
        Debugger::log(__METHOD__ . " " .  __LINE__ . " " . $packageId, ILogger::DEBUG);

        // 3 null
        // 5 null
        // 7 null
        // 8 null
        if (!is_numeric($packageId)) {
            $id = $packageId == "ASD" ? 2 : null;
            $appRequest->parameters['package'] = $id;
        }


        return $appRequest;
    }


}