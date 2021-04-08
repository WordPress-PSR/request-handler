<?php

declare(strict_types=1);

namespace WordPressPsr\Rector;

use PhpParser\Node;
use PhpParser\Node\Name;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 */
final class NoExit extends AbstractRector {

	public function getRuleDefinition(): RuleDefinition {
		return new RuleDefinition(
			'Change exit() or die() an exception',
			array(
				new CodeSample(
					<<<'CODE_SAMPLE'
die();
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
throw new \WordPressPsr\EarlyReturnException();
CODE_SAMPLE
				),

			)
		);
	}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array {
		return array( Node\Expr\Exit_::class );
	}

	/**
	 * @param Node\Expr\Exit_ $node
	 */
	public function refactor( Node $node ): ?Node {
		if ( ! $node instanceof Node\Expr\Exit_ ) {
			return null;
		}
		$args = array();
		if ( $node->expr ) {
			$args[] = new Node\Arg( $node->expr );
		}

		return new Node\Expr\FuncCall( new Node\Name('wp_exit'), $args );
	}
}
