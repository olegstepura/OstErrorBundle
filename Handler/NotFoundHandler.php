<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle\Handler;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * NotFoundHandler class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class NotFoundHandler
{
	public function onCoreException(GetResponseForExceptionEvent $event)
	{
		$exception = $event->getException();

		if ($exception instanceof NotFoundHttpException) {
			$event->stopPropagation();
		}
	}
}
