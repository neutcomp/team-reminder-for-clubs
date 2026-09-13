<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NEUTCOMP_TER_Reminder_Shortcode {
	const STYLE_HANDLE = 'neutcomp-reminder-shortcode';

	public static function init() {
		add_shortcode( 'neutcomp-schedule', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_register_style( self::STYLE_HANDLE, NEUTCOMP_TER_URL . 'assets/css/shortcode.css', array(), NEUTCOMP_TER_VERSION );

		$post = is_singular() ? get_post() : null;

		if ( $post && has_shortcode( $post->post_content, 'neutcomp-schedule' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	public static function render( $atts ) {
		$atts        = shortcode_atts( array( 'split' => 'false', 'dateformat' => 'long' ), $atts, 'neutcomp-schedule' );
		$split       = 'true' === strtolower( (string) $atts['split'] );
		$date_format = 'short' === strtolower( (string) $atts['dateformat'] ) ? 'short' : 'long';
		$reminder_ids = get_posts(
			array(
				'post_type'      => NEUTCOMP_TER_Reminder_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$reminders = array();

		foreach ( $reminder_ids as $reminder_id ) {
			$reminder = NEUTCOMP_TER_Reminder_Post_Type::get( $reminder_id );
			$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', $reminder['date'], wp_timezone() );

			if ( ! $date || $date->format( 'Y-m-d' ) !== $reminder['date'] ) {
				continue;
			}

			$reminder['date_object'] = $date;
			$reminders[]             = $reminder;
		}

		usort(
			$reminders,
			static function ( $first, $second ) {
				$date_comparison = $first['date_object']->getTimestamp() <=> $second['date_object']->getTimestamp();

				return $date_comparison ?: strcasecmp( $first['name'], $second['name'] );
			}
		);
		$columns = $split ? array_chunk( $reminders, (int) ceil( count( $reminders ) / 2 ) ) : array( $reminders );

		ob_start();
		?>
		<table class="neutcomp-schedule-table<?php echo $split ? ' neutcomp-schedule-table-split' : ''; ?>">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'team-reminder-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Team', 'team-reminder-for-clubs' ); ?></th>
					<?php if ( $split ) : ?>
						<th><?php esc_html_e( 'Date', 'team-reminder-for-clubs' ); ?></th>
						<th><?php esc_html_e( 'Team', 'team-reminder-for-clubs' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $reminders ) : ?>
				<tr><td colspan="<?php echo $split ? '4' : '2'; ?>"><?php esc_html_e( 'No team duties found.', 'team-reminder-for-clubs' ); ?></td></tr>
			<?php elseif ( $split ) : ?>
				<?php foreach ( $columns[0] as $index => $reminder ) : ?>
					<?php $second = isset( $columns[1][ $index ] ) ? $columns[1][ $index ] : null; ?>
					<tr>
						<td><?php echo esc_html( self::format_date( $reminder['date_object'], $date_format ) ); ?></td>
						<td><?php echo esc_html( $reminder['name'] ); ?></td>
						<td><?php echo $second ? esc_html( self::format_date( $second['date_object'], $date_format ) ) : ''; ?></td>
						<td><?php echo $second ? esc_html( $second['name'] ) : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : foreach ( $reminders as $reminder ) : ?>
				<tr>
					<td><?php echo esc_html( self::format_date( $reminder['date_object'], $date_format ) ); ?></td>
					<td><?php echo esc_html( $reminder['name'] ); ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	private static function format_date( $date, $format ) {
		if ( 'short' === $format ) {
			return $date->format( 'd-m-Y' );
		}

		return wp_date( 'l j F', $date->getTimestamp(), wp_timezone() );
	}
}