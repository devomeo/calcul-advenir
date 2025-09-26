
<?php
/**
 * Admin functionality for Advenir Eligibility Wizard.
 *
 * @package AdvenirEligibilityWizard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AEW_Admin
 */
class AEW_Admin {

	/**
	 * Option key used to persist rules JSON.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'aew_rules_json';

	/**
	 * Singleton instance.
	 *
	 * @var AEW_Admin|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return AEW_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * AEW_Admin constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the plugin admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			esc_html__( 'Assistant Advenir', 'advenir-eligibility-wizard' ),
			esc_html__( 'Advenir Wizard', 'advenir-eligibility-wizard' ),
			'manage_options',
			'aew-settings',
			array( $this, 'render_page' ),
			'dashicons-admin-generic',
			59
		);
	}

	/**
	 * Register the settings required for the JSON ruleset.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'aew_settings',
			self::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_rules' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize and validate JSON configuration before saving.
	 *
	 * @param string $value Raw textarea value.
	 *
	 * @return string
	 */
	public function sanitize_rules( $value ) {
		$current_value = get_option( self::OPTION_KEY, self::get_default_rules_json() );
		$value         = is_string( $value ) ? wp_unslash( $value ) : '';

		if ( '' === trim( $value ) ) {
			add_settings_error( self::OPTION_KEY, 'aew_rules_empty', esc_html__( 'Le JSON ne peut pas être vide.', 'advenir-eligibility-wizard' ) );

			return $current_value;
		}

		$decoded = json_decode( $value, true );

		if ( null === $decoded || ! is_array( $decoded ) ) {
			add_settings_error( self::OPTION_KEY, 'aew_rules_invalid', esc_html__( 'Le JSON fourni est invalide. Merci de vérifier la syntaxe.', 'advenir-eligibility-wizard' ) );

			return $current_value;
		}

		return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Render the admin configuration page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rules_json = get_option( self::OPTION_KEY, self::get_default_rules_json() );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Assistant Advenir – Règles', 'advenir-eligibility-wizard' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'aew_settings' ); ?>
				<?php do_settings_sections( 'aew_settings' ); ?>
				<?php settings_errors( self::OPTION_KEY ); ?>
				<p class="description">
				<?php echo esc_html__( 'Collez ou modifiez le JSON décrivant les questions, scénarios et textes du parcours. Vérifiez régulièrement les barèmes officiels Advenir.', 'advenir-eligibility-wizard' ); ?>
				</p>
				<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>" rows="25" class="large-text code"><?php echo esc_textarea( $rules_json ); ?></textarea>
				<?php submit_button( esc_html__( 'Enregistrer les règles', 'advenir-eligibility-wizard' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Retrieve the decoded rules configuration.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$json = get_option( self::OPTION_KEY, self::get_default_rules_json() );
		$data = json_decode( $json, true );

		if ( null === $data || ! is_array( $data ) ) {
			$data = self::get_default_rules_array();
		}

		return $data;
	}

	/**
	 * Return the default configuration as a JSON string.
	 *
	 * @return string
	 */
	public static function get_default_rules_json() {
		return wp_json_encode( self::get_default_rules_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Default configuration array.
	 *
	 * @return array
	 */
	public static function get_default_rules_array() {
		return array(
			'_meta'     => array(
				'last_updated' => '2025-09-26T00:00:00Z',
				'currency'     => 'EUR',
				'notes'        => 'Exemples. Mettez à jour selon les barèmes Advenir en vigueur.',
			),
			'questions' => array(
				array(
					'id'       => 'beneficiary',
					'label'    => 'Qui êtes-vous ?',
					'type'     => 'single',
					'required' => true,
					'options'  => array(
						array(
							'value' => 'particulier',
							'label' => 'Particulier (maison individuelle)',
						),
						array(
							'value' => 'copro',
							'label' => 'Copropriété (parking collectif)',
						),
						array(
							'value' => 'entreprise_prive',
							'label' => 'Entreprise — Parking privé réservé aux salariés',
						),
						array(
							'value' => 'entreprise_public',
							'label' => 'Entreprise — Parking ouvert au public',
						),
						array(
							'value' => 'collectivite',
							'label' => 'Collectivité / acteur public',
						),
					),
				),
				array(
					'id'       => 'site',
					'label'    => 'Où se situe l’installation ?',
					'type'     => 'single',
					'required' => true,
					'options'  => array(
						array(
							'value' => 'residence_principale',
							'label' => 'Résidence principale',
						),
						array(
							'value' => 'residence_secondaire',
							'label' => 'Résidence secondaire',
						),
						array(
							'value' => 'site_entreprise',
							'label' => 'Site d’entreprise / local professionnel',
						),
					),
				),
				array(
					'id'       => 'usage',
					'label'    => 'Quel sera l’usage principal de la borne ?',
					'type'     => 'single',
					'required' => true,
					'options'  => array(
						array(
							'value' => 'usage_prive',
							'label' => 'Usage privé (accès réservé)',
						),
						array(
							'value' => 'usage_residentiel_partage',
							'label' => 'Usage résidentiel partagé',
						),
						array(
							'value' => 'usage_public',
							'label' => 'Usage ouvert au public',
						),
					),
				),
				array(
					'id'         => 'points',
					'label'      => 'Combien de points de charge souhaitez-vous installer ?',
					'type'       => 'number',
					'required'   => true,
					'attributes' => array(
						'placeholder' => 1,
						'min'         => 1,
						'max'         => 50,
						'step'        => 1,
					),
				),
			),
			'scenarios' => array(
				array(
					'id'               => 'scenario_particulier',
					'label'            => 'Particulier résidentiel',
					'conditions'       => array(
						'beneficiary' => array( 'particulier' ),
						'usage'       => array( 'usage_prive' ),
					),
					'max_points'       => 1,
					'amount_per_point' => 960,
					'footnote'         => 'Montant indicatif pour un point de charge résidentiel individuel (≤22 kW).',
					'success_message'  => 'Votre profil correspond aux installations individuelles financées par Advenir.',
				),
				array(
					'id'               => 'scenario_copro',
					'label'            => 'Copropriété / résidentiel collectif',
					'conditions'       => array(
						'beneficiary' => array( 'copro' ),
						'usage'       => array( 'usage_residentiel_partage' ),
					),
					'max_points'       => 20,
					'amount_per_point' => 1500,
					'footnote'         => 'Montant indicatif pour les copropriétés. Vérifiez le plafond annuel en vigueur.',
					'success_message'  => 'Votre copropriété peut bénéficier d’un accompagnement renforcé du programme.',
				),
				array(
					'id'               => 'scenario_public',
					'label'            => 'Ouverture au public (entreprise ou collectivité)',
					'conditions'       => array(
						'beneficiary' => array( 'entreprise_public', 'collectivite' ),
						'usage'       => array( 'usage_public' ),
					),
					'max_points'       => 40,
					'amount_per_point' => 2400,
					'footnote'         => 'Montant indicatif pour les points ouverts au public, soumis aux plafonds Advenir.',
					'success_message'  => 'Votre projet contribue au maillage public et peut prétendre aux aides dédiées.',
				),
			),
			'results'   => array(
				'eligible'    => array(
					'title'   => 'Félicitations !',
					'message' => 'Selon vos réponses, votre projet semble répondre aux critères du programme Advenir. Déposez un dossier officiel pour confirmation.',
				),
				'not_eligible' => array(
					'title'   => 'Pas d’éligibilité apparente',
					'message' => 'Selon vos réponses, le projet ne semble pas entrer dans le périmètre actuel du programme. Vérifiez les critères ou contactez le service Advenir.',
				),
			),
		);
	}
}
