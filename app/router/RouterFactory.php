<?php

use Nette\Application\Routers\RouteList,
	Nette\Application\Routers\Route;


/**
 * Router factory.
 */
class RouterFactory
{

	/**
	 * @return Nette\Application\IRouter
	 */
	public function createRouter()
	{
		$router = new RouteList();
    	$router[] = new Route ( "[home]", "Index:default" );
    	$router[] = new Route ( "sign-in", "Sign:default" );
    	$router[] = new Route ( "check", "Sign:verify" );
    	$router[] = new Route ( "sign-out", "Sign:logout" );
		$router[] = new Route ( "calendar/<id>", "Calendar:viewCalendarEvents" );
		$router[] = new Route ( "calendar/new", "Calendar:addCalendar" );
		$router[] = new Route ( "event/<calendarId>/<eventId>", "Event:viewEvent" );
		$router[] = new Route ( "event/new", "Event:addEvent" );
    	$router[] = new Route ( "calendar/<id>/print/<mode=0>", "Calendar:printCalendarEvents" );
        $router[] = new Route ( "git", "Git:Git:default" );


    	$router[] = new Route('<presenter>/<action>[/<id>]', 'Index:default');
		return $router;
	}

}
