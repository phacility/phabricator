<?php

/**
 * This is just a test table that unit tests can use if they need to test
 * generic database operations. It won't change and break tests and stuff, and
 * mistakes in test construction or isolation won't impact the application in
 * any way.
 */
final class HarbormasterScratchTable extends HarbormasterDAO {

  protected $data;

}
