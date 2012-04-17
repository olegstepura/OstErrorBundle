<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Ost\ErrorBundle\Handler\ErrorHandler as ErrorNotifier;
use Ost\ErrorBundle\Handler\ExceptionHandler as ExceptionNotifier;

/**
 * OstErrorBundle class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class OstErrorBundle extends Bundle
{
    public function __construct(Kernel $kernel)
    {
        if ($kernel->isDebug() || 'cli' === PHP_SAPI) {
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }

        // This should be set in debug and non-debug modes
        error_reporting(-1);

        if (!$kernel->isDebug()) {
            $errorNotifier = ErrorNotifier::register(
                E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR |
                    E_USER_ERROR | E_RECOVERABLE_ERROR
            );
            $errorNotifier->setKernel($kernel);

            $exceptionNotifier = ExceptionNotifier::register();
            $exceptionNotifier->setKernel($kernel);
        }
    }
}
