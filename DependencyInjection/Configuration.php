<?php
/**
 * @author Oleg Stepura <github@oleg.stepura.com>
 * @copyright Oleg Stepura <github@oleg.stepura.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @version $Id$
 */

namespace Ost\ErrorBundle\DependencyInjection;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Configuration class.
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class Configuration
{
	/**
	 * Generates the configuration tree.
	 * @return \Symfony\Component\DependencyInjection\Configuration\NodeInterface
	 */
	public function getConfigTree()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode = $treeBuilder->root('ost_error');

		$rootNode
			->children()
				->arrayNode('mailer')
					->canBeUnset()
					->children()
						->scalarNode('to')->isRequired()->cannotBeEmpty()->end()
						->scalarNode('from')->isRequired()->cannotBeEmpty()->end()
						->booleanNode('report_not_found')->defaultFalse()->end()
					->end()
				->end()
				->arrayNode('display')
					->canBeUnset()
					->treatNullLike(array('always' => true))
					->treatTrueLike(array('always' => true))
					->children()
						->arrayNode('ips')
							->canBeUnset()
							->prototype('scalar')->end()
						->end()
						->booleanNode('always')->defaultFalse()->end()
					->end()
				->end()
			->end();

		return $treeBuilder->buildTree();
	}
}
