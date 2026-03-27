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
 * Replaces header() calls with wp_header() which fires the 'wp_header' action.
 * This allows the PSR request handler to capture headers for the PSR-7 response
 * instead of sending them directly to the client.
 */
final class NewHeaderFunction extends AbstractRector {

	public function getRuleDefinition(): RuleDefinition {
		return new RuleDefinition(
			'Change header() to wp_header() for PSR request handler compatibility',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
header('Content-Type: text/html');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_header('Content-Type: text/html');
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
		if ( ! $this->isName( $node, 'header' ) ) {
			return null;
		}

		return new FuncCall( new Name( 'wp_header' ), $node->args );
	}
}
