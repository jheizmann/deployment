<?php
/**
 * @author: Kai Kühn / ontoprise / 2011
 *
 * derived from
 * MediaWiki page data importer
 * Copyright (C) 2003,2005 Brion Vibber <brion@pobox.com>
 * http://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 *
 * Checks if an ontology can be properly imported and provides means to merge
 * it with existing versions.
 *
 *
 */

/**
 * @file
 * @ingroup DFIO
 *
 * The ontology merger component does 2 things:
 *
 *  (1) Transforms an wikitext so that all elements referenced in annotations
 *  get prefixes to distinguish them from others.
 *
 *  (2) Merges annotations in wiki text.
 *
 *  (3) Merges rules
 *
 * @author Kai Kühn / ontoprise / 2011
 *
 */
class OntologyMerger {

	private $prefix;

	private static $PROPERTY_LINK_PATTERN;

	private static  $CATEGORY_LINK_PATTERN;

	/**
	 * Creates a ontology merger object.
	 *
	 * @param array of string $objectProperties
	 *             All binary properties
	 *
	 * @param hash array $naryProperties
	 *             Array of nary properties pointing to a tuple of types
	 *             Example: 'Has domain and range'=> array('Type:Page','Type:Page')
	 *
	 * @param array of string
	 *             $fixProperties which do not get modified with any prefixes.
	 */
	public function __construct($objectProperties = array(), $naryProperties = array(), $fixProperties = array()) {

		$this->objectProperties = $objectProperties;
		$this->naryProperties = $naryProperties;
		self::$PROPERTY_LINK_PATTERN = '/\[\[                 # Beginning of the link
                                    (?:([^:][^]]*):[=:])+ # Property name (or a list of those)
                                    ([^\[\]]*)            # content: anything but [, |, ]
                                    \]\]                  # End of link
                                    /xu';

		global $dfgLang;
		self::$CATEGORY_LINK_PATTERN = '/\[\[                 # Beginning of the link
                                    (?:\s*'.$dfgLang->getLanguageString('category').'\s*:)+  # Property name (or a list of those)
                                    ([^\[\]]*)            # content: anything but [, |, ]
                                    \]\]                  # End of link
                                    /ixu';

		$this->fixProperties = $fixProperties;
	}

	/**
	 * Transforms ontology elements from wikitext $text using the given $prefix.
	 *
	 * @param string $text
	 * @return string
	 */
	public function transformOntologyElements($prefix, $text) {
		$this->prefix = $prefix;
		$t = $this->modifyCategoryAnnotations($text);
		$t = $this->modifyPropertyAnnotations($t);
		return $t;
	}
	
	/**
	 * Transforms IRIs in rules
	 * 
	 * @param string $prefix
	 * @param array of (name, ruletag) $rules
	 * 
	 * @return array of (name, ruletag) 
	 */
	public function transformRulesElements($prefix, $rules) {
		$results = array();
		$this->prefix = $prefix;
		$iri_pattern = '/<([^>]+)>/';
		foreach($rules as $r) {
			list($name, $ruletag) = $r;
			$ruletag = preg_replace_callback( $iri_pattern, array( $this, 'simpleParseIRICallback' ), $ruletag );
			$results[] = array($name, $ruletag);
		}
		return $results;
	}
	
	

	/**
	 * Removes all rules from wikitext $text
	 *
	 * @param $text
	 */
	public function stripRules($text) {
	   $ruleTagPattern = '/<rule(.*?>)(.*?.)<\/rule>/ixus';
		preg_match_all($ruleTagPattern, trim($text), $matches);
		foreach($matches[0] as $m) {
			$text = str_replace($m, "", $text);
		}
		return $text;
	}

	/**
	 * Extract all rules from wikitext $text.
	 * @param $text
	 *
	 * @return array of string
	 */
	public function extractRules($text) {
		$rules = array();
		$ruleTagPattern = '/<rule(.*?>)(.*?.)<\/rule>/ixus';
		preg_match_all($ruleTagPattern, trim($text), $matches);
		$i=0;
		for($i = 0; $i < count($matches[0]); $i++) {
			$header = trim($matches[1][$i]);
			$ruletext = trim($matches[2][$i]);

			// parse header parameters
			$ruleparamterPattern = "/([^=]+)=\"([^\"]*)\"/ixus";
			preg_match_all($ruleparamterPattern, $header, $matchesheader);
		    $name = NULL;
			for ($j = 0; $j < count($matchesheader[0]); $j++) {
				if (trim($matchesheader[1][$j]) == 'name') {
					$name = $matchesheader[2][$j];
				}
			}
			
			$rules[] = array($name, $matches[0][$i]);
		}
		return $rules;
	}

	/**
	 * Removes all annotation from wikitext $text
	 *
	 * @param $text
	 * @return string
	 */
	public function stripAnnotations($text) {
		$text = preg_replace(self::$PROPERTY_LINK_PATTERN, "", $text);
		$text = preg_replace(self::$CATEGORY_LINK_PATTERN, "", $text);
		return $text;
	}

	/**
	 * Extract all annotations from wikitext $text.
	 * @param $text
	 *
	 * @return array
	 */
	public function extractAnnotations($text) {
		$propertyMatches = array();
		$categoryMatches = array();
		preg_match_all(self::$PROPERTY_LINK_PATTERN, $text, $propertyMatches);
		preg_match_all(self::$CATEGORY_LINK_PATTERN, $text, $categoryMatches);
		return array_merge($propertyMatches[0], $categoryMatches[0]);
	}

	private function modifyCategoryAnnotations($text) {
		return preg_replace_callback( self::$CATEGORY_LINK_PATTERN, array( $this, 'simpleParseCategoriesCallback' ), $text );
	}

	private function modifyPropertyAnnotations($text) {
		return preg_replace_callback( self::$PROPERTY_LINK_PATTERN, array( $this, 'simpleParsePropertiesCallback' ), $text );
	}


	/**
	 * This callback function inserts the prefix.
	 * Could be replaced by a lambda-function but then it is restricted to PHP 5.3.x
	 */
	public function simpleParseCategoriesCallback( $categoryLink ) {
		$value = '';
		$caption = false;

		if ( array_key_exists( 1, $categoryLink ) ) {
			$parts = explode( '|', $categoryLink[1] );
			if ( array_key_exists( 0, $parts ) ) {
				$value = trim($parts[0]);
			}
			if ( array_key_exists( 1, $parts ) ) {
				$caption = trim($parts[1]);
			}
		}
		$prefix = $this->prefix;
		global $dfgLang;

		$category = $dfgLang->getLanguageString('category');
		if ( $caption !== false ) {
			return  "[[$category:$prefix$value|$caption]]";
		} else {
			return  "[[$category:$prefix$value]]";
		}
	}


	/**
	 * This callback function inserts the prefix.
	 * Could be replaced by a lambda-function but then it is restricted to PHP 5.3.x
	 */
	public function simpleParsePropertiesCallback( $semanticLink ) {
		$value = '';
		$caption = false;

		if ( array_key_exists( 2, $semanticLink ) ) {
			$parts = explode( '|', $semanticLink[2] );
			if ( array_key_exists( 0, $parts ) ) {
				$value = $parts[0];
			}
			if ( array_key_exists( 1, $parts ) ) {
				$caption = $parts[1];
			}
		}
		$prefix = $this->prefix;
		$property = trim($semanticLink[1]);
		$orig_property = $property;
		if (!in_array($property, $this->fixProperties)) {
			$property = $prefix.$property;
		}

		if (in_array($orig_property, $this->objectProperties)) {
			$value = $this->attachPrefix($value);
			if ( $caption !== false ) {
				return  "[[$property::$value|$caption]]";
			} else {
				return  "[[$property::$value]]";
			}
		} else if (array_key_exists($orig_property, $this->naryProperties)) {
			$values = explode(";", $value);
			$types = $this->naryProperties[$property];
			for($i = 0; $i < count($values); $i++) {
				if (isset($types[$i]) && ($types[$i] == "Type:Page" || is_null($types[$i]))) {
					$values[$i] = $this->attachPrefix($values[$i]);
				}
			}
			$value = implode("; ", $values);
			if ( $caption !== false ) {
				return  "[[$property::$value|$caption]]";
			} else {
				return  "[[$property::$value]]";
			}
		} else {
			if ( $caption !== false ) {
				return  "[[$property::$value|$caption]]";
			} else {
				return  "[[$property::$value]]";
			}
		}
	}
	
	/**
     * This callback function inserts the prefix into an IRI.
     * Could be replaced by a lambda-function but then it is restricted to PHP 5.3.x
     */
    public function simpleParseIRICallback($iri) {
        if (strpos($iri[0], 'http://$$_graph_$$/') === false) return $iri[0];
        $index = strpos($iri[1], "/", strlen('http://$$_graph_$$/')+1);
        $localname = substr($iri[1], $index+1);
        $ns = substr($iri[1], 0, $index+1);
        return "<".$ns.$this->prefix.$localname.">";
    } 

	private function attachPrefix($titlestring) {
		$title = Title::newFromText(trim($titlestring));
		$nsText = $title->getNamespace() !== NS_MAIN ? $title->getNsText().":" : "";
		return $nsText.$this->prefix.$title->getText();
	}

}