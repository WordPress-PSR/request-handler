<?php

namespace WordPressPsr\Rector;

use PhpParser\Node;

use PhpParser\Node\Expr\FuncCall;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class NewHeaderFunction extends AbstractRector
{
	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Change header to use static RequestHandler::addHeader()',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
header('header'');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_header('header');
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
		if ( ! $this->isName( $node, 'header' ) ) {
			return null;
		}

		return new Node\Expr\FuncCall( new Node\Name('wp_header'), $node->args );
	}
}