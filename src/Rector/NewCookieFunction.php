<?php

namespace WordPressPsr\Rector;

use PhpParser\Node;

use PhpParser\Node\Expr\FuncCall;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class NewCookieFunction extends AbstractRector
{
	public function getRuleDefinition(): RuleDefinition
	{
		return new RuleDefinition(
			'Change header to use static RequestHandler::addHeader()',
			[
				new CodeSample(
					<<<'CODE_SAMPLE'
setcookie('cookie', 'value');
CODE_SAMPLE
					,
					<<<'CODE_SAMPLE'
wp_setcookie('cookie', 'value');
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
		if ( ! $this->isName($node, 'setcookie') ) {
			return null;
		}

		return new Node\Expr\FuncCall( new Node\Name('wp_set_cookie'), $node->args );
	}
}