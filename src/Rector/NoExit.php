<?php

declare(strict_types=1);

namespace WordPressPsr\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces exit() and die() calls with wp_exit() which fires the 'wp_exit' action
 * before exiting. This allows the PSR request handler to catch the exit via
 * PrematureExitException and return a proper PSR-7 response.
 */
final class NoExit extends AbstractRector {

	public function getRuleDefinition(): RuleDefinition {
		return new RuleDefinition(
			'Change exit()/die() to wp_exit() for PSR request handler compatibility',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
die();
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_exit();
CODE_SAMPLE
				),
			]
		);
	}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array {
		return [ Exit_::class ];
	}

	/**
	 * @param Exit_ $node
	 */
	public function refactor( Node $node ): ?Node {
		$args = [];
		if ( $node->expr !== null ) {
			$args[] = new Arg( $node->expr );
		}

		return new FuncCall( new Name( 'wp_exit' ), $args );
	}
}
