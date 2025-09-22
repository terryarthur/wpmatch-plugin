<?php
/**
 * WPMatch Cache Manager
 *
 * Handles caching for improved performance.
 *
 * @package WPMatch
 * @since 1.7.0
 */

/**
 * Cache Manager class.
 */
class WPMatch_Cache_Manager {

	/**
	 * Instance of the class.
	 *
	 * @var WPMatch_Cache_Manager
	 */
	private static $instance = null;

	/**
	 * Cache groups.
	 *
	 * @var array
	 */
	private $cache_groups = array(
		'profiles'      => 3600,    // 1 hour.
		'matches'       => 1800,    // 30 minutes.
		'messages'      => 300,     // 5 minutes.
		'search'        => 900,     // 15 minutes.
		'recommendations' => 7200,   // 2 hours.
		'analytics'     => 3600,    // 1 hour.
		'events'        => 1800,    // 30 minutes.
		'gamification'  => 3600,    // 1 hour.
		'location'      => 600,     // 10 minutes.
		'verification'  => 86400,   // 24 hours.
		'api_responses' => 300,     // 5 minutes.
		'user_data'     => 1800,    // 30 minutes.
		'media'         => 86400,   // 24 hours.
		'social'        => 3600,    // 1 hour.
		'ml_scores'     => 7200,    // 2 hours.
	);

	/**
	 * Cache statistics.
	 *
	 * @var array
	 */
	private $cache_stats = array(
		'hits'   => 0,
		'misses' => 0,
		'sets'   => 0,
		'deletes' => 0,
	);

	/**
	 * Cache configuration.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Get the single instance.
	 *
	 * @return WPMatch_Cache_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_config();
		$this->init_cache_groups();
		$this->init_hooks();
	}

	/**
	 * Load cache configuration.
	 */
	private function load_config() {
		$this->config = array(
			'enabled'           => get_option( 'wpmatch_cache_enabled', true ),
			'redis_enabled'     => get_option( 'wpmatch_redis_enabled', false ),
			'redis_host'        => get_option( 'wpmatch_redis_host', '127.0.0.1' ),
			'redis_port'        => get_option( 'wpmatch_redis_port', 6379 ),
			'redis_password'    => get_option( 'wpmatch_redis_password', '' ),
			'redis_database'    => get_option( 'wpmatch_redis_database', 0 ),
			'memcached_enabled' => get_option( 'wpmatch_memcached_enabled', false ),
			'memcached_servers' => get_option( 'wpmatch_memcached_servers', array( '127.0.0.1:11211' ) ),
			'object_cache'      => get_option( 'wpmatch_object_cache_enabled', true ),
			'transient_cache'   => get_option( 'wpmatch_transient_cache_enabled', true ),
			'file_cache'        => get_option( 'wpmatch_file_cache_enabled', false ),
			'cache_compression' => get_option( 'wpmatch_cache_compression', false ),
			'cache_encryption'  => get_option( 'wpmatch_cache_encryption', false ),
			'debug_mode'        => get_option( 'wpmatch_cache_debug', false ),
		);
	}

	/**
	 * Initialize cache groups.
	 */
	private function init_cache_groups() {
		// Register cache groups with WordPress object cache.
		foreach ( array_keys( $this->cache_groups ) as $group ) {
			wp_cache_add_global_groups( array( 'wpmatch_' . $group ) );
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Cache invalidation hooks.
		add_action( 'wpmatch_profile_updated', array( $this, 'invalidate_profile_cache' ), 10, 1 );
		add_action( 'wpmatch_new_match', array( $this, 'invalidate_match_cache' ), 10, 2 );
		add_action( 'wpmatch_new_message', array( $this, 'invalidate_message_cache' ), 10, 2 );
		add_action( 'wpmatch_user_preferences_updated', array( $this, 'invalidate_user_cache' ), 10, 1 );
		add_action( 'wpmatch_event_updated', array( $this, 'invalidate_event_cache' ), 10, 1 );
		add_action( 'wpmatch_location_updated', array( $this, 'invalidate_location_cache' ), 10, 1 );

		// Cache warm-up hooks.
		add_action( 'wp_login', array( $this, 'warm_up_user_cache' ), 10, 2 );
		add_action( 'wpmatch_daily_warmup', array( $this, 'daily_cache_warmup' ) );

		// Cache cleanup hooks.
		add_action( 'wpmatch_cache_cleanup', array( $this, 'cleanup_expired_cache' ) );

		// Admin hooks.
		add_action( 'wp_ajax_wpmatch_flush_cache', array( $this, 'ajax_flush_cache' ) );
		add_action( 'wp_ajax_wpmatch_cache_stats', array( $this, 'ajax_cache_stats' ) );

		// REST API hooks.
		add_action( 'rest_api_init', array( $this, 'register_cache_endpoints' ) );

		// Schedule cache cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'wpmatch_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmatch_cache_cleanup' );
		}

		if ( ! wp_next_scheduled( 'wpmatch_daily_warmup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmatch_daily_warmup' );
		}
	}

	/**
	 * Get cached data.
	 *
	 * @param string $key Cache key.
	 * @param string $group Cache group.
	 * @return mixed|false Cached data or false if not found.
	 */
	public function get( $key, $group = 'default' ) {
		if ( ! $this->config['enabled'] ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $key, $group );

		// Try Redis first if enabled.
		if ( $this->config['redis_enabled'] ) {
			$data = $this->get_from_redis( $cache_key );
			if ( false !== $data ) {
				$this->cache_stats['hits']++;
				return $this->maybe_decrypt( $this->maybe_decompress( $data ) );
			}
		}

		// Try Memcached if enabled.
		if ( $this->config['memcached_enabled'] ) {
			$data = $this->get_from_memcached( $cache_key );
			if ( false !== $data ) {
				$this->cache_stats['hits']++;
				return $this->maybe_decrypt( $this->maybe_decompress( $data ) );
			}
		}

		// Try WordPress object cache.
		if ( $this->config['object_cache'] ) {
			$data = wp_cache_get( $cache_key, 'wpmatch_' . $group );
			if ( false !== $data ) {
				$this->cache_stats['hits']++;
				return $data;
			}
		}

		// Try transients.
		if ( $this->config['transient_cache'] ) {
			$data = get_transient( $cache_key );
			if ( false !== $data ) {
				$this->cache_stats['hits']++;
				return $data;
			}
		}

		// Try file cache.
		if ( $this->config['file_cache'] ) {
			$data = $this->get_from_file_cache( $cache_key );
			if ( false !== $data ) {
				$this->cache_stats['hits']++;
				return $data;
			}
		}

		$this->cache_stats['misses']++;
		return false;
	}

	/**
	 * Set cached data.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param string $group Cache group.
	 * @param int    $expiration Optional. Expiration time in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $data, $group = 'default', $expiration = null ) {
		if ( ! $this->config['enabled'] ) {
			return false;
		}

		if ( null === $expiration ) {
			$expiration = isset( $this->cache_groups[ $group ] ) ? $this->cache_groups[ $group ] : 3600;
		}

		$cache_key = $this->get_cache_key( $key, $group );
		$processed_data = $this->maybe_encrypt( $this->maybe_compress( $data ) );

		$success = false;

		// Set in Redis if enabled.
		if ( $this->config['redis_enabled'] ) {
			$success = $this->set_in_redis( $cache_key, $processed_data, $expiration ) || $success;
		}

		// Set in Memcached if enabled.
		if ( $this->config['memcached_enabled'] ) {
			$success = $this->set_in_memcached( $cache_key, $processed_data, $expiration ) || $success;
		}

		// Set in WordPress object cache.
		if ( $this->config['object_cache'] ) {
			$success = wp_cache_set( $cache_key, $data, 'wpmatch_' . $group, $expiration ) || $success;
		}

		// Set in transients.
		if ( $this->config['transient_cache'] ) {
			$success = set_transient( $cache_key, $data, $expiration ) || $success;
		}

		// Set in file cache.
		if ( $this->config['file_cache'] ) {
			$success = $this->set_in_file_cache( $cache_key, $data, $expiration ) || $success;
		}

		if ( $success ) {
			$this->cache_stats['sets']++;
		}

		return $success;
	}

	/**
	 * Delete cached data.
	 *
	 * @param string $key Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key, $group = 'default' ) {
		if ( ! $this->config['enabled'] ) {
			return false;
		}

		$cache_key = $this->get_cache_key( $key, $group );
		$success = false;

		// Delete from Redis.
		if ( $this->config['redis_enabled'] ) {
			$success = $this->delete_from_redis( $cache_key ) || $success;
		}

		// Delete from Memcached.
		if ( $this->config['memcached_enabled'] ) {
			$success = $this->delete_from_memcached( $cache_key ) || $success;
		}

		// Delete from WordPress object cache.
		if ( $this->config['object_cache'] ) {
			$success = wp_cache_delete( $cache_key, 'wpmatch_' . $group ) || $success;
		}

		// Delete from transients.
		if ( $this->config['transient_cache'] ) {
			$success = delete_transient( $cache_key ) || $success;
		}

		// Delete from file cache.
		if ( $this->config['file_cache'] ) {
			$success = $this->delete_from_file_cache( $cache_key ) || $success;
		}

		if ( $success ) {
			$this->cache_stats['deletes']++;
		}

		return $success;
	}

	/**
	 * Flush cache by group or all cache.
	 *
	 * @param string $group Optional. Cache group to flush.
	 * @return bool True on success, false on failure.
	 */
	public function flush( $group = null ) {
		if ( null === $group ) {
			return $this->flush_all();
		}

		return $this->flush_group( $group );
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array Cache statistics.
	 */
	public function get_stats() {
		$total_requests = $this->cache_stats['hits'] + $this->cache_stats['misses'];
		$hit_ratio = $total_requests > 0 ? ( $this->cache_stats['hits'] / $total_requests ) * 100 : 0;

		return array(
			'hits'       => $this->cache_stats['hits'],
			'misses'     => $this->cache_stats['misses'],
			'sets'       => $this->cache_stats['sets'],
			'deletes'    => $this->cache_stats['deletes'],
			'hit_ratio'  => round( $hit_ratio, 2 ),
			'total_requests' => $total_requests,
			'memory_usage' => $this->get_memory_usage(),
			'cache_size'   => $this->get_cache_size(),
		);
	}

	/**
	 * Generate cache key.
	 *
	 * @param string $key Base key.
	 * @param string $group Cache group.
	 * @return string Generated cache key.
	 */
	private function get_cache_key( $key, $group ) {
		$site_id = get_current_blog_id();
		return "wpmatch_{$site_id}_{$group}_{$key}";
	}

	/**
	 * Redis cache methods.
	 */

	/**
	 * Get Redis connection.
	 *
	 * @return Redis|false Redis connection or false on failure.
	 */
	private function get_redis_connection() {
		static $redis = null;

		if ( null === $redis && class_exists( 'Redis' ) ) {
			try {
				$redis = new Redis();
				$redis->connect( $this->config['redis_host'], $this->config['redis_port'] );

				if ( ! empty( $this->config['redis_password'] ) ) {
					$redis->auth( $this->config['redis_password'] );
				}

				if ( $this->config['redis_database'] > 0 ) {
					$redis->select( $this->config['redis_database'] );
				}
			} catch ( Exception $e ) {
				if ( $this->config['debug_mode'] ) {
					error_log( 'WPMatch Cache: Redis connection failed - ' . $e->getMessage() );
				}
				return false;
			}
		}

		return $redis;
	}

	/**
	 * Get data from Redis.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false.
	 */
	private function get_from_redis( $key ) {
		$redis = $this->get_redis_connection();
		if ( ! $redis ) {
			return false;
		}

		try {
			$data = $redis->get( $key );
			return $data !== false ? maybe_unserialize( $data ) : false;
		} catch ( Exception $e ) {
			if ( $this->config['debug_mode'] ) {
				error_log( 'WPMatch Cache: Redis get failed - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Set data in Redis.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Expiration time.
	 * @return bool True on success, false on failure.
	 */
	private function set_in_redis( $key, $data, $expiration ) {
		$redis = $this->get_redis_connection();
		if ( ! $redis ) {
			return false;
		}

		try {
			return $redis->setex( $key, $expiration, maybe_serialize( $data ) );
		} catch ( Exception $e ) {
			if ( $this->config['debug_mode'] ) {
				error_log( 'WPMatch Cache: Redis set failed - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Delete data from Redis.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	private function delete_from_redis( $key ) {
		$redis = $this->get_redis_connection();
		if ( ! $redis ) {
			return false;
		}

		try {
			return $redis->del( $key ) > 0;
		} catch ( Exception $e ) {
			if ( $this->config['debug_mode'] ) {
				error_log( 'WPMatch Cache: Redis delete failed - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Memcached cache methods.
	 */

	/**
	 * Get Memcached connection.
	 *
	 * @return Memcached|false Memcached connection or false on failure.
	 */
	private function get_memcached_connection() {
		static $memcached = null;

		if ( null === $memcached && class_exists( 'Memcached' ) ) {
			try {
				$memcached = new Memcached();

				foreach ( $this->config['memcached_servers'] as $server ) {
					$parts = explode( ':', $server );
					$host = $parts[0];
					$port = isset( $parts[1] ) ? (int) $parts[1] : 11211;
					$memcached->addServer( $host, $port );
				}
			} catch ( Exception $e ) {
				if ( $this->config['debug_mode'] ) {
					error_log( 'WPMatch Cache: Memcached connection failed - ' . $e->getMessage() );
				}
				return false;
			}
		}

		return $memcached;
	}

	/**
	 * Get data from Memcached.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false.
	 */
	private function get_from_memcached( $key ) {
		$memcached = $this->get_memcached_connection();
		if ( ! $memcached ) {
			return false;
		}

		return $memcached->get( $key );
	}

	/**
	 * Set data in Memcached.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Expiration time.
	 * @return bool True on success, false on failure.
	 */
	private function set_in_memcached( $key, $data, $expiration ) {
		$memcached = $this->get_memcached_connection();
		if ( ! $memcached ) {
			return false;
		}

		return $memcached->set( $key, $data, $expiration );
	}

	/**
	 * Delete data from Memcached.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	private function delete_from_memcached( $key ) {
		$memcached = $this->get_memcached_connection();
		if ( ! $memcached ) {
			return false;
		}

		return $memcached->delete( $key );
	}

	/**
	 * File cache methods.
	 */

	/**
	 * Get cache directory.
	 *
	 * @return string Cache directory path.
	 */
	private function get_cache_dir() {
		$cache_dir = WP_CONTENT_DIR . '/cache/wpmatch/';
		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
		return $cache_dir;
	}

	/**
	 * Get data from file cache.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached data or false.
	 */
	private function get_from_file_cache( $key ) {
		$file_path = $this->get_cache_dir() . md5( $key ) . '.cache';

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$data = file_get_contents( $file_path );
		if ( false === $data ) {
			return false;
		}

		$cache_data = maybe_unserialize( $data );

		// Check if expired.
		if ( isset( $cache_data['expires'] ) && $cache_data['expires'] < time() ) {
			unlink( $file_path );
			return false;
		}

		return isset( $cache_data['data'] ) ? $cache_data['data'] : false;
	}

	/**
	 * Set data in file cache.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $data Data to cache.
	 * @param int    $expiration Expiration time.
	 * @return bool True on success, false on failure.
	 */
	private function set_in_file_cache( $key, $data, $expiration ) {
		$file_path = $this->get_cache_dir() . md5( $key ) . '.cache';

		$cache_data = array(
			'data'    => $data,
			'expires' => time() + $expiration,
		);

		return file_put_contents( $file_path, maybe_serialize( $cache_data ) ) !== false;
	}

	/**
	 * Delete data from file cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	private function delete_from_file_cache( $key ) {
		$file_path = $this->get_cache_dir() . md5( $key ) . '.cache';

		if ( file_exists( $file_path ) ) {
			return unlink( $file_path );
		}

		return true;
	}

	/**
	 * Data processing methods.
	 */

	/**
	 * Maybe compress data.
	 *
	 * @param mixed $data Data to compress.
	 * @return mixed Compressed or original data.
	 */
	private function maybe_compress( $data ) {
		if ( ! $this->config['cache_compression'] || ! function_exists( 'gzcompress' ) ) {
			return $data;
		}

		$serialized = maybe_serialize( $data );
		if ( strlen( $serialized ) > 1024 ) { // Only compress if larger than 1KB.
			return array(
				'compressed' => true,
				'data'       => gzcompress( $serialized ),
			);
		}

		return $data;
	}

	/**
	 * Maybe decompress data.
	 *
	 * @param mixed $data Data to decompress.
	 * @return mixed Decompressed or original data.
	 */
	private function maybe_decompress( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['compressed'] ) || ! $data['compressed'] ) {
			return $data;
		}

		if ( function_exists( 'gzuncompress' ) ) {
			$decompressed = gzuncompress( $data['data'] );
			return maybe_unserialize( $decompressed );
		}

		return false;
	}

	/**
	 * Maybe encrypt data.
	 *
	 * @param mixed $data Data to encrypt.
	 * @return mixed Encrypted or original data.
	 */
	private function maybe_encrypt( $data ) {
		if ( ! $this->config['cache_encryption'] || ! function_exists( 'openssl_encrypt' ) ) {
			return $data;
		}

		$key = defined( 'WPMATCH_CACHE_KEY' ) ? WPMATCH_CACHE_KEY : 'wpmatch_default_key';
		$serialized = maybe_serialize( $data );
		$iv = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $serialized, 'AES-256-CBC', $key, 0, $iv );

		return array(
			'encrypted' => true,
			'data'      => $encrypted,
			'iv'        => base64_encode( $iv ),
		);
	}

	/**
	 * Maybe decrypt data.
	 *
	 * @param mixed $data Data to decrypt.
	 * @return mixed Decrypted or original data.
	 */
	private function maybe_decrypt( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['encrypted'] ) || ! $data['encrypted'] ) {
			return $data;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		$key = defined( 'WPMATCH_CACHE_KEY' ) ? WPMATCH_CACHE_KEY : 'wpmatch_default_key';
		$iv = base64_decode( $data['iv'] );

		$decrypted = openssl_decrypt( $data['data'], 'AES-256-CBC', $key, 0, $iv );

		return maybe_unserialize( $decrypted );
	}

	/**
	 * Cache management methods.
	 */

	/**
	 * Flush all cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function flush_all() {
		$success = true;

		// Flush Redis.
		if ( $this->config['redis_enabled'] ) {
			$redis = $this->get_redis_connection();
			if ( $redis ) {
				try {
					$redis->flushDB();
				} catch ( Exception $e ) {
					$success = false;
				}
			}
		}

		// Flush Memcached.
		if ( $this->config['memcached_enabled'] ) {
			$memcached = $this->get_memcached_connection();
			if ( $memcached ) {
				$memcached->flush();
			}
		}

		// Flush WordPress object cache.
		if ( $this->config['object_cache'] ) {
			wp_cache_flush();
		}

		// Clear all WPMatch transients.
		$this->clear_wpmatch_transients();

		// Clear file cache.
		if ( $this->config['file_cache'] ) {
			$this->clear_file_cache();
		}

		return $success;
	}

	/**
	 * Flush cache group.
	 *
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	private function flush_group( $group ) {
		// For simplicity, we'll increment the group version to invalidate all keys in the group.
		$version_key = "wpmatch_cache_version_{$group}";
		$current_version = wp_cache_get( $version_key, 'wpmatch_versions' );
		$new_version = $current_version ? $current_version + 1 : 1;

		return wp_cache_set( $version_key, $new_version, 'wpmatch_versions' );
	}

	/**
	 * Clear WPMatch transients.
	 */
	private function clear_wpmatch_transients() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wpmatch_%'
			OR option_name LIKE '_transient_timeout_wpmatch_%'"
		);
	}

	/**
	 * Clear file cache.
	 */
	private function clear_file_cache() {
		$cache_dir = $this->get_cache_dir();
		$files = glob( $cache_dir . '*.cache' );

		foreach ( $files as $file ) {
			unlink( $file );
		}
	}

	/**
	 * Get memory usage.
	 *
	 * @return string Memory usage.
	 */
	private function get_memory_usage() {
		if ( function_exists( 'memory_get_peak_usage' ) ) {
			return size_format( memory_get_peak_usage( true ) );
		}

		return 'N/A';
	}

	/**
	 * Get cache size.
	 *
	 * @return string Cache size.
	 */
	private function get_cache_size() {
		$size = 0;

		// Get file cache size.
		if ( $this->config['file_cache'] ) {
			$cache_dir = $this->get_cache_dir();
			$files = glob( $cache_dir . '*.cache' );

			foreach ( $files as $file ) {
				$size += filesize( $file );
			}
		}

		return size_format( $size );
	}

	/**
	 * Cache invalidation methods.
	 */

	/**
	 * Invalidate profile cache.
	 *
	 * @param int $user_id User ID.
	 */
	public function invalidate_profile_cache( $user_id ) {
		$this->delete( "profile_{$user_id}", 'profiles' );
		$this->delete( "user_data_{$user_id}", 'user_data' );
		$this->flush_group( 'search' );
		$this->flush_group( 'recommendations' );
	}

	/**
	 * Invalidate match cache.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 */
	public function invalidate_match_cache( $user1_id, $user2_id ) {
		$this->delete( "matches_{$user1_id}", 'matches' );
		$this->delete( "matches_{$user2_id}", 'matches' );
		$this->delete( "recommendations_{$user1_id}", 'recommendations' );
		$this->delete( "recommendations_{$user2_id}", 'recommendations' );
	}

	/**
	 * Invalidate message cache.
	 *
	 * @param int $user1_id First user ID.
	 * @param int $user2_id Second user ID.
	 */
	public function invalidate_message_cache( $user1_id, $user2_id ) {
		$this->delete( "conversation_{$user1_id}_{$user2_id}", 'messages' );
		$this->delete( "conversation_{$user2_id}_{$user1_id}", 'messages' );
		$this->delete( "conversations_{$user1_id}", 'messages' );
		$this->delete( "conversations_{$user2_id}", 'messages' );
	}

	/**
	 * Invalidate user cache.
	 *
	 * @param int $user_id User ID.
	 */
	public function invalidate_user_cache( $user_id ) {
		$this->delete( "user_data_{$user_id}", 'user_data' );
		$this->delete( "preferences_{$user_id}", 'user_data' );
		$this->flush_group( 'recommendations' );
	}

	/**
	 * Invalidate event cache.
	 *
	 * @param int $event_id Event ID.
	 */
	public function invalidate_event_cache( $event_id ) {
		$this->delete( "event_{$event_id}", 'events' );
		$this->flush_group( 'events' );
	}

	/**
	 * Invalidate location cache.
	 *
	 * @param int $user_id User ID.
	 */
	public function invalidate_location_cache( $user_id ) {
		$this->delete( "location_{$user_id}", 'location' );
		$this->flush_group( 'location' );
	}

	/**
	 * Cache warm-up methods.
	 */

	/**
	 * Warm up user cache on login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User object.
	 */
	public function warm_up_user_cache( $user_login, $user ) {
		// Warm up critical user data.
		$this->warm_up_profile_cache( $user->ID );
		$this->warm_up_matches_cache( $user->ID );
		$this->warm_up_conversations_cache( $user->ID );
	}

	/**
	 * Daily cache warm-up.
	 */
	public function daily_cache_warmup() {
		// Warm up popular data.
		$this->warm_up_popular_profiles();
		$this->warm_up_recent_events();
	}

	/**
	 * Warm up profile cache.
	 *
	 * @param int $user_id User ID.
	 */
	private function warm_up_profile_cache( $user_id ) {
		// This would fetch and cache profile data.
		// Implementation depends on your profile data structure.
	}

	/**
	 * Warm up matches cache.
	 *
	 * @param int $user_id User ID.
	 */
	private function warm_up_matches_cache( $user_id ) {
		// This would fetch and cache user matches.
		// Implementation depends on your matching system.
	}

	/**
	 * Warm up conversations cache.
	 *
	 * @param int $user_id User ID.
	 */
	private function warm_up_conversations_cache( $user_id ) {
		// This would fetch and cache recent conversations.
		// Implementation depends on your messaging system.
	}

	/**
	 * Warm up popular profiles.
	 */
	private function warm_up_popular_profiles() {
		// This would cache popular or recently active profiles.
	}

	/**
	 * Warm up recent events.
	 */
	private function warm_up_recent_events() {
		// This would cache upcoming events.
	}

	/**
	 * Cleanup expired cache.
	 */
	public function cleanup_expired_cache() {
		// Clean up file cache.
		if ( $this->config['file_cache'] ) {
			$cache_dir = $this->get_cache_dir();
			$files = glob( $cache_dir . '*.cache' );

			foreach ( $files as $file ) {
				$data = file_get_contents( $file );
				$cache_data = maybe_unserialize( $data );

				if ( isset( $cache_data['expires'] ) && $cache_data['expires'] < time() ) {
					unlink( $file );
				}
			}
		}

		// Clean up old transients.
		delete_expired_transients();
	}

	/**
	 * AJAX handlers.
	 */

	/**
	 * AJAX handler for flushing cache.
	 */
	public function ajax_flush_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_ajax_referer( 'wpmatch_admin_nonce', 'nonce' );

		$group = sanitize_text_field( $_POST['group'] ?? null );

		if ( $group ) {
			$success = $this->flush( $group );
			$message = $success ? "Cache group '{$group}' flushed successfully" : "Failed to flush cache group '{$group}'";
		} else {
			$success = $this->flush();
			$message = $success ? 'All cache flushed successfully' : 'Failed to flush cache';
		}

		wp_send_json( array( 'success' => $success, 'message' => $message ) );
	}

	/**
	 * AJAX handler for cache statistics.
	 */
	public function ajax_cache_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		wp_send_json_success( $this->get_stats() );
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_cache_endpoints() {
		// Cache flush endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/cache/flush',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_flush_cache' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'group' => array(
						'type' => 'string',
					),
				),
			)
		);

		// Cache stats endpoint.
		register_rest_route(
			'wpmatch/v1',
			'/cache/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_cache_stats' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST endpoint for flushing cache.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_flush_cache( $request ) {
		$group = $request->get_param( 'group' );
		$success = $this->flush( $group );

		return rest_ensure_response(
			array(
				'success' => $success,
				'message' => $success ? 'Cache flushed successfully' : 'Failed to flush cache',
			)
		);
	}

	/**
	 * REST endpoint for cache statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_cache_stats( $request ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->get_stats(),
			)
		);
	}

	/**
	 * Get cache configuration.
	 *
	 * @return array Cache configuration.
	 */
	public function get_config() {
		return $this->config;
	}

	/**
	 * Update cache configuration.
	 *
	 * @param array $config Configuration array.
	 * @return bool True on success, false on failure.
	 */
	public function update_config( $config ) {
		$valid_keys = array(
			'enabled', 'redis_enabled', 'redis_host', 'redis_port', 'redis_password',
			'redis_database', 'memcached_enabled', 'memcached_servers', 'object_cache',
			'transient_cache', 'file_cache', 'cache_compression', 'cache_encryption',
			'debug_mode'
		);

		foreach ( $valid_keys as $key ) {
			if ( isset( $config[ $key ] ) ) {
				update_option( 'wpmatch_' . $key, $config[ $key ] );
				$this->config[ $key ] = $config[ $key ];
			}
		}

		return true;
	}
}

// Initialize the cache manager.
WPMatch_Cache_Manager::get_instance();