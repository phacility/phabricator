<?php

/**
 * Thrown by storage engines to indicate an configuration error which should
 * abort the storage attempt, as opposed to a transient storage error which
 * should be retried on other engines.
 */
final class PhabricatorFileStorageConfigurationException extends Exception {}
