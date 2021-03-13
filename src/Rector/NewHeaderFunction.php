<?php

namespace Tgc\WordPressPsr\Rector;

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
header('key', 'value');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
\Tgc\WordPressPsr\RequestHandler::addHeader('key','value');
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
		if ( ! $this->isName($node, 'header') ) {
			return null;
		}

		return new Node\Expr\StaticCall( new Node\Name('\Tgc\WordPressPsr\RequestHandler'), 'addHeader', $node->args );
	}
}