<?php

namespace MediaWiki\Extension\TemplateStylesExtender\Hooks;

use MediaWiki\Extension\TemplateStyles\Hooks\TemplateStylesPropertySanitizerHook;
use MediaWiki\Extension\TemplateStylesExtender\StylePropertySanitizerExtender;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;

class PropertySanitizerHook implements TemplateStylesPropertySanitizerHook {
	/**
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Extension:TemplateStyles/Hooks/TemplateStylesPropertySanitizer
	 */
	public function onTemplateStylesPropertySanitizer(
		StylePropertySanitizer &$propertySanitizer,
		MatcherFactory $matcherFactory
	): void {
		$propertySanitizer = new StylePropertySanitizerExtender( $matcherFactory );
	}
}
