<?php
/**
 * AI Chatbot for WPMatch
 *
 * Provides AI-powered conversation starters, icebreakers, dating advice,
 * and personality analysis to enhance user interactions.
 *
 * @package WPMatch
 * @since 1.6.0
 */

class WPMatch_AI_Chatbot {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * AI provider configuration.
	 *
	 * @var array
	 */
	private $ai_config;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->setup_ai_config();
	}

	/**
	 * Initialize AI chatbot system.
	 */
	public static function init() {
		$instance = new self( 'wpmatch', WPMATCH_VERSION );
		add_action( 'init', array( $instance, 'setup_database' ) );
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );
		add_action( 'wp_footer', array( $instance, 'add_chatbot_widget' ) );
		add_filter( 'wpmatch_message_suggestions', array( $instance, 'get_conversation_starters' ), 10, 2 );

		// Schedule daily personality analysis updates.
		if ( ! wp_next_scheduled( 'wpmatch_update_personality_insights' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_update_personality_insights' );
		}
		add_action( 'wpmatch_update_personality_insights', array( $instance, 'update_personality_insights' ) );
	}

	/**
	 * Set up AI configuration.
	 */
	private function setup_ai_config() {
		$settings = get_option( 'wpmatch_ai_settings', array() );

		$this->ai_config = array(
			'provider'    => isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'openai',
			'api_key'     => isset( $settings['ai_api_key'] ) ? $settings['ai_api_key'] : '',
			'model'       => isset( $settings['ai_model'] ) ? $settings['ai_model'] : 'gpt-3.5-turbo',
			'max_tokens'  => isset( $settings['max_tokens'] ) ? (int) $settings['max_tokens'] : 150,
			'temperature' => isset( $settings['temperature'] ) ? (float) $settings['temperature'] : 0.7,
		);
	}

	/**
	 * Set up database tables.
	 */
	public function setup_database() {
		$this->create_ai_conversations_table();
		$this->create_conversation_starters_table();
		$this->create_personality_insights_table();
		$this->create_ai_suggestions_table();
		$this->create_dating_advice_table();
	}

	/**
	 * Create AI conversations table.
	 */
	private function create_ai_conversations_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_ai_conversations';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			session_id varchar(100) NOT NULL,
			message_type varchar(50) DEFAULT 'user',
			content longtext NOT NULL,
			ai_response longtext,
			context_data longtext,
			confidence_score decimal(3,2),
			feedback_rating tinyint(1),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY session_id (session_id),
			KEY message_type (message_type),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create conversation starters table.
	 */
	private function create_conversation_starters_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_conversation_starters';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			category varchar(100) NOT NULL,
			subcategory varchar(100),
			question text NOT NULL,
			follow_up_questions longtext,
			personality_types text,
			interests text,
			difficulty_level tinyint(1) DEFAULT 1,
			success_rate decimal(5,2),
			usage_count int(11) DEFAULT 0,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category (category),
			KEY difficulty_level (difficulty_level),
			KEY success_rate (success_rate),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default conversation starters.
		$this->insert_default_conversation_starters();
	}

	/**
	 * Create personality insights table.
	 */
	private function create_personality_insights_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_personality_insights';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			personality_type varchar(50),
			traits longtext,
			communication_style varchar(100),
			preferred_topics text,
			conversation_patterns longtext,
			ai_analysis longtext,
			confidence_score decimal(3,2),
			last_updated datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY personality_type (personality_type),
			KEY communication_style (communication_style),
			KEY last_updated (last_updated)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create AI suggestions table.
	 */
	private function create_ai_suggestions_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_ai_suggestions';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			target_user_id bigint(20),
			suggestion_type varchar(50) NOT NULL,
			content text NOT NULL,
			context longtext,
			priority tinyint(1) DEFAULT 1,
			is_used tinyint(1) DEFAULT 0,
			effectiveness_score decimal(3,2),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY target_user_id (target_user_id),
			KEY suggestion_type (suggestion_type),
			KEY priority (priority),
			KEY is_used (is_used),
			KEY expires_at (expires_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create dating advice table.
	 */
	private function create_dating_advice_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_dating_advice';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			category varchar(100) NOT NULL,
			title varchar(255) NOT NULL,
			content longtext NOT NULL,
			tags text,
			target_audience varchar(100),
			difficulty_level tinyint(1) DEFAULT 1,
			helpfulness_score decimal(3,2),
			view_count int(11) DEFAULT 0,
			is_featured tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY category (category),
			KEY difficulty_level (difficulty_level),
			KEY helpfulness_score (helpfulness_score),
			KEY is_featured (is_featured)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default dating advice.
		$this->insert_default_dating_advice();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Chatbot conversation endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_chat_with_ai' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'message' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'sanitize_callback' => array( $this, 'sanitize_context' ),
					),
				),
			)
		);

		// Conversation starters endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/starters',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_conversation_starters' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'target_user_id' => array(
						'sanitize_callback' => 'absint',
					),
					'category'       => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'count'          => array(
						'default'           => 5,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/ai/starters/personalized',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_personalized_starters' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'target_user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'count'          => array(
						'default'           => 3,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Personality analysis endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/personality/analyze',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_analyze_personality' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'text_data' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/ai/personality/insights',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_personality_insights' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'user_id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Dating advice endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/advice',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_dating_advice' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'category' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'situation' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/ai/advice/personalized',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_get_personalized_advice' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'situation' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context'   => array(
						'sanitize_callback' => array( $this, 'sanitize_context' ),
					),
				),
			)
		);

		// Message optimization endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/message/optimize',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_optimize_message' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'message'        => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'target_user_id' => array(
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'wpmatch/v1',
			'/ai/message/suggestions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_get_message_suggestions' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'conversation_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Compatibility analysis endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/compatibility',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_analyze_compatibility' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'target_user_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Feedback endpoints.
		register_rest_route(
			'wpmatch/v1',
			'/ai/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'api_submit_feedback' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
				'args'                => array(
					'suggestion_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'rating'        => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => array( $this, 'validate_rating' ),
					),
					'feedback'      => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'wpmatch-ai-chatbot',
			WPMATCH_PLUGIN_URL . 'public/js/wpmatch-ai-chatbot.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'wpmatch-ai-chatbot',
			'wpMatchAI',
			array(
				'apiUrl'      => home_url( '/wp-json/wpmatch/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'currentUser' => get_current_user_id(),
				'strings'     => array(
					'thinking'           => esc_html__( 'Thinking...', 'wpmatch' ),
					'error'              => esc_html__( 'Something went wrong. Please try again.', 'wpmatch' ),
					'noSuggestions'      => esc_html__( 'No suggestions available right now.', 'wpmatch' ),
					'askAnything'        => esc_html__( 'Ask me anything about dating!', 'wpmatch' ),
					'getStarters'        => esc_html__( 'Get conversation starters', 'wpmatch' ),
					'optimizeMessage'    => esc_html__( 'Optimize this message', 'wpmatch' ),
					'analyzePersonality' => esc_html__( 'Analyze personality', 'wpmatch' ),
					'getDatingAdvice'    => esc_html__( 'Get dating advice', 'wpmatch' ),
				),
			)
		);

		wp_enqueue_style(
			'wpmatch-ai-chatbot',
			WPMATCH_PLUGIN_URL . 'public/css/wpmatch-ai-chatbot.css',
			array(),
			$this->version
		);
	}

	/**
	 * Add chatbot widget to footer.
	 */
	public function add_chatbot_widget() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		?>
		<div id="wpmatch-ai-chatbot" class="wpmatch-ai-chatbot">
			<div class="chatbot-toggle">
				<button class="chatbot-toggle-btn">
					<i class="fas fa-robot"></i>
					<span class="notification-badge" style="display: none;"></span>
				</button>
			</div>

			<div class="chatbot-window" style="display: none;">
				<div class="chatbot-header">
					<div class="chatbot-avatar">
						<i class="fas fa-robot"></i>
					</div>
					<div class="chatbot-info">
						<h4><?php esc_html_e( 'Dating Assistant', 'wpmatch' ); ?></h4>
						<span class="chatbot-status"><?php esc_html_e( 'Online', 'wpmatch' ); ?></span>
					</div>
					<button class="chatbot-close">
						<i class="fas fa-times"></i>
					</button>
				</div>

				<div class="chatbot-messages">
					<div class="ai-message">
						<div class="message-avatar">
							<i class="fas fa-robot"></i>
						</div>
						<div class="message-content">
							<p><?php esc_html_e( 'Hi! I\'m your dating assistant. I can help you with conversation starters, dating advice, and personality insights. What can I help you with today?', 'wpmatch' ); ?></p>
						</div>
					</div>
				</div>

				<div class="chatbot-quick-actions">
					<button class="quick-action" data-action="starters">
						<i class="fas fa-comments"></i>
						<?php esc_html_e( 'Conversation Starters', 'wpmatch' ); ?>
					</button>
					<button class="quick-action" data-action="advice">
						<i class="fas fa-heart"></i>
						<?php esc_html_e( 'Dating Advice', 'wpmatch' ); ?>
					</button>
					<button class="quick-action" data-action="personality">
						<i class="fas fa-user-circle"></i>
						<?php esc_html_e( 'Personality Analysis', 'wpmatch' ); ?>
					</button>
				</div>

				<div class="chatbot-input">
					<textarea
						placeholder="<?php esc_attr_e( 'Ask me anything about dating...', 'wpmatch' ); ?>"
						class="chatbot-textarea"
						rows="1"
					></textarea>
					<button class="chatbot-send">
						<i class="fas fa-paper-plane"></i>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * API: Chat with AI.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_chat_with_ai( $request ) {
		$user_id = get_current_user_id();
		$message = $request->get_param( 'message' );
		$context = $request->get_param( 'context' );

		// Generate session ID if not provided.
		$session_id = 'chat_' . $user_id . '_' . time();

		// Get AI response.
		$ai_response = $this->generate_ai_response( $message, $context, $user_id );

		// Store conversation.
		$this->store_ai_conversation( $user_id, $session_id, $message, $ai_response, $context );

		return rest_ensure_response(
			array(
				'success'    => true,
				'data'       => array(
					'response'   => $ai_response['content'],
					'confidence' => $ai_response['confidence'],
					'suggestions' => $ai_response['suggestions'] ?? array(),
				),
			)
		);
	}

	/**
	 * API: Get conversation starters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function api_get_conversation_starters( $request ) {
		$target_user_id = $request->get_param( 'target_user_id' );
		$category = $request->get_param( 'category' );
		$count = $request->get_param( 'count' );

		$starters = $this->get_conversation_starters( $target_user_id, $category, $count );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'starters' => $starters,
					'count'    => count( $starters ),
				),
			)
		);
	}

	/**
	 * Generate AI response.
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @param int    $user_id User ID.
	 * @return array
	 */
	private function generate_ai_response( $message, $context = array(), $user_id = 0 ) {
		// Check if AI is configured.
		if ( empty( $this->ai_config['api_key'] ) ) {
			return $this->get_fallback_response( $message, $context );
		}

		// Get user personality insights for context.
		$personality = $this->get_user_personality_insights( $user_id );

		// Build AI prompt.
		$prompt = $this->build_ai_prompt( $message, $context, $personality );

		// Make API call to AI provider.
		$ai_response = $this->call_ai_api( $prompt );

		// Parse and enhance response.
		return $this->parse_ai_response( $ai_response, $message );
	}

	/**
	 * Get conversation starters based on user compatibility.
	 *
	 * @param int    $target_user_id Target user ID.
	 * @param string $category Category filter.
	 * @param int    $count Number of starters.
	 * @return array
	 */
	public function get_conversation_starters( $target_user_id = 0, $category = '', $count = 5 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_conversation_starters';

		$where_clause = 'WHERE is_active = 1';
		$params = array();

		if ( ! empty( $category ) ) {
			$where_clause .= ' AND category = %s';
			$params[] = $category;
		}

		if ( $target_user_id ) {
			// Get target user's interests for personalization.
			$user_interests = get_user_meta( $target_user_id, 'wpmatch_interests', true );
			if ( ! empty( $user_interests ) && is_array( $user_interests ) ) {
				$interests_sql = implode( '","', array_map( 'esc_sql', $user_interests ) );
				$where_clause .= ' AND (interests IS NULL OR interests REGEXP "(' . $interests_sql . ')")';
			}
		}

		$starters = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name
				$where_clause
				ORDER BY success_rate DESC, RAND()
				LIMIT %d",
				array_merge( $params, array( $count ) )
			)
		);

		$formatted_starters = array();
		foreach ( $starters as $starter ) {
			$formatted_starters[] = array(
				'id'            => $starter->id,
				'question'      => $starter->question,
				'category'      => $starter->category,
				'subcategory'   => $starter->subcategory,
				'follow_ups'    => maybe_unserialize( $starter->follow_up_questions ),
				'difficulty'    => $starter->difficulty_level,
				'success_rate'  => $starter->success_rate,
			);

			// Update usage count.
			$wpdb->update(
				$table_name,
				array( 'usage_count' => $starter->usage_count + 1 ),
				array( 'id' => $starter->id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return $formatted_starters;
	}

	/**
	 * Get user personality insights.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_user_personality_insights( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_personality_insights';

		$insights = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! $insights ) {
			return array();
		}

		return array(
			'personality_type'     => $insights->personality_type,
			'traits'               => maybe_unserialize( $insights->traits ),
			'communication_style'  => $insights->communication_style,
			'preferred_topics'     => maybe_unserialize( $insights->preferred_topics ),
			'conversation_patterns' => maybe_unserialize( $insights->conversation_patterns ),
		);
	}

	/**
	 * Build AI prompt for dating advice.
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @param array  $personality Personality insights.
	 * @return string
	 */
	private function build_ai_prompt( $message, $context = array(), $personality = array() ) {
		$prompt = "You are a professional dating coach and relationship expert. ";
		$prompt .= "Provide helpful, respectful, and encouraging dating advice. ";
		$prompt .= "Keep responses concise but valuable. ";

		if ( ! empty( $personality ) ) {
			$prompt .= "The user's personality type is: " . ( $personality['personality_type'] ?? 'unknown' ) . ". ";
			$prompt .= "Their communication style is: " . ( $personality['communication_style'] ?? 'adaptive' ) . ". ";
		}

		if ( ! empty( $context['situation'] ) ) {
			$prompt .= "Context: " . $context['situation'] . ". ";
		}

		$prompt .= "\n\nUser question: " . $message;
		$prompt .= "\n\nProvide a helpful response with 2-3 actionable tips.";

		return $prompt;
	}

	/**
	 * Call AI API.
	 *
	 * @param string $prompt AI prompt.
	 * @return string
	 */
	private function call_ai_api( $prompt ) {
		// Simulate AI API call - in production, integrate with OpenAI, etc.
		return "I understand you're looking for dating advice. Here are some personalized suggestions based on your situation...";
	}

	/**
	 * Parse AI response.
	 *
	 * @param string $response AI response.
	 * @param string $message Original message.
	 * @return array
	 */
	private function parse_ai_response( $response, $message ) {
		return array(
			'content'    => $response,
			'confidence' => 0.85,
			'suggestions' => array(),
		);
	}

	/**
	 * Get fallback response when AI is not available.
	 *
	 * @param string $message User message.
	 * @param array  $context Context data.
	 * @return array
	 */
	private function get_fallback_response( $message, $context = array() ) {
		$fallback_responses = array(
			'conversation' => "Here are some great conversation starters: Ask about their hobbies, travel experiences, or favorite movies. Remember to be genuine and listen actively!",
			'advice'       => "Dating tip: Be yourself, show genuine interest in getting to know them, and don't be afraid to be vulnerable. Good communication is key to any relationship.",
			'default'      => "I'm here to help with your dating questions! Try asking about conversation starters, dating advice, or relationship tips.",
		);

		$message_lower = strtolower( $message );

		if ( strpos( $message_lower, 'conversation' ) !== false || strpos( $message_lower, 'starter' ) !== false ) {
			$response_key = 'conversation';
		} elseif ( strpos( $message_lower, 'advice' ) !== false || strpos( $message_lower, 'tip' ) !== false ) {
			$response_key = 'advice';
		} else {
			$response_key = 'default';
		}

		return array(
			'content'    => $fallback_responses[ $response_key ],
			'confidence' => 0.6,
			'suggestions' => array(),
		);
	}

	/**
	 * Store AI conversation.
	 *
	 * @param int    $user_id User ID.
	 * @param string $session_id Session ID.
	 * @param string $message User message.
	 * @param array  $ai_response AI response.
	 * @param array  $context Context data.
	 */
	private function store_ai_conversation( $user_id, $session_id, $message, $ai_response, $context = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_ai_conversations';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'          => $user_id,
				'session_id'       => $session_id,
				'message_type'     => 'user',
				'content'          => $message,
				'ai_response'      => $ai_response['content'],
				'context_data'     => wp_json_encode( $context ),
				'confidence_score' => $ai_response['confidence'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f' )
		);
	}

	/**
	 * Insert default conversation starters.
	 */
	private function insert_default_conversation_starters() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_conversation_starters';

		$starters = array(
			array(
				'category'    => 'interests',
				'subcategory' => 'hobbies',
				'question'    => "What's a hobby you've picked up recently that you're really excited about?",
				'follow_ups'  => array( "How did you get started with that?", "What's been the most surprising thing about it?" ),
				'difficulty'  => 1,
			),
			array(
				'category'    => 'travel',
				'subcategory' => 'experiences',
				'question'    => "If you could teleport anywhere in the world right now, where would you go and why?",
				'follow_ups'  => array( "Have you been there before?", "What would be the first thing you'd do there?" ),
				'difficulty'  => 2,
			),
			array(
				'category'    => 'lifestyle',
				'subcategory' => 'food',
				'question'    => "What's the best meal you've had recently? I'm always looking for new places to try!",
				'follow_ups'  => array( "Do you cook at home often?", "What's your go-to comfort food?" ),
				'difficulty'  => 1,
			),
			array(
				'category'    => 'personal',
				'subcategory' => 'goals',
				'question'    => "What's something you're working towards that you're really excited about?",
				'follow_ups'  => array( "What inspired you to pursue that?", "How long have you been working on it?" ),
				'difficulty'  => 3,
			),
			array(
				'category'    => 'entertainment',
				'subcategory' => 'media',
				'question'    => "I'm looking for a new show to binge-watch. What's been keeping you glued to your screen lately?",
				'follow_ups'  => array( "What genre do you usually prefer?", "Any movies you'd recommend?" ),
				'difficulty'  => 1,
			),
		);

		foreach ( $starters as $starter ) {
			$wpdb->insert(
				$table_name,
				array(
					'category'             => $starter['category'],
					'subcategory'          => $starter['subcategory'],
					'question'             => $starter['question'],
					'follow_up_questions'  => maybe_serialize( $starter['follow_ups'] ),
					'difficulty_level'     => $starter['difficulty'],
					'success_rate'         => 75.0,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%f' )
			);
		}
	}

	/**
	 * Insert default dating advice.
	 */
	private function insert_default_dating_advice() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpmatch_dating_advice';

		$advice = array(
			array(
				'category' => 'first_date',
				'title'    => 'Making a Great First Impression',
				'content'  => 'Be punctual, dress appropriately for the venue, put your phone away, ask open-ended questions, and show genuine interest in getting to know them.',
				'tags'     => 'first impression, punctuality, conversation',
			),
			array(
				'category' => 'conversation',
				'title'    => 'Keeping Conversations Flowing',
				'content'  => 'Use the FORD method: Family, Occupation, Recreation, Dreams. Ask follow-up questions, share related experiences, and avoid controversial topics early on.',
				'tags'     => 'conversation, FORD method, questions',
			),
			array(
				'category' => 'confidence',
				'title'    => 'Building Dating Confidence',
				'content'  => 'Practice self-care, set realistic expectations, remember that rejection isn\'t personal, focus on having fun rather than outcomes, and celebrate small wins.',
				'tags'     => 'confidence, self-care, mindset',
			),
		);

		foreach ( $advice as $item ) {
			$wpdb->insert(
				$table_name,
				array(
					'category'           => $item['category'],
					'title'              => $item['title'],
					'content'            => $item['content'],
					'tags'               => $item['tags'],
					'target_audience'    => 'general',
					'difficulty_level'   => 1,
					'helpfulness_score'  => 80.0,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%f' )
			);
		}
	}

	/**
	 * Update personality insights (scheduled task).
	 */
	public function update_personality_insights() {
		// Implementation for analyzing user conversations and updating personality insights.
		// This would run daily to analyze user messaging patterns and update insights.
	}

	/**
	 * Validation and sanitization callbacks.
	 */

	/**
	 * Check user authentication.
	 *
	 * @return bool
	 */
	public function check_user_auth() {
		return is_user_logged_in();
	}

	/**
	 * Sanitize context data.
	 *
	 * @param array $context Context data.
	 * @return array
	 */
	public function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}

		$sanitized = array();
		$allowed_keys = array( 'situation', 'target_user_id', 'conversation_id', 'preferences' );

		foreach ( $allowed_keys as $key ) {
			if ( isset( $context[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( $context[ $key ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Validate rating.
	 *
	 * @param int $rating Rating value.
	 * @return bool
	 */
	public function validate_rating( $rating ) {
		return $rating >= 1 && $rating <= 5;
	}

	/**
	 * Placeholder methods for remaining API endpoints.
	 */

	public function api_get_personalized_starters( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_analyze_personality( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_get_personality_insights( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_get_dating_advice( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_get_personalized_advice( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_optimize_message( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_get_message_suggestions( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_analyze_compatibility( $request ) {
		return rest_ensure_response( array( 'success' => false, 'message' => 'Not implemented yet' ) );
	}

	public function api_submit_feedback( $request ) {
		return rest_ensure_response( array( 'success' => true, 'message' => 'Feedback received' ) );
	}
}