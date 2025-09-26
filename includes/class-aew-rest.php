
<?php
/**
 * REST API layer for Advenir Eligibility Wizard.
 *
 * @package AdvenirEligibilityWizard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AEW_REST
 */
class AEW_REST {

	/**
	 * Singleton instance.
	 *
	 * @var AEW_REST|null
	 */
	private static $instance = null;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'aew/v1';

	/**
	 * Retrieve singleton instance.
	 *
	 * @return AEW_REST
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * AEW_REST constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/config',
			array(
				'args'                => array(),
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/evaluate',
			array(
				'args'                => array(),
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'evaluate_answers' ),
				'permission_callback' => array( $this, 'validate_nonce' ),
			)
		);
	}

	/**
	 * Return the active configuration for the wizard.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response
	 */
	public function get_config( WP_REST_Request $request ) {
		$rules = AEW_Admin::get_rules();

		$response = array(
			'questions' => isset( $rules['questions'] ) ? array_values( $rules['questions'] ) : array(),
			'results'   => isset( $rules['results'] ) ? $rules['results'] : array(),
			'meta'      => array(
				'last_updated' => isset( $rules['_meta']['last_updated'] ) ? $rules['_meta']['last_updated'] : '',
				'currency'     => isset( $rules['_meta']['currency'] ) ? $rules['_meta']['currency'] : 'EUR',
				'notes'        => isset( $rules['_meta']['notes'] ) ? $rules['_meta']['notes'] : '',
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Evaluate answers against stored scenarios.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function evaluate_answers( WP_REST_Request $request ) {
		$rules   = AEW_Admin::get_rules();
		$answers = $request->get_param( 'answers' );

		if ( empty( $answers ) || ! is_array( $answers ) ) {
			return new WP_Error( 'aew_missing_answers', esc_html__( 'Aucune réponse valide n’a été fournie.', 'advenir-eligibility-wizard' ), array( 'status' => 400 ) );
		}

		$questions     = isset( $rules['questions'] ) ? $rules['questions'] : array();
		$sanitized     = array();
		$missing       = array();
		$invalid_types = array();

		foreach ( $questions as $question ) {
			if ( empty( $question['id'] ) ) {
				continue;
			}

			$question_id = sanitize_key( $question['id'] );
			$is_required = ! empty( $question['required'] );

			if ( ! array_key_exists( $question_id, $answers ) ) {
				if ( $is_required ) {
					$missing[] = $question_id;
				}

				continue;
			}

			$raw_value = $answers[ $question_id ];
			$type      = isset( $question['type'] ) ? $question['type'] : 'single';

			switch ( $type ) {
				case 'number':
					if ( ! is_numeric( $raw_value ) ) {
						$invalid_types[] = $question_id;
						break;
					}

					$sanitized[ $question_id ] = (int) $raw_value;
					break;
				case 'single':
				default:
					$sanitized[ $question_id ] = sanitize_text_field( wp_unslash( $raw_value ) );
					break;
			}
		}

		if ( ! empty( $missing ) ) {
			return new WP_Error(
				'aew_missing_fields',
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					esc_html__( 'Merci de répondre à toutes les questions obligatoires (%s).', 'advenir-eligibility-wizard' ),
					implode( ', ', $missing )
				),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $invalid_types ) ) {
			return new WP_Error(
				'aew_invalid_answers',
				sprintf(
					/* translators: %s: comma-separated list of invalid fields */
					esc_html__( 'Certaines réponses ne sont pas du bon format (%s).', 'advenir-eligibility-wizard' ),
					implode( ', ', $invalid_types )
				),
				array( 'status' => 400 )
			);
		}

		$matched = $this->match_scenario( isset( $rules['scenarios'] ) ? $rules['scenarios'] : array(), $sanitized );

		if ( empty( $matched ) ) {
			$results = isset( $rules['results']['not_eligible'] ) ? $rules['results']['not_eligible'] : array();

			return rest_ensure_response(
				array(
					'eligible' => false,
					'messages' => array(
						'title'   => isset( $results['title'] ) ? $results['title'] : esc_html__( 'Pas d’éligibilité apparente', 'advenir-eligibility-wizard' ),
						'message' => isset( $results['message'] ) ? $results['message'] : esc_html__( 'Selon vos réponses, le projet ne semble pas éligible au programme Advenir.', 'advenir-eligibility-wizard' ),
					),
				)
			);
		}

		$currency          = isset( $rules['_meta']['currency'] ) ? $rules['_meta']['currency'] : 'EUR';
		$requested_points  = isset( $sanitized['points'] ) ? max( 0, (int) $sanitized['points'] ) : 0;
		$max_points        = isset( $matched['max_points'] ) ? max( 0, (int) $matched['max_points'] ) : 0;
		$amount_per_point  = isset( $matched['amount_per_point'] ) ? (float) $matched['amount_per_point'] : 0.0;
		$eligible_points   = 0 === $max_points ? $requested_points : min( $requested_points, $max_points );
		$total_estimate    = $eligible_points * $amount_per_point;
		$eligible_messages = isset( $rules['results']['eligible'] ) ? $rules['results']['eligible'] : array();

		$response = array(
			'eligible' => true,
			'scenario' => array(
				'id'             => isset( $matched['id'] ) ? $matched['id'] : '',
				'label'          => isset( $matched['label'] ) ? $matched['label'] : '',
				'footnote'       => isset( $matched['footnote'] ) ? $matched['footnote'] : '',
				'success_message'=> isset( $matched['success_message'] ) ? $matched['success_message'] : '',
			),
			'amounts'  => array(
				'currency'        => $currency,
				'amount_per_point'=> $amount_per_point,
				'points_requested'=> $requested_points,
				'points_eligible' => $eligible_points,
				'total'           => $total_estimate,
			),
			'messages' => array(
				'title'   => isset( $eligible_messages['title'] ) ? $eligible_messages['title'] : esc_html__( 'Félicitations !', 'advenir-eligibility-wizard' ),
				'message' => isset( $eligible_messages['message'] ) ? $eligible_messages['message'] : esc_html__( 'Selon vos réponses, le projet semble éligible.', 'advenir-eligibility-wizard' ),
			),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Validate nonce for evaluate route.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return bool|WP_Error
	 */
	public function validate_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'aew_invalid_nonce', esc_html__( 'Nonce invalide. Rechargez la page et réessayez.', 'advenir-eligibility-wizard' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Match answers to scenarios.
	 *
	 * @param array $scenarios List of scenarios.
	 * @param array $answers   Sanitized answers.
	 *
	 * @return array|null
	 */
	private function match_scenario( $scenarios, $answers ) {
		foreach ( $scenarios as $scenario ) {
			if ( empty( $scenario['conditions'] ) || ! is_array( $scenario['conditions'] ) ) {
				return $scenario;
			}

			$matches = true;

			foreach ( $scenario['conditions'] as $question_id => $expected ) {
				$question_id = sanitize_key( $question_id );

				if ( ! array_key_exists( $question_id, $answers ) ) {
					$matches = false;
					break;
				}

				$answer_value = $answers[ $question_id ];

				if ( is_array( $expected ) ) {
					if ( $this->is_associative_array( $expected ) ) {
						$min = isset( $expected['min'] ) ? (float) $expected['min'] : null;
						$max = isset( $expected['max'] ) ? (float) $expected['max'] : null;

						if ( null !== $min && $answer_value < $min ) {
							$matches = false;
							break;
						}

						if ( null !== $max && $answer_value > $max ) {
							$matches = false;
							break;
						}
					} else {
						if ( is_array( $answer_value ) ) {
							$intersect = array_intersect( $answer_value, $expected );

							if ( empty( $intersect ) ) {
								$matches = false;
								break;
							}
						} elseif ( ! in_array( $answer_value, $expected, true ) ) {
							$matches = false;
							break;
						}
					}
				} else {
					if ( $answer_value !== $expected ) {
						$matches = false;
						break;
					}
				}
			}

			if ( $matches ) {
				return $scenario;
			}
		}

		return null;
	}

	/**
	 * Check if array is associative.
	 *
	 * @param array $array Array to test.
	 *
	 * @return bool
	 */
	private function is_associative_array( $array ) {
		if ( array() === $array ) {
			return false;
		}

		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}
