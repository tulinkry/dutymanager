<?php declare(strict_types=1);

/**
 * Minimal test doubles + a helper for constructing a presenter outside the full
 * Nette DI container/HTTP request cycle - just enough wiring (session, user,
 * template factory) for a presenter's own business logic to run, without needing
 * a live Google account or a real HTTP request.
 */

class FakeUserStorage implements Nette\Security\UserStorage
{
	public function saveAuthentication(Nette\Security\IIdentity $identity): void
	{
	}

	public function clearAuthentication(bool $clearIdentity): void
	{
	}

	public function getState(): array
	{
		return [true, new Nette\Security\SimpleIdentity('test-user'), null];
	}

	public function setExpiration(?string $expire, bool $clearIdentity): void
	{
	}
}

/**
 * Unlike FakeUserStorage (always pre-authenticated), this starts logged out - needed
 * to test BasePresenter's MOCK_GOOGLE_API auto-login, which only kicks in when the
 * user isn't logged in yet.
 */
class FakeLoggedOutUserStorage implements Nette\Security\UserStorage
{
	private ?Nette\Security\IIdentity $identity = null;

	public function saveAuthentication(Nette\Security\IIdentity $identity): void
	{
		$this->identity = $identity;
	}

	public function clearAuthentication(bool $clearIdentity): void
	{
		$this->identity = null;
	}

	public function getState(): array
	{
		return [$this->identity !== null, $this->identity, null];
	}

	public function setExpiration(?string $expire, bool $clearIdentity): void
	{
	}
}

class FakeTemplateFactory implements Nette\Application\UI\TemplateFactory
{
	public function createTemplate(?Nette\Application\UI\Control $control = null): Nette\Application\UI\Template
	{
		$latte = new Latte\Engine();
		// Registers {control}/{link} (UIExtension) and {form}/{input}/{label} (FormsExtension) -
		// without these the engine only understands core Latte tags, and any real presenter
		// template using {control xxx} would fail to compile at all.
		$latte->addExtension(new Nette\Bridges\ApplicationLatte\UIExtension($control));
		$latte->addExtension(new Nette\Bridges\FormsLatte\FormsExtension());
		return new Nette\Bridges\ApplicationLatte\DefaultTemplate($latte);
	}
}

/**
 * Renders a presenter's real .latte template file the same way Nette's own
 * Presenter::sendTemplate() would - this is what actually catches Latte compile
 * errors in the template itself (a bad {input ...} attribute, a stray macro, etc.),
 * as opposed to just rendering a form component in isolation.
 */
function renderPresenterTemplate(Nette\Application\UI\Presenter $presenter, string $templateFile): string
{
	$template = $presenter->getTemplate();
	// Normally set by Presenter::completeTemplate() from the real HTTP request;
	// @layout.latte references {$basePath} for asset URLs.
	$template->basePath = '';
	$template->baseUrl = 'http://localhost';
	$template->setFile($templateFile);
	return (string) $template;
}

/**
 * Wires just enough of a presenter's injected dependencies (http request/response,
 * a logged-in user, session, template factory) so its render/action/handle methods
 * can run standalone, the same way Nette's own DI container would inject them -
 * without needing the full Application/Router/PresenterFactory dispatch stack.
 */
function injectFakePresenterDependencies(Nette\Application\UI\Presenter $presenter, ?Nette\Security\UserStorage $userStorage = null): void
{
	$httpRequest = new Nette\Http\Request(new Nette\Http\UrlScript('http://localhost/'));
	$httpResponse = new Nette\Http\Response();
	$session = new Nette\Http\Session($httpRequest, $httpResponse);
	// Matches config.neon's `session: expiration: 30 days` - without it, this bare
	// Session defaults to PHP's short gc_maxlifetime, and BasePresenter's own
	// "alerts" section (which asks for +7 days) triggers an E_USER_NOTICE that
	// Nette Tester treats as a failure.
	$session->setExpiration('30 days');
	$user = new Nette\Security\User($userStorage ?? new FakeUserStorage());

	// The real router + a presenter factory using the app's own domain-namespaced
	// mapping (matching config.neon's application: mapping) - needed so {link Xxx:yyy}
	// in a real template can actually resolve to a URL. Nothing here dispatches or
	// constructs presenters, it's only used for link generation.
	$router = (new App\Core\RouterFactory())->createRouter();
	$presenterFactory = new Nette\Application\PresenterFactory();
	$presenterFactory->setMapping(['*' => 'App\Presentation\*\**Presenter']);

	$presenter->injectPrimary($httpRequest, $httpResponse, $presenterFactory, $router, $session, $user, new FakeTemplateFactory());
}
