<?php

declare(strict_types=1);

namespace Php\Pie\Platform;

use Php\Pie\Platform\TargetPhp\PhpBinaryPath;

use function array_key_exists;
use function explode;
use function preg_match;
use function trim;

/**
 * @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks
 *
 * @immutable
 */
class TargetPlatform
{
    public function __construct(
        public readonly OperatingSystem $operatingSystem,
        public readonly PhpBinaryPath $phpBinaryPath,
        public readonly Architecture $architecture,
        public readonly ThreadSafetyMode $threadSafety,
        public readonly WindowsCompiler|null $windowsCompiler,
    ) {
    }

    public static function fromPhpBinaryPath(PhpBinaryPath $phpBinaryPath): self
    {
        $os = $phpBinaryPath->operatingSystem();

        $phpinfo = $phpBinaryPath->phpinfo();

        $architecture = $phpBinaryPath->machineType();

        /**
         * Based on xdebug.org wizard, copyright Derick Rethans, used under MIT licence
         *
         * @link https://github.com/xdebug/xdebug.org/blob/aff649f2c3ca303ad471e6ed9dd29c0db16d3e22/src/XdebugVersion.php#L186-L190
         */
        if (
            preg_match('/Architecture([ =>\t]*)(x[0-9]*)/', $phpinfo, $m)
            && array_key_exists(2, $m)
            && $m[2] !== ''
        ) {
            $architecture = Architecture::parseArchitecture($m[2]);
        }

        $windowsCompiler = null;
        $threadSafety    = ThreadSafetyMode::ThreadSafe;

        /**
         * Based on xdebug.org wizard, copyright Derick Rethans, used under MIT licence
         *
         * @link https://github.com/xdebug/xdebug.org/blob/aff649f2c3ca303ad471e6ed9dd29c0db16d3e22/src/XdebugVersion.php#L276-L299
         */
        if (preg_match('/PHP Extension Build([ =>\t]+)(API.*)/', $phpinfo, $m)) {
            $parts = explode(',', trim($m[2]));
            foreach ($parts as $part) {
                switch ($part) {
                    case 'NTS':
                        $threadSafety = ThreadSafetyMode::NonThreadSafe;
                        break;
                    case 'TS':
                        $threadSafety = ThreadSafetyMode::ThreadSafe;
                        break;
                    case 'VC6':
                        $windowsCompiler = WindowsCompiler::VC6;
                        break;
                    case 'VC8':
                        $windowsCompiler = WindowsCompiler::VC8;
                        break;
                    case 'VC9':
                        $windowsCompiler = WindowsCompiler::VC9;
                        break;
                    case 'VC11':
                        $windowsCompiler = WindowsCompiler::VC11;
                        break;
                    case 'VC14':
                        $windowsCompiler = WindowsCompiler::VC14;
                        break;
                    case 'VC15':
                        $windowsCompiler = WindowsCompiler::VC15;
                        break;
                    case 'VS16':
                        $windowsCompiler = WindowsCompiler::VS16;
                        break;
                }
            }
        }

        return new self(
            $os,
            $phpBinaryPath,
            $architecture,
            $threadSafety,
            $windowsCompiler,
        );
    }
}
