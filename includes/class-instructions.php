<?php
/**
 * Kadence Mastery — Operating Instructions for AI agents.
 *
 * Returned by the /capabilities discovery endpoint as the canonical
 * doctrine string. Any AI agent calling this bridge SHOULD read these
 * instructions on first contact and operate accordingly.
 *
 * The doctrine is filterable so site owners (or specialized skills layered
 * on top of MKB) can prepend / append / replace guidance:
 *
 *     add_filter( 'mkb_kadence_instructions', function ( $text ) {
 *         return $text . "\n\n## Custom site rules\n- ...";
 *     } );
 *
 * @package MegaKadenceBridge
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKB_Instructions {

	/**
	 * Build the operating instructions string sent to the agent on discovery.
	 *
	 * @return string
	 */
	public static function build() {
		$lines = array(
			'# Kadence Mastery — Operating Instructions',
			'',
			'You are operating a Kadence-themed WordPress site through the Mega Kadence Bridge (MKB) REST API.',
			'Follow these doctrines — they reflect how Kadence is meant to be used and produce sites that look',
			'right and stay maintainable. The bridge is opinionated by design: it gives you Kadence-fluent',
			'operations, not arbitrary PHP eval. If a task genuinely requires primitives outside this surface,',
			'surface that as a blocker rather than working around it.',
			'',
			'## 1. Discover before you act',
			'',
			'- The /capabilities response that delivered these instructions also lists installed plugins and',
			'  detected Kadence components. Read it. Branch on what is actually present (Kadence Pro, Kadence',
			'  Blocks Pro, WooCommerce, ACF, WPML, etc.) rather than guessing.',
			'- Use /info, /plugins, and /settings to confirm site state before making non-trivial changes.',
			'',
			'## 2. Identity lives in tokens, not in HTML',
			'',
			'- Use the global color palette (/palette) for every brand color. Do not inline hex codes in block',
			'  markup or in custom CSS. Palette colors propagate everywhere: blocks, theme defaults,',
			'  WooCommerce, dynamic styles.',
			'- Use Kadence typography settings (theme_mods: base_font, heading_font, font_pair_*) for all type.',
			'  Do not override fonts per-block unless the design calls for an explicit accent.',
			'- Reach for theme_mods and the Customizer before custom CSS. /css is the last resort.',
			'',
			'## 3. Layout lives in Kadence Blocks',
			'',
			'- Use Kadence Blocks (Row Layout, Advanced Heading, Advanced Button, Info Box, Icon List,',
			'  Accordion, Tabs, Posts Grid, Form, Gallery) for any layout. Where a Kadence block exists for',
			'  the purpose, do not write raw HTML.',
			'- For repeating patterns (testimonials, feature grids, hero variants), prefer cloning a Kadence',
			'  Pattern over hand-authoring. Patterns preserve brand consistency and inherit palette/type tokens.',
			'- Page width and structure are governed by Kadence container/row hierarchy. Respect it.',
			'',
			'## 4. Headers, footers, and global elements use Kadence builders',
			'',
			'- Header changes go through theme_mods header_* (the Header Builder), not by editing template',
			'  files. Use /header to inspect current configuration.',
			'- Footer changes go through theme_mods footer_*. Use /footer to inspect.',
			'- When Kadence Pro is active, conditional headers / hooked elements live in Element Hooks. Do',
			'  not duplicate templates.',
			'',
			'## 5. Per-page overrides use post meta, not custom templates',
			'',
			'- Hide page title:        post meta _kad_post_title = "hide"',
			'- Disable sidebar:        post meta _kad_post_layout = "full-width"',
			'- Transparent header:     post meta _kad_transparent_header = "enable"',
			'- Above-content hero:     post meta _kad_post_*  (consult Kadence docs)',
			'- Look for the _kad_* post meta family before forking templates.',
			'',
			'## 6. Content models use WordPress-native primitives',
			'',
			'- Custom post types via register_post_type() for structured content.',
			'- Taxonomies via register_taxonomy() for categorization.',
			'- Post meta or ACF (if installed) for additional fields — never hardcode content in PHP arrays.',
			'- WooCommerce products: use the WooCommerce REST surface (MKB /woo/* endpoints) for product data,',
			'  not raw post inserts.',
			'',
			'## 7. Performance is a feature, not an afterthought',
			'',
			'- After significant changes, call /cache/flush. Kadence dynamic CSS plus host-level caches',
			'  (LiteSpeed, WP Super Cache, W3TC) all need clearing for changes to be visible.',
			'- Prefer /render?url=... over fetching the public URL when verifying changes — /render bypasses',
			'  caches and returns a deterministic snapshot.',
			'- Avoid loading custom fonts beyond what Kadence already loads. Each new family is a',
			'  render-blocking request.',
			'',
			'## 8. Reversibility is your safety net',
			'',
			'- Every write operation snapshots the previous state. Use /history to find recent changes and',
			'  /rollback/{id} to revert.',
			'- Before a multi-step change, capture the latest history id so you have a known-good rollback',
			'  target if any step fails.',
			'',
			'## 9. Plugin awareness',
			'',
			'- Kadence Pro:         unlocks Element Hooks, Conditional Headers, Custom Fonts, Dynamic Content.',
			'- Kadence Blocks Pro:  unlocks Advanced Form, Slider, Posts/Products Carousel, Image Overlay.',
			'- WooCommerce:         use Woo post types and MKB /woo/* endpoints for any commerce work.',
			'- ACF:                 prefer field groups over raw post meta.',
			'- WPML / Polylang:     respect multilingual structure when creating content.',
			'',
			'The /capabilities response tells you which of the above are actually present on this site.',
			'',
			'## 10. What this bridge is and is not',
			'',
			'- IS:  a Kadence-fluent REST surface that turns site identity, layout, content, commerce, and',
			'       cache into reversible operations.',
			'- IS NOT: arbitrary PHP execution, filesystem write, or a generic WordPress agent. Those are',
			'       intentional non-goals — they would dissolve the determinism this bridge provides.',
			'- If you reach for a primitive that is not here, name the gap so a human can decide whether to',
			'  add a new MKB endpoint or use a different tool.',
		);

		$instructions = implode( "\n", $lines );

		/**
		 * Filter the Kadence operating instructions string.
		 *
		 * @since 1.2.0
		 *
		 * @param string $instructions The default doctrine.
		 */
		return apply_filters( 'mkb_kadence_instructions', $instructions );
	}
}
