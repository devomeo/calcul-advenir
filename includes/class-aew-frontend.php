<?php
/**
 * Front-end integration for Advenir Eligibility Wizard.
 *
 * @package AdvenirEligibilityWizard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AEW_Frontend
 */
class AEW_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var AEW_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return AEW_Frontend
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Track whether assets were enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * AEW_Frontend constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register the shortcode handler.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'advenir_wizard', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register front-end assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'advenir-eligibility-wizard',
			AEW_PLUGIN_URL . 'assets/css/wizard.css',
			array(),
			AEW_VERSION
		);

		wp_register_script(
			'advenir-eligibility-wizard',
			AEW_PLUGIN_URL . 'assets/js/wizard.js',
			array(),
			AEW_VERSION,
			true
		);
	}

	/**
	 * Render the shortcode output.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! $this->assets_enqueued ) {
			wp_enqueue_style( 'advenir-eligibility-wizard' );
			wp_enqueue_script( 'advenir-eligibility-wizard' );
			wp_localize_script(
				'advenir-eligibility-wizard',
				'aewData',
				array(
					'restUrl' => esc_url_raw( rest_url( AEW_REST::REST_NAMESPACE . '/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'i18n'    => array(
						'next'            => esc_html__( 'Suivant', 'advenir-eligibility-wizard' ),
						'previous'        => esc_html__( 'Précédent', 'advenir-eligibility-wizard' ),
						'submit'          => esc_html__( 'Calculer mon éligibilité', 'advenir-eligibility-wizard' ),
						'step'            => esc_html__( 'Étape', 'advenir-eligibility-wizard' ),
						'of'              => esc_html__( 'sur', 'advenir-eligibility-wizard' ),
						'loading'         => esc_html__( 'Chargement…', 'advenir-eligibility-wizard' ),
						'errorTitle'      => esc_html__( 'Une erreur est survenue', 'advenir-eligibility-wizard' ),
						'retry'           => esc_html__( 'Réessayer', 'advenir-eligibility-wizard' ),
						'empty'           => esc_html__( 'Aucune question disponible.', 'advenir-eligibility-wizard' ),
						'loadError'       => esc_html__( 'Impossible de charger le questionnaire.', 'advenir-eligibility-wizard' ),
						'required'        => esc_html__( 'Cette information est obligatoire.', 'advenir-eligibility-wizard' ),
						'invalidNumber'   => esc_html__( 'Merci d’entrer un nombre valide.', 'advenir-eligibility-wizard' ),
						'minValue'        => esc_html__( 'Valeur trop faible (minimum %s).', 'advenir-eligibility-wizard' ),
						'maxValue'        => esc_html__( 'Valeur trop élevée (maximum %s).', 'advenir-eligibility-wizard' ),
						'perPoint'        => esc_html__( 'Montant par point', 'advenir-eligibility-wizard' ),
						'pointsEligible'  => esc_html__( 'Points éligibles', 'advenir-eligibility-wizard' ),
						'pointsRequested' => esc_html__( 'Points demandés', 'advenir-eligibility-wizard' ),
						'totalEstimated'  => esc_html__( 'Total estimé', 'advenir-eligibility-wizard' ),
						'answersTitle'    => esc_html__( 'Vos réponses', 'advenir-eligibility-wizard' ),
						'successTitle'    => esc_html__( 'Félicitations !', 'advenir-eligibility-wizard' ),
						'infoTitle'       => esc_html__( 'Information', 'advenir-eligibility-wizard' ),
					),
				)
			);
			$this->assets_enqueued = true;
		}

		ob_start();
		?>
		<div class="aew-container" data-aew-wizard>
			<div class="aew-card">
				<div class="aew-progress" aria-live="polite"></div>
				<div class="aew-step" data-aew-step-container></div>
				<div class="aew-navigation">
					<button type="button" class="aew-btn aew-btn-secondary" data-aew-prev disabled>
						<?php echo esc_html__( 'Précédent', 'advenir-eligibility-wizard' ); ?>
					</button>
					<button type="button" class="aew-btn aew-btn-primary" data-aew-next>
						<?php echo esc_html__( 'Suivant', 'advenir-eligibility-wizard' ); ?>
					</button>
				</div>
				<div class="aew-result" aria-live="polite" data-aew-result hidden></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
