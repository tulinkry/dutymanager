<?php declare(strict_types=1);

use Tester\Assert;
use App\Presentation\Index\IndexPresenter;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Regression coverage for BasePresenter::startup()'s MOCK_GOOGLE_API auto-login -
// this is what lets the whole app run without a real Google account when the fake
// service is active (see app/Model/Google/MockGoogleCalendarService.php).

test('MOCK_GOOGLE_API=1 auto-logs in a logged-out user', function () {
	putenv('MOCK_GOOGLE_API=1');

	$presenter = new IndexPresenter(new MockGoogleCalendarService());
	injectFakePresenterDependencies($presenter, new FakeLoggedOutUserStorage());

	Assert::false($presenter->getUser()->isLoggedIn());
	$presenter->startup();

	Assert::true($presenter->getUser()->isLoggedIn());
	Assert::same('mock-user', $presenter->getUser()->getId());

	putenv('MOCK_GOOGLE_API');
});

test('without MOCK_GOOGLE_API, startup() does not auto-login', function () {
	putenv('MOCK_GOOGLE_API');

	$presenter = new IndexPresenter(new MockGoogleCalendarService());
	injectFakePresenterDependencies($presenter, new FakeLoggedOutUserStorage());

	$presenter->startup();

	Assert::false($presenter->getUser()->isLoggedIn());
});
