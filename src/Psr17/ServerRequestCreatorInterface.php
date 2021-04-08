<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace WordPressPsr\Psr17;

use Psr\Http\Message\ServerRequestInterface;

interface ServerRequestCreatorInterface
{
    /**
     * @return ServerRequestInterface
     */
    public function createServerRequestFromGlobals(): ServerRequestInterface;
}
