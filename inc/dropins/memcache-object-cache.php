<?php
/**
 * Memcached drop in (uses Memcache)
 *
 * @package  atozsites-simple-cache
 */

// phpcs:ignoreFile

defined( 'ABSPATH' ) || exit;

// We need Memcache to continue
if ( ! class_exists( 'Memcache' ) ) {
	return;
}

/**
 * Copyright (C) 2016 Ryan Boren, Denis de Bernardy, Matt Martz
 */

if ( ! defined( 'WP_CACHE_KEY_SALT' ) ) {
	define( 'WP_CACHE_KEY_SALT', '' );
}

function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {

	global $wp_object_cache;

	return $wp_object_cache->add( $key, $data, $group, $expire );
}

function wp_cache_incr( $key, $n = 1, $group = '' ) {

	global $wp_object_cache;

	return $wp_object_cache->incr( $key, $n, $group );
}

function wp_cache_decr( $key, $n = 1, $group = '' ) {

	global $wp_object_cache;

	return $wp_object_cache->decr( $key, $n, $group );
}

function wp_cache_close() {

	global $wp_object_cache;

	return $wp_object_cache->close();
}

function wp_cache_delete( $key, $group = '' ) {

	global $wp_object_cache;

	return $wp_object_cache->delete( $key, $group );
}

function wp_cache_flush() {

	global $wp_object_cache;

	return $wp_object_cache->flush();
}

function wp_cache_get( $key, $group = '', $force = false ) {

	global $wp_object_cache;

	return $wp_object_cache->get( $key, $group, $force );
}

function wp_cache_init() {

	global $wp_object_cache;

	$wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {

	global $wp_object_cache;

	return $wp_object_cache->replace( $key, $data, $group, $expire );
}

function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {

	global $wp_object_cache;

	if ( defined( 'WP_INSTALLING' ) == false ) {
		return $wp_object_cache->set( $key, $data, $group, $expire );
	} else {
		return $wp_object_cache->delete( $key, $group );
	}
}

function wp_cache_switch_to_blog( $blog_id ) {

	global $wp_object_cache;

	return $wp_object_cache->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {

	global $wp_object_cache;

	$wp_object_cache->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {

	global $wp_object_cache;

	$wp_object_cache->add_non_persistent_groups( $groups );
}

class WP_Object_Cache {
	var $global_groups = array();

	var $no_mc_groups = array();

	var $cache     = array();
	var $mc        = array();
	var $stats     = array();
	var $group_ops = array();

	var $cache_enabled      = true;
	var $default_expiration = 0;

	function add( $id, $data, $group = 'default', $expire = 0 ) {

		$key = $this->key( $id, $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = $data;
			return true;
		} elseif ( isset( $this->cache[ $key ] ) && $this->cache[ $key ] !== false ) {
			return false;
		}

		$mc     =& $this->get_mc( $group );
		$expire = ( $expire == 0 ) ? $this->default_expiration : $expire;
		$result = $mc->add( $key, $data, false, $expire );

		if ( false !== $result ) {
			@ ++$this->stats['add'];
			$this->group_ops[ $group ][] = "add $id";
			$this->cache[ $key ]         = $data;
		}

		return $result;
	}

	function add_global_groups( $groups ) {

		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	function add_non_persistent_groups( $groups ) {

		if ( ! is_array( $groups ) ) {
			$groups = (array) $groups;
		}

		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	function incr( $id, $n = 1, $group = 'default' ) {

		$key                 = $this->key( $id, $group );
		$mc                  =& $this->get_mc( $group );
		$this->cache[ $key ] = $mc->increment( $key, $n );
		return $this->cache[ $key ];
	}

	function decr( $id, $n = 1, $group = 'default' ) {

		$key                 = $this->key( $id, $group );
		$mc                  =& $this->get_mc( $group );
		$this->cache[ $key ] = $mc->decrement( $key, $n );
		return $this->cache[ $key ];
	}

	function close() {

		foreach ( $this->mc as $bucket => $mc ) {
			$mc->close();
		}
	}

	function delete( $id, $group = 'default' ) {

		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			unset( $this->cache[ $key ] );
			return true;
		}

		$mc =& $this->get_mc( $group );

		$result = $mc->delete( $key );

		@ ++$this->stats['delete'];
		$this->group_ops[ $group ][] = "delete $id";

		if ( false !== $result ) {
			unset( $this->cache[ $key ] );
		}

		return $result;
	}

	function flush() {
		$ret = true;
		foreach ( array_keys( $this->mc ) as $group ) {
			$ret &= $this->mc[ $group ]->flush();
		}
		return $ret;
	}

	function get( $id, $group = 'default', $force = false ) {

		$key = $this->key( $id, $group );
		$mc  =& $this->get_mc( $group );

		if ( isset( $this->cache[ $key ] ) && ( ! $force || in_array( $group, $this->no_mc_groups ) ) ) {
			if ( is_object( $this->cache[ $key ] ) ) {
				$value = clone $this->cache[ $key ];
			} else {
				$value = $this->cache[ $key ];
			}
		} elseif ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[ $key ] = $value = false;
		} else {
			$value = $mc->get( $key );
			if ( null === $value ) {
					$value = false;
			}
			$this->cache[ $key ] = $value;
		}

		@ ++$this->stats['get'];
		$this->group_ops[ $group ][] = "get $id";

		if ( 'checkthedatabaseplease' === $value ) {
			unset( $this->cache[ $key ] );
			$value = false;
		}

		return $value;
	}

	function get_multi( $groups ) {

		/*
		format: $get['group-name'] = array( 'key1', 'key2' );
		*/
		$return = array();
		foreach ( $groups as $group => $ids ) {
			$mc =& $this->get_mc( $group );
			foreach ( $ids as $id ) {
				$key = $this->key( $id, $group );
				if ( isset( $this->cache[ $key ] ) ) {
					if ( is_object( $this->cache[ $key ] ) ) {
						$return[ $key ] = clone $this->cache[ $key ];
					} else {
						$return[ $key ] = $this->cache[ $key ];
					}
					continue;
				} elseif ( in_array( $group, $this->no_mc_groups ) ) {
					$return[ $key ] = false;
					continue;
				} else {
					$return[ $key ] = $mc->get( $key );
				}
			}
			if ( $to_get ) {
				$vals   = $mc->get_multi( $to_get );
				$return = array_merge( $return, $vals );
			}
		}
		@ ++$this->stats['get_multi'];
		$this->group_ops[ $group ][] = "get_multi $id";
		$this->cache                 = array_merge( $this->cache, $return );
		return $return;
	}

	function key( $key, $group ) {

		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( false !== array_search( $group, $this->global_groups ) ) {
			$prefix = $this->global_prefix;
		} else {
			$prefix = $this->blog_prefix;
		}

		return preg_replace( '/\s+/', '', WP_CACHE_KEY_SALT . "$prefix$group:$key" );
	}

	function replace( $id, $data, $group = 'default', $expire = 0 ) {

		$key    = $this->key( $id, $group );
		$expire = ( $expire == 0 ) ? $this->default_expiration : $expire;
		$mc     =& $this->get_mc( $group );

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$result = $mc->replace( $key, $data, false, $expire );
		if ( false !== $result ) {
			$this->cache[ $key ] = $data;
		}
		return $result;
	}

	function set( $id, $data, $group = 'default', $expire = 0 ) {

		$key = $this->key( $id, $group );
		if ( isset( $this->cache[ $key ] ) && ( 'checkthedatabaseplease' === $this->cache[ $key ] ) ) {
			return false;
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->cache[ $key ] = $data;

		if ( in_array( $group, $this->no_mc_groups ) ) {
			return true;
		}

		$expire = ( $expire == 0 ) ? $this->default_expiration : $expire;
		$mc     =& $this->get_mc( $group );
		$result = $mc->set( $key, $data, false, $expire );

		return $result;
	}

	function switch_to_blog( $blog_id ) {

		global $table_prefix;

		$blog_id           = (int) $blog_id;
		$this->blog_prefix = ( is_multisite() ? $blog_id : $table_prefix ) . ':';
	}

	function colorize_debug_line( $line ) {

		$colors = array(
			'get'    => 'green',
			'set'    => 'purple',
			'add'    => 'blue',
			'delete' => 'red',
		);

		$cmd = substr( $line, 0, strpos( $line, ' ' ) );

		$cmd2 = "<span style='color:{$colors[$cmd]}'>$cmd</span>";

		return $cmd2 . substr( $line, strlen( $cmd ) ) . "\n";
	}

	function stats() {

		echo "<p>\n";
		foreach ( $this->stats as $stat => $n ) {
			echo "<strong>$stat</strong> $n";
			echo "<br/>\n";
		}
		echo "</p>\n";
		echo '<h3>Memcached:</h3>';
		foreach ( $this->group_ops as $group => $ops ) {
			if ( ! isset( $_GET['debug_queries'] ) && 500 < count( $ops ) ) {
				$ops = array_slice( $ops, 0, 500 );
				echo "<big>Too many to show! <a href='" . add_query_arg( 'debug_queries', 'true' ) . "'>Show them anyway</a>.</big>\n";
			}
			echo "<h4>$group commands</h4>";
			echo "<pre>\n";
			$lines = array();
			foreach ( $ops as $op ) {
				$lines[] = $this->colorize_debug_line( $op );
			}
			print_r( $lines );
			echo "</pre>\n";
		}
	}

	function &get_mc( $group ) {

		if ( isset( $this->mc[ $group ] ) ) {
			return $this->mc[ $group ];
		}
		return $this->mc['default'];
	}

	function failure_callback( $host, $port ) {

		// error_log("Connection failure for $host:$port\n", 3, '/tmp/memcached.txt');
	}

	function __construct() {

		global $memcached_servers;

		if ( isset( $memcached_servers ) ) {
			$buckets = $memcached_servers;
		} else {
			$buckets = array( '127.0.0.1:11211' );
		}

		reset( $buckets );
		if ( is_int( key( $buckets ) ) ) {
			$buckets = array( 'default' => $buckets );
		}

		foreach ( $buckets as $bucket => $servers ) {
			$this->mc[ $bucket ] = new Memcache();
			foreach ( $servers as $server ) {
				if ( 'unix://' == substr( $server, 0, 7 ) ) {
					$node = $server;
					$port = 0;
				} else {
					list ( $node, $port ) = explode( ':', $server );
					if ( ! $port ) {
						$port = ini_get( 'memcache.default_port' );
					}
					$port = intval( $port );
					if ( ! $port ) {
						$port = 11211;
					}
				}
				$this->mc[ $bucket ]->addServer( $node, $port, true, 1, 1, 15, true, array( $this, 'failure_callback' ) );
				$this->mc[ $bucket ]->setCompressThreshold( 20000, 0.2 );
			}
		}

		global $blog_id, $table_prefix;
		$this->global_prefix = '';
		$this->blog_prefix   = '';
		if ( function_exists( 'is_multisite' ) ) {
			$this->global_prefix = ( is_multisite() || defined( 'CUSTOM_USER_TABLE' ) && defined( 'CUSTOM_USER_META_TABLE' ) ) ? '' : $table_prefix;
			$this->blog_prefix   = ( is_multisite() ? $blog_id : $table_prefix ) . ':';
		}

		$this->cache_hits   =& $this->stats['get'];
		$this->cache_misses =& $this->stats['add'];
	}
}

