<?php

namespace SkilldDrupal;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem;
use Composer\Util\Platform;

/**
 * A Composer plugin to remove files in Drupal packages.
 */
class DrupalCleanup implements PluginInterface, EventSubscriberInterface {

  /**
   * Composer object.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * IO object.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
      PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
    ];
  }

  /**
   * POST_PACKAGE_INSTALL event handler.
   *
   * @param \Composer\Installer\PackageEvent $event
   */
  public function onPostPackageInstall(PackageEvent $event) {
    if (Platform::getEnv('DRUPAL_CLEANUP_SKIP') ?? 0) {
      $this->io->write('Clean-up is skipped', TRUE, IOInterface::VERBOSE);
      return [];
    }

    $this->cleanPackage($event->getOperation()->getPackage());
  }

  /**
   * Clean a single package.
   *
   * This applies in the context of a package post-install or post-update event.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to clean.
   */
  public function cleanPackage(PackageInterface $package) {
    $package_type = $package->getType();

    if (!$this->isPackageTypeShouldBeCleared($package_type)) {
      return;
    }

    $rules = $this->getPackageCleanRules($package_type);

    if (count($rules) === 0) {
      $this->printMessage(
        $package,
        "skipped as settings for package type <comment>$package_type</comment> missing",
      );

      return;
    }

    $removed = 0;
    $package_path = $this
      ->composer
      ->getInstallationManager()
      ->getInstallPath($package);
    $fs = new Filesystem();

    foreach ($rules as $rule) {
      $paths = glob($package_path . DIRECTORY_SEPARATOR . $rule, GLOB_ERR);

      if (!$paths) {
        continue;
      }

      foreach ($paths as $path) {
        try {
          $fs->remove($path);
          $removed++;
        }
        catch (\Throwable $e) {
          $this->io->writeError(\sprintf(
            '<info>%s:</info> (<comment>%s</comment>) Error occurred: %s',
            $package->getName(),
            $package_type,
            $e->getMessage()
          ));
        }
      }
    }

    $this->printMessage($package, "removed <comment>$removed</comment>");
  }

  /**
   * Checks is current package type should be cleared.
   *
   * @param string $package_type
   *   The package type.
   *
   * @return bool
   *   TRUE if package type is listed and needs to be processed, FALSE
   *   otherwise.
   */
  private function isPackageTypeShouldBeCleared(string $package_type): bool {
    $extra = $this->composer->getPackage()->getExtra();

    foreach ($this->getSuitableScopes() as $scope) {
      if (isset($extra['drupal-cleanup'][$scope][$package_type])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Gets the list of available scopes.
   *
   * @return string[]
   *   An array with scope names where search for rules:
   *   - default: Always clean;
   *   - dev: When composer command called without '--no-dev';
   *   - no-dev: When composer command called with '--no-dev'.
   */
  private function getSuitableScopes(): array {
    return [
      'default',
      $this->isDevMode() ? 'dev' : 'no-dev',
    ];
  }

  /**
   * Checks whether composer install packages in dev mode or not.
   *
   * @return bool
   *   TRUE if comman run with dev mode, FALSE if running with '--no-dev'
   *   option.
   */
  private function isDevMode(): bool {
    return getenv('COMPOSER_DEV_MODE') === '1';
  }

  /**
   * Gets the package rules to clean up.
   *
   * @param string $package_type
   *   The package type.
   *
   * @return array
   *   An array of paths to clean up.
   */
  private function getPackageCleanRules(string $package_type): array {
    $extra = $this->composer->getPackage()->getExtra();
    $rules = [];

    foreach ($this->getSuitableScopes() as $scope) {
      if (!isset($extra['drupal-cleanup'][$scope][$package_type])) {
        continue;
      }

      $rules = \array_merge(
        $rules,
        $extra['drupal-cleanup'][$scope][$package_type],
      );
    }

    return \array_diff($rules, $this->getExcludeRules());
  }

  /**
   * Gets rules with exclude paths.
   *
   * @return array
   *   An array of paths that should be preserved.
   */
  private function getExcludeRules(): array {
    $extra = $this->composer->getPackage()->getExtra();

    return $extra['drupal-cleanup']['exclude'] ?? [];
  }

  /**
   * Prints a message into terminal.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The processed package.
   * @param string $message
   *   The message.
   */
  private function printMessage(PackageInterface $package, string $message): void {
    $this->io->write(sprintf(
      '  - Cleaning <info>%s</info> (<comment>%s</comment>): %s',
      $package->getName(),
      $package->getType(),
      $message
    ), TRUE, IOInterface::VERBOSE);
  }

  /**
   * POST_PACKAGE_UPDATE event handler.
   *
   * @param \Composer\Installer\PackageEvent $event
   */
  public function onPostPackageUpdate(PackageEvent $event) {
    if (Platform::getEnv('DRUPAL_CLEANUP_SKIP') ?? 0) {
      $this->io->write('Clean-up is skipped', TRUE, IOInterface::VERBOSE);
      return [];
    }

    $this->cleanPackage($event->getOperation()->getTargetPackage());
  }

}
