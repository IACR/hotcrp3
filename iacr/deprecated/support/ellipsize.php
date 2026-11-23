<?php
/**
 * From https://gist.github.com/themattharris/292167
 * ellipsize()
 * ellipsizes a string using english language rules
 *
 * @param string $text The original text string
 * @param int $maxlen The maximum length of the ellipsized string (this includes the ellipsized characters)
 * @param string $ellip The string to use for ellipsizing
 * @param bool $towords Whether to ellipsize to word boundaries or not
 */
function ellipsize($text, $maxlen, $ellip='...', $towords=TRUE) {
  // trim whitespace
  $text = trim($text);

  // do nothing if we're shorter than maxlen
  if (strlen($text) <= $maxlen) {
    return $text;
  }

  // if maxlen is less than the ellip symbol, make maxlen = length of ellip
  $maxlen = strlen($ellip) > $maxlen ? strlen($ellip) : $maxlen;

  // we're longer than maxlen. First thing we do is shorten to maxlen - $ellip
  $_text = substr($text, 0, $maxlen - strlen($ellip));

  if ($towords) {
    $_text = strrev($_text);
    // if we're matching to complete words we look for the last instance of a
    // sentence terminator
    $pattern = '/\s/';
    $count = preg_match($pattern, $_text, $matches);
    $_text = strrev(substr($_text, strpos($_text, $matches[0])));
    return $_text . $ellip;
  } else {
    // Rules
    // 1. If the last char in a shortened string is inside a just append the ellipsis, e.g. hello => hel...
    // 2. If the last char in a shortened string is a space, append the ellipsis after the space e.g. hello how => hello ...
    // 3. If the last char in a shortended string is the last letter in a word, remove an extra character from the string e.g. hello => hell...

    // we decide what to do here based on the last character of the shortened message
    // and the character which FOLLOWS the last character in the shortened message
    $last_char = $text[$maxlen - strlen($ellip)-1];
    $switch_char = $text[$maxlen - strlen($ellip)];

    // last character is a space or both are non-terminating characters
    $terminators = array('.',',','!','?','%');
    if (($last_char == ' ') || ( ! in_array($last_char, $terminators) && ! in_array($switch_char, $terminators) && $switch_char != ' '))
      return $_text . $ellip;
    elseif ( ! in_array($last_char, $terminators) && (in_array($switch_char, $terminators) || $switch_char == ' '))
      return substr($_text, 0, strlen($_text)-1) . $ellip;
    elseif ( ! in_array($last_char, $terminators) && ! in_array($switch_char, $terminators) && $switch_char != ' ')
      return $_text . $ellip;
    else {
      // we get here if the last char and switch char are both terminators. e.g. '. '
      // in this situation we run ourselves again but with the characters trimmed off
      return ellipsize($text, $maxlen-1, $ellip, $towords);
    }
  }
}
?>