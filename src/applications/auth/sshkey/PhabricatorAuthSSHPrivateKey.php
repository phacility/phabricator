<?php

/**
 * Data structure representing a raw private key.
 */
final class PhabricatorAuthSSHPrivateKey extends Phobject {

  private $body;
  private $passphrase;

  private function __construct() {
    // <internal>
  }

  public function setPassphrase(PhutilOpaqueEnvelope $passphrase) {
    $this->passphrase = $passphrase;
    return $this;
  }

  public function getPassphrase() {
    return $this->passphrase;
  }

  public static function newFromRawKey(PhutilOpaqueEnvelope $entire_key) {
    $key = new self();

    $key->body = $entire_key;

    return $key;
  }

  public function getKeyBody() {
    return $this->body;
  }

  public function newBarePrivateKey() {
    if (!Filesystem::binaryExists('ssh-keygen')) {
      throw new Exception(
        pht(
          'Analyzing or decrypting SSH keys requires the "ssh-keygen" binary, '.
          'but it is not available in "$PATH". Make it available to work with '.
          'SSH private keys.'));
    }

    $old_body = $this->body;

    // Some versions of "ssh-keygen" are sensitive to trailing whitespace for
    // some keys. Trim any trailing whitespace and replace it with a single
    // newline.
    $raw_body = $old_body->openEnvelope();
    $raw_body = rtrim($raw_body)."\n";
    $old_body = new PhutilOpaqueEnvelope($raw_body);

    $tmp = $this->newTemporaryPrivateKeyFile($old_body);

    // See T13454 for discussion of why this is so awkward. In broad strokes,
    // we don't have a straightforward way to distinguish between keys with an
    // invalid format and keys with a passphrase which we don't know.

    // First, try to extract the public key from the file using the (possibly
    // empty) passphrase we were given. If everything is in good shape, this
    // should work.

    $passphrase = $this->getPassphrase();
    if ($passphrase) {
      list($err, $stdout, $stderr) = exec_manual(
        'ssh-keygen -y -P %P -f %R',
        $passphrase,
        $tmp);
    } else {
      list($err, $stdout, $stderr) = exec_manual(
        'ssh-keygen -y -P %s -f %R',
        '',
        $tmp);
    }

    // If that worked, the key is good and the (possibly empty) passphrase is
    // correct. Strip the passphrase if we have one, then return the bare key.

    if (!$err) {
      if ($passphrase) {
        execx(
          'ssh-keygen -p -P %P -N %s -f %R',
          $passphrase,
          '',
          $tmp);

        $new_body = new PhutilOpaqueEnvelope(Filesystem::readFile($tmp));
        unset($tmp);
      } else {
        $new_body = $old_body;
      }

      return self::newFromRawKey($new_body);
    }

    // We were not able to extract the public key. Try to figure out why. The
    // reasons we expect are:
    //
    //   - We were given a passphrase, but the key has no passphrase.
    //   - We were given a passphrase, but the passphrase is wrong.
    //   - We were not given a passphrase, but the key has a passphrase.
    //   - The key format is invalid.
    //
    // Our ability to separate these cases varies a lot, particularly because
    // some versions of "ssh-keygen" return very similar diagnostic messages
    // for any error condition. Try our best.

    if ($passphrase) {
      // First, test for "we were given a passphrase, but the key has no
      // passphrase", since this is a conclusive test.
      list($err) = exec_manual(
        'ssh-keygen -y -P %s -f %R',
        '',
        $tmp);
      if (!$err) {
        throw new PhabricatorAuthSSHPrivateKeySurplusPassphraseException(
          pht(
            'A passphrase was provided for this private key, but it does '.
            'not require a passphrase. Check that you supplied the correct '.
            'key, or omit the passphrase.'));
      }
    }

    // We're out of conclusive tests, so try to guess why the error occurred.
    // In some versions of "ssh-keygen", we get a usable diagnostic message. In
    // other versions, not so much.

    $reason_format = 'format';
    $reason_passphrase = 'passphrase';
    $reason_unknown = 'unknown';

    $patterns = array(
      // macOS 10.14.6
      '/incorrect passphrase supplied to decrypt private key/'
        => $reason_passphrase,

      // macOS 10.14.6
      '/invalid format/' => $reason_format,

      // Ubuntu 14
      '/load failed/' => $reason_unknown,
    );

    $reason = 'unknown';
    foreach ($patterns as $pattern => $pattern_reason) {
      $ok = preg_match($pattern, $stderr);

      if ($ok === false) {
        throw new Exception(
          pht(
            'Pattern "%s" is not valid.',
            $pattern));
      }

      if ($ok) {
        $reason = $pattern_reason;
        break;
      }
    }

    if ($reason === $reason_format) {
      throw new PhabricatorAuthSSHPrivateKeyFormatException(
        pht(
          'This private key is not formatted correctly. Check that you '.
          'have provided the complete text of a valid private key.'));
    }

    if ($reason === $reason_passphrase) {
      if ($passphrase) {
        throw new PhabricatorAuthSSHPrivateKeyIncorrectPassphraseException(
          pht(
            'This private key requires a passphrase, but the wrong '.
            'passphrase was provided. Check that you supplied the correct '.
            'key and passphrase.'));
      } else {
        throw new PhabricatorAuthSSHPrivateKeyIncorrectPassphraseException(
          pht(
            'This private key requires a passphrase, but no passphrase was '.
            'provided. Check that you supplied the correct key, or provide '.
            'the passphrase.'));
      }
    }

    if ($passphrase) {
      throw new PhabricatorAuthSSHPrivateKeyUnknownException(
        pht(
          'This private key could not be opened with the provided passphrase. '.
          'This might mean that the passphrase is wrong or that the key is '.
          'not formatted correctly. Check that you have supplied the '.
          'complete text of a valid private key and the correct passphrase.'));
    } else {
      throw new PhabricatorAuthSSHPrivateKeyUnknownException(
        pht(
          'This private key could not be opened. This might mean that the '.
          'key requires a passphrase, or might mean that the key is not '.
          'formatted correctly. Check that you have supplied the complete '.
          'text of a valid private key and the correct passphrase.'));
    }
  }

  private function newTemporaryPrivateKeyFile(PhutilOpaqueEnvelope $key_body) {
    $tmp = new TempFile();

    Filesystem::writeFile($tmp, $key_body->openEnvelope());

    return $tmp;
  }

}
