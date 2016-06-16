<?php

/**
 * At-rest encryption format using AES256 CBC.
 */
final class PhabricatorFileAES256StorageFormat
  extends PhabricatorFileStorageFormat {

  const FORMATKEY = 'aes-256-cbc';

  private $keyName;

  public function getStorageFormatName() {
    return pht('Encrypted (AES-256-CBC)');
  }

  public function canGenerateNewKeyMaterial() {
    return true;
  }

  public function generateNewKeyMaterial() {
    $envelope = self::newAES256Key();
    $material = $envelope->openEnvelope();
    return base64_encode($material);
  }

  public function canCycleMasterKey() {
    return true;
  }

  public function cycleStorageProperties() {
    $file = $this->getFile();
    list($key, $iv) = $this->extractKeyAndIV($file);
    return $this->formatStorageProperties($key, $iv);
  }

  public function newReadIterator($raw_iterator) {
    $file = $this->getFile();
    $data = $file->loadDataFromIterator($raw_iterator);

    list($key, $iv) = $this->extractKeyAndIV($file);

    $data = $this->decryptData($data, $key, $iv);

    return array($data);
  }

  public function newWriteIterator($raw_iterator) {
    $file = $this->getFile();
    $data = $file->loadDataFromIterator($raw_iterator);

    list($key, $iv) = $this->extractKeyAndIV($file);

    $data = $this->encryptData($data, $key, $iv);

    return array($data);
  }

  public function newStorageProperties() {
    // Generate a unique key and IV for this block of data.
    $key_envelope = self::newAES256Key();
    $iv_envelope = self::newAES256IV();

    return $this->formatStorageProperties($key_envelope, $iv_envelope);
  }

  private function formatStorageProperties(
    PhutilOpaqueEnvelope $key_envelope,
    PhutilOpaqueEnvelope $iv_envelope) {

    // Encode the raw binary data with base64 so we can wrap it in JSON.
    $data = array(
      'iv.base64' => base64_encode($iv_envelope->openEnvelope()),
      'key.base64' => base64_encode($key_envelope->openEnvelope()),
    );

    // Encode the base64 data with JSON.
    $data_clear = phutil_json_encode($data);

    // Encrypt the block key with the master key, using a unique IV.
    $data_iv = self::newAES256IV();
    $key_name = $this->getMasterKeyName();
    $master_key = $this->getMasterKeyMaterial($key_name);
    $data_cipher = $this->encryptData($data_clear, $master_key, $data_iv);

    return array(
      'key.name' => $key_name,
      'iv.base64' => base64_encode($data_iv->openEnvelope()),
      'payload.base64' => base64_encode($data_cipher),
    );
  }

  private function extractKeyAndIV(PhabricatorFile $file) {
    $outer_iv = $file->getStorageProperty('iv.base64');
    $outer_iv = base64_decode($outer_iv);
    $outer_iv = new PhutilOpaqueEnvelope($outer_iv);

    $outer_payload = $file->getStorageProperty('payload.base64');
    $outer_payload = base64_decode($outer_payload);

    $outer_key_name = $file->getStorageProperty('key.name');
    $outer_key = $this->getMasterKeyMaterial($outer_key_name);

    $payload = $this->decryptData($outer_payload, $outer_key, $outer_iv);
    $payload = phutil_json_decode($payload);

    $inner_iv = $payload['iv.base64'];
    $inner_iv = base64_decode($inner_iv);
    $inner_iv = new PhutilOpaqueEnvelope($inner_iv);

    $inner_key = $payload['key.base64'];
    $inner_key = base64_decode($inner_key);
    $inner_key = new PhutilOpaqueEnvelope($inner_key);

    return array($inner_key, $inner_iv);
  }

  private function encryptData(
    $data,
    PhutilOpaqueEnvelope $key,
    PhutilOpaqueEnvelope $iv) {

    $method = 'aes-256-cbc';
    $key = $key->openEnvelope();
    $iv = $iv->openEnvelope();

    $result = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
    if ($result === false) {
      throw new Exception(
        pht(
          'Failed to openssl_encrypt() data: %s',
          openssl_error_string()));
    }

    return $result;
  }

  private function decryptData(
    $data,
    PhutilOpaqueEnvelope $key,
    PhutilOpaqueEnvelope $iv) {

    $method = 'aes-256-cbc';
    $key = $key->openEnvelope();
    $iv = $iv->openEnvelope();

    $result = openssl_decrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
    if ($result === false) {
      throw new Exception(
        pht(
          'Failed to openssl_decrypt() data: %s',
          openssl_error_string()));
    }

    return $result;
  }

  public static function newAES256Key() {
    // Unsurprisingly, AES256 uses a 256 bit key.
    $key = Filesystem::readRandomBytes(phutil_units('256 bits in bytes'));
    return new PhutilOpaqueEnvelope($key);
  }

  public static function newAES256IV() {
    // AES256 uses a 256 bit key, but the initialization vector length is
    // only 128 bits.
    $iv = Filesystem::readRandomBytes(phutil_units('128 bits in bytes'));
    return new PhutilOpaqueEnvelope($iv);
  }

  public function selectMasterKey($key_name) {
    // Require that the key exist on the key ring.
    $this->getMasterKeyMaterial($key_name);

    $this->keyName = $key_name;
    return $this;
  }

  private function getMasterKeyName() {
    if ($this->keyName !== null) {
      return $this->keyName;
    }

    $default = PhabricatorKeyring::getDefaultKeyName(self::FORMATKEY);
    if ($default !== null) {
      return $default;
    }

    throw new Exception(
      pht(
        'No AES256 key is specified in the keyring as a default encryption '.
        'key, and no encryption key has been explicitly selected.'));
  }

  private function getMasterKeyMaterial($key_name) {
    return PhabricatorKeyring::getKey($key_name, self::FORMATKEY);
  }

}
