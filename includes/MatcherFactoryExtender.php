<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace MediaWiki\Extension\TemplateStylesExtender;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\CustomPropertyMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Objects\Token;

class MatcherFactoryExtender extends MatcherFactory {
	/** @inheritDoc */
	public function colorHex(): TokenMatcher {
		return new TokenMatcher( Token::T_HASH, static function ( Token $t ) {
			return preg_match( '/^([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $t->value() );
		} );
	}

	/** @inheritDoc */
	protected function colorFuncs() {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$var = new FunctionMatcher( 'var', new CustomPropertyMatcher() );
			$i = new Alternative( [ $var, $this->integer() ] );
			$n = new Alternative( [ $var, $this->number() ] );
			$p = new Alternative( [ $var, $this->percentage() ] );
			$this->cache[__METHOD__] = [
				new FunctionMatcher( 'rgb', new Alternative( [
					Quantifier::hash( $i, 3, 3 ),
					Quantifier::hash( $p, 3, 3 ),
					Quantifier::hash( $var, 1, 3 ),
				] ) ),
				new FunctionMatcher( 'rgba', new Alternative( [
					new Juxtaposition( [ $i, $i, $i, $n ], true ),
					new Juxtaposition( [ $p, $p, $p, $n ], true ),
					Quantifier::hash( $var, 1, 4 ),
					new Juxtaposition( [ Quantifier::hash( $var, 1, 3 ), $n ], true ),
				] ) ),
				new FunctionMatcher( 'hsl', new Alternative( [
					new Juxtaposition( [ $n, $p, $p ], true ),
					Quantifier::hash( $var, 1, 3 ),
				] ) ),
				new FunctionMatcher( 'hsla', new Alternative( [
					new Juxtaposition( [ $n, $p, $p, $n ], true ),
					Quantifier::hash( $var, 1, 4 ),
				] ) ),
			];
		}
		return $this->cache[__METHOD__];
	}
}
