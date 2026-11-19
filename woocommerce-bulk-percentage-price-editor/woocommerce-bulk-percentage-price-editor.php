<?php
/**
 * Plugin Name: WooCommerce Bulk Percentage Price Editor
 * Description: Preview and apply percentage price changes to simple products and individual variations in small batches.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: woocommerce-bulk-percentage-price-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WVBPE_Plugin {
	const PAGE_SLUG     = 'wvbpe-bulk-price-editor';
	const BATCH_SIZE     = 25;
	const JOB_LIFETIME   = DAY_IN_SECONDS;
	const MAX_PRODUCTS   = 5000;
	const MAX_PRICE_ITEMS = 10000;

	/** Register the admin-only plugin entry points. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_wvbpe_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'admin_post_wvbpe_apply', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
	}

	/** Add one submenu under WooCommerce. */
	public static function register_menu() {
		add_submenu_page(
			'woocommerce',
			'ویرایش گروهی قیمت',
			'ویرایش گروهی قیمت',
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** Show a clear notice when WooCommerce is inactive. */
	public static function woocommerce_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || class_exists( 'WooCommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && false !== strpos( $screen->id, self::PAGE_SLUG ) ) {
			echo '<div class="notice notice-error"><p>برای استفاده از ویرایش گروهی قیمت، افزونه ووکامرس باید فعال باشد.</p></div>';
		}
	}

	/** Render the filter form and any previewed or resumable job. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html( 'شما اجازه دسترسی به این صفحه را ندارید.' ) );
		}

		if ( ! function_exists( 'wc_get_products' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>ویرایش گروهی قیمت محصولات</h1>';
		self::render_notice();
		self::render_filter_form();

		$token = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
		if ( preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			$job = get_transient( self::job_key( $token ) );
			if ( is_array( $job ) && isset( $job['user_id'] ) && (int) $job['user_id'] === get_current_user_id() ) {
				self::render_job( $token, $job );
			} elseif ( '' !== $token ) {
				echo '<div class="notice notice-warning"><p>پیش‌نمایش پیدا نشد یا زمان اعتبار آن تمام شده است. لطفاً دوباره پیش‌نمایش بگیرید.</p></div>';
			}
		}

		echo '</div>';
	}

	/** Render a short-lived notice created by an admin action. */
	private static function render_notice() {
		$key    = 'wvbpe_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		$class = isset( $notice['type'] ) && 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	}

	/** Render scope and percentage controls. */
	private static function render_filter_form() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		echo '<p>محصولات منتشرشده را بر اساس نوع و دسته انتخاب کنید. برای هر محصول متغیر، قیمت هر متغیر جداگانه محاسبه می‌شود.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wvbpe_preview">';
		wp_nonce_field( 'wvbpe_preview' );
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="wvbpe-product-type">نوع محصول</label></th><td><select id="wvbpe-product-type" name="product_type">';
		self::option( 'all', 'ساده و متغیر', 'all' );
		self::option( 'simple', 'فقط ساده', 'all' );
		self::option( 'variable', 'فقط متغیر', 'all' );
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="wvbpe-category">دسته‌بندی</label></th><td><select id="wvbpe-category" name="category_id"><option value="0">همه دسته‌ها</option>';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$depth = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
				echo '<option value="' . esc_attr( $term->term_id ) . '">' . esc_html( str_repeat( '— ', $depth ) . $term->name ) . '</option>';
			}
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="wvbpe-target">قیمت مورد تغییر</label></th><td><select id="wvbpe-target" name="target">';
		self::option( 'regular', 'قیمت عادی', 'regular' );
		self::option( 'sale', 'قیمت فروش ویژه', 'regular' );
		self::option( 'both', 'قیمت عادی و فروش ویژه', 'regular' );
		echo '</select><p class="description">در حالت «هر دو»، درصد یکسان روی قیمت عادی و قیمت فروش ویژه موجود اعمال می‌شود تا نسبت تخفیف حفظ شود.</p></td></tr>';

		echo '<tr><th scope="row"><label for="wvbpe-direction">عملیات</label></th><td><select id="wvbpe-direction" name="direction">';
		self::option( 'increase', 'افزایش درصدی', 'increase' );
		self::option( 'decrease', 'کاهش درصدی', 'increase' );
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="wvbpe-percent">درصد</label></th><td><input id="wvbpe-percent" name="percent" type="text" inputmode="decimal" value="10" class="small-text" required><p class="description">برای کاهش، درصد باید حداکثر ۱۰۰ باشد. گرد کردن با تعداد رقم اعشار تنظیم‌شده در ووکامرس انجام می‌شود.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'نمایش پیش‌نمایش', 'primary', 'submit', false );
		echo '</form>';
	}

	/** Output an option with a server-selected default. */
	private static function option( $value, $label, $default ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $default, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}

	/** Validate inputs, build a stable preview, and save it for this user. */
	public static function handle_preview() {
		self::authorize_request();
		check_admin_referer( 'wvbpe_preview' );

		$criteria = self::read_criteria();
		if ( is_wp_error( $criteria ) ) {
			self::redirect_notice( $criteria->get_error_message(), 'error' );
		}

		$preview = self::build_preview( $criteria );
		if ( is_wp_error( $preview ) ) {
			self::redirect_notice( $preview->get_error_message(), 'error' );
		}
		if ( empty( $preview['items'] ) ) {
			self::redirect_notice( 'قیمتی برای تغییر پیدا نشد. فیلترها یا درصد را بررسی کنید.', 'error' );
		}

		$token = wp_generate_password( 32, false, false );
		$job   = array_merge(
			$preview,
			array(
				'user_id'        => get_current_user_id(),
				'criteria'       => $criteria,
				'status'         => 'previewed',
				'cursor'         => 0,
				'applied'        => 0,
				'stale'          => 0,
				'failed'         => 0,
				'undo_status'    => 'available',
				'undo_cursor'    => 0,
				'undo_restored'  => 0,
				'undo_conflicts' => 0,
			)
		);
		set_transient( self::job_key( $token ), $job, self::JOB_LIFETIME );

		wp_safe_redirect( self::page_url( array( 'job' => $token ) ) );
		exit;
	}

	/** Ensure only a WooCommerce manager can submit admin actions. */
	private static function authorize_request() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html( 'شما اجازه اجرای این عملیات را ندارید.' ), '', array( 'response' => 403 ) );
		}
		if ( ! function_exists( 'wc_get_products' ) ) {
			self::redirect_notice( 'ووکامرس فعال نیست.', 'error' );
		}
	}

	/** Read and validate the form values, accepting Persian and Arabic numerals. */
	private static function read_criteria() {
		$type      = isset( $_POST['product_type'] ) ? sanitize_key( wp_unslash( $_POST['product_type'] ) ) : 'all';
		$category  = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;
		$target    = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : 'regular';
		$direction = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : 'increase';
		$raw       = isset( $_POST['percent'] ) ? self::normalize_number( wp_unslash( $_POST['percent'] ) ) : '';

		if ( ! in_array( $type, array( 'all', 'simple', 'variable' ), true ) ) {
			return new WP_Error( 'invalid_type', 'نوع محصول معتبر نیست.' );
		}
		if ( ! in_array( $target, array( 'regular', 'sale', 'both' ), true ) ) {
			return new WP_Error( 'invalid_target', 'نوع قیمت معتبر نیست.' );
		}
		if ( ! in_array( $direction, array( 'increase', 'decrease' ), true ) ) {
			return new WP_Error( 'invalid_direction', 'نوع عملیات معتبر نیست.' );
		}
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return new WP_Error( 'invalid_percent', 'درصد باید یک عدد معتبر باشد.' );
		}

		$percent = (float) $raw;
		if ( ! is_finite( $percent ) || $percent <= 0 || $percent > 100000 ) {
			return new WP_Error( 'invalid_percent', 'درصد باید بیشتر از صفر و حداکثر ۱۰۰٬۰۰۰ باشد.' );
		}
		if ( 'decrease' === $direction && $percent > 100 ) {
			return new WP_Error( 'invalid_decrease', 'درصد کاهش نمی‌تواند بیشتر از ۱۰۰ باشد.' );
		}
		if ( $category ) {
			$term = get_term( $category, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'invalid_category', 'دسته‌بندی انتخاب‌شده معتبر نیست.' );
			}
		}

		return array(
			'product_type' => $type,
			'category_id'  => $category,
			'target'       => $target,
			'direction'    => $direction,
			'percent'      => $percent,
		);
	}

	/** Convert localized digits and decimal separators to a normalized number. */
	private static function normalize_number( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		$value = strtr(
			$value,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
				'٫' => '.', '٬' => '', '،' => '.',
			)
		);
		if ( false !== strpos( $value, ',' ) ) {
			$value = false === strpos( $value, '.' ) ? str_replace( ',', '.', $value ) : str_replace( ',', '', $value );
		}
		return preg_match( '/^\d+(?:\.\d{1,6})?$/', $value ) ? $value : '';
	}

	/** Find product IDs, expand variable products to variations, and calculate preview values. */
	private static function build_preview( $criteria ) {
		$types = 'all' === $criteria['product_type'] ? array( 'simple', 'variable' ) : array( $criteria['product_type'] );
		$args  = array(
			'type'     => $types,
			'status'   => 'publish',
			'limit'    => self::MAX_PRODUCTS + 1,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'return'   => 'ids',
		);
		if ( $criteria['category_id'] ) {
			$term = get_term( $criteria['category_id'], 'product_cat' );
			$args['category'] = array( $term->slug );
		}

		$product_ids = wc_get_products( $args );
		if ( count( $product_ids ) > self::MAX_PRODUCTS ) {
			return new WP_Error( 'too_many_products', 'بیش از ۵۰۰۰ محصول با این فیلتر پیدا شد. برای پیش‌نمایش دقیق، یک دسته یا نوع محصول محدودتر انتخاب کنید.' );
		}

		$items         = array();
		$skip_reasons  = array();
		$sample        = array();
		$candidate_count = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'simple' ) ) {
				$candidate_count++;
				$result = self::plan_item( $product, 0, $criteria );
				self::collect_preview_item( $result, $items, $sample, $skip_reasons );
			} elseif ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
						continue;
					}
					$candidate_count++;
					$result = self::plan_item( $variation, $product->get_id(), $criteria );
					self::collect_preview_item( $result, $items, $sample, $skip_reasons );
					if ( $candidate_count > self::MAX_PRICE_ITEMS ) {
						return new WP_Error( 'too_many_items', 'بیش از ۱۰٬۰۰۰ محصول یا متغیر پیدا شد. برای اجرای مطمئن، فیلتر دسته‌بندی را محدودتر کنید.' );
					}
				}
			}
			if ( $candidate_count > self::MAX_PRICE_ITEMS ) {
				return new WP_Error( 'too_many_items', 'بیش از ۱۰٬۰۰۰ محصول یا متغیر پیدا شد. برای اجرای مطمئن، فیلتر دسته‌بندی را محدودتر کنید.' );
			}
		}

		return array(
			'items'           => $items,
			'sample'          => $sample,
			'skip_reasons'    => $skip_reasons,
			'candidate_count' => $candidate_count,
			'eligible_count'  => count( $items ),
		);
	}

	/** Add an item to the plan or count why it cannot be changed. */
	private static function collect_preview_item( $result, &$items, &$sample, &$skip_reasons ) {
		if ( is_wp_error( $result ) ) {
			$reason = $result->get_error_code();
			if ( ! isset( $skip_reasons[ $reason ] ) ) {
				$skip_reasons[ $reason ] = array( 'label' => $result->get_error_message(), 'count' => 0 );
			}
			$skip_reasons[ $reason ]['count']++;
			return;
		}

		$items[] = $result;
		if ( count( $sample ) < 8 ) {
			$sample[] = $result;
		}
	}

	/** Prepare old and new values for one simple product or one variation. */
	private static function plan_item( $product, $parent_id, $criteria ) {
		$regular = self::canonical_price( $product->get_regular_price( 'edit' ) );
		$sale    = self::canonical_price( $product->get_sale_price( 'edit' ) );
		$target  = $criteria['target'];

		if ( in_array( $target, array( 'regular', 'both' ), true ) && '' === $regular ) {
			return new WP_Error( 'missing_regular', 'قیمت عادی ندارد' );
		}
		if ( in_array( $target, array( 'sale', 'both' ), true ) && 'sale' === $target && '' === $sale ) {
			return new WP_Error( 'missing_sale', 'قیمت فروش ویژه ندارد' );
		}
		if ( 'sale' === $target && '' === $regular ) {
			return new WP_Error( 'missing_regular_for_sale', 'برای کنترل قیمت فروش ویژه، قیمت عادی ندارد' );
		}
		if ( 'both' === $target && '' !== $sale && (float) $sale > (float) $regular ) {
			return new WP_Error( 'invalid_existing_sale', 'قیمت فروش ویژه از قیمت عادی بیشتر است' );
		}

		$after_regular = $regular;
		$after_sale    = $sale;
		$regular_changed = in_array( $target, array( 'regular', 'both' ), true );
		$sale_changed    = ( 'sale' === $target ) || ( 'both' === $target && '' !== $sale );

		if ( $regular_changed ) {
			$after_regular = self::adjust_price( $regular, $criteria['direction'], $criteria['percent'] );
		}
		if ( $sale_changed ) {
			$after_sale = self::adjust_price( $sale, $criteria['direction'], $criteria['percent'] );
		}

		if ( 'regular' === $target && '' !== $sale && (float) $sale > (float) $after_regular ) {
			return new WP_Error( 'sale_would_exceed_regular', 'با قیمت فعلی فروش ویژه، قیمت عادی جدید معتبر نمی‌ماند' );
		}
		if ( $sale_changed && '' !== $after_regular && (float) $after_sale > (float) $after_regular ) {
			return new WP_Error( 'sale_exceeds_regular', 'قیمت فروش ویژه جدید از قیمت عادی بیشتر می‌شود' );
		}
		if ( ( $regular_changed && self::same_price( $regular, $after_regular ) ) && ( ! $sale_changed || self::same_price( $sale, $after_sale ) ) ) {
			return new WP_Error( 'rounds_to_same', 'پس از گرد کردن، قیمت تغییر نمی‌کند' );
		}

		$label = $product->get_name();
		if ( $product->is_type( 'variation' ) ) {
			$attributes = wc_get_formatted_variation( $product, true, true );
			$label     .= ' — ' . ( $attributes ? $attributes : 'متغیر' ) . ' (#' . $product->get_id() . ')';
		}

		return array(
			'id'               => $product->get_id(),
			'parent_id'        => absint( $parent_id ),
			'label'            => $label,
			'before_regular'   => $regular,
			'before_sale'      => $sale,
			'after_regular'    => $after_regular,
			'after_sale'       => $after_sale,
			'regular_changed'  => $regular_changed,
			'sale_changed'     => $sale_changed,
			'guard_regular'    => ! $regular_changed,
			'guard_sale'       => ! $sale_changed,
			'forward_state'    => 'pending',
			'undo_state'       => 'pending',
		);
	}

	/** Apply a percentage and round using the store's WooCommerce precision. */
	private static function adjust_price( $price, $direction, $percent ) {
		$factor = ( 'increase' === $direction ) ? ( 1 + ( $percent / 100 ) ) : ( 1 - ( $percent / 100 ) );
		$value  = max( 0, (float) $price * $factor );
		return self::canonical_price( wc_format_decimal( round( $value, wc_get_price_decimals(), PHP_ROUND_HALF_UP ), wc_get_price_decimals() ) );
	}

	/** Normalize stored price strings consistently for preview checks. */
	private static function canonical_price( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		return wc_format_decimal( $value, wc_get_price_decimals(), true );
	}

	/** Compare normalized decimal price strings. */
	private static function same_price( $left, $right ) {
		return self::canonical_price( $left ) === self::canonical_price( $right );
	}

	/** Render preview values, exclusions, and actions for the current job. */
	private static function render_job( $token, $job ) {
		$criteria = $job['criteria'];
		echo '<hr><h2>پیش‌نمایش قیمت‌ها</h2>';
		echo '<p>' . esc_html( self::describe_criteria( $criteria ) ) . '</p>';
		echo '<p>قابل تغییر: <strong>' . esc_html( number_format_i18n( $job['eligible_count'] ) ) . '</strong> مورد از ' . esc_html( number_format_i18n( $job['candidate_count'] ) ) . ' محصول/متغیر بررسی‌شده. برای محصولات متغیر، هر متغیر یک مورد مستقل است.</p>';

		if ( ! empty( $job['sample'] ) ) {
			echo '<table class="widefat striped"><thead><tr><th>محصول یا متغیر</th><th>قیمت عادی قبل</th><th>قیمت ویژه قبل</th><th>قیمت عادی بعد</th><th>قیمت ویژه بعد</th></tr></thead><tbody>';
			foreach ( $job['sample'] as $item ) {
				echo '<tr><td>' . esc_html( $item['label'] ) . '</td>';
				self::price_cell( $item['before_regular'] );
				self::price_cell( $item['before_sale'] );
				self::price_cell( $item['after_regular'] );
				self::price_cell( $item['after_sale'] );
				echo '</tr>';
			}
			echo '</tbody></table><p class="description">نمونه بالا فقط چند مورد اول را نشان می‌دهد؛ تعداد کامل در خلاصه آمده است.</p>';
		}

		if ( ! empty( $job['skip_reasons'] ) ) {
			echo '<details><summary>مواردی که از پیش‌نمایش کنار گذاشته شدند (' . esc_html( number_format_i18n( $job['candidate_count'] - $job['eligible_count'] ) ) . ')</summary><ul>';
			foreach ( $job['skip_reasons'] as $reason ) {
				echo '<li>' . esc_html( $reason['label'] ) . ': ' . esc_html( number_format_i18n( $reason['count'] ) ) . '</li>';
			}
			echo '</ul></details>';
		}

		if ( 'previewed' === $job['status'] ) {
			self::render_job_button( $token, 'forward', 'اجرای تغییرات (مرحله اول)', 'primary' );
		} elseif ( 'running' === $job['status'] ) {
			self::render_job_progress( $job );
			self::render_job_button( $token, 'forward', 'ادامه اجرای تغییرات', 'primary' );
		} elseif ( 'completed' === $job['status'] ) {
			self::render_job_progress( $job );
			if ( 'available' === $job['undo_status'] ) {
				self::render_job_button( $token, 'undo', 'بازگرداندن همین تغییرات', 'secondary' );
			} elseif ( 'running' === $job['undo_status'] ) {
				self::render_undo_progress( $job );
				self::render_job_button( $token, 'undo', 'ادامه بازگردانی', 'secondary' );
			} elseif ( 'completed' === $job['undo_status'] ) {
				self::render_undo_progress( $job );
			}
		}
	}

	/** Render a formatted price cell. */
	private static function price_cell( $value ) {
		if ( '' === $value || null === $value ) {
			echo '<td>—</td>';
			return;
		}
		echo '<td>' . wp_kses_post( wc_price( (float) $value ) ) . '</td>';
	}

	/** Describe the exact filter and adjustment for the preview. */
	private static function describe_criteria( $criteria ) {
		$type_labels = array( 'all' => 'محصولات ساده و متغیر', 'simple' => 'محصولات ساده', 'variable' => 'محصولات متغیر' );
		$target_labels = array( 'regular' => 'قیمت عادی', 'sale' => 'قیمت فروش ویژه', 'both' => 'قیمت عادی و فروش ویژه' );
		$direction = 'increase' === $criteria['direction'] ? 'افزایش' : 'کاهش';
		$category = 'همه دسته‌ها';
		if ( $criteria['category_id'] ) {
			$term = get_term( $criteria['category_id'], 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$category = $term->name;
			}
		}
		return sprintf(
			'%1$s، دسته «%2$s»؛ %3$s %4$s درصد روی %5$s.',
			$type_labels[ $criteria['product_type'] ],
			$category,
			$direction,
			number_format_i18n( $criteria['percent'], 2 ),
			$target_labels[ $criteria['target'] ]
		);
	}

	/** Render an explicit POST action tied to the current user and job. */
	private static function render_job_button( $token, $mode, $label, $style ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:12px 8px 12px 0">';
		echo '<input type="hidden" name="action" value="wvbpe_apply"><input type="hidden" name="job" value="' . esc_attr( $token ) . '"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '">';
		wp_nonce_field( 'wvbpe_' . $mode . '_' . $token );
		submit_button( $label, $style, 'submit', false );
		echo '</form>';
	}

	/** Show forward-operation totals. */
	private static function render_job_progress( $job ) {
		echo '<p>وضعیت: ' . ( 'completed' === $job['status'] ? 'پایان یافته' : 'در حال اجرا' ) . '؛ انجام‌شده: ' . esc_html( number_format_i18n( $job['applied'] ) ) . '؛ قیمت‌های تغییرکرده بعد از پیش‌نمایش: ' . esc_html( number_format_i18n( $job['stale'] ) ) . '؛ خطاها: ' . esc_html( number_format_i18n( $job['failed'] ) ) . '.</p>';
	}

	/** Show rollback totals. */
	private static function render_undo_progress( $job ) {
		echo '<p>بازگردانی: ' . ( 'completed' === $job['undo_status'] ? 'پایان یافته' : 'در حال اجرا' ) . '؛ بازگردانده‌شده: ' . esc_html( number_format_i18n( $job['undo_restored'] ) ) . '؛ تغییرهای جدیدی که برای حفظشان بازگردانده نشدند: ' . esc_html( number_format_i18n( $job['undo_conflicts'] ) ) . '.</p>';
	}

	/** Apply one safe batch or begin/resume an undo. */
	public static function handle_apply() {
		$token = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
		$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) || ! in_array( $mode, array( 'forward', 'undo' ), true ) ) {
			self::redirect_notice( 'درخواست معتبر نیست. دوباره پیش‌نمایش بگیرید.', 'error' );
		}

		self::authorize_request();
		check_admin_referer( 'wvbpe_' . $mode . '_' . $token );
		$job = get_transient( self::job_key( $token ) );
		if ( ! is_array( $job ) || (int) $job['user_id'] !== get_current_user_id() ) {
			self::redirect_notice( 'پیش‌نمایش منقضی شده است. دوباره پیش‌نمایش بگیرید.', 'error' );
		}

		if ( 'forward' === $mode ) {
			if ( ! in_array( $job['status'], array( 'previewed', 'running' ), true ) ) {
				self::redirect_notice( 'این پیش‌نمایش قبلاً اجرا شده یا دیگر قابل اجرا نیست.', 'error' );
			}
			$job['status'] = 'running';
			$job           = self::process_batch( $job, false );
			if ( $job['cursor'] >= count( $job['items'] ) ) {
				$job['status'] = 'completed';
			}
		} else {
			if ( 'completed' !== $job['status'] || ! in_array( $job['undo_status'], array( 'available', 'running' ), true ) ) {
				self::redirect_notice( 'بازگردانی برای این عملیات در دسترس نیست.', 'error' );
			}
			if ( 'available' === $job['undo_status'] ) {
				$job['undo_status'] = 'running';
			}
			$job = self::process_batch( $job, true );
			if ( $job['undo_cursor'] >= count( $job['items'] ) ) {
				$job['undo_status'] = 'completed';
			}
		}

		set_transient( self::job_key( $token ), $job, self::JOB_LIFETIME );
		wp_safe_redirect( self::page_url( array( 'job' => $token ) ) );
		exit;
	}

	/** Process up to 25 previewed product/variation records in one request. */
	private static function process_batch( $job, $undo ) {
		$cursor_key = $undo ? 'undo_cursor' : 'cursor';
		$end        = min( count( $job['items'] ), $job[ $cursor_key ] + self::BATCH_SIZE );
		$parents    = array();

		for ( $index = $job[ $cursor_key ]; $index < $end; $index++ ) {
			$item = $job['items'][ $index ];
			if ( $undo && ! in_array( $item['forward_state'], array( 'applied', 'partial' ), true ) ) {
				$job['items'][ $index ]['undo_state'] = 'skipped';
				continue;
			}
			if ( ! $undo && 'pending' !== $item['forward_state'] ) {
				continue;
			}

			$product = wc_get_product( $item['id'] );
			if ( ! $product ) {
				if ( $undo ) {
					$job['items'][ $index ]['undo_state'] = 'conflict';
					$job['undo_conflicts']++;
				} else {
					$job['items'][ $index ]['forward_state'] = 'failed';
					$job['failed']++;
				}
				continue;
			}

			$to_regular   = $undo ? $item['before_regular'] : $item['after_regular'];
			$to_sale      = $undo ? $item['before_sale'] : $item['after_sale'];

			if ( self::matches_item_prices( $product, $item, $to_regular, $to_sale ) ) {
				if ( $undo ) {
					$job['items'][ $index ]['undo_state'] = 'restored';
					$job['undo_restored']++;
				} else {
					$job['items'][ $index ]['forward_state'] = 'applied';
					$job['applied']++;
				}
				continue;
			}
			if ( ! self::matches_item_transition( $product, $item ) ) {
				if ( $undo ) {
					$job['items'][ $index ]['undo_state'] = 'conflict';
					$job['undo_conflicts']++;
				} else {
					$job['items'][ $index ]['forward_state'] = 'stale';
					$job['stale']++;
				}
				continue;
			}

			try {
				if ( $item['regular_changed'] ) {
					$product->set_regular_price( $to_regular );
				}
				if ( $item['sale_changed'] ) {
					$product->set_sale_price( $to_sale );
				}
				$product->save();
				$saved = wc_get_product( $item['id'] );
				if ( ! $saved || ! self::matches_item_prices( $saved, $item, $to_regular, $to_sale ) ) {
					throw new RuntimeException( 'WooCommerce did not confirm the saved prices.' );
				}
				if ( $undo ) {
					$job['items'][ $index ]['undo_state'] = 'restored';
					$job['undo_restored']++;
				} else {
					$job['items'][ $index ]['forward_state'] = 'applied';
					$job['applied']++;
				}
				if ( ! empty( $item['parent_id'] ) ) {
					$parents[ $item['parent_id'] ] = true;
				}
			} catch ( Throwable $error ) {
				$saved = wc_get_product( $item['id'] );
				if ( $saved && self::matches_item_prices( $saved, $item, $to_regular, $to_sale ) ) {
					if ( $undo ) {
						$job['items'][ $index ]['undo_state'] = 'restored';
						$job['undo_restored']++;
					} else {
						$job['items'][ $index ]['forward_state'] = 'applied';
						$job['applied']++;
					}
				} elseif ( $undo ) {
					$job['items'][ $index ]['undo_state'] = 'error';
					$job['undo_conflicts']++;
				} else {
					$before_matches = $saved && self::matches_item_prices( $saved, $item, $item['before_regular'], $item['before_sale'] );
					$job['items'][ $index ]['forward_state'] = ( $saved && ! $before_matches && self::matches_item_transition( $saved, $item ) ) ? 'partial' : 'failed';
					$job['failed']++;
				}
			}
		}

		foreach ( array_keys( $parents ) as $parent_id ) {
			WC_Product_Variable::sync( $parent_id );
			wc_delete_product_transients( $parent_id );
		}

		$job[ $cursor_key ] = $end;
		return $job;
	}

	/** Check only the price fields this job intends to change. */
	private static function matches_item_prices( $product, $item, $regular, $sale ) {
		if ( $item['regular_changed'] && ! self::same_price( $product->get_regular_price( 'edit' ), $regular ) ) {
			return false;
		}
		if ( $item['sale_changed'] && ! self::same_price( $product->get_sale_price( 'edit' ), $sale ) ) {
			return false;
		}
		if ( $item['guard_regular'] && ! self::same_price( $product->get_regular_price( 'edit' ), $item['before_regular'] ) ) {
			return false;
		}
		if ( $item['guard_sale'] && ! self::same_price( $product->get_sale_price( 'edit' ), $item['before_sale'] ) ) {
			return false;
		}
		return true;
	}

	/** Allow safe recovery when a request saved only part of a product's price fields. */
	private static function matches_item_transition( $product, $item ) {
		if ( $item['guard_regular'] && ! self::same_price( $product->get_regular_price( 'edit' ), $item['before_regular'] ) ) {
			return false;
		}
		if ( $item['guard_sale'] && ! self::same_price( $product->get_sale_price( 'edit' ), $item['before_sale'] ) ) {
			return false;
		}

		if ( $item['regular_changed'] ) {
			$current = self::canonical_price( $product->get_regular_price( 'edit' ) );
			if ( ! self::same_price( $current, $item['before_regular'] ) && ! self::same_price( $current, $item['after_regular'] ) ) {
				return false;
			}
		}
		if ( $item['sale_changed'] ) {
			$current = self::canonical_price( $product->get_sale_price( 'edit' ) );
			if ( ! self::same_price( $current, $item['before_sale'] ) && ! self::same_price( $current, $item['after_sale'] ) ) {
				return false;
			}
		}
		return true;
	}

	/** Store a per-user notice and return to the plugin page. */
	private static function redirect_notice( $message, $type = 'success' ) {
		set_transient(
			'wvbpe_notice_' . get_current_user_id(),
			array( 'message' => $message, 'type' => $type ),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/** Generate an admin URL on this site. */
	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/** Derive an opaque transient key from the temporary job token. */
	private static function job_key( $token ) {
		return 'wvbpe_job_' . hash( 'sha256', $token );
	}
}

WVBPE_Plugin::init();
