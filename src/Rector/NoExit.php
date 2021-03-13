<?php

declare(strict_types=1);

namespace Tgc\WordPressPsr\Rector;

use PhpParser\Node;
use PhpParser\Node\Name;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 */
final class NoExit extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change exit() or die() an exception',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
die();
CODE_SAMPLE
,
                    <<<'CODE_SAMPLE'
throw new \Tgc\WordPressPsr\EarlyReturnException();
CODE_SAMPLE
                ),

            ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Node\Expr\Exit_::class];
    }

    /**
     * @param Node\Expr\Exit_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Expr\Exit_ ) {
            return null;
        }

        return new Node\Expr\New_(new Name('\Tgc\WordPressPsr\EarlyReturnException'));
    }
}
