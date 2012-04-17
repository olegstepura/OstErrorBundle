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
use Symfony\Component\HttpKernel\Debug\ErrorHandler as BaseErrorHandler;

/**
 * ErrorHandler class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class ErrorHandler extends BaseErrorHandler
{
	/**
	 * @var \Symfony\Component\HttpKernel\Kernel
	 */
	protected $kernel;

	/**
	 * @param \Symfony\Component\HttpKernel\Kernel $kernel
	 * @param int $level
	 */
	public function setKernel(Kernel $kernel)
	{
		$this->kernel = $kernel;
	}

	/**
	 * {@inheritdoc}
	 */
	public function handle($level, $message, $file, $line, $context)
	{
		parent::handle($level, $message, $file, $line, $context);

		// we would not get here if the error was converted to an Exception
		// so since we are here we could say it was an error type
		// which is not handled by default error handler
		if (0 === error_reporting()) {
			return false;
		}

		if ($this->kernel !== null) {
			$this->kernel->boot();
			$this->kernel->getContainer()
				->get('ost_error.mailer')
				->sendError($level, $message, $file, $line, $context);
		}

		// prevent default error handler
		return true;
	}
}