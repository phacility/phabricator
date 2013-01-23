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
