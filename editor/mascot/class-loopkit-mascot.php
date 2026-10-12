<?php
/**
 * Loopkit mascot.
 *
 * A small character in the bottom right of the builder and the Loopkit admin
 * screens. It reacts to what you do and explains a widget the first time you
 * hover it.
 *
 * Nothing here is generated at request time: the shapes are paths in CSS and
 * the lines below are written once. No API, no network.
 *
 * @package Loopkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mascot loader.
 */
class Loopkit_Mascot {

	/**
	 * Hook it up.
	 */
	public static function init() {
		// Before admin_enqueue_scripts, or saving "on" has no effect until the
		// next page load: the enqueue decision is made from the old value.
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'loopkit_editor_enqueue', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Save the toggle, early enough to take effect on this same request.
	 */
	public static function maybe_save() {
		if ( empty( $_POST['loopkit_settings_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['loopkit_settings_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['loopkit_settings_nonce'] ) ), 'loopkit_settings_save' ) ) {
			return;
		}
		if ( 'general' !== sanitize_key( wp_unslash( $_POST['active_tab'] ?? '' ) ) ) {
			return; // the toggle only lives on the General tab
		}

		$was = get_option( 'loopkit_mascot', '1' );
		$now = empty( $_POST['loopkit_mascot'] ) ? '0' : '1';
		update_option( 'loopkit_mascot', $now );
		if ( '1' === $now && $was !== $now ) {
			// Beat a dismissal the person made from the mascot itself, which
			// lives in their browser rather than in the site.
			update_option( 'loopkit_mascot_on_at', time() );
		}
	}

	/**
	 * Is the mascot switched on for this site?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '0' !== get_option( 'loopkit_mascot', '1' );
	}

	/**
	 * Load it on Loopkit's own admin screens and nowhere else.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function enqueue_admin( $hook ) {
		if ( false === strpos( (string) $hook, 'loopkit' ) ) {
			return;
		}
		self::enqueue();
	}

	/**
	 * Register and load the two files.
	 */
	public static function enqueue() {
		if ( ! self::enabled() ) {
			return;
		}
		if ( wp_script_is( 'loopkit-mascot-js', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'loopkit-mascot-css',
			LOOPKIT_PLUGIN_URL . 'editor/mascot/loopkit-mascot.css',
			array(),
			LOOPKIT_VERSION
		);
		wp_enqueue_script(
			'loopkit-mascot-js',
			LOOPKIT_PLUGIN_URL . 'editor/mascot/loopkit-mascot.js',
			array(),
			LOOPKIT_VERSION,
			true
		);
		wp_localize_script(
			'loopkit-mascot-js',
			'loopkitMascot',
			array(
				'tips'  => self::tips(),
				// When the site last switched it back on. A browser-side
				// dismissal older than this is overruled.
				'onAt'  => (int) get_option( 'loopkit_mascot_on_at', 0 ),
				'text' => array(
					'dismiss'    => __( 'Dismiss', 'loopkit' ),
					'offerTitle' => __( 'Too chatty?', 'loopkit' ),
					'offerBody'  => __( 'Switch me off. Loopkit → Settings → General turns me back on.', 'loopkit' ),
					'turnOff'    => __( 'Turn me off', 'loopkit' ),
					'shyTitle'   => __( 'Oh', 'loopkit' ),
					// Looked at for a few seconds. Said once, then it stops.
					'shy'        => array(
						__( 'You are staring. That makes me shy.', 'loopkit' ),
						__( 'I can feel you looking at me.', 'loopkit' ),
						__( 'Is there something on my face?', 'loopkit' ),
						__( 'Shouldn\'t you be building something?', 'loopkit' ),
						__( 'I do not get any prettier, you know.', 'loopkit' ),
						__( 'Still here. Still a small blue box.', 'loopkit' ),
						__( 'This is a lot of eye contact.', 'loopkit' ),
					),
					'deleteTitle' => __( 'Hey', 'loopkit' ),
					'deletes'     => array(
						__( 'Why are you deleting that?', 'loopkit' ),
						__( 'It did nothing to you.', 'loopkit' ),
						__( 'Gone. I hope you meant that.', 'loopkit' ),
						__( 'That one was my favourite.', 'loopkit' ),
					),
				),
			)
		);
	}

	/**
	 * What it says about each widget.
	 *
	 * Two sentences: what the widget is, then the thing people get wrong with
	 * it. A line that only restates the name ("This is a button") is worse
	 * than no line, so a widget with nothing useful to say gets none — it
	 * simply stays quiet on hover.
	 *
	 * @return array Widget key => label and tip.
	 */
	public static function tips() {
		$tips = array(
			'heading'               => __( 'The page outline, not a font size. One h1, then h2s under it — jumping to h4 because it looks smaller is what breaks the outline for screen readers and search.', 'loopkit' ),
			'text-editor'           => __( 'A block of body copy. Set the measure here, not by squeezing the column: past about 75 characters a line the eye loses its place on the way back.', 'loopkit' ),
			'image'                 => __( 'Fill in the alt text — it is what a screen reader reads and what shows when the file fails. A purely decorative image takes an empty alt, not a missing one.', 'loopkit' ),
			'video'                 => __( 'Set a poster image or the space sits blank until the file loads. Autoplay only works muted, and it spends someone\'s data before they have asked for it.', 'loopkit' ),
			'button'                => __( 'Say what happens next, not "Click here". One obvious primary button per screenful — a second of equal weight makes the visitor choose instead of act.', 'loopkit' ),
			'divider'               => __( 'A line between sections. If the spacing already reads as a break, the line is noise; reach for it when the gap alone is not doing the work.', 'loopkit' ),
			'spacer'                => __( 'Sometimes the honest tool. But if you are stacking spacers to push something down, the padding on the section above is the real fix.', 'loopkit' ),
			'icon'                  => __( 'An icon beside a label reads faster than either alone. On its own it needs a title — not everyone reads the same symbol the same way.', 'loopkit' ),
			'image-box'             => __( 'Image, heading and text as one unit. Keep the images the same shape across a row or the headings below them will not line up.', 'loopkit' ),
			'icon-box'              => __( 'A feature in three parts. The heading carries the meaning — if the icon were removed the box should still make sense.', 'loopkit' ),
			'icon-list'             => __( 'For short parallel items: features, what is included. Once a line wraps to three rows it has become body copy and wants a text block.', 'loopkit' ),
			'social-icons'          => __( 'These send people away from your page. Footer, usually — not beside the thing you want them to do.', 'loopkit' ),
			'html'                  => __( 'Raw markup, straight out. Nothing here is sanitised or responsive for you, so what you paste is exactly what ships.', 'loopkit' ),
			'shortcode'             => __( 'Runs another plugin\'s shortcode. It renders on the live page but usually not in the builder, so check the front end before calling it done.', 'loopkit' ),
			'site-logo'             => __( 'Pulls the logo from site settings, so changing it once changes it everywhere. Give it a height, not a width — logos differ in shape more than in height.', 'loopkit' ),
			'site-title'            => __( 'The site name from settings. In a header this is usually a link home; as a page heading it competes with the real h1.', 'loopkit' ),
			'nav-menu'              => __( 'Your WordPress menu. Seven items is about the limit before people stop reading and start scanning — anything more belongs in a submenu.', 'loopkit' ),
			'search-form'           => __( 'Worth having once results are worth finding. On a small site it often just advertises that there is not much here.', 'loopkit' ),
			'post-title'            => __( 'The title of the post being viewed. Only resolves inside a Single template; anywhere else it comes out empty.', 'loopkit' ),
			'post-featured-image'   => __( 'The post\'s own image. Set a ratio so a tall photo and a wide one do not shove the rest of the template around.', 'loopkit' ),
			'post-excerpt'          => __( 'The short summary. If the post has none written, WordPress cuts one mid-sentence — writing excerpts is what makes a listing read well.', 'loopkit' ),
			'post-info'             => __( 'Date, author, category. Pick what a reader actually needs; four meta items above the first line is throat-clearing.', 'loopkit' ),
			'post-content'          => __( 'Where the post body lands in a Single template. One per template — a second copy renders the same content twice.', 'loopkit' ),
			'author-box'            => __( 'Who wrote it. Worth it on a multi-author site, filler on a one-person blog.', 'loopkit' ),
			'post-navigation'       => __( 'Previous and next. Useful in a series, arbitrary when the posts have nothing to do with each other.', 'loopkit' ),
			'post-comments'         => __( 'The comment thread. If comments are off in Discussion settings this renders nothing, which looks like a bug but is not.', 'loopkit' ),
			'archive-title'         => __( 'The title of the archive being viewed. Only resolves in an Archive template.', 'loopkit' ),
			'archive-description'   => __( 'The category or tag description. Most sites never fill these in, so it is usually empty — write them or drop this.', 'loopkit' ),
			'posts'                 => __( 'A list of posts with its own query. Set the count deliberately: the default is the site\'s, and it changes under you when someone edits Reading settings.', 'loopkit' ),
			'loop-grid'             => __( 'Posts rendered through a Loop Item template, so the card is yours. Build the Loop Item first — without it there is nothing to repeat.', 'loopkit' ),
			'product-grid'          => __( 'Products in a grid. Decide what happens at three items and at thirty; a grid that looks right with nine often falls apart at four.', 'loopkit' ),
			'add-to-cart'           => __( 'The buy button for one product. On a variable product it needs the variation controls too, or it adds nothing.', 'loopkit' ),
			'product-price'         => __( 'Price, with the sale price when there is one. It follows the product, so do not type the number anywhere else.', 'loopkit' ),
			'product-gallery'       => __( 'The product\'s images. Shoppers zoom — upload bigger than you display.', 'loopkit' ),
			'cart-icon'             => __( 'Cart with a count. Keep it in the header on every page; a shopper who cannot find the cart does not go looking.', 'loopkit' ),
			'cart-table'            => __( 'The cart contents. Belongs on the Cart page only — anywhere else it renders someone\'s cart in the middle of your content.', 'loopkit' ),
			'checkout-form'         => __( 'The whole checkout. Every field you add loses you orders, so ask only for what you need to ship.', 'loopkit' ),
			'product-categories'    => __( 'Browsable categories. If you have four, show them; if you have forty, this becomes a wall.', 'loopkit' ),
			'product-filters'       => __( 'Narrow by attribute. Only filter on what people shop by — size and price, usually, not everything you store.', 'loopkit' ),
			'mini-cart'             => __( 'The cart in a dropdown. It needs a clear route to checkout or it is a dead end.', 'loopkit' ),
			'product-rating'        => __( 'Stars from real reviews. With two reviews it reads as thin; wait until you have some.', 'loopkit' ),
			'my-account'            => __( 'The customer\'s account area. Belongs on its own page behind a login, not in your main flow.', 'loopkit' ),
			'marquee'               => __( 'Scrolling text. Readable as a strip of logos or short phrases, unreadable for anything you expect people to take in.', 'loopkit' ),
			'team-member'           => __( 'A person: photo, name, role. Crop every face to the same ratio or the row looks accidental.', 'loopkit' ),
			'testimonial-carousel'  => __( 'Several quotes in rotation. Most visitors see the first and nothing else, so put your strongest one there.', 'loopkit' ),
			'blockquote'            => __( 'A pulled quote. For emphasis, not for every paragraph you like — once everything is pulled out, nothing is.', 'loopkit' ),
			'business-hours'        => __( 'Opening times. Remember the holiday that makes this wrong; an out-of-date table is worse than none.', 'loopkit' ),
			'hotspot'               => __( 'Markers over an image. Touch has no hover, so on a phone each marker must be tappable.', 'loopkit' ),
			'portfolio'             => __( 'Filterable work. The filter earns its place at about a dozen items; below that it is a control nobody touches.', 'loopkit' ),
			'login-form'            => __( 'Sign in without wp-admin. Set where it goes afterwards, or people land on a dashboard they did not want.', 'loopkit' ),
			'flip-box'              => __( 'Hides its back until hover. Nothing important goes on the back — on a phone it may never be seen.', 'loopkit' ),
			'price-table'           => __( 'Plans side by side. Mark the one you want chosen; three undifferentiated columns leave people stuck.', 'loopkit' ),
			'countdown'             => __( 'Only for a deadline that is real. A timer that resets on refresh gets noticed, and it costs trust you do not get back.', 'loopkit' ),
			'animated-headline'     => __( 'One phrase that changes. The sentence has to read properly with every variant, including the longest.', 'loopkit' ),
			'call-to-action'        => __( 'Image, heading and a button as one block. One idea and one button — a CTA with two buttons is a menu.', 'loopkit' ),
			'share-buttons'         => __( 'Sharing links. Four is plenty; a row of twelve looks like hope rather than strategy.', 'loopkit' ),
			'pagination'            => __( 'Page numbers for a list. It only does something where a query is paged, so it belongs with Posts or Loop Grid.', 'loopkit' ),
			'accordion'             => __( 'Good for questions people scan. Bad for anything they must read — folded content is content most visitors never open.', 'loopkit' ),
			'toggle'                => __( 'One panel that opens. For a single aside; several of these want to be an accordion so only one is open at a time.', 'loopkit' ),
			'tabs'                  => __( 'For alternatives: pick one of these. Not for a sequence — if panel two only makes sense after panel one, they want to be sections.', 'loopkit' ),
			'counter'               => __( 'A number that counts up on scroll. It needs a label saying what it counts, or it is a moving digit.', 'loopkit' ),
			'progress-bar'          => __( 'A proportion, shown. Say what the number is out of, or the bar means nothing.', 'loopkit' ),
			'alert'                 => __( 'A notice that needs reading. Use it rarely — two alerts on a page and people stop seeing either.', 'loopkit' ),
			'star-rating'           => __( 'A rating you type in yourself. Say where it came from; an unsourced five stars reads as decoration.', 'loopkit' ),
			'testimonial'           => __( 'One quote. A name, a role and a face make it believable — without them it reads as something you wrote.', 'loopkit' ),
			'image-carousel'        => __( 'Images in rotation. Most people see the first and nothing else, so if image three matters it should not be in here.', 'loopkit' ),
			'image-gallery'         => __( 'A grid of images. Set the columns per breakpoint: four across looks right on a laptop and turns into stamps on a phone.', 'loopkit' ),
			'google-maps'           => __( 'An embed that loads third-party code on every visit. If the address is the point, a static image and a link are lighter and just as useful.', 'loopkit' ),
			'contact-form'          => __( 'Every field costs you submissions. Ask for what you will actually use, and label the fields above them, not inside.', 'loopkit' ),
			'tooltip'               => __( 'A hint on hover. Touch has no hover, so nothing a visitor needs can live only in here.', 'loopkit' ),
			'table-of-contents'     => __( 'Built from the headings. It is only as good as the outline, so fix the headings first and this fixes itself.', 'loopkit' ),
			'collection-list'       => __( 'Shows rows from a CMS collection, one card each. Bind the image, title and link to fields once — then adding a row updates the page by itself.', 'loopkit' ),
		);

		$out = array();
		foreach ( $tips as $key => $tip ) {
			$out[ $key ] = array(
				'label' => self::label( $key ),
				'tip'   => $tip,
			);
		}
		return $out;
	}

	/**
	 * A widget's own name, so the bubble heading matches the panel.
	 *
	 * @param string $key Widget key.
	 * @return string
	 */
	protected static function label( $key ) {
		// get_widget lives on the instance, not the class.
		if ( class_exists( 'Loopkit_Widgets_Registry' ) ) {
			$w = Loopkit_Widgets_Registry::instance()->get_widget( $key );
			if ( is_array( $w ) && ! empty( $w['title'] ) ) {
				return $w['title'];
			}
			if ( is_object( $w ) && ! empty( $w->title ) ) {
				return $w->title;
			}
		}
		return ucwords( str_replace( array( '-', '_' ), ' ', $key ) );
	}
}
