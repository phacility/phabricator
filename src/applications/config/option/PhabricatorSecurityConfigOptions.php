<?php

final class PhabricatorSecurityConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Security');
  }

  public function getDescription() {
    return pht('Security options.');
  }

  public function getIcon() {
    return 'fa-lock';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $doc_href = PhabricatorEnv::getDoclink('Configuring a File Domain');
    $doc_name = pht('Configuration Guide: Configuring a File Domain');

    $default_address_blacklist = array(
      // This is all of the IANA special/reserved blocks in IPv4 space.
      '0.0.0.0/8',
      '10.0.0.0/8',
      '100.64.0.0/10',
      '127.0.0.0/8',
      '169.254.0.0/16',
      '172.16.0.0/12',
      '192.0.0.0/24',
      '192.0.2.0/24',
      '192.88.99.0/24',
      '192.168.0.0/16',
      '198.18.0.0/15',
      '198.51.100.0/24',
      '203.0.113.0/24',
      '224.0.0.0/4',
      '240.0.0.0/4',
      '255.255.255.255/32',

      // And these are the IANA special/reserved blocks in IPv6 space.
      '::/128',
      '::1/128',
      '::ffff:0:0/96',
      '100::/64',
      '64:ff9b::/96',
      '2001::/32',
      '2001:10::/28',
      '2001:20::/28',
      '2001:db8::/32',
      '2002::/16',
      'fc00::/7',
      'fe80::/10',
      'ff00::/8',
    );

    $keyring_type = 'custom:PhabricatorKeyringConfigOptionType';
    $keyring_description = $this->deformat(pht(<<<EOTEXT
The keyring stores master encryption keys. For help with configuring a keyring
and encryption, see **[[ %s | Configuring Encryption ]]**.
EOTEXT
      ,
      PhabricatorEnv::getDoclink('Configuring Encryption')));

    $require_mfa_description = $this->deformat(pht(<<<EOTEXT
By default, this software allows users to add multi-factor authentication to
their accounts, but does not require it. By enabling this option, you can
force all users to add at least one authentication factor before they can use
their accounts.

Administrators can query a list of users who do not have MFA configured in
{nav People}:

  - **[[ %s | %s ]]**
EOTEXT
      ,
      '/people/?mfa=false',
      pht('List of Users Without MFA')));

    return array(
      $this->newOption('security.alternate-file-domain', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('Alternate domain to serve files from.'))
        ->setDescription(
          pht(
            'By default, this software serves files from the same domain '.
            'the application is served from. This is convenient, but '.
            'presents a security risk.'.
            "\n\n".
            'You should configure a CDN or alternate file domain to mitigate '.
            'this risk. Configuring a CDN will also improve performance. See '.
            '[[ %s | %s ]] for instructions.',
            $doc_href,
            $doc_name))
        ->addExample('https://files.phabcdn.net/', pht('Valid Setting')),
      $this->newOption(
        'security.hmac-key',
        'string',
        '[D\t~Y7eNmnQGJ;rnH6aF;m2!vJ8@v8C=Cs:aQS\.Qw')
        ->setHidden(true)
        ->setSummary(
          pht('Key for HMAC digests.'))
        ->setDescription(
          pht(
            'Default key for HMAC digests where the key is not important '.
            '(i.e., the hash itself is secret). You can change this if you '.
            'want (to any other string), but doing so will break existing '.
            'sessions and CSRF tokens. This option is deprecated. Newer '.
            'code automatically manages HMAC keys.')),
      $this->newOption('security.require-https', 'bool', false)
        ->setLocked(true)
        ->setSummary(
          pht('Force users to connect via HTTPS instead of HTTP.'))
        ->setDescription(
          pht(
            "If the web server responds to both HTTP and HTTPS requests but ".
            "you want users to connect with only HTTPS, you can set this ".
            "to `true` to make this service redirect HTTP requests to HTTPS.".
            "\n\n".
            "Normally, you should just configure your server not to accept ".
            "HTTP traffic, but this setting may be useful if you originally ".
            "used HTTP and have now switched to HTTPS but don't want to ".
            "break old links, or if your webserver sits behind a load ".
            "balancer which terminates HTTPS connections and you can not ".
            "reasonably configure more granular behavior there.".
            "\n\n".
            "IMPORTANT: A request is identified as HTTP or HTTPS by examining ".
            "the PHP `%s` variable. If you run Apache/mod_php this will ".
            "probably be set correctly for you automatically, but if you run ".
            "as CGI/FCGI (e.g., through nginx or lighttpd), you need to ".
            "configure your web server so that it passes the value correctly ".
            "based on the connection type.".
            "\n\n".
            "If you configure clustering, note that this ".
            "setting is ignored by intracluster requests.",
            "\$_SERVER['HTTPS']"))
        ->setBoolOptions(
          array(
            pht('Force HTTPS'),
            pht('Allow HTTP'),
          )),
      $this->newOption('security.require-multi-factor-auth', 'bool', false)
        ->setLocked(true)
        ->setSummary(
          pht('Require all users to configure multi-factor authentication.'))
        ->setDescription($require_mfa_description)
        ->setBoolOptions(
          array(
            pht('Multi-Factor Required'),
            pht('Multi-Factor Optional'),
          )),
       $this->newOption(
        'uri.allowed-protocols',
        'set',
        array(
          'http' => true,
          'https' => true,
          'mailto' => true,
        ))
        ->setSummary(
          pht(
            'Determines which URI protocols are valid for links and '.
            'redirects.'))
        ->setDescription(
          pht(
            'When users write comments which have URIs, they will be '.
            'automatically turned into clickable links if the URI protocol '.
            'appears in this set.'.
            "\n\n".
            'This set of allowed protocols is primarily intended to prevent '.
            'security issues with "javascript:" and other potentially '.
            'dangerous URI handlers.'.
            "\n\n".
            'This set is also used to enforce valid redirect URIs. '.
            'This service will refuse to issue a HTTP "Location" redirect '.
            'to a URI with a protocol not on this set.'.
            "\n\n".
            'Usually, "http" and "https" should be present in this set. If '.
            'you remove one or both protocols, some features which rely on '.
            'links or redirects may not work.'))
        ->addExample("http\nhttps", pht('Valid Setting'))
        ->setLocked(true),
      $this->newOption(
        'uri.allowed-editor-protocols',
        'set',
        array(
          'http' => true,
          'https' => true,

          // This handler is installed by Textmate.
          'txmt' => true,

          // This handler is for MacVim.
          'mvim' => true,

          // Unofficial handler for Vim.
          'vim' => true,

          // Unofficial handler for Sublime.
          'subl' => true,

          // Unofficial handler for Emacs.
          'emacs' => true,

          // This isn't a standard handler installed by an application, but
          // is a reasonable name for a user-installed handler.
          'editor' => true,

          // This handler is for Visual Studio Code.
          'vscode' => true,

          // This is for IntelliJ IDEA.
          'idea' => true,
        ))
        ->setSummary(pht('Whitelists editor protocols for "Open in Editor".'))
        ->setDescription(
          pht(
            'Users can configure a URI pattern to open files in a text '.
            'editor. The URI must use a protocol on this whitelist.'))
        ->setLocked(true),
       $this->newOption('remarkup.enable-embedded-youtube', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Embed YouTube videos'),
            pht("Don't embed YouTube videos"),
          ))
        ->setSummary(
          pht('Determines whether or not YouTube videos get embedded.'))
        ->setDescription(
          pht(
            "If you enable this, linked YouTube videos will be embedded ".
            "inline. This has mild security implications (you'll leak ".
            "referrers to YouTube) and is pretty silly (but sort of ".
            "awesome).")),
        $this->newOption(
          'security.outbound-blacklist',
          'list<string>',
          $default_address_blacklist)
          ->setLocked(true)
          ->setSummary(
            pht(
              'Blacklist subnets to prevent user-initiated outbound '.
              'requests.'))
          ->setDescription(
            pht(
              'Users can make requests to other services from '.
              'service hosts in some circumstances (for example, by '.
              'creating a repository with a remote URL).'.
              "\n\n".
              'This may represent a security vulnerability if services on '.
              'the same subnet will accept commands or reveal private '.
              'information over unauthenticated HTTP GET, based on the source '.
              'IP address. In particular, all hosts in EC2 have access to '.
              'such a service.'.
              "\n\n".
              'This option defines a list of netblocks which requests will '.
              'never be issued to. Generally, you should list all '.
              'private IP space here.'))
          ->addExample(array('0.0.0.0/0'), pht('No Outbound Requests')),
        $this->newOption('security.strict-transport-security', 'bool', false)
          ->setLocked(true)
          ->setBoolOptions(
            array(
              pht('Use HSTS'),
              pht('Do Not Use HSTS'),
            ))
          ->setSummary(pht('Enable HTTP Strict Transport Security (HSTS).'))
          ->setDescription(
            pht(
              'HTTP Strict Transport Security (HSTS) sends a header which '.
              'instructs browsers that the site should only be accessed '.
              'over HTTPS, never HTTP. This defuses an attack where an '.
              'adversary gains access to your network, then proxies requests '.
              'through an unsecured link.'.
              "\n\n".
              'Do not enable this option if you serve (or plan to ever serve) '.
              'unsecured content over plain HTTP. It is very difficult to '.
              'undo this change once users\' browsers have accepted the '.
              'setting.')),
        $this->newOption('keyring', $keyring_type, array())
          ->setHidden(true)
          ->setSummary(pht('Configure master encryption keys.'))
          ->setDescription($keyring_description),
    );
  }

  protected function didValidateOption(
    PhabricatorConfigOption $option,
    $value) {

    $key = $option->getKey();
    if ($key == 'security.alternate-file-domain') {

      $uri = new PhutilURI($value);
      $protocol = $uri->getProtocol();
      if ($protocol !== 'http' && $protocol !== 'https') {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must start with ".
            "'%s' or '%s'.",
            $key,
            'http://',
            'https://'));
      }

      $domain = $uri->getDomain();
      if (strpos($domain, '.') === false) {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must contain a dot ('.'), ".
            "like '%s', not just a bare name like '%s'. ".
            "Some web browsers will not set cookies on domains with no TLD.",
            $key,
            'http://example.com/',
            'http://example/'));
      }

      $path = $uri->getPath();
      if ($path !== '' && $path !== '/') {
        throw new PhabricatorConfigValidationException(
          pht(
            "Config option '%s' is invalid. The URI must NOT have a path, ".
            "e.g. '%s' is OK, but '%s' is not. This software must be ".
            "installed on an entire domain; it can not be installed on a path.",
            $key,
            'http://devtools.example.com/',
            'http://example.com/devtools/'));
      }
    }
  }


}
