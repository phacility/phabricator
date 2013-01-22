<?php

final class PhabricatorSetup {

  public static function runSetup() {
    header("Content-Type: text/plain");
    self::write("PHABRICATOR SETUP\n\n");

    // Force browser to stop buffering.
    self::write(str_repeat(' ', 2048));
    usleep(250000);

    self::write("This setup mode will guide you through setting up your ".
                "Phabricator configuration.\n");

    self::writeHeader("CORE CONFIGURATION");

    // NOTE: Test this first since other tests depend on the ability to
    // execute system commands and will fail if safe_mode is enabled.
    $safe_mode = ini_get('safe_mode');
    if ($safe_mode) {
      self::writeFailure();
      self::write(
        "Setup failure! You have 'safe_mode' enabled. Phabricator will not ".
        "run in safe mode, and it has been deprecated in PHP 5.3 and removed ".
        "in PHP 5.4.\n");
      return;
    } else {
      self::write(" okay  PHP's deprecated 'safe_mode' is disabled.\n");
    }

    // NOTE: Also test this early since we can't include files from other
    // libraries if this is set strictly.

    $open_basedir = ini_get('open_basedir');
    if ($open_basedir) {

      // 'open_basedir' restricts which files we're allowed to access with
      // file operations. This might be okay -- we don't need to write to
      // arbitrary places in the filesystem -- but we need to access certain
      // resources. This setting is unlikely to be providing any real measure
      // of security so warn even if things look OK.

      try {
        $open_libphutil = class_exists('Future');
      } catch (Exception $ex) {
        $message = $ex->getMessage();
        self::write("Unable to load modules from libphutil: {$message}\n");
        $open_libphutil = false;
      }

      try {
        $open_arcanist = class_exists('ArcanistDiffParser');
      } catch (Exception $ex) {
        $message = $ex->getMessage();
        self::write("Unable to load modules from Arcanist: {$message}\n");
        $open_arcanist = false;
      }

      $open_urandom = false;
      try {
        Filesystem::readRandomBytes(1);
        $open_urandom = true;
      } catch (FilesystemException $ex) {
        self::write($ex->getMessage()."\n");
      }

      try {
        $tmp = new TempFile();
        file_put_contents($tmp, '.');
        $open_tmp = @fopen((string)$tmp, 'r');
      } catch (Exception $ex) {
        $message = $ex->getMessage();
        $dir = sys_get_temp_dir();
        self::write("Unable to open temp files from '{$dir}': {$message}\n");
        $open_tmp = false;
      }

      if (!$open_urandom || !$open_tmp || !$open_libphutil || !$open_arcanist) {
        self::writeFailure();
        self::write(
          "Setup failure! Your server is configured with 'open_basedir' in ".
          "php.ini which prevents Phabricator from opening files it needs to ".
          "access. Either make the setting more permissive or remove it. It ".
          "is unlikely you derive significant security benefits from having ".
          "this configured; files outside this directory can still be ".
          "accessed through system command execution.");
        return;
      } else {
        self::write(
          "[WARN] You have an 'open_basedir' configured in your php.ini. ".
          "Although the setting seems permissive enough that Phabricator ".
          "will run properly, you may run into problems because of it. It is ".
          "unlikely you gain much real security benefit from having it ".
          "configured, because the application can still access files outside ".
          "the 'open_basedir' by running system commands.\n");
      }
    } else {
      self::write(" okay  'open_basedir' is not set.\n");
    }

    if (!PhabricatorEnv::getEnvConfig('security.alternate-file-domain')) {
      self::write(
        "[WARN] You have not configured 'security.alternate-file-domain'. ".
        "This makes your installation vulnerable to attack. Make sure you ".
        "read the documentation for this parameter and understand the ".
        "consequences of leaving it unconfigured.\n");
    }

    self::write("[OKAY] Core configuration OKAY.\n");

    $root = dirname(phutil_get_library_root('phabricator'));

    self::writeHeader("BASIC CONFIGURATION");

    $env = PhabricatorEnv::getEnvConfig('phabricator.env');
    if ($env == 'production' || $env == 'default' || $env == 'development') {
      self::writeFailure();
      self::write(
        "Setup failure! Your PHABRICATOR_ENV is set to '{$env}', which is ".
        "a Phabricator environmental default. You should create a custom ".
        "environmental configuration instead of editing the defaults ".
        "directly. See this document for instructions:\n");
        self::writeDoc('article/Configuration_Guide.html');
      return;
    }

    self::write("[OKAY] Basic configuration OKAY\n");

    self::writeHeader("MySQL DATABASE & STORAGE CONFIGURATION");

    $conf = PhabricatorEnv::newObjectFromConfig('mysql.configuration-provider');
    $conn_user = $conf->getUser();
    $conn_pass = $conf->getPassword();
    $conn_host = $conf->getHost();

    self::write(" okay  Trying to connect to MySQL database ".
                "{$conn_user}@{$conn_host}...\n");

    ini_set('mysql.connect_timeout', 2);

    $conn_raw = PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        array(
          'user'      => $conn_user,
          'pass'      => $conn_pass,
          'host'      => $conn_host,
          'database'  => null,
        ),
      ));

    try {
      queryfx($conn_raw, 'SELECT 1');
      self::write(" okay  Connection successful!\n");
    } catch (AphrontQueryConnectionException $ex) {
      $message = $ex->getMessage();
      self::writeFailure();
      self::write(
        "Setup failure! MySQL exception: {$message} \n".
        "Edit Phabricator configuration keys 'mysql.user', ".
        "'mysql.host' and 'mysql.pass' to enable Phabricator ".
        "to connect.");
      return;
    }

    $engines = queryfx_all($conn_raw, 'SHOW ENGINES');
    $engines = ipull($engines, 'Support', 'Engine');

    $innodb = idx($engines, 'InnoDB');
    if ($innodb != 'YES' && $innodb != 'DEFAULT') {
      self::writeFailure();
      self::write(
        "Setup failure! The 'InnoDB' engine is not available. Enable ".
        "InnoDB in your MySQL configuration. If you already created tables, ".
        "MySQL incorrectly used some other engine. You need to convert ".
        "them or drop and reinitialize them.");
      return;
    } else {
      self::write(" okay  InnoDB is available.\n");
    }

    $namespace = PhabricatorEnv::getEnvConfig('storage.default-namespace');

    $databases = queryfx_all($conn_raw, 'SHOW DATABASES');
    $databases = ipull($databases, 'Database', 'Database');
    if (empty($databases[$namespace.'_meta_data'])) {
      self::writeFailure();
      self::write(
        "Setup failure! You haven't run 'bin/storage upgrade'. See this ".
        "article for instructions:\n");
      self::writeDoc('article/Configuration_Guide.html');
      return;
    } else {
      self::write(" okay  Databases have been initialized.\n");
    }

    self::write("[OKAY] Database and storage configuration OKAY\n");

    self::writeHeader('SUCCESS!');
    self::write(
      "Congratulations! Your setup seems mostly correct, or at least fairly ".
      "reasonable.\n\n".
      "*** NEXT STEP ***\n".
      "Edit your configuration file (conf/{$env}.conf.php) and remove the ".
      "'phabricator.setup' line to finish installation.");

  }

  private static function writeFailure() {
    self::write("\n\n<<< *** FAILURE! *** >>>\n");
  }

  private static function write($str) {
    echo $str;
    ob_flush();
    flush();

    // This, uh, makes it look cool. -_-
    usleep(20000);
  }

  public static function writeHeader($header) {
    $template = '>>>'.str_repeat('-', 77);
    $template = substr_replace(
      $template,
      '  '.$header.'  ',
      3,
      strlen($header) + 4);
    self::write("\n\n{$template}\n\n");
  }

  public static function writeDoc($doc) {
    self::write(
      "\n".
      '    http://www.phabricator.com/docs/phabricator/'.$doc.
      "\n\n");
  }

}
