<?php

declare(strict_types=1);

namespace Php\Pie\DependencyResolver;

use Composer\Composer;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Php\Pie\ExtensionType;
use Php\Pie\Platform\TargetPhp\ResolveTargetPhpToPlatformRepository;
use Php\Pie\Platform\TargetPlatform;

use function preg_match;
use function str_starts_with;

/** @internal This is not public API for PIE, so should not be depended upon unless you accept the risk of BC breaks */
final class ResolveDependencyWithComposer implements DependencyResolver
{
    public function __construct(
        private readonly Composer $composer,
        private readonly ResolveTargetPhpToPlatformRepository $resolveTargetPhpToPlatformRepository,
    ) {
    }

    private function factoryRepositorySet(string|null $requestedVersion): RepositorySet
    {
        $minimumStability = 'stable';

        /** Stability options from {@see https://getcomposer.org/doc/04-schema.md#minimum-stability} */
        if ($requestedVersion !== null) {
            if (preg_match('#@(dev|alpha|beta|RC|stable)$#', $requestedVersion, $matches)) {
                $minimumStability = $matches[1];
            }

            // If a specific stability was not requested, but the version requested was `dev-` something, change to dev min stability
            if (! $matches && str_starts_with($requestedVersion, 'dev-')) {
                $minimumStability = 'dev';
            }
        }

        $repositorySet = new RepositorySet($minimumStability);
        $repositorySet->addRepository(new CompositeRepository($this->composer->getRepositoryManager()->getRepositories()));

        return $repositorySet;
    }

    public function __invoke(TargetPlatform $targetPlatform, string $packageName, string|null $requestedVersion): Package
    {
        $package = (new VersionSelector(
            $this->factoryRepositorySet($requestedVersion),
            ($this->resolveTargetPhpToPlatformRepository)($targetPlatform->phpBinaryPath),
        ))
            ->findBestCandidate($packageName, $requestedVersion);

        if (! $package instanceof CompletePackageInterface) {
            throw UnableToResolveRequirement::fromRequirement($packageName, $requestedVersion);
        }

        /**
         * If a specific commit hash is requested, override the references in the package. This is approximately what
         * Composer does anyway:
         *
         * > ArrayLoader::parseLinks is in charge of this, it drops commit refs, for package resolution purposes we
         * > only use dev-main, but we ensure in the PoolBuilder that root references (#...) are set so the dev-main
         * > package has its source and dist refs overridden to be whatever you specify and that applies at install
         * > time then but package metadata is only read from the branch's head
         */
        if ($requestedVersion !== null && preg_match('/#([a-f0-9]{40})$/', $requestedVersion, $matches)) {
            $package->setSourceDistReferences($matches[1]);
        }

        if (! ExtensionType::isValid($package->getType())) {
            throw UnableToResolveRequirement::toPhpOrZendExtension($package, $packageName, $requestedVersion);
        }

        return Package::fromComposerCompletePackage($package);
    }
}
