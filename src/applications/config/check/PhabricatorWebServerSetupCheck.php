<?php

final class PhabricatorWebServerSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    // The documentation says these headers exist, but it's not clear if they
    // are entirely reliable in practice.
    if (isset($_SERVER['HTTP_X_MOD_PAGESPEED']) ||
        isset($_SERVER['HTTP_X_PAGE_SPEED'])) {
      $this->newIssue('webserver.pagespeed')
        ->setName(pht('Disable Pagespeed'))
        ->setSummary(pht('Pagespeed is enabled, but should be disabled.'))
        ->setMessage(
          pht(
            'This server received an "X-Mod-Pagespeed" or "X-Page-Speed" '.
            'HTTP header on this request, which indicates that you have '.
            'enabled "mod_pagespeed" on this server. This module is not '.
            'compatible with this software. You should disable the module.'));
    }

    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    if ($base_uri === null || !strlen($base_uri)) {
      // If `phabricator.base-uri` is not set then we can't really do
      // anything.
      return;
    }

    $expect_user = 'alincoln';
    $expect_pass = 'hunter2';

    $send_path = '/test-%252A/';
    $expect_path = '/test-%2A/';

    $expect_key = 'duck-sound';
    $expect_value = 'quack';

    $base_uri = id(new PhutilURI($base_uri))
      ->setPath($send_path)
      ->replaceQueryParam($expect_key, $expect_value);

    $self_future = id(new HTTPSFuture($base_uri))
      ->addHeader('X-Setup-SelfCheck', 1)
      ->addHeader('Accept-Encoding', 'gzip')
      ->setDisableContentDecoding(true)
      ->setHTTPBasicAuthCredentials(
        $expect_user,
        new PhutilOpaqueEnvelope($expect_pass))
      ->setTimeout(5);

    if (AphrontRequestStream::supportsGzip()) {
      $gzip_uncompressed = str_repeat('Quack! ', 128);
      $gzip_compressed = gzencode($gzip_uncompressed);

      $gzip_future = id(new HTTPSFuture($base_uri))
        ->addHeader('X-Setup-SelfCheck', 1)
        ->addHeader('Content-Encoding', 'gzip')
        ->setTimeout(5)
        ->setData($gzip_compressed);

    } else {
      $gzip_future = null;
    }

    // Make a request to the metadata service available on EC2 instances,
    // to test if we're running on a T2 instance in AWS so we can warn that
    // this is a bad idea. Outside of AWS, this request will just fail.
    $ec2_uri = 'http://169.254.169.254/latest/meta-data/instance-type';
    $ec2_future = id(new HTTPSFuture($ec2_uri))
      ->setTimeout(1);

    $futures = array(
      $self_future,
      $ec2_future,
    );

    if ($gzip_future) {
      $futures[] = $gzip_future;
    }

    $futures = new FutureIterator($futures);
    foreach ($futures as $future) {
      // Just resolve the futures here.
    }

    try {
      list($body) = $ec2_future->resolvex();
      $body = trim($body);
      if (preg_match('/^t2/', $body)) {
        $message = pht(
          'This software appears to be installed on a very small EC2 instance '.
          '(of class "%s") with burstable CPU. This is strongly discouraged. '.
          'This software regularly needs CPU, and these instances are often '.
          'choked to death by CPU throttling. Use an instance with a normal '.
          'CPU instead.',
          $body);

        $this->newIssue('ec2.burstable')
          ->setName(pht('Installed on Burstable CPU Instance'))
          ->setSummary(
            pht(
              'Do not install this software on an instance class with '.
              'burstable CPU.'))
          ->setMessage($message);
      }
    } catch (Exception $ex) {
      // If this fails, just continue. We're probably not running in EC2.
    }

    try {
      list($body, $headers) = $self_future->resolvex();
    } catch (Exception $ex) {
      // If this fails for whatever reason, just ignore it. Hopefully, the
      // error is obvious and the user can correct it on their own, but we
      // can't do much to offer diagnostic advice.
      return;
    }

    if (BaseHTTPFuture::getHeader($headers, 'Content-Encoding') != 'gzip') {
      $message = pht(
        'This software sent itself a request with "Accept-Encoding: gzip", '.
        'but received an uncompressed response.'.
        "\n\n".
        'This may indicate that your webserver is not configured to '.
        'compress responses. If so, you should enable compression. '.
        'Compression can dramatically improve performance, especially '.
        'for clients with less bandwidth.');

      $this->newIssue('webserver.gzip')
        ->setName(pht('GZip Compression May Not Be Enabled'))
        ->setSummary(pht('Your webserver may have compression disabled.'))
        ->setMessage($message);
    } else {
      if (function_exists('gzdecode')) {
        $body = @gzdecode($body);
      } else {
        $body = null;
      }
      if (!$body) {
        // For now, just bail if we can't decode the response.
        // This might need to use the stronger magic in "AphrontRequestStream"
        // to decode more reliably.
        return;
      }
    }

    $structure = null;
    $extra_whitespace = ($body !== trim($body));

    try {
      $structure = phutil_json_decode(trim($body));
    } catch (Exception $ex) {
      // Ignore the exception, we only care if the decode worked or not.
    }

    if (!$structure || $extra_whitespace) {
      if (!$structure) {
        $short = id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(1024)
          ->truncateString($body);

        $message = pht(
          'This software sent itself a test request with the '.
          '"X-Setup-SelfCheck" header and expected to get a valid JSON '.
          'response back. Instead, the response begins:'.
          "\n\n".
          '%s'.
          "\n\n".
          'Something is misconfigured or otherwise mangling responses.',
          phutil_tag('pre', array(), $short));
      } else {
        $message = pht(
          'This software sent itself a test request and expected to get a '.
          'bare JSON response back. It received a JSON response, but the '.
          'response had extra whitespace at the beginning or end.'.
          "\n\n".
          'This usually means you have edited a file and left whitespace '.
          'characters before the opening %s tag, or after a closing %s tag. '.
          'Remove any leading whitespace, and prefer to omit closing tags.',
          phutil_tag('tt', array(), '<?php'),
          phutil_tag('tt', array(), '?>'));
      }

      $this->newIssue('webserver.mangle')
        ->setName(pht('Mangled Webserver Response'))
        ->setSummary(pht('Your webserver produced an unexpected response.'))
        ->setMessage($message);

      // We can't run the other checks if we could not decode the response.
      if (!$structure) {
        return;
      }
    }

    $actual_user = idx($structure, 'user');
    $actual_pass = idx($structure, 'pass');
    if (($expect_user != $actual_user) || ($actual_pass != $expect_pass)) {
      $message = pht(
        'This software sent itself a test request with an "Authorization" '.
        'HTTP header, and expected those credentials to be transmitted. '.
        'However, they were absent or incorrect when received. This '.
        'software sent username "%s" with password "%s"; received '.
        'username "%s" and password "%s".'.
        "\n\n".
        'Your webserver may not be configured to forward HTTP basic '.
        'authentication. If you plan to use basic authentication (for '.
        'example, to access repositories) you should reconfigure it.',
        $expect_user,
        $expect_pass,
        $actual_user,
        $actual_pass);

      $this->newIssue('webserver.basic-auth')
        ->setName(pht('HTTP Basic Auth Not Configured'))
        ->setSummary(pht('Your webserver is not forwarding credentials.'))
        ->setMessage($message);
    }

    $actual_path = idx($structure, 'path');
    if ($expect_path != $actual_path) {
      $message = pht(
        'This software sent itself a test request with an unusual path, to '.
        'test if your webserver is rewriting paths correctly. The path was '.
        'not transmitted correctly.'.
        "\n\n".
        'This software sent a request to path "%s", and expected the '.
        'webserver to decode and rewrite that path so that it received a '.
        'request for "%s". However, it received a request for "%s" instead.'.
        "\n\n".
        'Verify that your rewrite rules are configured correctly, following '.
        'the instructions in the documentation. If path encoding is not '.
        'working properly you will be unable to access files with unusual '.
        'names in repositories, among other issues.'.
        "\n\n".
        '(This problem can be caused by a missing "B" in your RewriteRule.)',
        $send_path,
        $expect_path,
        $actual_path);

      $this->newIssue('webserver.rewrites')
        ->setName(pht('HTTP Path Rewriting Incorrect'))
        ->setSummary(pht('Your webserver is rewriting paths improperly.'))
        ->setMessage($message);
    }

    $actual_key = pht('<none>');
    $actual_value = pht('<none>');
    foreach (idx($structure, 'params', array()) as $pair) {
      if (idx($pair, 'name') == $expect_key) {
        $actual_key = idx($pair, 'name');
        $actual_value = idx($pair, 'value');
        break;
      }
    }

    if (($expect_key !== $actual_key) || ($expect_value !== $actual_value)) {
      $message = pht(
        'This software sent itself a test request with an HTTP GET parameter, '.
        'but the parameter was not transmitted. Sent "%s" with value "%s", '.
        'got "%s" with value "%s".'.
        "\n\n".
        'Your webserver is configured incorrectly and large parts of '.
        'this software will not work until this issue is corrected.'.
        "\n\n".
        '(This problem can be caused by a missing "QSA" in your RewriteRule.)',
        $expect_key,
        $expect_value,
        $actual_key,
        $actual_value);

      $this->newIssue('webserver.parameters')
        ->setName(pht('HTTP Parameters Not Transmitting'))
        ->setSummary(
          pht('Your webserver is not handling GET parameters properly.'))
        ->setMessage($message);
    }

    if ($gzip_future) {
      $this->checkGzipResponse(
        $gzip_future,
        $gzip_uncompressed,
        $gzip_compressed);
    }
  }

  private function checkGzipResponse(
    Future $future,
    $uncompressed,
    $compressed) {

    try {
      list($body, $headers) = $future->resolvex();
    } catch (Exception $ex) {
      return;
    }

    try {
      $structure = phutil_json_decode(trim($body));
    } catch (Exception $ex) {
      return;
    }

    $raw_body = idx($structure, 'raw.base64');
    $raw_body = @base64_decode($raw_body);

    // The server received the exact compressed bytes we expected it to, so
    // everything is working great.
    if ($raw_body === $compressed) {
      return;
    }

    // If the server received a prefix of the raw uncompressed string, it
    // is almost certainly configured to decompress responses inline. Guide
    // users to this problem narrowly.

    // Otherwise, something is wrong but we don't have much of a clue what.

    $message = array();
    $message[] = pht(
      'This software sent itself a test request that was compressed with '.
      '"Content-Encoding: gzip", but received different bytes than it '.
      'sent.');

    $prefix_len = min(strlen($raw_body), strlen($uncompressed));
    if ($prefix_len > 16 && !strncmp($raw_body, $uncompressed, $prefix_len)) {
      $message[] = pht(
        'The request body that the server received had already been '.
        'decompressed. This strongly suggests your webserver is configured '.
        'to decompress requests inline, before they reach PHP.');
      $message[] = pht(
        'If you are using Apache, your server may be configured with '.
        '"SetInputFilter DEFLATE". This directive destructively mangles '.
        'requests and emits them with "Content-Length" and '.
        '"Content-Encoding" headers that no longer match the data in the '.
        'request body.');
    } else {
      $message[] = pht(
        'This suggests your webserver is configured to decompress or mangle '.
        'compressed requests.');

      $message[] = pht(
        'The request body that was sent began:');
      $message[] = $this->snipBytes($compressed);

      $message[] = pht(
        'The request body that was received began:');
      $message[] = $this->snipBytes($raw_body);
    }

    $message[] = pht(
      'Identify the component in your webserver configuration which is '.
      'decompressing or mangling requests and disable it. This software '.
      'will not work properly until you do.');

    $message = phutil_implode_html("\n\n", $message);

    $this->newIssue('webserver.accept-gzip')
      ->setName(pht('Compressed Requests Not Received Properly'))
      ->setSummary(
        pht(
          'Your webserver is not handling compressed request bodies '.
          'properly.'))
      ->setMessage($message);
  }

  private function snipBytes($raw) {
    if (!strlen($raw)) {
      $display = pht('<empty>');
    } else {
      $snip = substr($raw, 0, 24);
      $display = phutil_loggable_string($snip);

      if (strlen($snip) < strlen($raw)) {
        $display .= '...';
      }
    }

    return phutil_tag('tt', array(), $display);
  }

}
