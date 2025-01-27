<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\TemplateStylesExtender;

use Config;
use InvalidArgumentException;
use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\CustomPropertyMatcher;
use Wikimedia\CSS\Grammar\DelimMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\WhitespaceMatcher;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

class TemplateStylesExtender {
	/**
	 * Adds a css wide keyword matcher for css variables
	 * Matches 0-INF preceding css declarations at least one var( --content ) and 0-INF following declarations
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 * @param MatcherFactory $factory
	 */
	public function addVarSelector( StylePropertySanitizer $propertySanitizer, MatcherFactory $factory ): void {
		$anyProperty = new Alternative( [
			$factory->color(),
			$factory->image(),
			$factory->length(),
			$factory->integer(),
			$factory->percentage(),
			$factory->number(),
			$factory->angle(),
			$factory->frequency(),
			$factory->resolution(),
			$factory->position(),
			$factory->cssSingleEasingFunction(),
			$factory->comma(),
			$factory->cssWideKeywords(),
			new KeywordMatcher( [
				'solid', 'double', 'dotted', 'dashed', 'wavy'
			] )
		] );

		$var = new FunctionMatcher(
			'var',
			new Juxtaposition( [
				new WhitespaceMatcher( [ 'significant' => false ] ),
				new CustomPropertyMatcher(),
				new WhitespaceMatcher( [ 'significant' => false ] ),
				Quantifier::optional( new Juxtaposition( [
					$factory->comma(),
					new WhitespaceMatcher( [ 'significant' => false ] ),
					new Alternative( [
						$anyProperty,
						new FunctionMatcher( 'var', new CustomPropertyMatcher() )
					] ),
				] ) ),
				new WhitespaceMatcher( [ 'significant' => false ] ),
			] )
		);

		$anyProperty = Quantifier::star( new Alternative( [
			$var,
			$anyProperty,
		] ) );

		// Match anything*\s?[var anything|anything var]+\s?anything*(!important)?
		// The problem is, that var() can be used more or less anywhere
		// Setting ONLY var as a CssWideKeywordMatcher would limit the matching to one property
		// E.g.: color: var( --color-base );             would work
		//       border: 1px var( --border-type ) black; would not
		// So we need to construct a matcher that matches anything + var somewhere
		$propertySanitizer->setCssWideKeywordsMatcher(
			new Alternative( [
				$factory->cssWideKeywords(),
				new Juxtaposition( [
					$anyProperty,
					new WhitespaceMatcher( [ 'significant' => false ] ),
					Quantifier::plus(
						new Alternative( [
							new Juxtaposition( [ $var, $anyProperty ] ),
							new Juxtaposition( [ $anyProperty, $var ] ),
						] )
					),
					new WhitespaceMatcher( [ 'significant' => false ] ),
					$anyProperty,
					Quantifier::optional(
						new KeywordMatcher( [ '!important' ] )
					)
				] ),
			] ),
		);
	}

	/**
	 * Adds the image-rendering matcher
	 * T222678
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 */
	public function addImageRendering( StylePropertySanitizer $propertySanitizer ): void {
		try {
			$propertySanitizer->addKnownProperties( [
				'image-rendering' => new KeywordMatcher( [
					'auto',
					'crisp-edges',
					'pixelated',
				] )
			] );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}

	/**
	 * Adds the ruby-position and ruby-align matcher
	 * T277755
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 */
	public function addRuby( StylePropertySanitizer $propertySanitizer ): void {
		try {
			$propertySanitizer->addKnownProperties( [
				'ruby-position' => new KeywordMatcher( [
					'start',
					'center',
					'space-between',
					'space-around',
				] )
			] );

			$propertySanitizer->addKnownProperties( [
				'ruby-align' => new KeywordMatcher( [
					'over',
					'under',
					'inter-character',
				] )
			] );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}

	/**
	 * Adds scroll-margin-* and scroll-padding-* matcher
	 * TODO: This is not well tested
	 * T271598
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 * @param MatcherFactory $factory
	 */
	public function addScrollMarginProperties( $propertySanitizer, $factory ): void {
		$suffixes = [
			'margin-block-end',
			'margin-block-start',
			'margin-block',
			'margin-bottom',
			'margin-inline-end',
			'margin-inline-start',
			'margin-inline',
			'margin-left',
			'margin-right',
			'margin-top',
			'margin',
			'padding-block-end',
			'padding-block-start',
			'padding-block',
			'padding-bottom',
			'padding-inline-end',
			'padding-inline-start',
			'padding-inline',
			'padding-left',
			'padding-right',
			'padding-top',
			'padding',
		];

		foreach ( $suffixes as $suffix ) {
			try {
				$propertySanitizer->addKnownProperties( [
					sprintf( 'scroll-%s', $suffix ) => new Alternative( [
						$factory->length()
					] )
				] );
			} catch ( InvalidArgumentException ) {
				// fail silently
			}
		}
	}

	/**
	 * Adds padding|margin-inline|block support
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 * @param MatcherFactory $factory
	 */
	public function addInsetProperties( $propertySanitizer, $factory ): void {
		$auto = new KeywordMatcher( 'auto' );
		$autoLengthPct = new Alternative( [ $auto, $factory->lengthPercentage() ] );

		$props = [];

		$props['inset'] = Quantifier::count( $autoLengthPct, 1, 4 );

		$props['inset-block'] = Quantifier::count( $autoLengthPct, 1, 2 );
		$props['inset-block-end'] = $autoLengthPct;
		$props['inset-block-start'] = $autoLengthPct;

		$props['inset-inline'] = Quantifier::count( $autoLengthPct, 1, 2 );
		$props['inset-inline-end'] = $autoLengthPct;
		$props['inset-inline-start'] = $autoLengthPct;

		try {
			$propertySanitizer->addKnownProperties( $props );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}

	/**
	 * Adds the pointer-events matcher
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 */
	public function addPointerEvents( StylePropertySanitizer $propertySanitizer ): void {
		try {
			$propertySanitizer->addKnownProperties( [
				'pointer-events' => new KeywordMatcher( [
					'auto',
					'none',
					'visiblePainted',
					'visibleFill',
					'visibleStroke',
					'visible',
					'painted',
					'fill',
					'stroke',
					'all',
				] )
			] );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}

	/**
	 * Adds the aspect-ratio matcher
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 * @param MatcherFactory $factory
	 */
	public function addAspectRatio( StylePropertySanitizer $propertySanitizer, MatcherFactory $factory ): void {
		try {
			$propertySanitizer->addKnownProperties( [
				'aspect-ratio' => new Alternative( [
					$factory->cssWideKeywords(),
					new Juxtaposition( [
						$factory->number(),
						Quantifier::optional(
							new Juxtaposition( [
								new WhitespaceMatcher( [ 'significant' => false ] ),
								new DelimMatcher( '/' ),
								new WhitespaceMatcher( [ 'significant' => false ] ),
								$factory->number()
							] )
						)
					] ),
				] )
			] );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}

	/**
	 * Adds the backdrop-filter matcher
	 *
	 * @param StylePropertySanitizer $propertySanitizer
	 */
	public function addBackdropFilter( StylePropertySanitizer $propertySanitizer ): void {
		try {
			$filter = $propertySanitizer->getKnownProperties()['filter'];

			$propertySanitizer->addKnownProperties( [
				'backdrop-filter' => Quantifier::plus( $filter ),
			] );
		} catch ( InvalidArgumentException ) {
			// fail silently
		}
	}
}
