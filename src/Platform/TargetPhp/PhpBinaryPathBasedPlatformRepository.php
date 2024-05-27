<?php

declare(strict_types=1);

namespace Php\Pie\Platform\TargetPhp;

use Composer\Package\CompletePackage;
use Composer\Pcre\Preg;
use Composer\Repository\PlatformRepository;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

use function str_replace;
use function strtolower;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
class PhpBinaryPathBasedPlatformRepository extends PlatformRepository
{
    private VersionParser $versionParser;

    public function __construct(PhpBinaryPath $phpBinaryPath)
    {
        $this->versionParser = new VersionParser();
        $this->packages      = [];

        $phpVersion = $phpBinaryPath->version();
        $php        = new CompletePackage('php', $this->versionParser->normalize($phpVersion), $phpVersion);
        $php->setDescription('The PHP interpreter');
        $this->addPackage($php);

        $extVersions = $phpBinaryPath->extensions();

        foreach ($extVersions as $extension => $extensionVersion) {
            $this->addPackage($this->packageForExtension($extension, $extensionVersion));
        }

        parent::__construct();
    }

    private function packageForExtension(string $name, string $prettyVersion): CompletePackage
    {
        $extraDescription = '';

        try {
            $version = $this->versionParser->normalize($prettyVersion);
        } catch (UnexpectedValueException) {
            $extraDescription = ' (actual version: ' . $prettyVersion . ')';
            if (Preg::isMatchStrictGroups('{^(\d+\.\d+\.\d+(?:\.\d+)?)}', $prettyVersion, $match)) {
                $prettyVersion = $match[1];
            } else {
                $prettyVersion = '0';
            }

            $version = $this->versionParser->normalize($prettyVersion);
        }

        $package = new CompletePackage(
            'ext-' . str_replace(' ', '-', strtolower($name)),
            $version,
            $prettyVersion,
        );
        $package->setDescription('The ' . $name . ' PHP extension' . $extraDescription);
        $package->setType('php-ext');

        return $package;
    }
}
