<?php

namespace App\Core;

use Nette\Application\Routers\RouteList,
	Nette\Application\Routers\Route,
	Nette\Routing\Router;


/**
 * Router factory.
 */
class RouterFactory
{

	public function createRouter(): Router
	{
		$router = new RouteList();
		$router->addRoute( "[home]", "Index:default" );
		$router->addRoute( "sign-in", "Sign:default" );
		$router->addRoute( "check", "Sign:verify" );
		$router->addRoute( "sign-out", "Sign:logout" );
		$router->addRoute( "calendar/<id>", "Calendar:viewCalendarEvents" );
		$router->addRoute( "calendar/new", "Calendar:addCalendar" );
		$router->addRoute( "event/<calendarId>/<eventId>", "Event:viewEvent" );
		$router->addRoute( "event/new", "Event:addEvent" );
		$router->addRoute( "calendar/<id>/print/<mode=0>", "Calendar:printCalendarEvents" );

		$router->addRoute('<presenter>/<action>[/<id>]', 'Index:default');
		return $router;
	}

}
