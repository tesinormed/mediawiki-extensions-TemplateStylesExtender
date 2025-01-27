<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace MediaWiki\Extension\TemplateStylesExtender;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\BlockMatcher;
use Wikimedia\CSS\Grammar\CustomPropertyMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Grammar\UnorderedGroup;
use Wikimedia\CSS\Objects\CSSObject;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

class StylePropertySanitizerExtender extends StylePropertySanitizer {
	private static $extendedCssBorderBackground = false;
	private static $extendedCssSizingAdditions = false;
	private static $extendedCss1Grid = false;

	/** @inheritDoc */
	public function __construct( MatcherFactory $matcherFactory ) {
		parent::__construct( new MatcherFactoryExtender() );
	}

	/**
	 * @inheritDoc
	 * @see https://phabricator.wikimedia.org/T265675
	 */
	protected function cssBorderBackground3( MatcherFactory $matcherFactory ) {
		if ( self::$extendedCssBorderBackground && isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}

		$props = parent::cssBorderBackground3( $matcherFactory );

		$props['border'] = UnorderedGroup::someOf( [
			new KeywordMatcher( [
				'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'
			] ),
			new Alternative( [
				new KeywordMatcher( [ 'thin', 'medium', 'thick' ] ), $matcherFactory->length(),
			] ),
			new Alternative( [
				$matcherFactory->color(),
				new FunctionMatcher( 'var', new CustomPropertyMatcher() ),
			] )
		] );

		$props['box-shadow'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::hash( UnorderedGroup::allOf( [
				Quantifier::optional( new KeywordMatcher( 'inset' ) ),
				Quantifier::count( $matcherFactory->length(), 2, 4 ),
				Quantifier::optional( new Alternative( [
					$matcherFactory->color(),
					new FunctionMatcher( 'var', new CustomPropertyMatcher() ),
				] ) ),
			] ) )
		] );

		$this->cache[__METHOD__] = $props;
		self::$extendedCssBorderBackground = true;

		return $props;
	}

	/**
	 * @inheritDoc
	 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/clamp
	 */
	protected function getSizingAdditions( MatcherFactory $matcherFactory ) {
		if ( self::$extendedCssSizingAdditions && isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}

		$props = parent::getSizingAdditions( $matcherFactory );

		$props[] = new FunctionMatcher( 'clamp', Quantifier::hash( new Alternative( [
			$matcherFactory->length(),
			$matcherFactory->lengthPercentage(),
			$matcherFactory->frequency(),
			$matcherFactory->angle(),
			$matcherFactory->anglePercentage(),
			$matcherFactory->time(),
			$matcherFactory->number(),
			$matcherFactory->integer(),
		] ), 3, 3 ) );

		$this->cache[__METHOD__] = $props;

		self::$extendedCssSizingAdditions = true;

		return $this->cache[__METHOD__];
	}

	/**
	 * @inheritDoc
	 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/var
	 */
	protected function cssGrid1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( self::$extendedCss1Grid && isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$var = new FunctionMatcher( 'var', new CustomPropertyMatcher() );

		$props = parent::cssGrid1( $matcherFactory );
		$comma = $matcherFactory->comma();
		$customIdent = $matcherFactory->customIdent( [ 'span' ] );
		$lineNamesO = Quantifier::optional( new BlockMatcher(
			Token::T_LEFT_BRACKET, Quantifier::star( $customIdent )
		) );
		$trackBreadth = new Alternative( [
			$matcherFactory->lengthPercentage(),
			new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) {
				return $t->value() >= 0 && !strcasecmp( $t->unit(), 'fr' );
			} ),
			new KeywordMatcher( [ 'min-content', 'max-content', 'auto' ] ),
			$var
		] );
		$inflexibleBreadth = new Alternative( [
			$matcherFactory->lengthPercentage(),
			new KeywordMatcher( [ 'min-content', 'max-content', 'auto' ] ),
			$var
		] );
		$fixedBreadth = $matcherFactory->lengthPercentage();
		$trackSize = new Alternative( [
			$trackBreadth,
			new FunctionMatcher( 'minmax',
				new Juxtaposition( [ $inflexibleBreadth, $trackBreadth ], true )
			),
			new FunctionMatcher( 'fit-content', $matcherFactory->lengthPercentage() ),
			$var
		] );
		$fixedSize = new Alternative( [
			$fixedBreadth,
			new FunctionMatcher( 'minmax', new Juxtaposition( [ $fixedBreadth, $trackBreadth ], true ) ),
			new FunctionMatcher( 'minmax',
				new Juxtaposition( [ $inflexibleBreadth, $fixedBreadth ], true )
			),
			$var
		] );
		$trackRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			new Alternative( [ $matcherFactory->integer(), $var ] ),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $trackSize ] ) ),
			$lineNamesO
		] ) );
		$autoRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			new Alternative( [ new KeywordMatcher( [ 'auto-fill', 'auto-fit' ] ), $var ] ),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $fixedSize ] ) ),
			$lineNamesO
		] ) );
		$fixedRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			$matcherFactory->integer(),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $fixedSize ] ) ),
			$lineNamesO
		] ) );
		$trackList = new Juxtaposition( [
			Quantifier::plus( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $trackSize, $trackRepeat ] )
			] ) ),
			$lineNamesO
		] );
		$autoTrackList = new Juxtaposition( [
			Quantifier::star( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $fixedSize, $fixedRepeat ] )
			] ) ),
			$lineNamesO,
			$autoRepeat,
			Quantifier::star( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $fixedSize, $fixedRepeat ] )
			] ) ),
			$lineNamesO,
		] );

		$props['grid-template-columns'] = new Alternative( [
			new KeywordMatcher( 'none' ), $trackList, $autoTrackList
		] );
		$props['grid-template-rows'] = $props['grid-template-columns'];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/** @inheritDoc */
	protected function doSanitize( CSSObject $object ): CSSObject {
		if ( method_exists( $object, 'getName' ) && !str_starts_with( $object->getName(), '--' ) ) {
			return parent::doSanitize( $object );
		}

		$this->clearSanitizationErrors();
		return $object;
	}
}
