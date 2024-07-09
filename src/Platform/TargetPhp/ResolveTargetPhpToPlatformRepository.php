<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Repository\PlatformRepository;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class ResolveTargetPhpToPlatformRepository
{
    public function __invoke(PhpBinaryPath $phpBinaryPath): PlatformRepository
    {
        return new PhpBinaryPathBasedPlatformRepository($phpBinaryPath);
    }
}
