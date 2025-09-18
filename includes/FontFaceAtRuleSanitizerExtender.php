<?php

namespace MediaWiki\Extension\TemplateStylesExtender;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Sanitizer\FontFaceAtRuleSanitizer;

class FontFaceAtRuleSanitizerExtender extends FontFaceAtRuleSanitizer {
	/** @inheritDoc */
	public function __construct( MatcherFactory $matcherFactory ) {
		parent::__construct( $matcherFactory );

		$matcher = new Alternative( [
			new KeywordMatcher( 'normal' ),
			$matcherFactory->percentage()
		] );

		$this->propertySanitizer->setKnownProperties( [
			'ascent-override' => $matcher,
			'descent-override' => $matcher,
			'font-display' => new KeywordMatcher( [ 'auto', 'block', 'swap', 'fallback', 'optional' ] ),
			'line-gap-override' => $matcher,
			'size-adjust' => $matcher,
		] + $this->propertySanitizer->getKnownProperties() );
	}
}
