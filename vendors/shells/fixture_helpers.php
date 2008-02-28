<?php

/**
* Fixture helpers
*/
class FixtureHelpers
{
  
  public function now()
  {
    return date('Y-m-d H:i:s');
  }
  
  public function lastMonth()
  {
    return date('Y-m-d H:i:s', mktime(date('G'), date('i'), date('s'), date('n')-1, date('j'), date('Y')));
  }
  
  function uuid()
  {
    return String::uuid();
  }
  
	public function lorem()
	{
		return "Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Maecenas quis justo. Cras purus lectus, rhoncus lacinia, ornare in, porttitor non, enim. Donec ac eros a pede semper porta. Nunc vel nibh. Praesent dignissim tellus facilisis ante. Suspendisse porttitor interdum nulla. Maecenas ligula. Sed nec ante. Ut tincidunt purus bibendum pede. Sed ullamcorper euismod justo. Phasellus euismod molestie odio. Pellentesque tristique pede et nisl. Phasellus lacus nunc, accumsan eu, vehicula eu, laoreet vel, tortor. Nam et pede eget lorem dapibus rutrum. Vivamus et orci. In adipiscing. Sed pulvinar pharetra lorem. Ut ullamcorper leo.";
	}
}
