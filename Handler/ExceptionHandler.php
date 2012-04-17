<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle\Handler;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\Response;

/**
 * ExceptionHandler class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class ExceptionHandler
{
	/**
	 * @var \Symfony\Component\HttpKernel\Kernel
	 */
	protected $kernel;

	/**
	 * Register the exception handler.
	 * @return self The registered exception handler
	 */
	static public function register()
	{
		$handler = new static();

		set_exception_handler(array($handler, 'handle'));

		return $handler;
	}

	/**
	 * @param \Symfony\Component\HttpKernel\Kernel $kernel
	 * @param int $level
	 */
	public function setKernel(Kernel $kernel)
	{
		$this->kernel = $kernel;
	}

	/**
	 * Render an error page and notify developers about exception.
	 * @param \Exception $exception An \Exception instance
	 */
	public function handle(\Exception $exception)
	{
		if ($this->kernel !== null) {
			try {
				$this->kernel->boot();
				$container = $this->kernel->getContainer();
				$container->get('ost_error.mailer')->sendException($exception);

				if ('cli' === PHP_SAPI) {
					$errorPage = $exception->getMessage() . "\n\n" .
						$exception->getTraceAsString();
				} else {
					$errorPage = $container->get('templating')->render(
						'TwigBundle:Exception:error.html.twig'
					);
				}

				$response = new Response($errorPage);
				$response->send();
			} catch (\Exception $e) {
				try {
					$this->kernel->boot();
					$this->kernel->getContainer()
						->get('logger')
						->err($e->getMessage());
				} catch (\Exception $e) {
					echo $e->getMessage();
				}
			}
		}
	}
}
