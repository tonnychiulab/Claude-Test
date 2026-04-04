<?php
/**
 * WUAT Admin — audit log list table and admin menu.
 *
 * @package WUAT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not auto-loaded outside the admin context.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WUAT_Admin
 *
 * Registers the top-level "Audit Trail" menu and renders a WP_List_Table with
 * filtering, sorting, and CSV-export via AJAX.
 */
class WUAT_Admin {

	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wuat_export_csv',    array( $this, 'ajax_export_csv' ) );
		add_action( 'wp_ajax_wuat_delete_logs',   array( $this, 'ajax_delete_logs' ) );
	}

	// ── Menu ─────────────────────────────────────────────────────────────────

	public function add_menu() {
		add_menu_page(
			__( 'Audit Trail', 'wp-unauthorized-access-tracker' ),
			__( 'Audit Trail', 'wp-unauthorized-access-tracker' ),
			'manage_options',
			'wuat-audit-trail',
			array( $this, 'render_log_page' ),
			'dashicons-shield-alt',
			80
		);
	}

	// ── Log list page ─────────────────────────────────────────────────────────

	public function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new WUAT_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit Trail', 'wp-unauthorized-access-tracker' ); ?></h1>

			<?php $this->render_filter_form(); ?>

			<form method="post">
				<?php
				wp_nonce_field( 'wuat_bulk_action', 'wuat_nonce' );
				$table->display();
				?>
			</form>

			<div class="wuat-export-bar">
				<button type="button" id="wuat-export-csv" class="button button-secondary">
					<?php esc_html_e( 'Export CSV', 'wp-unauthorized-access-tracker' ); ?>
				</button>
				<span id="wuat-export-status"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the search/filter form above the table.
	 */
	private function render_filter_form() {
		// Read-only filter form — nonce not required for GET search/filter requests.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$event_type = sanitize_key( wp_unslash( $_GET['event_type'] ?? '' ) );
		$user_login = sanitize_user( wp_unslash( $_GET['user_login'] ?? '' ) );
		$ip_address = sanitize_text_field( wp_unslash( $_GET['ip_address'] ?? '' ) );
		$date_from  = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
		$date_to    = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$event_types = array(
			''               => __( 'All Events', 'wp-unauthorized-access-tracker' ),
			'login_success'  => __( 'Login Success', 'wp-unauthorized-access-tracker' ),
			'login_failed'   => __( 'Login Failed', 'wp-unauthorized-access-tracker' ),
			'logout'         => __( 'Logout', 'wp-unauthorized-access-tracker' ),
			'lockout'        => __( 'Lockout', 'wp-unauthorized-access-tracker' ),
			'offhours_login' => __( 'Off-Hours Login', 'wp-unauthorized-access-tracker' ),
		);
		?>
		<div class="wuat-filter-bar">
			<form method="get">
				<input type="hidden" name="page" value="wuat-audit-trail">
				<select name="event_type">
					<?php foreach ( $event_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_type, $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="user_login" placeholder="<?php esc_attr_e( 'Username', 'wp-unauthorized-access-tracker' ); ?>" value="<?php echo esc_attr( $user_login ); ?>">
				<input type="text" name="ip_address" placeholder="<?php esc_attr_e( 'IP Address', 'wp-unauthorized-access-tracker' ); ?>" value="<?php echo esc_attr( $ip_address ); ?>">
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
				<span>–</span>
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
				<?php submit_button( __( 'Filter', 'wp-unauthorized-access-tracker' ), 'secondary', '', false ); ?>
				<?php if ( $event_type || $user_login || $ip_address || $date_from || $date_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wuat-audit-trail' ) ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'wp-unauthorized-access-tracker' ); ?>
					</a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wuat-audit-trail' !== $hook
			&& 'audit-trail_page_wuat-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wuat-admin-css',
			WUAT_PLUGIN_URL . 'assets/css/wuat-admin.css',
			array(),
			WUAT_VERSION
		);

		wp_enqueue_script(
			'wuat-admin-js',
			WUAT_PLUGIN_URL . 'assets/js/wuat-admin.js',
			array( 'jquery' ),
			WUAT_VERSION,
			true
		);

		wp_localize_script(
			'wuat-admin-js',
			'wuat_ajax',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wuat_admin_nonce' ),
				'export_text'  => __( 'Exporting…', 'wp-unauthorized-access-tracker' ),
				'done_text'    => __( 'Export complete.', 'wp-unauthorized-access-tracker' ),
				'error_text'   => __( 'Export failed.', 'wp-unauthorized-access-tracker' ),
				/* translators: %d = number of items selected for deletion */
				'confirm_text' => __( '%d item(s) will be permanently deleted. Continue?', 'wp-unauthorized-access-tracker' ),
				// Pass current filters so JS can send them with the export request.
				// phpcs:disable WordPress.Security.NonceVerification.Recommended
				'filters'      => array(
					'event_type' => sanitize_key( wp_unslash( $_GET['event_type'] ?? '' ) ),
					'user_login' => sanitize_user( wp_unslash( $_GET['user_login'] ?? '' ) ),
					'ip_address' => sanitize_text_field( wp_unslash( $_GET['ip_address'] ?? '' ) ),
					'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
					'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
				),
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	// ── AJAX: CSV export ──────────────────────────────────────────────────────

	public function ajax_export_csv() {
		check_ajax_referer( 'wuat_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'wp-unauthorized-access-tracker' ) ),
				403
			);
		}

		$args = array(
			'event_type' => sanitize_key( wp_unslash( $_POST['event_type'] ?? '' ) ),
			'user_login' => sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ) ),
			'ip_address' => sanitize_text_field( wp_unslash( $_POST['ip_address'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) ),
			'per_page'   => 50000,
			'offset'     => 0,
		);

		$logs = WUAT_Query::get_logs( $args );

		// Build CSV rows.
		$rows   = array();
		$header = array( 'ID', 'Event Type', 'Username', 'User ID', 'IP Address', 'User Agent', 'Time', 'Details' );
		$rows[] = implode( ',', array_map( array( $this, 'csv_escape' ), $header ) );

		foreach ( $logs as $row ) {
			$rows[] = implode( ',', array_map( array( $this, 'csv_escape' ), array(
				$row->id,
				$row->event_type,
				$row->user_login,
				$row->user_id,
				$row->ip_address,
				$row->user_agent,
				$row->event_time,
				$row->details,
			) ) );
		}

		$csv = implode( "\r\n", $rows );

		wp_send_json_success( array( 'csv' => $csv ) );
	}

	// ── AJAX: Bulk delete ─────────────────────────────────────────────────────

	public function ajax_delete_logs() {
		check_ajax_referer( 'wuat_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'wp-unauthorized-access-tracker' ) ),
				403
			);
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No items selected.', 'wp-unauthorized-access-tracker' ) ) );
		}

		global $wpdb;
		$table       = $wpdb->prefix . WUAT_TABLE_NAME;
		$placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholder})", ...$ids )
		);

		wp_send_json_success( array( 'deleted' => count( $ids ) ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Escape a single CSV cell value.
	 *
	 * @param string $value Raw value.
	 * @return string Quoted value.
	 */
	private function csv_escape( $value ) {
		$value = (string) $value;
		// Prefix cells that start with =, +, -, @ to prevent CSV injection.
		if ( preg_match( '/^[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}
		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}

// ── Query helper (used by both Admin and List Table) ─────────────────────────

/**
 * Class WUAT_Query
 *
 * Provides a single, safe DB query method shared between the list table,
 * the CSV export, and any custom integration.
 */
class WUAT_Query {

	/**
	 * Fetch audit-log rows with optional filters.
	 *
	 * @param array $args {
	 *     @type string $event_type  Event type filter.
	 *     @type string $user_login  Username filter.
	 *     @type string $ip_address  IP filter.
	 *     @type string $date_from   Start date (Y-m-d).
	 *     @type string $date_to     End date (Y-m-d).
	 *     @type int    $per_page    Rows per page.
	 *     @type int    $offset      Row offset.
	 *     @type string $orderby     Column to sort by.
	 *     @type string $order       ASC|DESC.
	 * }
	 * @return array
	 */
	public static function get_logs( array $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . WUAT_TABLE_NAME;

		$defaults = array(
			'event_type' => '',
			'user_login' => '',
			'ip_address' => '',
			'date_from'  => '',
			'date_to'    => '',
			'per_page'   => 20,
			'offset'     => 0,
			'orderby'    => 'event_time',
			'order'      => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		// Whitelist orderby / order to prevent SQL injection.
		$allowed_orderby = array( 'id', 'event_time', 'event_type', 'user_login', 'ip_address' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'event_time';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_key( $args['event_type'] );
		}
		if ( ! empty( $args['user_login'] ) ) {
			$where[]  = 'user_login = %s';
			$values[] = sanitize_user( $args['user_login'] );
		}
		if ( ! empty( $args['ip_address'] ) ) {
			$where[]  = 'ip_address = %s';
			$values[] = sanitize_text_field( $args['ip_address'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'event_time >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'event_time <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );
		$per_page     = absint( $args['per_page'] );
		$offset       = absint( $args['offset'] );

		// Table name from $wpdb->prefix (trusted); orderby/order whitelisted above;
		// per_page/offset are absint(). User-supplied values go through prepare() below.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count total rows matching the same filters (for pagination).
	 *
	 * @param array $args Same filter keys as get_logs(), per_page/offset ignored.
	 * @return int
	 */
	public static function count_logs( array $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . WUAT_TABLE_NAME;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_key( $args['event_type'] );
		}
		if ( ! empty( $args['user_login'] ) ) {
			$where[]  = 'user_login = %s';
			$values[] = sanitize_user( $args['user_login'] );
		}
		if ( ! empty( $args['ip_address'] ) ) {
			$where[]  = 'ip_address = %s';
			$values[] = sanitize_text_field( $args['ip_address'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'event_time >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'event_time <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_clause}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$sql = $wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->get_var( $sql );
	}
}

// ── WP_List_Table subclass ────────────────────────────────────────────────────

/**
 * Class WUAT_List_Table
 *
 * Extends WP_List_Table to render the audit log with sortable columns,
 * pagination, and per-row detail expansion.
 */
class WUAT_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'log entry', 'wp-unauthorized-access-tracker' ),
				'plural'   => __( 'log entries', 'wp-unauthorized-access-tracker' ),
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox">',
			'event_type' => __( 'Event', 'wp-unauthorized-access-tracker' ),
			'user_login' => __( 'Username', 'wp-unauthorized-access-tracker' ),
			'ip_address' => __( 'IP Address', 'wp-unauthorized-access-tracker' ),
			'user_agent' => __( 'User Agent', 'wp-unauthorized-access-tracker' ),
			'event_time' => __( 'Time', 'wp-unauthorized-access-tracker' ),
			'details'    => __( 'Details', 'wp-unauthorized-access-tracker' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'event_type' => array( 'event_type', false ),
			'user_login' => array( 'user_login', false ),
			'ip_address' => array( 'ip_address', false ),
			'event_time' => array( 'event_time', true ), // default sort
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'wp-unauthorized-access-tracker' ),
		);
	}

	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Read-only filter — nonce not required for GET search/sort requests.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'event_type' => sanitize_key( wp_unslash( $_GET['event_type'] ?? '' ) ),
			'user_login' => sanitize_user( wp_unslash( $_GET['user_login'] ?? '' ) ),
			'ip_address' => sanitize_text_field( wp_unslash( $_GET['ip_address'] ?? '' ) ),
			'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
			'per_page'   => $per_page,
			'offset'     => $offset,
			'orderby'    => sanitize_key( wp_unslash( $_GET['orderby'] ?? 'event_time' ) ),
			'order'      => ( 'asc' === sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ) ) ? 'ASC' : 'DESC',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$total        = WUAT_Query::count_logs( $args );
		$this->items  = WUAT_Query::get_logs( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d">', absint( $item->id ) );
	}

	protected function column_event_type( $item ) {
		$icons = array(
			'login_success'  => '🟢',
			'login_failed'   => '🔴',
			'logout'         => '🔵',
			'lockout'        => '⛔',
			'offhours_login' => '🌙',
		);
		$icon  = $icons[ $item->event_type ] ?? '⬜';
		$label = $this->event_type_label( $item->event_type );
		$class = 'wuat-event-' . esc_attr( $item->event_type );
		return sprintf( '<span class="wuat-event-badge %s">%s %s</span>', $class, $icon, esc_html( $label ) );
	}

	private function event_type_label( $type ) {
		$labels = array(
			'login_success'  => __( 'Login Success', 'wp-unauthorized-access-tracker' ),
			'login_failed'   => __( 'Login Failed', 'wp-unauthorized-access-tracker' ),
			'logout'         => __( 'Logout', 'wp-unauthorized-access-tracker' ),
			'lockout'        => __( 'Lockout', 'wp-unauthorized-access-tracker' ),
			'offhours_login' => __( 'Off-Hours Login', 'wp-unauthorized-access-tracker' ),
		);
		return $labels[ $type ] ?? esc_html( $type );
	}

	protected function column_user_login( $item ) {
		return esc_html( $item->user_login );
	}

	protected function column_ip_address( $item ) {
		$ip       = esc_html( $item->ip_address );
		$settings = get_option( 'wuat_settings', array() );

		if ( ! empty( $settings['show_ip_lookup_link'] ) ) {
			$url = esc_url( 'https://ipinfo.io/' . rawurlencode( $item->ip_address ) );
			return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $ip . '</a>';
		}

		return '<span class="wuat-ip">' . $ip . '</span>';
	}

	protected function column_user_agent( $item ) {
		$ua = esc_html( substr( $item->user_agent, 0, 80 ) );
		if ( strlen( $item->user_agent ) > 80 ) {
			$ua .= '…';
		}
		return '<span title="' . esc_attr( $item->user_agent ) . '">' . $ua . '</span>';
	}

	protected function column_event_time( $item ) {
		return esc_html( $item->event_time );
	}

	protected function column_details( $item ) {
		if ( empty( $item->details ) ) {
			return '—';
		}
		$data = json_decode( $item->details, true );
		if ( ! is_array( $data ) ) {
			return esc_html( $item->details );
		}
		$html = '<dl class="wuat-details">';
		foreach ( $data as $k => $v ) {
			$v     = is_array( $v ) ? implode( ', ', $v ) : $v;
			$html .= '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( (string) $v ) . '</dd>';
		}
		$html .= '</dl>';
		return $html;
	}

	public function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}
}
