<?php
/**
 * Language abstraction.
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 */
abstract class DF_Language {
	protected $language_constants;

	public function getLanguageString($key) {
		return $this->language_constants[$key];
	}
}
?>