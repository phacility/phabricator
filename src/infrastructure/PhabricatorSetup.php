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

    $path = getenv('PATH');
    if (empty($path)) {
      self::writeFailure();
      self::write(
        "Setup failure! The environmental \$PATH variable is empty. ".
        "Phabricator needs to execute system commands like 'svn', 'git', ".
        "'hg', and 'diff'. Set up your webserver so that it passes a valid ".
        "\$PATH to the PHP process.\n\n");
      if (php_sapi_name() == 'fpm-fcgi') {
        self::write(
          "You're running php-fpm, so the easiest way to do this is to add ".
          "this line to your php-fpm.conf:\n\n".
          "  env[PATH] = /usr/local/bin:/usr/bin:/bin\n\n".
          "Then restart php-fpm.\n");
      }
      return;
    } else {
      self::write(" okay  \$PATH is nonempty.\n");
    }

    self::write("[OKAY] Core configuration OKAY.\n");

    self::writeHeader("REQUIRED PHP EXTENSIONS");
    $extensions = array(
      'mysql',
      'hash',
      'json',
      'openssl',
      'mbstring',
      'iconv',

      // There is a chance we might not need this, but some configurations (like
      // OAuth or Amazon SES) will require it. Just mark it 'required' since
      // it's widely available and relatively core.
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

    list($err, $stdout, $stderr) = exec_manual('which php');
    if ($err) {
      self::writeFailure();
      self::write("Unable to locate 'php' on the command line from the web ".
                  "server. Verify that 'php' is in the webserver's PATH.\n".
                  "   err: {$err}\n".
                  "stdout: {$stdout}\n".
                  "stderr: {$stderr}\n");
      return;
    } else {
      self::write(" okay  PHP binary found on the command line.\n");
      $php_bin = trim($stdout);
    }

    // NOTE: In cPanel + suphp installs, 'php' may be the PHP CGI SAPI, not the
    // PHP CLI SAPI. proc_open() will pass the environment to the child process,
    // which will re-execute the webpage (causing an infinite number of
    // processes to spawn). To test that the 'php' binary is safe to execute,
    // we call php_sapi_name() using "env -i" to wipe the environment so it
    // doesn't execute another reuqest if it's the wrong binary. We can't use
    // "-r" because php-cgi doesn't support that flag.

    $tmp_file = new TempFile('sapi.php');
    Filesystem::writeFile($tmp_file, '<?php echo php_sapi_name();');

    list($err, $stdout, $stderr) = exec_manual(
      '/usr/bin/env -i %s -f %s',
      $php_bin,
      $tmp_file);
    if ($err) {
      self::writeFailure();
      self::write("Unable to execute 'php' on the command line from the web ".
                  "server.\n".
                  "   err: {$err}\n".
                  "stdout: {$stdout}\n".
                  "stderr: {$stderr}\n");
      return;
    } else {
      self::write(" okay  PHP is available from the command line.\n");

      $sapi = trim($stdout);
      if ($sapi != 'cli') {
        self::writeFailure();
        self::write(
          "The 'php' binary on this system uses the '{$sapi}' SAPI, but the ".
          "'cli' SAPI is expected. Replace 'php' with the php-cli SAPI ".
          "binary, or edit your webserver configuration so the first 'php' ".
          "in PATH is the 'cli' SAPI.\n\n".
          "If you're running cPanel with suphp, the easiest way to fix this ".
          "is to add '/usr/local/bin' before '/usr/bin' for 'env_path' in ".
          "suconf.php:\n\n".
          '  env_path="/bin:/usr/local/bin:/usr/bin"'.
          "\n\n");
        return;
      } else {
        self::write(" okay  'php' is CLI SAPI.\n");
      }
    }

    $root = dirname(phutil_get_library_root('phabricator'));

    // On RHEL6, doing a distro install of pcntl makes it available from the
    // CLI binary but not from the Apache module. This isn't entirely
    // unreasonable and we don't need it from Apache, so do an explicit test
    // for CLI availability.
    list($err, $stdout, $stderr) = exec_manual(
      'php %s',
      "{$root}/scripts/setup/pcntl_available.php");
    if ($err) {
      self::writeFailure();
      self::write("Unable to execute scripts/setup/pcntl_available.php to ".
                  "test for the availability of pcntl from the CLI.\n".
                  "   err: {$err}\n".
                  "stdout: {$stdout}\n".
                  "stderr: {$stderr}\n");
      return;
    } else {
      if (trim($stdout) == 'YES') {
        self::write(" okay  pcntl is available from the command line.\n");
        self::write("[OKAY] All extensions OKAY\n");
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
    self::write("[OKAY] All submodules OKAY.\n");

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
      $host_uri = new PhutilURI($host);
      $protocol = $host_uri->getProtocol();
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
        self::write(" okay  phabricator.base-uri protocol\n");
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

      $host_domain = $host_uri->getDomain();
      if (strpos($host_domain, '.') !== false) {
        self::write(" okay  phabricator.base-uri domain\n");
      } else {
        self::writeFailure();
        self::write(
          "You must host Phabricator on a domain that contains a dot ('.'). ".
          "The current domain, '{$host_domain}', does not have a dot, so some ".
          "browsers will not set cookies on it. For instance, ".
          "'http://example.com/ is OK, but 'http://example/' won't work. ".
          "If you are using localhost, create an entry in the hosts file like ".
          "'127.0.0.1 example.com', and access the localhost with ".
          "'http://example.com/'.");
        return;
      }

      $host_path = $host_uri->getPath();
      if ($host_path == '/') {
        self::write(" okay  phabricator.base-uri path\n");
      } else {
        self::writeFailure();
        self::write(
          "Your 'phabricator.base-uri' setting includes a path, but should ".
          "not (e.g., 'http://phabricator.example.com/' is OK, but ".
          "'http://example.com/phabricator/' is not). Phabricator must be ".
          "installed on an entire domain, it can not be installed on a ".
          "path alongside other applications. Consult the documentation ".
          "for more details.");
        return;
      }
    }

    $timezone = nonempty(
      PhabricatorEnv::getEnvConfig('phabricator.timezone'),
      ini_get('date.timezone'));
    if (!$timezone) {
      self::writeFailure();
      self::write(
        "Setup failure! Your configuration fails to specify a server ".
        "timezone. Either set 'date.timezone' in your php.ini or ".
        "'phabricator.timezone' in your Phabricator configuration. See the ".
        "PHP documentation for a list of supported timezones:\n\n".
        "http://www.php.net/manual/en/timezones.php\n");
      return;
    } else {
      self::write(" okay  Timezone '{$timezone}' configured.\n");
    }

    self::write("[OKAY] Basic configuration OKAY\n");


    $issue_gd_warning = false;
    self::writeHeader('GD LIBRARY');
    if (extension_loaded('gd')) {
      self::write(" okay  Extension 'gd' is loaded.\n");
      $image_type_map = array(
        'imagepng'  => 'PNG',
        'imagegif'  => 'GIF',
        'imagejpeg' => 'JPEG',
      );
      foreach ($image_type_map as $function => $image_type) {
        if (function_exists($function)) {
          self::write(" okay  Support for '{$image_type}' is available.\n");
        } else {
          self::write(" warn  Support for '{$image_type}' is not available!\n");
          $issue_gd_warning = true;
        }
      }
    } else {
      self::write(" warn  Extension 'gd' is not loaded.\n");
      $issue_gd_warning = true;
    }

    if ($issue_gd_warning) {
      self::write(
        "[WARN] The 'gd' library is missing or lacks full support. ".
        "Phabricator will not be able to generate image thumbnails without ".
        "gd.\n");
    } else {
      self::write("[OKAY] 'gd' loaded and has full image type support.\n");
    }


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

    self::writeHeader("MySQL DATABASE & STORAGE CONFIGURATION");

    $conf = PhabricatorEnv::newObjectFromConfig('mysql.configuration-provider');
    $conn_user = $conf->getUser();
    $conn_pass = $conf->getPassword();
    $conn_host = $conf->getHost();

    $timeout = ini_get('mysql.connect_timeout');
    if ($timeout > 5) {
      self::writeNote(
        "Your MySQL connect timeout is very high ({$timeout} seconds). ".
        "Consider reducing it to 5 or below by setting ".
        "'mysql.connect_timeout' in your php.ini.");
    }

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

    $index_min_length = queryfx_one(
      $conn_raw,
      'SHOW VARIABLES LIKE %s',
      'ft_min_word_len');
    $index_min_length = idx($index_min_length, 'Value', 4);
    if ($index_min_length >= 4) {
      self::writeNote(
        "MySQL is configured with a 'ft_min_word_len' of 4 or greater, which ".
        "means you will not be able to search for 3-letter terms. Consider ".
        "setting this in your configuration:\n".
        "\n".
        "    [mysqld]\n".
        "        ft_min_word_len=3\n".
        "\n".
        "Then optionally run:\n".
        "\n".
        "    REPAIR TABLE {$namespace}_search.search_documentfield QUICK;\n".
        "\n".
        "...to reindex existing documents.");
    }

    $max_allowed_packet = queryfx_one(
      $conn_raw,
      'SHOW VARIABLES LIKE %s',
      'max_allowed_packet');
    $max_allowed_packet = idx($max_allowed_packet, 'Value', PHP_INT_MAX);

    $recommended_minimum = 1024 * 1024;
    if ($max_allowed_packet < $recommended_minimum) {
      self::writeNote(
        "MySQL is configured with a small 'max_allowed_packet' ".
        "('{$max_allowed_packet}'), which may cause some large writes to ".
        "fail. Consider raising this to at least {$recommended_minimum}.");
    } else {
      self::write(" okay  max_allowed_packet = {$max_allowed_packet}.\n");
    }

    $local_key = 'storage.local-disk.path';
    $local_path = PhabricatorEnv::getEnvConfig($local_key);
    if ($local_path) {
      if (!Filesystem::pathExists($local_path) ||
          !is_readable($local_path) ||
          !is_writable($local_path)) {
        self::writeFailure();
        self::write(
          "Setup failure! You have configured local disk storage but the ".
          "path you specified ('{$local_path}') does not exist or is not ".
          "readable or writable.\n");
        if ($open_basedir) {
          self::write(
            "You have an 'open_basedir' setting -- make sure Phabricator is ".
            "allowed to open files in the local storage directory.\n");
        }
        return;
      } else {
        self::write(" okay  Local disk storage exists and is writable.\n");
      }
    } else {
      self::write(" skip  Not configured for local disk storage.\n");
    }

    $selector = PhabricatorEnv::getEnvConfig('storage.engine-selector');

    try {
      $storage_selector_exists = class_exists($selector);
    } catch (Exception $ex) {
      $storage_selector_exists = false;
    }

    if ($storage_selector_exists) {
      self::write(" okay  Using '{$selector}' as a storage engine selector.\n");
    } else {
      self::writeFailure();
      self::write(
        "Setup failure! You have configured '{$selector}' as a storage engine ".
        "selector but it does not exist or could not be loaded.\n");
      return;
    }

    self::write("[OKAY] Database and storage configuration OKAY\n");


    self::writeHeader("OUTBOUND EMAIL CONFIGURATION");

    $have_adapter = false;
    $is_ses = false;

    $adapter = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
    switch ($adapter) {
      case 'PhabricatorMailImplementationPHPMailerLiteAdapter':

        $have_adapter = true;

        if (!Filesystem::pathExists('/usr/bin/sendmail') &&
          !Filesystem::pathExists('/usr/sbin/sendmail')) {
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

    self::writeHeader('CONFIG CLASSES');
    foreach (PhabricatorEnv::getRequiredClasses() as $key => $instanceof) {
      $config = PhabricatorEnv::getEnvConfig($key);
      if (!$config) {
        self::writeNote("'$key' is not set.");
      } else {
        try {
          $r = new ReflectionClass($config);
          if (!$r->isSubclassOf($instanceof)) {
            throw new Exception(
              "Config setting '$key' must be an instance of '$instanceof'.");
          } else if (!$r->isInstantiable()) {
            throw new Exception("Config setting '$key' must be instantiable.");
          }
        } catch (Exception $ex) {
          self::writeFailure();
          self::write("Setup failure! ".$ex->getMessage());
          return;
        }
      }
    }
    self::write("[OKAY] Config classes OKAY\n");

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
    usleep(20000);
  }

  private static function writeNote($note) {
    $note = "*** NOTE: ".wordwrap($note, 75, "\n", true);
    $note = "\n".str_replace("\n", "\n          ", $note)."\n\n";
    self::write($note);
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
