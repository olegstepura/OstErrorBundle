<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle\Mailer;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Swift_Mailer as BaseMailer;
use Swift_Message as Message;
use Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Mailer class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class Mailer
{
	/**
	 * Human error levels.
	 * @var array
	 */
	protected $levels = array(
		E_ERROR => "Error",
		E_WARNING => "Warning",
		E_PARSE => "Parsing Error",
		E_NOTICE => "Notice",
		E_CORE_ERROR => "Core Error",
		E_CORE_WARNING => "Core Warning",
		E_COMPILE_ERROR => "Compile Error",
		E_COMPILE_WARNING => "Compile Warning",
		E_USER_ERROR => "User Error",
		E_USER_WARNING => "User Warning",
		E_USER_NOTICE => "User Notice",
		E_STRICT => "Runtime Notice",
		E_RECOVERABLE_ERROR => "Catchable Fatal Error",
	);

	/**
	 * @var \Swift_Mailer
	 */
	protected $mailer;

	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var boolean
	 */
	protected $mail = false;

	/**
	 * @var boolean
	 */
	protected $display = false;

	/**
	 * @var string
	 */
	protected $host = '';

	/**
	 * @var string|bool
	 */
	protected $remoteIp = false;

	/**
	 * @var bool
	 */
	protected $reportNotFound = false;

	/**
	 * @var int
	 */
	protected $displayCount = 0;

	/**
	 * @param \Swift_Mailer $mailer
	 * @param Container $container
	 */
	public function __construct(BaseMailer $mailer, Container $container)
	{
		$this->mailer = $mailer;
		$this->container = $container;

		try {
			$this->mail = $container->getParameter(
				'ost_error.mailer.enabled'
			);
			$this->display = $container->getParameter(
				'ost_error.display.always'
			);
			$this->reportNotFound = $container->getParameter(
				'ost_error.mailer.report_not_found'
			);

			if (PHP_SAPI !== 'cli') {
				$this->remoteIp = getenv('HTTP_X_FORWARDED_FOR');
				if (!$this->remoteIp) {
					$this->remoteIp = getenv('REMOTE_ADDR');
				}
			}

			if (!$this->display && $this->remoteIp) {
				$ips = $container->getParameter('ost_error.display.ips');
				$this->display = in_array($this->remoteIp, $ips);
			}

			$this->host = @trim(`hostname -s`) ?: '[unknown]';
		} catch (Exception $e) {
			$this->mail = true;
			$this->display = false;
			$this->sendException($e);
		}
	}

	/**
	 * Exception error handler.
	 * @param GetResponseForExceptionEvent $Event
	 * @return void
	 */
	public function onCoreException(GetResponseForExceptionEvent $event)
	{
		$this->sendException($event->getException());
	}

	/**
	 * Sends info notification to developers.
	 * It could be not an error but some message that needs to be noticed.
	 * @param string $message
	 * @param string $file
	 * @param int $line
	 * @return void
	 */
	public function sendInfo($message, $file = '', $line = 0)
	{
		if ($this->mail) {
			try {
				$request = $this->getRequest();

				$e = new Exception();
				$trace = $e->getTrace();
				array_shift($trace);
				array_shift($trace);

				$parameters = $this->getParameters(
					$request,
					$message,
					array(),
					$trace,
					$file,
					$line
				);

				$subject = $this->getSubject($request, 'INFO');

				$template = $this->container->getParameter(
					'ost_error.mailer.info_template'
				);

				$mailMessage = $this->createMessage(
					$subject,
					$this->container->get('templating')->render(
						$template,
						$parameters
					),
					$this->calculateCrc($file, $line, $message)
				);

				$this->sendMail($mailMessage);
			} catch (Exception $e) {
				$previous = new Exception($message);
				$this->reportExceptionThrownOnMailComposing(
					$e,
					$previous,
					'info'
				);
			}
		}
	}

	/**
	 * Sends error notification.
	 * @return void
	 */
	public function sendError($level, $message, $file = '', $line = 0, $context = array())
	{
		if ($this->mail || $this->display) {
			try {
				$levelHuman = isset($this->levels[$level])
					? $this->levels[$level]
					: $level;

				$request = $this->getRequest();

				$e = new Exception();
				$trace = $e->getTrace();
				array_shift($trace);
				array_shift($trace);

				$parameters = $this->getParameters(
					$request,
					$message,
					$context,
					$trace,
					$file,
					$line,
					$levelHuman,
					$level,
					true
				);

				$this->displayError($parameters);

				if ($this->mail) {
					$subject = $this->getSubject($request, $levelHuman, true);

					$template = $this->container->getParameter(
						'ost_error.mailer.error_template'
					);

					$mailMessage = $this->createMessage(
						$subject,
						$this->container->get('templating')->render(
							$template,
							$parameters
						),
						$this->calculateCrc($file, $line, $levelHuman)
					);

					$this->sendMail($mailMessage);
				}
			} catch (Exception $e) {
				$previous = new Exception($message);
				$this->reportExceptionThrownOnMailComposing($e, $previous);
			}
		}
	}

	/**
	 * Sends exception notification.
	 * @param \Exception $e
	 * @return void
	 */
	public function sendException(Exception $e)
	{
		if ($this->mail) {
			if ($e instanceof NotFoundHttpException) {
				if (!$this->reportNotFound) {
					return;
				}
			}

			try {
				$request = $this->getRequest();

				$trace = $e->getTrace();
				array_shift($trace);

				$parameters = $this->getParameters(
					$request,
					$e->getMessage(),
					array(),
					$trace,
					$e->getFile(),
					$e->getLine(),
					'',
					$e->getCode()
				);

				$parameters['exception_class'] = get_class($e);

				$subject = $this->getSubject($request, 'Exception');

				$template = $this->container->getParameter(
					'ost_error.mailer.exception_template'
				);

				$message = $this->createMessage(
					$subject,
					$this->container->get('templating')->render(
						$template,
						$parameters
					),
					$this->calculateCrc(
						$e->getFile(),
						$e->getLine(),
						get_class($e)
					)
				);

				$this->sendMail($message);
			} catch (Exception $exception) {
				$this->reportExceptionThrownOnMailComposing(
					$exception,
					$e,
					'exception'
				);
			}
		}
	}

	/**
	 * Composes the mail subject.
	 * @param \Symfony\Component\HttpFoundation\Request $request
	 * @param string $type
	 * @return string
	 */
	public function getSubject(Request $request, $type, $canBeDisplayed = false)
	{
		$subject = array($type);

		if ('cli' !== PHP_SAPI) {
			$subject[] = $request->getHost();
		} else {
			if (isset($GLOBALS['argv'][0])) {
				$param = basename($GLOBALS['argv'][0]);
				if (isset($GLOBALS['argv'][1])) {
					$param .= ' ' . $GLOBALS['argv'][1];
					if (count($GLOBALS['argv']) > 2) {
						$param .= ' ... ';
					}
					$subject[] = $param;
				}
			}
		}

		if ($this->display && $canBeDisplayed) {
			$subject[] = 'displayed';
		}

		return implode(' / ', $subject);
	}

	/**
	 * Creates template parameters array.
	 * @param \Symfony\Component\HttpFoundation\Request $request
	 * @param $message
	 * @param array $context
	 * @param array $trace
	 * @param string $file
	 * @param int $line
	 * @param string $codeHuman
	 * @param int $code
	 * @param bool $canBeDisplayed
	 * @return array
	 */
	public function getParameters(
		Request $request,
		$message,
		array $context,
		array $trace,
		$file = '',
		$line = 0,
		$codeHuman = '',
		$code = 0,
		$canBeDisplayed = false
	)
	{
		$requestUrl = false;
		$referer = false;
		if ('cli' !== PHP_SAPI) {
			$requestUrl = urldecode($request->getUri());
			$referer = $request->server->get('HTTP_REFERER', '[empty]');
		}

		return array(
			'message' => $this->formatMessage($message),
			'trace' => $trace,
			'file' => $file,
			'line' => $line,
			'code' => $code,
			'code_human' => $codeHuman,
			'context' => $this->filterArrayValues($context),
			'remote_ip' => $this->remoteIp,
			'referer' => $referer,
			'request_url' => $requestUrl,
			'request_headers' => $request->server->getHeaders(),
			'request_post' => $request->request->all(),
			'request_attributes' => $this->filterArrayValues($request->attributes->all()),
			'server_params' => $request->server->all(),
			'displayed' => $this->display && $canBeDisplayed,
			'host' => $this->host,
			'argv' => @$GLOBALS['argv'] ? : array(),
			'error_number' => &$this->displayCount,
		);
	}

	/**
	 * Displays error on the screen.
	 * @param array $parameters
	 */
	public function displayError(array $parameters)
	{
		$isCli = 'cli' === PHP_SAPI;
		if ($this->display || $isCli) {
			try {
				$this->displayCount++;
				if ($isCli) {
					$templateName = 'ost_error.error_display_cli_template';
					$parameters['message'] = htmlspecialchars_decode(
						htmlspecialchars_decode(html_entity_decode(
							$parameters['message'], ENT_QUOTES, 'UTF-8'
						 ), ENT_QUOTES),
						ENT_QUOTES
					);
				} else {
					$templateName = 'ost_error.error_display_template';
				}
				$template = $this->container->getParameter($templateName);
				echo $this->container->get('templating')->render(
					$template,
					$parameters
				);
			} catch (\Exception $e) {
				echo '<!-- \'"--><div style="background-color: #660000;' .
					'color: #fff; margin: 0; padding: 20px;' .
					'border: 2px solid black;clear: both;' .
					'font: 14px Courier New, Courier, monospace;">' .
					'Error on trying to display an error: ' . get_class($e) .
					'(' . $e->getMessage() . '<br />' .
					nl2br($e->getTraceAsString(), true) . '</div>';
			}
		}
	}

	/**
	 * @param \Exception $e
	 * @return void
	 */
	protected function reportExceptionThrownOnMailComposing(
		Exception $e,
		Exception $previous = null,
		$type = 'error'
	)
	{
		try {
			$body = '<h3>Current Error:</h3>' .
				$e->getMessage() . '<br /><br />' .
				$this->formatMessage($e->getTraceAsString());

			if (null !== $previous) {
				$body .= '<hr /><h3>Previous Error:</h3>' .
					$previous->getMessage() . '<br /><br />' .
					$this->formatMessage($previous->getTraceAsString());
			}

			if (!empty($_SERVER)) {
				$body .= '<hr><h3>$_SERVER:</h3>' .
					nl2br(var_export($_SERVER, true));
			}

			if (!empty($GLOBALS['argv'])) {
				$body .= '<hr><h3>$argv:</h3>' .
					nl2br(var_export($GLOBALS['argv'], true));
			}

			$message = $this->createMessage(
				'Error on trying to report an ' . $type,
				$body,
				$this->calculateCrc(
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);

			$this->sendMail($message);
		} catch (Exception $exception) {
			$this->container
				->get('logger')
				->err('Error reporting error: ' . $exception->getMessage());

			mail(
				$this->container->getParameter('ost_error.mailer.to'),
				'Error reporting error on ' . $this->host,
				$exception->getMessage() . "\n\n" .
				$exception->getTraceAsString() . "\n\n" .
				'Initial error: ' . $e->getMessage() . "\n\n" .
				$e->getTraceAsString()
			);
		}
	}

	/**
	 * @param array $source
	 * @return array
	 */
	public function filterArrayValues(array $source)
	{
		$filtered = array();

		foreach ($source as $key => $value) {
			if (is_object($value)) {
				$filtered[$key] = get_class($value);
			} else if (is_array($value)) {
				$filtered[$key] = 'array(' . count($value) . ')';
			} else if (is_bool($value)) {
				$filtered[$key] = '(bool) "' . (
				$value ? 'true' : 'false') . '"';
			} else if ($value === null) {
				$filtered[$key] = 'null';
			} else {
				$filtered[$key] = '(' . gettype($value) . ') "' . $value . '"';
			}
		}

		return $filtered;
	}

	/**
	 * @return \Symfony\Component\HttpFoundation\Request
	 */
	protected function getRequest()
	{
		try {
			$request = $this->container->get(
				'request',
				Container::NULL_ON_INVALID_REFERENCE
			);
		} catch (\Exception $e) {
			$request = null;
		}

		if ($request === null) {
			$request = Request::createFromGlobals();
		}

		return $request;
	}

	/**
	 * Prepares new message.
	 * @param string $subject
	 * @param string $body
	 * @return \Swift_Message
	 */
	protected function createMessage($subject, $body, $crc = '')
	{
		$from = $this->container->getParameter('ost_error.mailer.from');
		$to = $this->container->getParameter('ost_error.mailer.to');

		if (empty($crc)) {
			$crc = $this->calculateCrc($subject);
		}

		$m = Message::newInstance()
			->setFrom($from, $this->host)
			->setTo($to)
			->setContentType('text/html')
			->setId($crc . '.' . $from)
			->setSubject(sprintf('%s [%s]', $subject, substr($crc, 0, 6)))
			->setBody($body);

		$m->getHeaders()->addPathHeader('References', $crc . '.' . $from);

		return $m;
	}

	/**
	 * Calculates crc of the message.
	 * @return string
	 */
	public function calculateCrc()
	{
		return md5(implode(func_get_args()));
	}

	/**
	 * Sends mail message.
	 * @param \Swift_Message $message
	 * @return void
	 */
	protected function sendMail($message)
	{
		try {
			$this->mailer->send($message);
		} catch (Exception $e) {
			$this->container
				->get('logger')
				->err('Mail sending error: ' . $e->getMessage());

			mail(
				$this->container->getParameter('ost_error.mailer.to'),
				'Mail sending error on ' . $this->host,
				$e->getMessage() . "\n\n" .
				$e->getTraceAsString() . "\n\n" .
				'Initial error: ' . $message
			);
		}
	}

	/**
	 * @param string $message
	 * @return string
	 */
	protected function formatMessage($message)
	{
		$message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
		$message = htmlentities($message, ENT_QUOTES, 'UTF-8');
		$message = str_replace("\t", str_repeat('&nbsp;', 4), $message);
		$message = nl2br($message, true);

		return $message;
	}
}
