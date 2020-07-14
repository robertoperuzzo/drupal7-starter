<?php

define('OUTPUT_REDIRECTION', '>');

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Robo\Exception\TaskException;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

  use \Boedah\Robo\Task\Drush\loadTasks;

  const PROJECT_FOLDER = __DIR__;
  const DRUPAL_ROOT_FOLDER = self::PROJECT_FOLDER . '/web';
  const DATABASE_DUMP_FOLDER = self::PROJECT_FOLDER . '/backups';
  const CHECKUP_FOLDER = self::PROJECT_FOLDER . '/checkup';
  const REPORT_FILE_SECURITY = self::CHECKUP_FOLDER . '/security.out';

  /**
   * Site.
   *
   * @var string
   */
  protected $site = 'default';

  /**
   * Setup from database.
   *
   * @command install:database
   *
   * @param string $dump_file
   *
   * @return \Robo\Collection\CollectionBuilder
   */
  public function installDatabase($dump_file) {
    $task_list = [
      'sqlDrop' => $this->initDrush()
        ->drush('sql:drop'),
      'sqlCli' => $this->initDrush()
        ->arg("< $dump_file")
        ->drush('sql:cli'),
      'cacheClear' => $this->initDrush()->clearCache(),
    ];
    $this->getBuilder()->addTaskList($task_list);
    return $this->getBuilder();
  }

  /**
   * Scaffold file for Drupal.
   *
   * Create the settings.php and service.yml from default file template or twig
   * twig template.
   *
   * @return $this
   *
   * @throws \Robo\Exception\TaskException
   */
  public function scaffold() {
    $base = self::DRUPAL_ROOT_FOLDER . "/sites/{$this->site}";
    $scaffold = self::PROJECT_FOLDER . "/scaffold";

    // Throws exception if dir site folder not exists.
    if (!file_exists($base)) {
      $this->io()->warning('No website found! You have to copy Drupal codebase in /web folder.');
      return NULL;
    }

    $settingsLocalSource = $scaffold . DIRECTORY_SEPARATOR . 'tpl.settings.local.php';
    $settingsOriginCopy = $scaffold . DIRECTORY_SEPARATOR . 'origin.settings.php';
    $settingsLocaldestination = $base . DIRECTORY_SEPARATOR . 'settings.local.php';
    $settingsFilePath = $base . DIRECTORY_SEPARATOR . 'settings.php';
    $settingsSourceAppend = $scaffold . DIRECTORY_SEPARATOR . 'tpl.settings.php';

    if (!file_exists($settingsOriginCopy)) {
      $this->taskFilesystemStack()
        ->copy($settingsFilePath, $settingsOriginCopy)
        ->run();
    }

    $this->getBuilder()->addTaskList([
      'add-settings-local-php' => $this->taskFilesystemStack()
        ->copy($settingsLocalSource, $settingsLocaldestination),
      'set-permission-default' =>  $this->taskFilesystemStack()
        ->chmod($base, 0755),
      'write-permission-settings-php' =>  $this->taskFilesystemStack()
        ->chmod($settingsFilePath, 0644),
      'append-settings-php' => $this->taskConcat([
        $settingsOriginCopy,
        $settingsSourceAppend,
      ])->to($settingsFilePath),
      'read-permission-settings-php' =>  $this->taskFilesystemStack()
        ->chmod($settingsFilePath, 0444),
    ]);

    return $this->getBuilder();
  }

  /**
   * Build drupal project.
   */
  public function build() {
    $task_list = [
      'siteBuild' => $this->initDrush()
        ->arg('project.make.yml')
        ->arg(self::DRUPAL_ROOT_FOLDER)
        ->drush('make'),
      'databaseUpdate' => $this->initDrush()
        ->drush('updb'),
    ];
    $this->getBuilder()->addTaskList($task_list);
    return $this->getBuilder();
  }

  /**
   * Install drupal project.
   */
  public function install() {
    $this->initDrush()
      ->siteName('Drupal 7')
      ->siteMail('sitemail@example.com')
      ->accountMail('mail@example.com')
      ->accountName('admin')
      ->accountPass('admin')
      ->disableUpdateStatusModule()
      ->siteInstall('standard')
      ->run();
  }

  /**
   * Sets up drush defaults.
   *
   * @param string $site
   * @return \Boedah\Robo\Task\Drush\DrushStack
   */
  protected function initDrush($site = 'default') {
    return $this->taskDrushStack('/usr/local/bin/drush')
      ->drupalRootDirectory(self::DRUPAL_ROOT_FOLDER)
      ->uri($site);
  }

}
