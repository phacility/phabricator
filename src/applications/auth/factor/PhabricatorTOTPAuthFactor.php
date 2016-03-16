<?php

final class PhabricatorTOTPAuthFactor extends PhabricatorAuthFactor {

  public function getFactorKey() {
    return 'totp';
  }

  public function getFactorName() {
    return pht('Mobile Phone App (TOTP)');
  }

  public function getFactorDescription() {
    return pht(
      'Attach a mobile authenticator application (like Authy '.
      'or Google Authenticator) to your account. When you need to '.
      'authenticate, you will enter a code shown on your phone.');
  }

  public function processAddFactorForm(
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user) {

    $totp_token_type = PhabricatorAuthTOTPKeyTemporaryTokenType::TOKENTYPE;

    $key = $request->getStr('totpkey');
    if (strlen($key)) {
      // If the user is providing a key, make sure it's a key we generated.
      // This raises the barrier to theoretical attacks where an attacker might
      // provide a known key (such attacks are already prevented by CSRF, but
      // this is a second barrier to overcome).

      // (We store and verify the hash of the key, not the key itself, to limit
      // how useful the data in the table is to an attacker.)

      $temporary_token = id(new PhabricatorAuthTemporaryTokenQuery())
        ->setViewer($user)
        ->withTokenResources(array($user->getPHID()))
        ->withTokenTypes(array($totp_token_type))
        ->withExpired(false)
        ->withTokenCodes(array(PhabricatorHash::digest($key)))
        ->executeOne();
      if (!$temporary_token) {
        // If we don't have a matching token, regenerate the key below.
        $key = null;
      }
    }

    if (!strlen($key)) {
      $key = self::generateNewTOTPKey();

      // Mark this key as one we generated, so the user is allowed to submit
      // a response for it.

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        id(new PhabricatorAuthTemporaryToken())
          ->setTokenResource($user->getPHID())
          ->setTokenType($totp_token_type)
          ->setTokenExpires(time() + phutil_units('1 hour in seconds'))
          ->setTokenCode(PhabricatorHash::digest($key))
          ->save();
      unset($unguarded);
    }

    $code = $request->getStr('totpcode');

    $e_code = true;
    if ($request->getExists('totp')) {
      $okay = self::verifyTOTPCode(
        $user,
        new PhutilOpaqueEnvelope($key),
        $code);

      if ($okay) {
        $config = $this->newConfigForUser($user)
          ->setFactorName(pht('Mobile App (TOTP)'))
          ->setFactorSecret($key);

        return $config;
      } else {
        if (!strlen($code)) {
          $e_code = pht('Required');
        } else {
          $e_code = pht('Invalid');
        }
      }
    }

    $form->addHiddenInput('totp', true);
    $form->addHiddenInput('totpkey', $key);

    $form->appendRemarkupInstructions(
      pht(
        'First, download an authenticator application on your phone. Two '.
        'applications which work well are **Authy** and **Google '.
        'Authenticator**, but any other TOTP application should also work.'));

    $form->appendInstructions(
      pht(
        'Launch the application on your phone, and add a new entry for '.
        'this Phabricator install. When prompted, scan the QR code or '.
        'manually enter the key shown below into the application.'));

    $prod_uri = new PhutilURI(PhabricatorEnv::getProductionURI('/'));
    $issuer = $prod_uri->getDomain();

    $uri = urisprintf(
      'otpauth://totp/%s:%s?secret=%s&issuer=%s',
      $issuer,
      $user->getUsername(),
      $key,
      $issuer);

    $qrcode = $this->renderQRCode($uri);
    $form->appendChild($qrcode);

    $form->appendChild(
      id(new AphrontFormStaticControl())
        ->setLabel(pht('Key'))
        ->setValue(phutil_tag('strong', array(), $key)));

    $form->appendInstructions(
      pht(
        '(If given an option, select that this key is "Time Based", not '.
        '"Counter Based".)'));

    $form->appendInstructions(
      pht(
        'After entering the key, the application should display a numeric '.
        'code. Enter that code below to confirm that you have configured '.
        'the authenticator correctly:'));

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel(pht('TOTP Code'))
        ->setName('totpcode')
        ->setValue($code)
        ->setError($e_code));

  }

  public function renderValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    AphrontFormView $form,
    PhabricatorUser $viewer,
    $validation_result) {

    if (!$validation_result) {
      $validation_result = array();
    }

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setName($this->getParameterName($config, 'totpcode'))
        ->setLabel(pht('App Code'))
        ->setCaption(pht('Factor Name: %s', $config->getFactorName()))
        ->setValue(idx($validation_result, 'value'))
        ->setError(idx($validation_result, 'error', true)));
  }

  public function processValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request) {

    $code = $request->getStr($this->getParameterName($config, 'totpcode'));
    $key = new PhutilOpaqueEnvelope($config->getFactorSecret());

    if (self::verifyTOTPCode($viewer, $key, $code)) {
      return array(
        'error' => null,
        'value' => $code,
        'valid' => true,
      );
    } else {
      return array(
        'error' => strlen($code) ? pht('Invalid') : pht('Required'),
        'value' => $code,
        'valid' => false,
      );
    }
  }


  public static function generateNewTOTPKey() {
    return strtoupper(Filesystem::readRandomCharacters(16));
  }

  public static function verifyTOTPCode(
    PhabricatorUser $user,
    PhutilOpaqueEnvelope $key,
    $code) {

    $now = (int)(time() / 30);

    // Allow the user to enter a code a few minutes away on either side, in
    // case the server or client has some clock skew.
    for ($offset = -2; $offset <= 2; $offset++) {
      $real = self::getTOTPCode($key, $now + $offset);
      if (phutil_hashes_are_identical($real, $code)) {
        return true;
      }
    }

    // TODO: After validating a code, this should mark it as used and prevent
    // it from being reused.

    return false;
  }


  public static function base32Decode($buf) {
    $buf = strtoupper($buf);

    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = str_split($map);
    $map = array_flip($map);

    $out = '';
    $len = strlen($buf);
    $acc = 0;
    $bits = 0;
    for ($ii = 0; $ii < $len; $ii++) {
      $chr = $buf[$ii];
      $val = $map[$chr];

      $acc = $acc << 5;
      $acc = $acc + $val;

      $bits += 5;
      if ($bits >= 8) {
        $bits = $bits - 8;
        $out .= chr(($acc & (0xFF << $bits)) >> $bits);
      }
    }

    return $out;
  }

  public static function getTOTPCode(PhutilOpaqueEnvelope $key, $timestamp) {
    $binary_timestamp = pack('N*', 0).pack('N*', $timestamp);
    $binary_key = self::base32Decode($key->openEnvelope());

    $hash = hash_hmac('sha1', $binary_timestamp, $binary_key, true);

    // See RFC 4226.

    $offset = ord($hash[19]) & 0x0F;

    $code = ((ord($hash[$offset + 0]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
            ((ord($hash[$offset + 3])       )      );

    $code = ($code % 1000000);
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);

    return $code;
  }


  /**
   * @phutil-external-symbol class QRcode
   */
  private function renderQRCode($uri) {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/phpqrcode/phpqrcode.php';

    $lines = QRcode::text($uri);

    $total_width = 240;
    $cell_size = floor($total_width / count($lines));

    $rows = array();
    foreach ($lines as $line) {
      $cells = array();
      for ($ii = 0; $ii < strlen($line); $ii++) {
        if ($line[$ii] == '1') {
          $color = '#000';
        } else {
          $color = '#fff';
        }

        $cells[] = phutil_tag(
          'td',
          array(
            'width' => $cell_size,
            'height' => $cell_size,
            'style' => 'background: '.$color,
          ),
          '');
      }
      $rows[] = phutil_tag('tr', array(), $cells);
    }

    return phutil_tag(
      'table',
      array(
        'style' => 'margin: 24px auto;',
      ),
      $rows);
  }

}
