<?php


/**
 * All email event bodies that may contain information that is dangerous for secure bugs must
 * implement this interface. Common examples of potential "leaky" information are comments, inline code hunks,
 * titles, bug names, etc.
 */
interface PublicEmailBody {

}