<?php

/**
 * Form Performance endpoint
 *
 * @package Give
 */

namespace Give\API\Endpoints\Reports;

class FormPerformance extends Endpoint {

	protected $payments;

	public function __construct() {
		$this->endpoint = 'form-performance';
	}

	public function get_report( $request ) {

		// // Check if a cached version exists
		// $cached_report = $this->get_cached_report( $request );
		// if ( $cached_report !== null ) {
		// Bail and return the cached version
		// return new \WP_REST_Response(
		// [
		// 'data' => $cached_report,
		// ]
		// );
		// }

		$start = date_create( $request['start'] );
		$end   = date_create( $request['end'] );
		$diff  = date_diff( $start, $end );

		$data = $this->get_data( $start, $end );

		// Cache the report data
		$result = $this->cache_report( $request, $data );

		return new \WP_REST_Response(
			[
				'data' => $data,
			]
		);
	}

	public function get_data( $start, $end ) {

		$this->payments = $this->get_payments( $start->format( 'Y-m-d H:i:s' ), $end->format( 'Y-m-d H:i:s' ), 'date', -1 );

		$forms    = [];
		$labels   = [];
		$tooltips = [];

		foreach ( $this->payments as $payment ) {
			if ( $payment->status === 'publish' ) {
				$forms[ $payment->form_id ]['income']    += $payment->total;
				$forms[ $payment->form_id ]['donations'] += 1;
				$forms[ $payment->form_id ]['title']      = $payment->form_title;
			}
		}

		$sorted = usort( $forms, [ $this, 'compare_forms' ] );

		if ( $sorted === true ) {
			$forms = array_slice( $forms, 0, 5 );

			foreach ( $forms as $key => $value ) {
				$tooltips[]    = [
					'title'  => give_currency_filter( give_format_amount( $value['income'] ), [ 'decode_currency' => true ] ),
					'body'   => $value['donations'] . ' ' . __( 'Donations', 'give' ),
					'footer' => $value['title'],
				];
				$labels[]      = $value['title'];
				$forms[ $key ] = $value['income'];
			}

			$forms = array_values( $forms );
		}

		// Create data objec to be returned, with 'highlights' object containing total and average figures to display
		$data = [
			'datasets' => [
				[
					'data'     => $forms,
					'tooltips' => $tooltips,
					'labels'   => $labels,
				],
			],
		];

		return $data;

	}

	public function compare_forms( $a, $b ) {
		if ( $a['income'] == $b['income'] ) {
				return 0;
		}
		return ( $a['income'] > $b['income'] ) ? -1 : 1;
	}
}
