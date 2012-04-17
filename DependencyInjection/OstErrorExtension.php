<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle\DependencyInjection;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

/**
 * OstErrorExtension class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class OstErrorExtension extends Extension
{
	public function load(array $configs, ContainerBuilder $container)
	{
		$processor = new Processor();
		$configuration = new Configuration();

		$config = $processor->process($configuration->getConfigTree(), $configs);
		$alias = $this->getAlias();

		if (isset($config['mailer'])) {
			$container->setParameter($alias . '.mailer.enabled', true);
			$container->setParameter($alias . '.mailer.to', $config['mailer']['to']);
			$container->setParameter($alias . '.mailer.from', $config['mailer']['from']);
			$container->setParameter(
				$alias . '.mailer.report_not_found',
				$config['mailer']['report_not_found']
			);
		} else {
			$container->setParameter($alias . '.mailer.enabled', false);
			$container->setParameter($alias . '.mailer.to', '');
			$container->setParameter($alias . '.mailer.from', '');
			$container->setParameter($alias . '.mailer.report_not_found', false);
		}

		if (isset($config['display'])) {
			$container->setParameter($alias . '.display.always', $config['display']['always']);
			$container->setParameter($alias . '.display.ips', $config['display']['ips']);
		} else {
			$container->setParameter($alias . '.display.always', false);
			$container->setParameter($alias . '.display.ips', array());
		}

		$loader = new XmlFileLoader(
			$container,
			new FileLocator(__DIR__ . '/../Resources/config')
		);
		$loader->load('error.xml');
	}
}
