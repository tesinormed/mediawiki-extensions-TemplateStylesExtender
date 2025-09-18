<?php

namespace MediaWiki\Extension\TemplateStylesExtender;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\CustomPropertyMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\UnorderedGroup;
use Wikimedia\CSS\Objects\CSSObject;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

class StylePropertySanitizerExtender extends StylePropertySanitizer {
	private static $extendedCssBorderBackground = false;
	private static $extendedCssSizingAdditions = false;
	private static $extendedCssTransforms = false;
	private static $extendedCssTransitions = false;

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

		$boxKeywords = new KeywordMatcher( $this->backgroundTypes( $matcherFactory )['boxKeywords'] );
		$props['background-origin'] = $boxKeywords;
		$props['background-clip'] = new Alternative( [
			$boxKeywords,
			new KeywordMatcher( [
				'text',
				'border-area',
			] )
		] );

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

		$this->cache[__METHOD__] = array_merge(
			parent::getSizingAdditions( $matcherFactory ),
			[
				new KeywordMatcher( 'fit-content' ),
				new FunctionMatcher( 'clamp', Quantifier::hash( new Alternative( [
					$matcherFactory->length(),
					$matcherFactory->lengthPercentage(),
					$matcherFactory->frequency(),
					$matcherFactory->angle(),
					$matcherFactory->anglePercentage(),
					$matcherFactory->time(),
					$matcherFactory->number(),
					$matcherFactory->integer(),
				] ), 3, 3 ) )
			]
		);

		self::$extendedCssSizingAdditions = true;
		return $this->cache[__METHOD__];
	}

	/**
	 * @inheritDoc
	 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/transform
	 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/transform-style
	 */
	protected function cssTransforms1( MatcherFactory $matcherFactory ) {
		if ( self::$extendedCssTransforms && isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}

		$props = parent::cssTransforms1( $matcherFactory );

		$a = $matcherFactory->angle();
		$az = new Alternative( [
			$matcherFactory->zero(),
			$a,
		] );
		$n = $matcherFactory->number();
		$l = $matcherFactory->length();
		$lp = $matcherFactory->lengthPercentage();

		$props['transform'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::plus( new Alternative( [
				new FunctionMatcher( 'matrix', Quantifier::hash( $n, 6, 6 ) ),
				new FunctionMatcher( 'matrix3d', Quantifier::hash( $n, 16, 16 ) ),
				new FunctionMatcher( 'perspective', $l ),
				new FunctionMatcher( 'rotate', $az ),
				new FunctionMatcher( 'rotate3d', new Juxtaposition( [ $n, $n, $n, $az ], commas: true ) ),
				new FunctionMatcher( 'rotateX', $az ),
				new FunctionMatcher( 'rotateY', $az ),
				new FunctionMatcher( 'rotateZ', $az ),
				new FunctionMatcher( 'scale', Quantifier::hash( $n, 1, 2 ) ),
				new FunctionMatcher( 'scale3d', Quantifier::hash( $n, 3, 3 ) ),
				new FunctionMatcher( 'scaleX', $n ),
				new FunctionMatcher( 'scaleY', $n ),
				new FunctionMatcher( 'scaleZ', $n ),
				new FunctionMatcher( 'skew', Quantifier::hash( $az, 1, 2 ) ),
				new FunctionMatcher( 'skewX', $az ),
				new FunctionMatcher( 'skewY', $az ),
				new FunctionMatcher( 'translate', Quantifier::hash( $lp, 1, 2 ) ),
				new FunctionMatcher( 'translate3d', new Juxtaposition( [ $lp, $lp, $l ], commas: true ) ),
				new FunctionMatcher( 'translateX', $lp ),
				new FunctionMatcher( 'translateY', $lp ),
				new FunctionMatcher( 'translateZ', $lp ),
			] ) )
		] );
		$props['transform-style'] = new KeywordMatcher( [
			'flat',
			'preserve-3d'
		] );

		$this->cache[__METHOD__] = $props;
		self::$extendedCssTransforms = true;

		return $props;
	}

	/**
	 * @inheritDoc
	 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/transition-behavior
	 */
	protected function cssTransitions( MatcherFactory $matcherFactory ) {
		if ( self::$extendedCssTransitions && isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}

		$props = parent::cssTransitions( $matcherFactory );

		$props['transition-behavior'] = new KeywordMatcher( [
			'normal',
			'allow-discrete'
		] );

		$this->cache[__METHOD__] = $props;
		self::$extendedCssTransitions = true;

		return $props;
	}

	/** @inheritDoc */
	protected function doSanitize( CSSObject $object ) {
		if ( !method_exists( $object, 'getName' ) || !str_starts_with( $object->getName(), '--' ) ) {
			return parent::doSanitize( $object );
		}

		$this->clearSanitizationErrors();
		return $object;
	}
}
