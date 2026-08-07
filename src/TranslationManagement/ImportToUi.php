<?php

namespace RoboComponents\TranslationManagement;

use Robo\ResultData;

/**
 * Logic to import translations into Drupal UI translations.
 *
 * Imports translations from "config/po_files/[langcode].po" files.
 */
trait ImportToUi {

  /**
   * Import the interface translations from a PO file.
   *
   * @param bool $is_local
   *   Whether to run Drush locally (TRUE), or on Pantheon (FALSE).
   *   Default: TRUE.
   * @param string $env
   *   The Pantheon environment to run on, if $is_local = FALSE. Default: test.
   *
   * @return \Robo\ResultData
   *   The result.
   *
   * @throws \Exception
   */
  public function localeImport(bool $is_local = TRUE, string $env = 'test'): ResultData {
    $commands = [];
    $po_files_path = $this->getPoFilesPath();
    if ($is_local) {
      foreach ($this->getInstalledLanguages() as $language) {
        $commands[] = "drush locale:import --override=not-customized $language ../$po_files_path/$language.po";
      }
    }
    else {
      $pantheon_info = $this->getPantheonNameAndEnv();
      $pantheon_terminus_environment = $pantheon_info['name'] . '.' . $env;
      foreach ($this->getInstalledLanguages() as $language) {
        $commands[] = "terminus drush $pantheon_terminus_environment -- locale:import --override=not-customized $language ../$po_files_path/$language.po";
      }
    }
    return $this->_exec(implode(' && ', $commands));
  }

}
