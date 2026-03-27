<?php

declare(strict_types=1);

namespace WordPressPsr\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces header_remove() calls with wp_header_remove() which fires the 'wp_header_remove' action.
 * This allows the PSR request handler to track header removals for the PSR-7 response.
 */
final class NewHeaderRemoveFunction extends AbstractRector {

	public function getRuleDefinition(): RuleDefinition {
		return new RuleDefinition(
			'Change header_remove() to wp_header_remove() for PSR request handler compatibility',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
header_remove('X-Powered-By');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_header_remove('X-Powered-By');
CODE_SAMPLE
				),
			]
		);
	}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array {
		return [ FuncCall::class ];
	}

	/**
	 * @param FuncCall $node
	 */
	public function refactor( Node $node ): ?Node {
		if ( ! $this->isName( $node, 'header_remove' ) ) {
			return null;
		}

		return new FuncCall( new Name( 'wp_header_remove' ), $node->args );
	}
}
