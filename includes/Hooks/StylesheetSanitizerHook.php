<?php

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
		$extended->addScrollSpace( $extender, $matcherFactory );
		$extended->addAspectRatio( $extender, $matcherFactory );
		$extended->addInsetProperties( $extender, $matcherFactory );
		$extended->addBackdropFilter( $extender );
		$extended->addContentVisibility( $extender );
		$extended->cssText4( $extender );

		$propertySanitizer->setKnownProperties( $extender->getKnownProperties() );
	}
}
