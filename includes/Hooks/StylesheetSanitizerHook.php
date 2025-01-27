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

namespace MediaWiki\Extension\TemplateStylesExtender\Hooks;

use MediaWiki\Extension\TemplateStyles\Hooks\TemplateStylesStylesheetSanitizerHook;
use MediaWiki\Extension\TemplateStylesExtender\FontFaceAtRuleSanitizerExtender;
use MediaWiki\Extension\TemplateStylesExtender\StylePropertySanitizerExtender;
use MediaWiki\Extension\TemplateStylesExtender\TemplateStylesExtender;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;

class StylesheetSanitizerHook implements TemplateStylesStylesheetSanitizerHook {
	/**
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Extension:TemplateStyles/Hooks/TemplateStylesStylesheetSanitizer
	 */
	public function onTemplateStylesStylesheetSanitizer(
		StylesheetSanitizer &$sanitizer,
		StylePropertySanitizer $propertySanitizer,
		MatcherFactory $matcherFactory
	): void {
		$newRules = $sanitizer->getRuleSanitizers();
		$newRules['@font-face'] = new FontFaceAtRuleSanitizerExtender( $matcherFactory );
		$sanitizer->setRuleSanitizers( $newRules );

		$extended = new TemplateStylesExtender();

		$extender = new StylePropertySanitizerExtender( $matcherFactory );
		$extended->addVarSelector( $propertySanitizer, $matcherFactory );
		$extended->addImageRendering( $extender );
		$extended->addRuby( $extender );
		$extended->addPointerEvents( $extender );
		$extended->addScrollMarginProperties( $extender, $matcherFactory );
		$extended->addAspectRatio( $extender, $matcherFactory );
		$extended->addInsetProperties( $extender, $matcherFactory );
		$extended->addBackdropFilter( $extender );

		$propertySanitizer->setKnownProperties( $extender->getKnownProperties() );
	}
}
