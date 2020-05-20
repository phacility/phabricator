<?php

/**
 * Implement this interface to mark an object as capable of producing a
 * PhutilSafeHTML representation. This is primarily useful for building
 * renderable HTML views.
 */
interface PhutilSafeHTMLProducerInterface {

  public function producePhutilSafeHTML();

}
