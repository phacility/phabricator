<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorSetup {

  const EXPECTED_SCHEMA_VERSION = 36;

  public static function runSetup() {
    header("Content-Type: text/plain");
    self::write("PHABRICATOR SETUP\n\n");

    // Force browser to stop buffering.
    self::write(str_repeat(' ', 2048));
    usleep(250000);

    self::write("This setup mode will guide you through setting up your ".
                "Phabricator configuration.\n");

    self::writeHeader("REQUIRED PHP EXTENSIONS");
    $extensions = array(
      'mysql',
      'hash',
      'json',
      'openssl',

      // There is a chance we might not need this, but some configurations (like
      // Amazon SES) will require it. Just mark it 'required' since it's widely
      // available and relatively core.
      'curl',
    );
    foreach ($extensions as $extension) {
      $ok = self::requireExtension($extension);
      if (!$ok) {
        self::writeFailure();
        self::write("Setup failure! Install PHP extension '{$extension}'.");
        return;
      }
    }

    $root = dirname(phutil_get_library_root('phabricator'));

    // On RHEL6, doing a distro install of pcntl makes it available from the
    // CLI binary but not from the Apache module. This isn't entirely
    // unreasonable and we don't need it from Apache, so do an explicit test
    // for CLI availability.
    list($err, $stdout, $stderr) = exec_manual(
      '%s/scripts/setup/pcntl_available.php',
      $root);
    if ($err) {
      self::writeFailure();
      self::write("Unable to execute scripts/setup/pcntl_available.php.");
      return;
    } else {
      if (trim($stdout) == 'YES') {
        self::write(" okay  pcntl is available from the command line.\n");
        self::write("[OKAY] All extensions OKAY\n\n");
      } else {
        self::write(" warn  pcntl is not available!\n");
        self::write("[WARN] *** WARNING *** pcntl extension not available. ".
                    "You will not be able to run daemons.\n");
      }
    }

    self::writeHeader("GIT SUBMODULES");
    if (!Filesystem::pathExists($root.'/.git')) {
      self::write(" skip  Not a git clone.\n\n");
    } else {
      list($info) = execx(
        '(cd %s && git submodule status)',
        $root);
      foreach (explode("\n", rtrim($info)) as $line) {
        $matches = null;
        if (!preg_match('/^(.)([0-9a-f]{40}) (\S+)(?: |$)/', $line, $matches)) {
          self::writeFailure();
          self::write(
            "Setup failure! 'git submodule' produced unexpected output:\n".
            $line);
          return;
        }

        $status = $matches[1];
        $module = $matches[3];

        switch ($status) {
          case '-':
          case '+':
          case 'U':
            self::writeFailure();
            self::write(
              "Setup failure! Git submodule '{$module}' is not up to date. ".
              "Run:\n\n".
              "  cd {$root} && git submodule update --init\n\n".
              "...to update submodules.");
            return;
          case ' ':
            self::write(" okay  Git submodule '{$module}' up to date.\n");
            break;
          default:
            self::writeFailure();
            self::write(
              "Setup failure! 'git submodule' reported unknown status ".
              "'{$status}' for submodule '{$module}'. This is a bug; report ".
              "it to the Phabricator maintainers.");
            return;
        }
      }
    }
    self::write("[OKAY] All submodules OKAY.");

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
    } else {
      $host = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
      $protocol = id(new PhutilURI($host))->getProtocol();
      $allowed_protocols = array(
        'http'  => true,
        'https' => true,
      );
      if (empty($allowed_protocols[$protocol])) {
        self::writeFailure();
        self::write(
          "You must specify the protocol over which your host works (e.g.: ".
          "\"http:// or https://\")\nin your custom config file.\nRefer to ".
          "'default.conf.php' for documentation on configuration options.\n");
        return;
      }
      if (preg_match('/.*\/$/', $host)) {
        self::write(" okay  phabricator.base-uri\n");
      } else {
        self::writeFailure();
        self::write(
          "You must add a trailing slash at the end of the host\n(e.g.: ".
          "\"http://phabricator.example.com/ instead of ".
          "http://phabricator.example.com\")\nin your custom config file.".
          "\nRefer to 'default.conf.php' for documentation on configuration ".
          "options.\n");
        return;
      }
    }

    self::write("[OKAY] Basic configuration OKAY\n");

    self::writeHeader('FACEBOOK INTEGRATION');
    $fb_auth = PhabricatorEnv::getEnvConfig('facebook.auth-enabled');
    if (!$fb_auth) {
      self::write(" skip  'facebook.auth-enabled' not enabled.\n");
    } else {
      self::write(" okay  'facebook.auth-enabled' is enabled.\n");
      $app_id = PhabricatorEnv::getEnvConfig('facebook.application-id');
      $app_secret = PhabricatorEnv::getEnvConfig('facebook.application-secret');

      if (!$app_id) {
        self::writeFailure();
        self::write(
          "Setup failure! 'facebook.auth-enabled' is true but there is no ".
          "setting for 'facebook.application-id'.\n");
        return;
      } else {
        self::write(" okay  'facebook.application-id' is set.\n");
      }

      if (!is_string($app_id)) {
        self::writeFailure();
        self::write(
          "Setup failure! 'facebook.application-id' should be a string.");
        return;
      } else {
        self::write(" okay  'facebook.application-id' is string.\n");
      }

      if (!$app_secret) {
        self::writeFailure();
        self::write(
          "Setup failure! 'facebook.auth-enabled' is true but there is no ".
          "setting for 'facebook.application-secret'.");
        return;
      } else {
        self::write(" okay  'facebook.application-secret is set.\n");
      }

      self::write("[OKAY] Facebook integration OKAY\n");
    }

    self::writeHeader("MySQL DATABASE CONFIGURATION");

    $conn_user = PhabricatorEnv::getEnvConfig('mysql.user');
    $conn_pass = PhabricatorEnv::getEnvConfig('mysql.pass');
    $conn_host = PhabricatorEnv::getEnvConfig('mysql.host');

    $timeout = ini_get('mysql.connect_timeout');
    if ($timeout > 5) {
      self::writeNote(
        "Your MySQL connect timeout is very high ({$timeout} seconds). ".
        "Consider reducing it by setting 'mysql.connect_timeout' in your ".
        "php.ini.");
    }

    self::write(" okay  Trying to connect to MySQL database ".
                "{$conn_user}@{$conn_host}...\n");

    ini_set('mysql.connect_timeout', 2);

    $conn_raw = new AphrontMySQLDatabaseConnection(
      array(
        'user'      => $conn_user,
        'pass'      => $conn_pass,
        'host'      => $conn_host,
        'database'  => null,
      ));

    try {
      queryfx($conn_raw, 'SELECT 1');
      self::write(" okay  Connection successful!\n");
    } catch (AphrontQueryConnectionException $ex) {
      self::writeFailure();
      self::write(
        "Setup failure! Unable to connect to MySQL database ".
        "'{$conn_host}' with user '{$conn_user}'. Edit Phabricator ".
        "configuration keys 'mysql.user', 'mysql.host' and 'mysql.pass' to ".
        "enable Phabricator to connect.");
      return;
    }

    $databases = queryfx_all($conn_raw, 'SHOW DATABASES');
    $databases = ipull($databases, 'Database');
    $databases = array_fill_keys($databases, true);
    if (empty($databases['phabricator_meta_data'])) {
      self::writeFailure();
      self::write(
        "Setup failure! You haven't loaded the 'initialize.sql' file into ".
        "MySQL. This file initializes necessary databases. See this guide for ".
        "instructions:\n");
      self::writeDoc('article/Configuration_Guide.html');
      return;
    } else {
      self::write(" okay  Databases have been initialized.\n");
    }

    $schema_version = queryfx_one(
      $conn_raw,
      'SELECT version FROM phabricator_meta_data.schema_version');
    $schema_version = idx($schema_version, 'version', 'null');

    $expect = PhabricatorSQLPatchList::getExpectedSchemaVersion();
    if ($schema_version != $expect) {
      self::writeFailure();
      self::write(
        "Setup failure! You haven't upgraded your database schema to the ".
        "latest version. Expected version is '{$expect}', but your local ".
        "version is '{$schema_version}'. See this guide for instructions:\n");
      self::writeDoc('article/Upgrading_Schema.html');
      return;
    } else {
      self::write(" okay  Database schema are up to date (v{$expect}).\n");
    }

    self::write("[OKAY] Database configuration OKAY\n");


    self::writeHeader("OUTBOUND EMAIL CONFIGURATION");

    $have_adapter = false;
    $is_ses = false;

    $adapter = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
    switch ($adapter) {
      case 'PhabricatorMailImplementationPHPMailerLiteAdapter':

        $have_adapter = true;

        if (!Filesystem::pathExists('/usr/bin/sendmail')) {
          self::writeFailure();
          self::write(
            "Setup failure! You don't have a 'sendmail' binary on this system ".
            "but outbound email is configured to use sendmail. Install an MTA ".
            "(like sendmail, qmail or postfix) or use a different outbound ".
            "mail configuration. See this guide for configuring outbound ".
            "email:\n");
          self::writeDoc('article/Configuring_Outbound_Email.html');
          return;
        } else {
          self::write(" okay  Sendmail is configured.\n");
        }

        break;
      case 'PhabricatorMailImplementationAmazonSESAdapter':

        $is_ses = true;
        $have_adapter = true;

        if (PhabricatorEnv::getEnvConfig('metamta.can-send-as-user')) {
          self::writeFailure();
          self::write(
            "Setup failure! 'metamta.can-send-as-user' must be false when ".
            "configured with Amazon SES.");
          return;
        } else {
          self::write(" okay  Sender config looks okay.\n");
        }

        if (!PhabricatorEnv::getEnvConfig('amazon-ses.access-key')) {
          self::writeFailure();
          self::write(
            "Setup failure! 'amazon-ses.access-key' is not set, but ".
            "outbound mail is configured to deliver via Amazon SES.");
          return;
        } else {
          self::write(" okay Amazon SES access key is set.\n");
        }

        if (!PhabricatorEnv::getEnvConfig('amazon-ses.secret-key')) {
          self::writeFailure();
          self::write(
            "Setup failure! 'amazon-ses.secret-key' is not set, but ".
            "outbound mail is configured to deliver via Amazon SES.");
          return;
        } else {
          self::write(" okay Amazon SES secret key is set.\n");
        }

        if (PhabricatorEnv::getEnvConfig('metamta.send-immediately')) {
          self::writeNote(
            "Your configuration uses Amazon SES to deliver email but tries ".
            "to send it immediately. This will work, but it's slow. ".
            "Consider configuring the MetaMTA daemon.");
        }
        break;
      case 'PhabricatorMailImplementationTestAdapter':
        self::write(" skip  You have disabled outbound email.\n");
        break;
      default:
        self::write(" skip  Configured with a custom adapter.\n");
        break;
    }

    if ($have_adapter) {
      $default = PhabricatorEnv::getEnvConfig('metamta.default-address');
      if (!$default || $default == 'noreply@example.com') {
        self::writeFailure();
        self::write(
          "Setup failure! You have not set 'metamta.default-address'.");
        return;
      } else {
        self::write(" okay  metamta.default-address is set.\n");
      }

      if ($is_ses) {
        self::writeNote(
          "Make sure you've verified your 'from' address ('{$default}') with ".
          "Amazon SES. Until you verify it, you will be unable to send mail ".
          "using Amazon SES.");
      }

      $domain = PhabricatorEnv::getEnvConfig('metamta.domain');
      if (!$domain || $domain == 'example.com') {
        self::writeFailure();
        self::write(
          "Setup failure! You have not set 'metamta.domain'.");
        return;
      } else {
        self::write(" okay  metamta.domain is set.\n");
      }

      self::write("[OKAY] Mail configuration OKAY\n");
    }

    self::writeHeader('SUCCESS!');
    self::write(
      "Congratulations! Your setup seems mostly correct, or at least fairly ".
      "reasonable.\n\n".
      "*** NEXT STEP ***\n".
      "Edit your configuration file (conf/{$env}.conf.php) and remove the ".
      "'phabricator.setup' line to finish installation.");

  }

  public static function requireExtension($extension) {
    if (extension_loaded($extension)) {
      self::write(" okay  Extension '{$extension}' installed.\n");
      return true;
    } else {
      self::write("[FAIL] Extension '{$extension}' is NOT INSTALLED!\n");
      return false;
    }
  }

  private static function writeFailure() {
    self::write("\n\n<<< *** FAILURE! *** >>>\n");
  }

  private static function write($str) {
    echo $str;
    ob_flush();
    flush();

    // This, uh, makes it look cool. -_-
    usleep(40000);
  }

  private static function writeNote($note) {
    self::write(
      'Note: '.wordwrap($note, 75, "\n      ", true)."\n\n");
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
      '    http://phabricator.com/docs/phabricator/'.$doc.
      "\n\n");
  }

}
