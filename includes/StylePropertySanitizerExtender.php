<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace MediaWiki\Extension\TemplateStylesExtender;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\CustomPropertyMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\UnorderedGroup;
use Wikimedia\CSS\Objects\CSSObject;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

class StylePropertySanitizerExtender extends StylePropertySanitizer {
	private static $extendedCssBorderBackground = false;
	private static $extendedCssSizingAdditions = false;

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

		$this->cache[__METHOD__] = array_merge(
			parent::getSizingAdditions( $matcherFactory ),
			[
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

	/** @inheritDoc */
	protected function doSanitize( CSSObject $object ): CSSObject {
		if ( !method_exists( $object, 'getName' ) || !str_starts_with( $object->getName(), '--' ) ) {
			return parent::doSanitize( $object );
		}

		$this->clearSanitizationErrors();
		return $object;
	}
}
