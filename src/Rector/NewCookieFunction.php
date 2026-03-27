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
 * Replaces setcookie() calls with wp_set_cookie() which fires the 'wp_set_cookie' action.
 * This allows the PSR request handler to capture cookies for the PSR-7 response
 * instead of sending them directly via PHP's setcookie().
 */
final class NewCookieFunction extends AbstractRector {

	public function getRuleDefinition(): RuleDefinition {
		return new RuleDefinition(
			'Change setcookie() to wp_set_cookie() for PSR request handler compatibility',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
setcookie('cookie', 'value');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_set_cookie('cookie', 'value');
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
		if ( ! $this->isName( $node, 'setcookie' ) ) {
			return null;
		}

		return new FuncCall( new Name( 'wp_set_cookie' ), $node->args );
	}
}
