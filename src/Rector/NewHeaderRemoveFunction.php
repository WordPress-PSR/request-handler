<?php

namespace WordPressPsr\Rector;

use PhpParser\Node;

use PhpParser\Node\Expr\FuncCall;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class NewHeaderRemoveFunction extends AbstractRector
{
	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Change header to use static RequestHandler::addHeader()',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
header_remove('header'');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_header_remove('header');
CODE_SAMPLE
				),

			]);
	}

	/**
	 * @return array<class-string<Node>>
	 */
	public function getNodeTypes(): array
	{
		return [FuncCall::class];
	}

	/**
	 * @param FuncCall $node
	 */
	public function refactor(Node $node): ?Node
	{
		if ( ! $this->isName( $node, 'header_remove' ) ) {
			return null;
		}

		return new Node\Expr\FuncCall( new Node\Name('wp_header_remove'), $node->args );
	}
}